<?php
declare(strict_types=1);

/**
 * Desempate do presidente na própria tela de condução: com empate, a janela
 * "Empate: seu voto decide" aparece e o voto volta para a condução.
 *
 * Uso: C:\xampp\php\php.exe tests/DesempateConducaoTest.php
 */

require __DIR__ . '/_db.php';

use App\Controllers\VotacaoController;
use App\Models\SessaoConducao;
use App\Models\SessaoPresenca;
use App\Models\SessaoVoto;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVotoDesempate;

$pdo = preparar_banco_teste();
$pres = criar_usuario_teste($pdo, 'Presidente Desempate', 'PRESIDENTE');
$v1 = criar_usuario_teste($pdo, 'Vereador Sim');
$v2 = criar_usuario_teste($pdo, 'Vereador Nao');
$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 91, 'EM_VOTACAO')")->execute(['Sessao desempate ' . bin2hex(random_bytes(3))]);
$sid = (int) $pdo->lastInsertId();
$presUser = ['id' => $pres, 'nome' => 'Presidente Desempate', 'email' => 'p@t', 'role' => 'PRESIDENTE'];

try {
    $ins = $pdo->prepare("INSERT INTO sessao_roteiro (sessao_id, ordem, tipo_item, titulo, texto_fala, tipo_passo, gera_votacao, ativo, status_execucao) VALUES (?,?,?,?,?,?,?,1,?)");
    $ins->execute([$sid, 1, 'MANUAL', 'Projeto X', 'Coloco em votação.', 'FALA', 1, 'EM_ANDAMENTO']);
    $passo = (int) $pdo->lastInsertId();
    foreach ([$pres, $v1, $v2] as $u) { SessaoPresenca::set($sid, $u, true); }
    SessaoConducao::iniciar($sid, $pres);
    $rid = SessaoVotacaoRodada::abrir($sid, 'Projeto X', $passo, null, null, [$v1, $v2], 0);

    $html = rodar_cenario(['user' => $presUser, 'metodo' => 'GET', 'get_r' => 'presidencia/conduzir/' . $sid])['saida'];
    confere(str_contains($html, 'id="conducao-desempate"') && str_contains($html, 'data-auto-abrir="0"'), 'sem empate: janela existe e fica fechada');

    SessaoVoto::voteRodada($sid, $rid, null, $v1, 'SIM', null);
    SessaoVoto::voteRodada($sid, $rid, null, $v2, 'NAO', null);
    $html = rodar_cenario(['user' => $presUser, 'metodo' => 'GET', 'get_r' => 'presidencia/conduzir/' . $sid])['saida'];
    confere(str_contains($html, 'data-auto-abrir="1"'), 'empate: a janela de desempate abre sozinha');
    confere(str_contains($html, 'Empate: seu voto decide') && str_contains($html, 'name="return_to" value="conduzir"'), 'janela envia o voto voltando para a condução');
    confere((bool) preg_match('#id="conducao-abrir-desempate"\s*>#', $html), 'botão "Votar desempate" visível no empate');

    $res = rodar_cenario(['user' => $presUser, 'metodo' => 'POST', 'get_r' => 'votacao/votarRodada/' . $sid . '/' . $rid,
        'post' => ['user_id' => (string) $pres, 'voto' => 'SIM', 'return_to' => 'conduzir']]);
    confere($res['http'] === 302 || $res['http'] === 200, 'voto de desempate aceito (http ' . $res['http'] . ')');
    confere(SessaoVotoDesempate::voto($rid) === 'SIM', 'voto de desempate gravado como Sim');

    $m = new ReflectionMethod(VotacaoController::class, 'destinoAposVoto');
    $m->setAccessible(true);
    $_POST = ['return_to' => 'conduzir'];
    confere(str_contains((string) $m->invoke(null, $sid, $rid), 'presidencia/conduzir/' . $sid), 'voltando da condução: redireciona para a condução');
    $_POST = [];
    confere(str_contains((string) $m->invoke(null, $sid, $rid), 'votacao/painel/' . $sid), 'sem return_to: continua indo para o painel');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach (['sessao_votos_desempate', 'sessao_votos', 'sessao_votacao_rodadas', 'sessao_conducao', 'sessao_presentes', 'sessao_roteiro'] as $t) {
        try { $pdo->exec("DELETE FROM $t WHERE sessao_id = $sid"); } catch (\Throwable $e) {}
    }
    $pdo->exec("DELETE FROM sessoes WHERE id = $sid");
    $pdo->exec("DELETE FROM users WHERE id IN ($pres, $v1, $v2)");
}

finalizar_testes();
