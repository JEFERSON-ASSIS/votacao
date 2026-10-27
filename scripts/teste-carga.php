<?php
declare(strict_types=1);

/*
 * Teste de carga das telas ao vivo do Plenário.
 *
 * Simula N notebooks de vereadores + M telões abertos ao mesmo tempo, do jeito
 * que o navegador faz: cada tela mantém uma conexão ao vivo (stream) e ainda
 * consulta o estado a cada 2 s. Enquanto roda, conduza a sessão normalmente
 * (próximo passo, abrir votação...) e veja quantas telas recebem cada mudança.
 *
 * Rodar DENTRO do container, para medir também a memória do servidor:
 *
 *   docker exec -it $(docker ps -q -f name=plenario_plenario_app) \
 *     php scripts/teste-carga.php --url=https://plenariovera.com.br --sessao=2
 *
 * Opções:
 *   --url=...          endereço do sistema (obrigatório)
 *   --sessao=N         id da sessão em andamento (obrigatório)
 *   --vereadores=12    quantos notebooks simular
 *   --teloes=3         quantos telões simular (tela pública, sem login)
 *   --minutos=10       duração do teste
 *   --logins=arquivo   arquivo com uma conta por linha: email;senha
 *                      (vereadores PRESENTES na sessão). As contas são reusadas
 *                      se houver menos contas que notebooks. Sem este arquivo,
 *                      os notebooks são simulados como telões (carga parecida,
 *                      um pouco menor). Apague o arquivo depois do teste.
 *
 * O teste só lê: não vota, não pede a palavra, não muda nada na sessão.
 */

if (PHP_SAPI !== 'cli') {
    exit("Rode pela linha de comando.\n");
}
if (!function_exists('curl_multi_init')) {
    exit("Extensão curl do PHP não encontrada.\n");
}

$opt = getopt('', ['url:', 'sessao:', 'vereadores::', 'teloes::', 'minutos::', 'logins::']);
$base = rtrim((string) ($opt['url'] ?? ''), '/');
$sessaoId = (int) ($opt['sessao'] ?? 0);
if ($base === '' || $sessaoId <= 0) {
    exit("Uso: php scripts/teste-carga.php --url=https://plenariovera.com.br --sessao=2 [--vereadores=12] [--teloes=3] [--minutos=10] [--logins=contas.txt]\n");
}
$nVereadores = max(0, (int) ($opt['vereadores'] ?? 12));
$nTeloes = max(0, (int) ($opt['teloes'] ?? 3));
$duracao = max(1, (int) ($opt['minutos'] ?? 10)) * 60;
if (!str_contains($base, 'index.php')) {
    $base .= '/index.php';
}

function rota(string $base, string $r, string $extra = ''): string
{
    return $base . '?r=' . $r . ($extra !== '' ? '&' . $extra : '');
}

function fmtMs(float $s): string
{
    return number_format($s * 1000, 0, ',', '.') . ' ms';
}

function percentil(array $v, float $p): float
{
    if ($v === []) {
        return 0.0;
    }
    sort($v);
    return $v[(int) min(count($v) - 1, floor(count($v) * $p))];
}

/** Memória do container (cgroup v2 ou v1) e processos do Apache. Só funciona dentro do container. */
function memoriaServidor(): array
{
    $usado = null;
    $limite = null;
    foreach ([['/sys/fs/cgroup/memory.current', '/sys/fs/cgroup/memory.max'],
              ['/sys/fs/cgroup/memory/memory.usage_in_bytes', '/sys/fs/cgroup/memory/memory.limit_in_bytes']] as [$u, $l]) {
        if (is_readable($u)) {
            $usado = (int) trim((string) file_get_contents($u));
            $lim = trim((string) @file_get_contents($l));
            $limite = ctype_digit($lim) && (int) $lim < PHP_INT_MAX / 2 ? (int) $lim : null;
            break;
        }
    }
    $procs = 0;
    foreach (glob('/proc/[0-9]*/comm') ?: [] as $f) {
        if (trim((string) @file_get_contents($f)) === 'apache2') {
            $procs++;
        }
    }

    return ['usado' => $usado, 'limite' => $limite, 'apache' => $procs];
}

