<?php
declare(strict_types=1);

/**
 * Canais ao vivo (telão, painel, tela do vereador) e login expirado.
 *
 * Uso: C:\xampp\php\php.exe tests/TempoRealTest.php
 */

require __DIR__ . '/_db.php';

use App\Helpers\ParlamentarAcompanhar;
use App\Helpers\PublicoSessaoEstado;
use App\Helpers\VotacaoPainelEstado;

preparar_banco_teste();

// 1) O tempo restante do cronômetro, que muda a cada segundo, não conta como
//    mudança de estado: o aparelho calcula o tempo pelo fim_ts.
$base = [
    'ok' => true,
    'ts' => 1000,
    'destaque' => ['nome' => 'Fulano', 'tempo' => ['fim_ts' => 5000, 'restante_seg' => 120, 'esgotado' => false]],
    'rodada' => ['titulo' => 'PL 1', 'tempo' => ['fim_ts' => 6000, 'restante_seg' => 60, 'esgotado' => false]],
];
$umSegundoDepois = $base;
$umSegundoDepois['ts'] = 1001;
$umSegundoDepois['destaque']['tempo']['restante_seg'] = 119;
$umSegundoDepois['rodada']['tempo']['restante_seg'] = 0;
$umSegundoDepois['rodada']['tempo']['esgotado'] = true;
$outroFim = $base;
$outroFim['destaque']['tempo']['fim_ts'] = 5300;

foreach ([
    'telao' => [PublicoSessaoEstado::class, 'hashEstado'],
    'painel' => [VotacaoPainelEstado::class, 'hashEstado'],
    'vereador' => [ParlamentarAcompanhar::class, 'hashEstado'],
] as $canal => $hash) {
    confere($hash($base) === $hash($umSegundoDepois), "$canal: passar 1 segundo nao gera nova atualizacao");
    confere($hash($base) !== $hash($outroFim), "$canal: mudar o cronometro (fim_ts) gera atualizacao");
}

// 2) Login expirado: chamadas ao vivo recebem 401 em JSON, não a página de login
$res = rodar_cenario([
    'user' => null,
    'metodo' => 'GET',
    'get_r' => 'parlamentar/estado/1',
    'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
]);
confere($res['http'] === 401, 'chamada JSON sem login responde 401 (obtido ' . $res['http'] . ')');
confere(str_contains($res['saida'], '"sessao_expirada":true'), 'resposta JSON avisa sessao_expirada');

$res = rodar_cenario([
    'user' => null,
    'metodo' => 'GET',
    'get_r' => 'parlamentar/stream/1',
    'headers' => ['HTTP_ACCEPT' => 'text/event-stream'],
]);
confere($res['http'] === 401, 'canal ao vivo (SSE) sem login responde 401 (obtido ' . $res['http'] . ')');

$res = rodar_cenario(['user' => null, 'metodo' => 'GET', 'get_r' => 'dashboard/index']);
confere($res['http'] === 302, 'pagina normal sem login continua indo para o login (302)');

finalizar_testes();
