<?php
declare(strict_types=1);

/**
 * Retirada de pauta durante a condução: pula os passos da matéria, marca a
 * matéria como retirada (com motivo), registra na linha do tempo e na ata,
 * e pode ser desfeita.
 *
 * Uso: C:\xampp\php\php.exe tests/RetiradaPautaTest.php
 */

require __DIR__ . '/_db.php';

use App\Models\SessaoRoteiro;
use App\Services\RetiradaPauta;
use App\Services\SessaoAtaMinuta;

$pdo = preparar_banco_teste();
$condutor = criar_usuario_teste($pdo, 'Presidente Retirada', 'PRESIDENTE');
$outro = criar_usuario_teste($pdo, 'Outro Presidente Retirada', 'PRESIDENTE');
$userCondutor = ['id' => $condutor, 'nome' => 'Presidente Retirada', 'email' => 'p@test', 'role' => 'PRESIDENTE'];
$userOutro = ['id' => $outro, 'nome' => 'Outro', 'email' => 'o@test', 'role' => 'PRESIDENTE'];

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 8, 'ABERTA')")
    ->execute(['Sessao retirada ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO sessao_conducao (sessao_id, titular_user_id, condutor_user_id, atualizado_por) VALUES (?, ?, ?, ?)')
    ->execute([$sessaoId, $condutor, $condutor, $condutor]);

$item = static function (string $titulo) use ($pdo, $sessaoId): int {
    $pdo->prepare('INSERT INTO pauta_itens (sessao_id, titulo) VALUES (?, ?)')->execute([$sessaoId, $titulo]);

    return (int) $pdo->lastInsertId();
};
$passo = static function (int $ordem, string $tipoItem, ?int $itemId, string $texto, string $status = 'PENDENTE', int $votacao = 0) use ($pdo, $sessaoId): int {
    $pdo->prepare('INSERT INTO sessao_roteiro (sessao_id, ordem, tipo_item, pauta_item_id, texto_fala, status_execucao, gera_votacao) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$sessaoId, $ordem, $tipoItem, $itemId, $texto, $status, $votacao]);

    return (int) $pdo->lastInsertId();
};
$status = static fn (int $id): string => (string) $pdo->query("SELECT status_execucao FROM sessao_roteiro WHERE id = $id")->fetchColumn();

try {
    $pll = $item('Projeto de Lei Legislativo nº 9/2026');
    $ind = $item('Indicação nº 8/2026');
    $abertura = $passo(1, 'SESSAO', null, 'Abertura', 'CONCLUIDO');
    $leitura = $passo(2, 'CONDUCAO', $pll, 'Peço a leitura do PLL 9/2026.', 'EM_ANDAMENTO');
    $defesa = $passo(3, 'CONDUCAO', null, 'Com a palavra o autor.');
    $votacao = $passo(4, 'CONDUCAO', null, 'Coloco em votação.', 'PENDENTE', 1);
    $leituraInd = $passo(5, 'CONDUCAO', $ind, 'Peço a leitura da Indicação.');
    $votInd = $passo(6, 'CONDUCAO', $ind, 'Coloco em votação a Indicação.', 'PENDENTE', 1);
    $tribuna = $passo(7, 'TRIBUNA', null, 'Tribuna livre');

    $materia = RetiradaPauta::materiaDoPasso(SessaoRoteiro::find($defesa));
    confere((int) ($materia['id'] ?? 0) === $pll, 'identifica a materia do passo (passo herda a materia anterior)');
    confere(RetiradaPauta::materiaDoPasso(SessaoRoteiro::find($tribuna)) === null, 'tribuna nao pertence a nenhuma materia');

    // Quem não conduz não retira
    rodar_cenario(['user' => $userOutro, 'metodo' => 'POST', 'get_r' => "presidencia/retirarPauta/$sessaoId", 'post' => ['pauta_item_id' => $pll, 'motivo' => 'A pedido do autor']]);
    confere($status($leitura) === 'EM_ANDAMENTO', 'presidente que nao conduz nao retira materia');

    // Quem conduz retira
    rodar_cenario(['user' => $userCondutor, 'metodo' => 'POST', 'get_r' => "presidencia/retirarPauta/$sessaoId", 'post' => ['pauta_item_id' => $pll, 'motivo' => 'A pedido do autor']]);
    confere($status($leitura) === 'RETIRADO' && $status($defesa) === 'RETIRADO' && $status($votacao) === 'RETIRADO', 'passos da materia ficam como retirados');
    confere($status($leituraInd) === 'PENDENTE', 'passos da proxima materia nao mudam');
    confere((int) (SessaoRoteiro::passoAtual($sessaoId)['id'] ?? 0) === $leituraInd, 'conducao segue para a proxima materia');
    $row = $pdo->query("SELECT status, retirada_motivo FROM pauta_itens WHERE id = $pll")->fetch(PDO::FETCH_ASSOC);
    confere($row['status'] === 'RETIRADA' && $row['retirada_motivo'] === 'A pedido do autor', 'materia marcada como retirada, com motivo');
    $contagem = SessaoRoteiro::contagem($sessaoId);
    confere($contagem['concluidos'] === 4, 'progresso conta os passos retirados como resolvidos (' . $contagem['concluidos'] . ')');
    $evento = (int) $pdo->query("SELECT COUNT(*) FROM sessao_eventos WHERE sessao_id = $sessaoId AND descricao LIKE '%retirado de pauta%'")->fetchColumn();
    confere($evento === 1, 'registrado na linha do tempo');
    confere(str_contains(SessaoAtaMinuta::gerar($sessaoId), 'Projeto de Lei Legislativo nº 9/2026 — retirado de pauta (A pedido do autor)'), 'aparece na minuta da ata');

    // Não retira matéria com votação aberta
    $pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, roteiro_passo_id, item_id, titulo, status) VALUES (?, ?, ?, 'Indicação', 'ABERTA')")
        ->execute([$sessaoId, $votInd, $ind]);
    $recusou = false;
    try {
        RetiradaPauta::retirar($sessaoId, $ind, 'A pedido do autor', $condutor);
    } catch (\DomainException $e) {
        $recusou = true;
    }
    confere($recusou, 'nao retira materia com votacao aberta');

    // Desfazer
    RetiradaPauta::desfazer($sessaoId, $pll, $condutor);
    confere($status($leitura) === 'PENDENTE' && $status($votacao) === 'PENDENTE', 'desfazer devolve os passos para a pauta');
    confere($pdo->query("SELECT status FROM pauta_itens WHERE id = $pll")->fetchColumn() === 'PENDENTE', 'desfazer devolve a materia para pendente');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach (['sessao_votacao_rodadas', 'sessao_roteiro', 'pauta_itens', 'sessao_eventos'] as $t) {
        $pdo->exec("DELETE FROM $t WHERE sessao_id = $sessaoId");
    }
    $pdo->exec("DELETE FROM sessoes WHERE id = $sessaoId");
    $pdo->exec("DELETE FROM users WHERE id IN ($condutor, $outro)");
}

finalizar_testes();