function login(string $base, string $email, string $senha): ?string
{
    $ch = curl_init(rota($base, 'auth/login'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['email' => $email, 'senha' => $senha]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = (string) curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($codigo !== 302 || !preg_match_all('/^Set-Cookie:\s*([^=;\s]+)=([^;\r\n]*)/mi', $resp, $m, PREG_SET_ORDER)) {
        return null;
    }
    $cookies = [];
    foreach ($m as $c) {
        $cookies[$c[1]] = $c[1] . '=' . $c[2];
    }

    return implode('; ', $cookies);
}

// ---------------------------------------------------------------- telas
$contas = [];
if (!empty($opt['logins'])) {
    $linhas = @file((string) $opt['logins'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) {
        exit("Não consegui ler o arquivo de logins.\n");
    }
    foreach ($linhas as $l) {
        [$email, $senha] = array_map('trim', explode(';', $l, 2) + [1 => '']);
        if ($email === '') {
            continue;
        }
        $cookie = login($base, $email, $senha);
        echo $cookie ? "Login ok: $email\n" : "Login FALHOU: $email (confira e-mail e senha)\n";
        if ($cookie) {
            $contas[] = ['email' => $email, 'cookie' => $cookie];
        }
    }
}

$telas = [];
for ($i = 0; $i < $nVereadores; $i++) {
    $conta = $contas !== [] ? $contas[$i % count($contas)] : null;
    $telas[] = $conta
        ? ['nome' => 'Notebook ' . ($i + 1), 'cookie' => $conta['cookie'],
           'stream' => rota($base, 'parlamentar/stream/' . $sessaoId),
           'estado' => rota($base, 'parlamentar/estado/' . $sessaoId, 'html_voto=1')]
        : ['nome' => 'Notebook ' . ($i + 1) . ' (como telão)', 'cookie' => '',
           'stream' => rota($base, 'publico/stream/' . $sessaoId),
           'estado' => rota($base, 'publico/estado/' . $sessaoId)];
}
for ($i = 0; $i < $nTeloes; $i++) {
    $telas[] = ['nome' => 'Telão ' . ($i + 1), 'cookie' => '',
                'stream' => rota($base, 'publico/stream/' . $sessaoId),
                'estado' => rota($base, 'publico/estado/' . $sessaoId)];
}
if ($telas === []) {
    exit("Nenhuma tela para simular.\n");
}
if ($nVereadores > 0 && $contas === []) {
    echo "Aviso: sem --logins, os notebooks dos vereadores estão sendo simulados como telões.\n";
}

// Confere se cada tipo de tela responde antes de começar.
foreach (array_unique(array_map(fn ($t) => $t['estado'] . '|' . $t['cookie'], $telas)) as $chave) {
    [$url, $cookie] = explode('|', $chave, 2);
    $ch = curl_init($url . '&_=' . time());
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_COOKIE => $cookie]);
    $corpo = (string) curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = json_decode($corpo, true);
    if ($codigo !== 200 || !is_array($json) || empty($json['ok'])) {
        exit("A tela não respondeu como esperado ($codigo) em $url\n"
            . "Confira o endereço, se a sessão $sessaoId está em andamento e se os vereadores das contas estão presentes.\n");
    }
}

// ---------------------------------------------------------------- loop
$mh = curl_multi_init();
$handles = [];          // id do handle => ['tela' => i, 'tipo' => 'stream'|'poll', 'inicio' => t]
$estado = [];           // por tela
$agora = microtime(true);
foreach ($telas as $i => $t) {
    $estado[$i] = ['stream' => null, 'reabrirEm' => $agora + $i * 0.1, 'eventos' => 0,
                   'pollEm' => $agora + 1 + ($i % 20) * 0.1, 'pollAtivo' => false, 'buffer' => ''];
}
$janela = ['polls' => 0, 'erros' => 0, 'lat' => [], 'reconexoes' => 0];
$total = ['polls' => 0, 'erros' => 0, 'lat' => [], 'reconexoes' => 0, 'memMax' => 0, 'apacheMax' => 0];
$onda = null;           // mudança sendo acompanhada
$ondas = [];
$inicio = microtime(true);
$proximoRelatorio = $inicio + 10;

$abrirStream = function (int $i) use (&$handles, &$estado, $telas, $mh): void {
    $ch = curl_init($telas[$i]['stream']);
    $estado[$i]['primeiroEvento'] = true;
    curl_setopt_array($ch, [
        CURLOPT_COOKIE => $telas[$i]['cookie'],
        CURLOPT_HTTPHEADER => ['Accept: text/event-stream'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_WRITEFUNCTION => function ($ch, string $dados) use ($i, &$estado): int {
            $estado[$i]['buffer'] .= $dados;
            while (($p = strpos($estado[$i]['buffer'], "\n\n")) !== false) {
                $bloco = substr($estado[$i]['buffer'], 0, $p);
                $estado[$i]['buffer'] = substr($estado[$i]['buffer'], $p + 2);
                if (str_contains($bloco, 'event: estado')) {
                    $estado[$i]['eventos']++;
                    $estado[$i]['ultimoEvento'] = microtime(true);
                    if (!empty($estado[$i]['primeiroEvento'])) {
                        $estado[$i]['primeiroEvento'] = false; // estado inicial da conexão, não é mudança
                    } else {
                        $estado[$i]['mudouEm'] = microtime(true);
                    }
                }
            }
            return strlen($dados);
        },
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[spl_object_id($ch)] = ['tela' => $i, 'tipo' => 'stream', 'inicio' => microtime(true), 'ch' => $ch];
    $estado[$i]['stream'] = $ch;
};

echo "\nSimulando " . count($telas) . " telas por " . ($duracao / 60) . " min em $base (sessão $sessaoId).\n";
echo "Conduza a sessão enquanto o teste roda. Ctrl+C para parar antes.\n\n";

while (microtime(true) - $inicio < $duracao) {
    $agora = microtime(true);
    foreach ($telas as $i => $t) {
        if ($estado[$i]['stream'] === null && $agora >= $estado[$i]['reabrirEm']) {
            $abrirStream($i);
        }
        if (!$estado[$i]['pollAtivo'] && $agora >= $estado[$i]['pollEm']) {
            $ch = curl_init($t['estado'] . '&_=' . (int) ($agora * 1000));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                                    CURLOPT_COOKIE => $t['cookie'], CURLOPT_HTTPHEADER => ['Accept: application/json']]);
            curl_multi_add_handle($mh, $ch);
            $handles[spl_object_id($ch)] = ['tela' => $i, 'tipo' => 'poll', 'inicio' => $agora, 'ch' => $ch];
            $estado[$i]['pollAtivo'] = true;
        }
    }

    curl_multi_exec($mh, $rodando);
    curl_multi_select($mh, 0.1);
    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        $h = $handles[spl_object_id($ch)] ?? null;
        unset($handles[spl_object_id($ch)]);
        curl_multi_remove_handle($mh, $ch);
        if (!$h) {
            continue;
        }
        $i = $h['tela'];
        if ($h['tipo'] === 'poll') {
            $lat = microtime(true) - $h['inicio'];
            $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $json = json_decode((string) curl_multi_getcontent($ch), true);
            $ok = $info['result'] === CURLE_OK && $codigo === 200 && is_array($json) && !empty($json['ok']);
            foreach (['janela', 'total'] as $alvo) {
                ${$alvo}['polls']++;
                ${$alvo}['lat'][] = $lat;
                if (!$ok) {
                    ${$alvo}['erros']++;
                }
            }
            $estado[$i]['pollAtivo'] = false;
            $estado[$i]['pollEm'] = microtime(true) + 2;
        } else {
            // Stream terminou (o servidor encerra a cada 5 min ou caiu): reabre como o navegador.
            $estado[$i]['stream'] = null;
            $estado[$i]['buffer'] = '';
            $estado[$i]['reabrirEm'] = microtime(true) + 1;
            $janela['reconexoes']++;
            $total['reconexoes']++;
        }
        curl_close($ch);
    }

    // Ondas: quando uma tela recebe mudança, mede quantas outras recebem em até 5 s.
    $agora = microtime(true);
    foreach ($estado as $i => $e) {
        if (!empty($e['mudouEm'])) {
            if ($onda === null) {
                $onda = ['inicio' => $e['mudouEm'], 'telas' => []];
            }
            $onda['telas'][$i] = $e['mudouEm'] - $onda['inicio'];
            $estado[$i]['mudouEm'] = null;
        }
    }
    if ($onda !== null && $agora - $onda['inicio'] > 5) {
        $n = count($onda['telas']);
        $lenta = max($onda['telas']);
        $ondas[] = ['n' => $n, 'lenta' => $lenta];
        printf("  ▸ Mudança na sessão: %d de %d telas atualizaram; a mais lenta em %s\n",
            $n, count($telas), number_format($lenta, 1, ',', '.') . ' s');
        $onda = null;
    }

    if ($agora >= $proximoRelatorio) {
        $mem = memoriaServidor();
        $streamsAbertos = count(array_filter($estado, fn ($e) => $e['stream'] !== null));
        $memTxt = 'memória: (rode dentro do container para ver)';
        if ($mem['usado'] !== null) {
            $total['memMax'] = max($total['memMax'], $mem['usado']);
            $total['apacheMax'] = max($total['apacheMax'], $mem['apache']);
            $memTxt = sprintf('memória: %d MB%s · processos Apache: %d',
                $mem['usado'] / 1048576,
                $mem['limite'] ? sprintf(' de %d MB (%d%%)', $mem['limite'] / 1048576, 100 * $mem['usado'] / $mem['limite']) : '',
                $mem['apache']);
        }
        printf("[%s] conexões ao vivo: %d/%d · consultas: %d (erros: %d) · tempo de resposta: média %s, 95%% até %s · %s\n",
            gmdate('i:s', (int) ($agora - $inicio)), $streamsAbertos, count($telas), $janela['polls'], $janela['erros'],
            fmtMs($janela['lat'] ? array_sum($janela['lat']) / count($janela['lat']) : 0),
            fmtMs(percentil($janela['lat'], 0.95)), $memTxt);
        $janela = ['polls' => 0, 'erros' => 0, 'lat' => [], 'reconexoes' => 0];
        $proximoRelatorio = $agora + 10;
    }
}

foreach ($handles as $h) {
    curl_multi_remove_handle($mh, $h['ch']);
    curl_close($h['ch']);
}
curl_multi_close($mh);

// ---------------------------------------------------------------- resumo
$p95 = percentil($total['lat'], 0.95);
$taxaErro = $total['polls'] ? $total['erros'] / $total['polls'] : 1;
$memInfo = memoriaServidor();
echo "\n================ RESUMO ================\n";
printf("Telas simuladas: %d · consultas: %d · erros: %d (%.1f%%)\n", count($telas), $total['polls'], $total['erros'], $taxaErro * 100);
printf("Tempo de resposta: 95%% das consultas em até %s, pior %s\n", fmtMs($p95), fmtMs($total['lat'] ? max($total['lat']) : 0));
if ($total['memMax'] > 0) {
    printf("Pico de memória: %d MB%s · pico de processos Apache: %d\n", $total['memMax'] / 1048576,
        $memInfo['limite'] ? sprintf(' de %d MB (%d%%)', $memInfo['limite'] / 1048576, 100 * $total['memMax'] / $memInfo['limite']) : '',
        $total['apacheMax']);
}
if ($ondas !== []) {
    $completas = count(array_filter($ondas, fn ($o) => $o['n'] === count($telas)));
    printf("Mudanças acompanhadas: %d · chegaram em todas as telas: %d · a mais lenta: %s s\n",
        count($ondas), $completas, number_format(max(array_column($ondas, 'lenta')), 1, ',', '.'));
} else {
    echo "Nenhuma mudança na sessão durante o teste (conduza a sessão enquanto ele roda para medir a atualização).\n";
}

$problemas = [];
if ($taxaErro > 0.01) {
    $problemas[] = 'mais de 1% das consultas falharam';
}
if ($p95 > 1.5) {
    $problemas[] = 'respostas lentas (95% acima de 1,5 s)';
}
if ($total['memMax'] > 0 && $memInfo['limite'] && $total['memMax'] > 0.8 * $memInfo['limite']) {
    $problemas[] = 'memória acima de 80% do limite';
}
if ($ondas !== [] && count(array_filter($ondas, fn ($o) => $o['n'] < count($telas) || $o['lenta'] > 3)) > 0) {
    $problemas[] = 'alguma mudança demorou mais de 3 s ou não chegou em todas as telas';
}
echo $problemas === []
    ? "\nRESULTADO: OK — o servidor aguentou essa quantidade de telas com folga.\n"
    : "\nRESULTADO: ATENÇÃO — " . implode('; ', $problemas) . ".\n";
