<?php
declare(strict_types=1);

/**
 * Zera votações localmente (testes).
 *
 * Uso:
 *   php scripts/reset_votacao_local.php              → lista sessões
 *   php scripts/reset_votacao_local.php 11           → zera sessão id 11
 *   php scripts/reset_votacao_local.php --todas      → zera todas as sessões
 *   php scripts/reset_votacao_local.php 11 --roteiro → também reinicia passos do roteiro
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Models\Sessao;
use App\Models\SessaoRoteiro;

$env = (string) config('env', 'local');
$force = in_array('--force', $argv, true);
if ($env !== 'local' && !$force) {
    fwrite(STDERR, "Ambiente não é local (APP_ENV={$env}). Use --force se tiver certeza.\n");
    exit(1);
}

$resetRoteiro = in_array('--roteiro', $argv, true);
$args = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $a): bool => !str_starts_with($a, '--')
));

$pdo = DB::conn();

if ($args === [] || $args[0] === 'help' || $args[0] === '-h') {
    echo "Sessões disponíveis:\n";
    $rows = $pdo->query(
        "SELECT id, numero, ano, titulo, status FROM sessoes WHERE status != 'RASCUNHO' ORDER BY id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf(
            "  id=%d  #%d/%d  %-12s  %s\n",
            (int) $r['id'],
            (int) $r['numero'],
            (int) $r['ano'],
            $r['status'],
            mb_strimwidth((string) $r['titulo'], 0, 50, '…')
        );
    }
    echo "\nExemplo: php scripts/reset_votacao_local.php {id}\n";
    echo "         php scripts/reset_votacao_local.php --todas --roteiro\n";
    exit(0);
}

$sessaoIds = [];
if ($args[0] === '--todas' || $args[0] === 'all') {
    $sessaoIds = $pdo->query('SELECT id FROM sessoes')->fetchAll(PDO::FETCH_COLUMN);
    $sessaoIds = array_map('intval', $sessaoIds);
} else {
    $sessaoIds = [(int) $args[0]];
}

foreach ($sessaoIds as $sessaoId) {
    if ($sessaoId <= 0) {
        continue;
    }
    $stmt = $pdo->prepare('SELECT id, titulo, status FROM sessoes WHERE id = :id');
    $stmt->execute(['id' => $sessaoId]);
    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sessao) {
        echo "Sessão {$sessaoId} não encontrada.\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        $delVotos = $pdo->prepare('DELETE FROM sessao_votos WHERE sessao_id = :id');
        $delVotos->execute(['id' => $sessaoId]);
        $nVotos = $delVotos->rowCount();

        $delRodadas = $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE sessao_id = :id');
        $delRodadas->execute(['id' => $sessaoId]);
        $nRodadas = $delRodadas->rowCount();

        $pdo->prepare(
            "UPDATE pauta_itens SET status = 'PENDENTE' WHERE sessao_id = :id AND status IN ('APROVADO','REJEITADO')"
        )->execute(['id' => $sessaoId]);

        Sessao::updateStatus($sessaoId, 'ABERTA');

        if ($resetRoteiro) {
            SessaoRoteiro::resetExecucao($sessaoId);
        }

        $pdo->commit();

        echo "Sessão {$sessaoId} — " . $sessao['titulo'] . "\n";
        echo "  Votos removidos: {$nVotos}\n";
        echo "  Rodadas removidas: {$nRodadas}\n";
        echo "  Status da sessão: ABERTA\n";
        if ($resetRoteiro) {
            echo "  Roteiro: todos os passos voltaram para PENDENTE\n";
        } else {
            echo "  Roteiro: mantido (use --roteiro para reiniciar do início)\n";
        }
        echo "\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

echo "Pronto. Abra Presidência → Conduzir sessão e teste de novo.\n";
