<?php
declare(strict_types=1);

/**
 * Integridade da votação:
 *  1) a apuração conta só os vereadores da lista da votação;
 *  2) ninguém registra voto em nome de quem não está na lista;
 *  3) presença (e destaque no telão) só para vereadores;
 *  4) a regra "só quem conduz" vale para qualquer forma de endereço.
 *
 * Uso: C:\xampp\php\php.exe tests/IntegridadeVotacaoTest.php
 */

require __DIR__ . '/_db.php';

use App\Helpers\VotacaoPainelEstado;
use App\Models\RodadaEleitor;
use App\Models\SessaoConducao;
use App\Models\SessaoPresenca;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;
use App\Models\SessaoVotoDesempate;
use App\Services\SessaoAtaMinuta;

$pdo = preparar_banco_teste();

$condutor = criar_usuario_teste($pdo, 'Presidente Condutor', 'PRESIDENTE');
$outroPresidente = criar_usuario_teste($pdo, 'Outro Presidente', 'PRESIDENTE');
$admin = criar_usuario_teste($pdo, 'Admin Teste', 'ADMIN');
$v1 = criar_usuario_teste($pdo, 'Vereador Um');
$v2 = criar_usuario_teste($pdo, 'Vereador Dois');
$estranho = criar_usuario_teste($pdo, 'Usuario Comum', 'USER');
$usuarios = [$condutor, $outroPresidente, $admin, $v1, $v2, $estranho];

function sessao_user(int $id, string $nome, string $role): array
{
    return ['id' => $id, 'nome' => $nome, 'email' => "u$id@test", 'role' => $role];
}

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 1, 'EM_VOTACAO')")
    ->execute(['Sessao integridade ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO sessao_conducao (sessao_id, titular_user_id, condutor_user_id, atualizado_por) VALUES (?, ?, ?, ?)')
    ->execute([$sessaoId, $condutor, $condutor, $condutor]);
SessaoPresenca::set($sessaoId, $v1, true);
SessaoPresenca::set($sessaoId, $v2, true);

function nova_rodada(PDO $pdo, int $sessaoId, string $titulo, array $eleitores): int
{
    $pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, titulo, status) VALUES (?, ?, 'ABERTA')")
        ->execute([$sessaoId, $titulo]);
    $id = (int) $pdo->lastInsertId();
    RodadaEleitor::criarFotografia($id, $eleitores);

    return $id;
}

