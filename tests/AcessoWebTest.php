<?php
declare(strict_types=1);

/**
 * Garante que arquivos internos não podem ser baixados pelo navegador
 * e que o migrate.php não roda pela web.
 *
 * Uso (com o Apache ligado):
 *   C:\xampp\php\php.exe tests/AcessoWebTest.php [url-da-raiz-do-projeto]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$base =rtrim($argv[1] ?? 'http://localhost/producao/antigos/votacao', '/');
$falhas = 0;

function status_http(string $url): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code;
}

function confere(bool $ok, string $descricao): void
{
    global $falhas;
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$ok) {
        $falhas++;
    }
}

// 1) Arquivos internos devem ser bloqueados (403 ou 404)
$bloqueados = [
    '/.env',
    '/.env.example',
    '/.git/HEAD',
    '/logs/app.log',
    '/database/schema.sql',
    '/app/Config/config.php',
    '/app/bootstrap.php',
    '/README.md',
    '/tests/AcessoWebTest.php',
];
foreach ($bloqueados as $caminho) {
    $code = status_http($base . $caminho);
    confere(in_array($code, [403, 404], true), "bloqueia $caminho (status $code)");
}

// 2) O sistema continua funcionando
$liberados = [
    '/' => [200, 302],
    '/public/' => [200, 302],
    '/public/?r=auth/login' => [200],
    '/public/assets/css/app.css' => [200],
    '/assets/css/app.css' => [200],
];
foreach ($liberados as $caminho => $esperados) {
    $code = status_http($base . $caminho);
    confere(in_array($code, $esperados, true), "libera $caminho (status $code)");
}

// 3) migrate.php recusa rodar fora da linha de comando.
//    DB_PORT=1 garante que, se a proteção falhar, nada é gravado no banco.
$phpCgi = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php-cgi.exe';
if (is_file($phpCgi)) {
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'migrate.php';
    $env = array_merge(getenv(), [
        'DB_PORT' => '1',
        'DB_HOST' => '127.0.0.1',
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => $script,
        'REQUEST_METHOD' => 'GET',
    ]);
    $proc = proc_open([$phpCgi], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($proc);
    confere(
        str_contains($saida, 'Status: 403') && !str_contains($saida, 'Migracao concluida'),
        'migrate.php recusa execucao via web (403)'
    );
} else {
    echo "[AVISO] php-cgi.exe nao encontrado; teste do migrate pulado\n";
}

echo PHP_EOL . ($falhas === 0 ? 'Tudo certo.' : "$falhas falha(s).") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
