<?php
declare(strict_types=1);

/**
 * Corrige votos gravados sem rodada_id (legado) para a rodada aberta da mesma sessão.
 * Uso: php scripts/reparar_votos_rodada.php [sessao_id]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Models\SessaoVotacaoRodada;

$sessaoId = (int) ($argv[1] ?? 0);
if ($sessaoId <= 0) {
    echo "Uso: php scripts/reparar_votos_rodada.php <sessao_id>\n";
    exit(1);
}

$rodada = SessaoVotacaoRodada::findAberta($sessaoId);
if (!$rodada) {
    echo "Nenhuma rodada aberta na sessão {$sessaoId}.\n";
    exit(0);
}

$rodadaId = (int) $rodada['id'];
$pdo = DB::conn();

$stmt = $pdo->prepare(
    'UPDATE sessao_votos
     SET rodada_id = :rodada_id
     WHERE sessao_id = :sessao_id
       AND rodada_id IS NULL
       AND voto IN (\'SIM\', \'NAO\', \'ABSTENCAO\')'
);
$stmt->execute(['rodada_id' => $rodadaId, 'sessao_id' => $sessaoId]);

echo 'Votos reparados (rodada_id atribuído): ' . $stmt->rowCount() . PHP_EOL;