try {
    // 1) Apuração só com a lista da votação
    $r1 = nova_rodada($pdo, $sessaoId, 'Apuracao', [$v1, $v2]);
    SessaoVoto::voteRodada($sessaoId, $r1, null, $v1, 'SIM');
    SessaoVoto::voteRodada($sessaoId, $r1, null, $v2, 'NAO');
    SessaoVoto::voteRodada($sessaoId, $r1, null, $estranho, 'SIM'); // voto gravado indevidamente
    SessaoVotoDesempate::registrar($sessaoId, $r1, $condutor, 'NAO');

    $_SESSION['user'] = sessao_user($condutor, 'Presidente Condutor', 'PRESIDENTE');
    $painel = VotacaoPainelEstado::jsonRodada($r1);
    confere(($painel['resumo']['SIM'] ?? -1) === 1, 'placar do painel ignora voto de fora da lista (Sim = 1)');

    $apuracao = SessaoVotacaoRodada::encerrar($r1);
    confere(($apuracao['resultado'] ?? '') === 'REJEITADO', 'empate 1x1 + desempate Nao = Rejeitado (obtido: ' . ($apuracao['resultado'] ?? '') . ')');
    confere(str_contains(SessaoAtaMinuta::gerar($sessaoId), 'Sim: 1 | Não: 1'), 'ata registra o placar so da lista');

    // 2) Voto em nome de quem não está na lista
    $r2 = nova_rodada($pdo, $sessaoId, 'Voto por terceiro', [$v1, $v2]);
    $res = rodar_cenario([
        'user' => sessao_user($admin, 'Admin Teste', 'ADMIN'),
        'metodo' => 'POST',
        'get_r' => "votacao/votarRodada/$sessaoId/$r2",
        'post' => ['user_id' => $estranho, 'voto' => 'SIM'],
    ]);
    confere(SessaoVoto::votoUsuarioRodada($r2, $estranho) === null, 'admin nao registra voto de quem nao esta na lista (http ' . $res['http'] . ')');

    // Mesmo cenário numa sessão sem condução definida
    $pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 2, 'EM_VOTACAO')")
        ->execute(['Sessao sem conducao ' . bin2hex(random_bytes(3))]);
    $sessaoSemConducao = (int) $pdo->lastInsertId();
    $r2b = nova_rodada($pdo, $sessaoSemConducao, 'Sem conducao', [$v1, $v2]);
    $res = rodar_cenario([
        'user' => sessao_user($admin, 'Admin Teste', 'ADMIN'),
        'metodo' => 'POST',
        'get_r' => "votacao/votarRodada/$sessaoSemConducao/$r2b",
        'post' => ['user_id' => $estranho, 'voto' => 'SIM'],
    ]);
    confere(SessaoVoto::votoUsuarioRodada($r2b, $estranho) === null, 'sem conducao: admin nao registra voto de quem nao esta na lista');

    // 3) Presença e destaque só para vereadores
    $res = rodar_cenario([
        'user' => sessao_user($admin, 'Admin Teste', 'ADMIN'),
        'metodo' => 'POST',
        'get_r' => "votacao/presenca/$sessaoId",
        'post' => ['user_id' => $estranho, 'presente' => '1'],
    ]);
    confere(!in_array($estranho, SessaoPresenca::idsPresentes($sessaoId), true), 'presenca recusa quem nao e vereador');

    $recusou = false;
    try {
        SessaoConducao::setDestaque($sessaoId, $estranho);
    } catch (\RuntimeException $e) {
        $recusou = true;
    }
    confere($recusou, 'destaque no telao recusa quem nao e vereador');
    confere(!in_array($estranho, SessaoPresenca::idsPresentes($sessaoId), true), 'destaque recusado nao registra presenca');

    // 4) "Só quem conduz" vale também pelo endereço alternativo (PATH_INFO)
    $r3 = nova_rodada($pdo, $sessaoId, 'Rota alternativa', [$v1, $v2]);
    $res = rodar_cenario([
        'user' => sessao_user($outroPresidente, 'Outro Presidente', 'PRESIDENTE'),
        'metodo' => 'POST',
        'path_info' => "/votacao/situacaoEleitor/$sessaoId/$r3",
        'post' => ['user_id' => $v2, 'situacao' => 'AUSENTE'],
    ]);
    confere(RodadaEleitor::usuarioApto($r3, $v2), 'presidente que nao conduz nao altera a votacao pelo endereco alternativo');
    confere($res['http'] === 403, 'endereco alternativo responde 403 (obtido ' . $res['http'] . ')');

    $res = rodar_cenario([
        'user' => sessao_user($condutor, 'Presidente Condutor', 'PRESIDENTE'),
        'metodo' => 'POST',
        'path_info' => "/votacao/situacaoEleitor/$sessaoId/$r3",
        'post' => ['user_id' => $v2, 'situacao' => 'AUSENTE'],
    ]);
    confere(!RodadaEleitor::usuarioApto($r3, $v2), 'quem conduz continua podendo agir pelo endereco alternativo');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    foreach (array_filter([$sessaoId, $sessaoSemConducao ?? 0]) as $sid) {
        $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE sessao_id = ?')->execute([$sid]);
        $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sid]);
    }
    $in = implode(',', $usuarios);
    foreach (['auditoria_logs', 'sessao_eventos'] as $tabela) {
        try {
            $pdo->exec("DELETE FROM $tabela WHERE user_id IN ($in)");
        } catch (\Throwable $e) {
        }
    }
    $pdo->exec("DELETE FROM users WHERE id IN ($in)");
}

finalizar_testes();
