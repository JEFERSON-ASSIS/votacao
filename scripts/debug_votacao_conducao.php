<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Models\SessaoCrud;
use App\Models\SessaoRoteiro;
use App\Models\SessaoVotacaoRodada;

$sessaoId = (int) ($argv[1] ?? 11);
$passo = SessaoRoteiro::passoAtual($sessaoId);
echo "Sessao {$sessaoId}\n";
if ($passo) {
    echo "Passo atual: id={$passo['id']} ordem={$passo['ordem']} gera_votacao={$passo['gera_votacao']} tipo={$passo['tipo_passo']}\n";
    echo "Fala: " . substr((string) ($passo['texto_fala'] ?? ''), 0, 80) . "\n";
    echo "Instr: " . substr((string) ($passo['texto_instrucao'] ?? ''), 0, 80) . "\n";
}
$stmt = DB::conn()->query(
    "SELECT id, ordem, gera_votacao, tipo_passo,
            LEFT(COALESCE(NULLIF(texto_fala,''), texto_instrucao), 50) AS txt
     FROM sessao_roteiro WHERE sessao_id = {$sessaoId} AND gera_votacao = 1 ORDER BY ordem"
);
echo "\nPassos com gera_votacao=1:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #{$r['ordem']} id={$r['id']} {$r['tipo_passo']}: {$r['txt']}\n";
}
$ab = SessaoVotacaoRodada::findAberta($sessaoId);
echo "\nRodada aberta: " . ($ab ? "id={$ab['id']} passo={$ab['roteiro_passo_id']} status={$ab['status']} {$ab['titulo']}" : 'nenhuma') . "\n";
if ($passo) {
    $rp = SessaoVotacaoRodada::findByPasso((int) $passo['id']);
    echo "Rodada do passo atual: " . ($rp ? "id={$rp['id']} status={$rp['status']}" : 'nenhuma') . "\n";
}
