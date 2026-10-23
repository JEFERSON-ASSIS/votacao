<?php
declare(strict_types=1);

/**
 * O presidente pode marcar um vereador da votação como "Impedido" ou
 * "Ausente" (saiu do plenário). Ele deixa de ser esperado, não pode votar,
 * e a situação aparece no painel, no telão e na ata.
 *
 * Uso: C:\xampp\php\php.exe tests/SituacaoEleitorTest.php
 */

require __DIR__ . '/_db.php';

use App\Helpers\PublicoSessaoEstado;
use App\Helpers\VotacaoPainelEstado;
use App\Models\RodadaEleitor;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;
use App\Services\SessaoAtaMinuta;

$pdo = preparar_banco_teste();

$presidente = criar_usuario_teste($pdo, 'Presidente Teste', 'PRESIDENTE');
$ana = criar_usuario_teste($pdo, 'Ana Teste');
$bruno = criar_usuario_teste($pdo, 'Bruno Teste');
$carla = criar_usuario_teste($pdo, 'Carla Teste');
$_SESSION['user'] = ['id' => $presidente, 'nome' => 'Presidente Teste', 'email' => 'p@test', 'role' => 'PRESIDENTE'];

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 1, 'EM_VOTACAO')")
    ->execute(['Sessao situacao ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, titulo, status) VALUES (?, 'Projeto teste', 'ABERTA')")
    ->execute([$sessaoId]);
$rodadaId = (int) $pdo->lastInsertId();
RodadaEleitor::criarFotografia($rodadaId, [$ana, $bruno, $carla]);

function situacao_de(PDO $pdo, int $rodadaId, int $userId): string
{
    $s = $pdo->prepare('SELECT situacao FROM sessao_votacao_eleitores WHERE rodada_id = ? AND user_id = ?');
    $s->execute([$rodadaId, $userId]);

    return (string) $s->fetchColumn();
}

function recusa(callable $acao): bool
{
    try {
        $acao();
    } catch (\DomainException $e) {
        return true;
    }

    return false;
}

try {
    // Permissão: presidente pode, vereador não
    confere(
        \App\Helpers\Permissao::legadoPermite('PRESIDENTE', 'votacao.situacaoEleitor'),
        'perfil presidente pode marcar impedido/ausente'
    );
    confere(
        !\App\Helpers\Permissao::legadoPermite('PARLAMENTAR', 'votacao.situacaoEleitor'),
        'perfil parlamentar nao pode marcar impedido/ausente'
    );
    confere(
        method_exists(\App\Controllers\VotacaoController::class, 'situacaoEleitor'),
        'existe a acao votacao/situacaoEleitor'
    );

    // Ana vota; Bruno é impedido; Carla sai do plenário
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $ana, 'SIM', null, true);

    RodadaEleitor::alterarSituacao($rodadaId, $bruno, 'IMPEDIDO', $presidente, 'Interesse pessoal na matéria');
    confere(situacao_de($pdo, $rodadaId, $bruno) === 'IMPEDIDO', 'Bruno fica marcado como IMPEDIDO');
    confere(!RodadaEleitor::usuarioApto($rodadaId, $bruno), 'vereador impedido nao pode votar');

    $nomesPendentes = array_column(RodadaEleitor::pendentes($rodadaId), 'nome');
    confere($nomesPendentes === ['Carla Teste'], 'impedido deixa de ser esperado (pendente so Carla)');

    RodadaEleitor::alterarSituacao($rodadaId, $carla, 'AUSENTE', $presidente);
    confere(RodadaEleitor::pendentes($rodadaId) === [], 'ausente deixa de ser esperado (ninguem pendente)');
    confere(VotacaoPainelEstado::validarEncerramento($rodadaId)['permitido'], 'votacao pode ser encerrada');

    // Regras de recusa
    confere(
        recusa(fn () => RodadaEleitor::alterarSituacao($rodadaId, $ana, 'IMPEDIDO', $presidente)),
        'recusa marcar quem ja votou'
    );
    confere(
        recusa(fn () => RodadaEleitor::alterarSituacao($rodadaId, $carla, 'QUALQUER', $presidente)),
        'recusa situacao invalida'
    );
    confere(
        recusa(fn () => RodadaEleitor::alterarSituacao($rodadaId, 999999, 'AUSENTE', $presidente)),
        'recusa quem nao esta na lista da votacao'
    );

    // Desfazer
    RodadaEleitor::alterarSituacao($rodadaId, $carla, 'PENDENTE', $presidente);
    confere(RodadaEleitor::usuarioApto($rodadaId, $carla), 'desfazer devolve Carla para a votacao');
    RodadaEleitor::alterarSituacao($rodadaId, $carla, 'AUSENTE', $presidente);

    // Painel do presidente
    $painel = VotacaoPainelEstado::jsonRodada($rodadaId);
    $porNome = array_column($painel['parlamentares'], null, 'nome');
    confere(($porNome['Bruno Teste']['situacao'] ?? '') === 'IMPEDIDO', 'painel informa a situacao do vereador');
    confere(($porNome['Bruno Teste']['voto_label'] ?? '') === 'Impedido', 'painel mostra a etiqueta Impedido');
    confere(($porNome['Carla Teste']['voto_label'] ?? '') === 'Ausente', 'painel mostra a etiqueta Ausente');
    confere(($porNome['Bruno Teste']['aguardando'] ?? true) === false, 'impedido nao aparece como aguardando voto');
    confere(($painel['resumo_parlamentares']['SIM'] ?? 0) === 1, 'placar conta so o voto de Ana');

    // Telão
    $telao = PublicoSessaoEstado::estadoJson($sessaoId);
    $vereadoresTelao = json_encode($telao, JSON_UNESCAPED_UNICODE);
    confere(str_contains((string) $vereadoresTelao, 'Impedido'), 'telao mostra Impedido');

    // Tela do vereador explica o impedimento / a ausência
    $estadoBruno = \App\Helpers\ParlamentarAcompanhar::estadoJson($sessaoId, $bruno);
    confere(($estadoBruno['situacao_votacao'] ?? '') === 'IMPEDIDO', 'tela do vereador sabe que ele esta impedido');
    confere(str_contains((string) ($estadoBruno['situacao_votacao_texto'] ?? ''), 'impedido'), 'tela do vereador mostra mensagem de impedimento');
    $estadoCarla = \App\Helpers\ParlamentarAcompanhar::estadoJson($sessaoId, $carla);
    confere(($estadoCarla['situacao_votacao'] ?? '') === 'AUSENTE', 'tela do vereador sabe que ele foi retirado da votacao');
    $estadoAna = \App\Helpers\ParlamentarAcompanhar::estadoJson($sessaoId, $ana);
    confere(($estadoAna['situacao_votacao'] ?? null) === null, 'vereador normal nao recebe mensagem de situacao');

    // Encerrada não pode mais ser alterada
    SessaoVotacaoRodada::encerrar($rodadaId);
    confere(
        recusa(fn () => RodadaEleitor::alterarSituacao($rodadaId, $carla, 'PENDENTE', $presidente)),
        'recusa alterar depois de encerrada'
    );

    // Ata
    $ata = SessaoAtaMinuta::gerar($sessaoId);
    confere(str_contains($ata, 'Impedidos: Bruno Teste'), 'ata registra os impedidos');
    confere(str_contains($ata, 'Ausentes na votação: Carla Teste'), 'ata registra os ausentes');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessao_eventos WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sessaoId]);
    $in = implode(',', [$presidente, $ana, $bruno, $carla]);
    $pdo->exec("DELETE FROM users WHERE id IN ($in)");
}

finalizar_testes();
