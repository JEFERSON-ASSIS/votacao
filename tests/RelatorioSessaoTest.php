<?php
declare(strict_types=1);

/**
 * O histórico da sessão mostra ao administrador tudo o que ocorreu:
 * presença, cada votação com resultado explicado, placar e voto de cada
 * vereador (inclusive impedidos), além da linha do tempo.
 *
 * Uso: C:\xampp\php\php.exe tests/RelatorioSessaoTest.php
 */

require __DIR__ . '/_db.php';

use App\Models\RodadaEleitor;
use App\Models\SessaoPresenca;
use App\Models\SessaoVoto;

$pdo = preparar_banco_teste();
$admin = criar_usuario_teste($pdo, 'Admin Relatorio', 'ADMIN');
$ana = criar_usuario_teste($pdo, 'Ana Relatorio');
$bia = criar_usuario_teste($pdo, 'Bia Relatorio');
$caio = criar_usuario_teste($pdo, 'Caio Relatorio');
$userAdmin = ['id' => $admin, 'nome' => 'Admin Relatorio', 'email' => 'a@test', 'role' => 'ADMIN'];

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status, data_sessao, hora_inicio, sapl_sessao_id) VALUES (?, 2026, 5, 'ENCERRADA', '2026-10-26', '09:00', ?)")
    ->execute(['Sessao relatorio ' . bin2hex(random_bytes(3)), 800000 + random_int(1, 99999)]);
$sessaoId = (int) $pdo->lastInsertId();

try {
    foreach ([$ana, $bia, $caio] as $v) {
        SessaoPresenca::set($sessaoId, $v, true);
    }
    $pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, titulo, status) VALUES (?, 'Projeto de Lei nº 48/2026', 'ABERTA')")->execute([$sessaoId]);
    $rodadaId = (int) $pdo->lastInsertId();
    RodadaEleitor::criarFotografia($rodadaId, [$ana, $bia, $caio]);
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $ana, 'SIM');
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $bia, 'NAO');
    RodadaEleitor::alterarSituacao($rodadaId, $caio, 'IMPEDIDO', $admin);
    $pdo->prepare("UPDATE sessao_votacao_rodadas SET status = 'ENCERRADA', resultado = 'REJEITADO' WHERE id = ?")->execute([$rodadaId]);

    $res = rodar_cenario(['user' => $userAdmin, 'metodo' => 'GET', 'get_r' => "historico/sessao/$sessaoId"]);
    $html = $res['saida'];
    confere($res['http'] === 200, 'historico abre (http ' . $res['http'] . ')');
    confere(str_contains($html, 'Presentes (3)'), 'mostra a presenca com a quantidade');
    confere(str_contains($html, 'Projeto de Lei nº 48/2026'), 'lista a votacao pelo titulo');
    confere(str_contains($html, 'conducao-resultado--rejeitado'), 'resultado com selo colorido');
    confere(str_contains($html, 'Sim e Não empataram') || str_contains($html, 'Houve mais votos'), 'traz a explicacao do resultado');
    confere(str_contains($html, 'Ana Relatorio') && str_contains($html, 'voto-chip voto-sim'), 'mostra o voto de cada vereador');
    confere(str_contains($html, 'Impedido'), 'mostra o vereador impedido');
    confere(str_contains($html, 'Importada do SAPL'), 'indica que a sessao veio do SAPL');
    confere(str_contains($html, 'Linha do tempo'), 'mantem a linha do tempo');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessao_presentes WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sessaoId]);
    $pdo->exec("DELETE FROM users WHERE id IN ($admin, $ana, $bia, $caio)");
}

finalizar_testes();
