<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Helpers\ConducaoTexto;
use App\Models\ConducaoModelo;

$pdo = DB::conn();

echo "Removendo texto legado favoraveis/contrarios...\n";
$updTpl = $pdo->prepare('UPDATE conducao_modelo_passos SET texto_template = :t WHERE id = :id');
foreach ($pdo->query('SELECT id, texto_template FROM conducao_modelo_passos') as $row) {
    $limpo = ConducaoTexto::semInstrucaoVotacaoFisica((string) $row['texto_template']);
    if ($limpo !== (string) $row['texto_template']) {
        $updTpl->execute(['t' => $limpo, 'id' => (int) $row['id']]);
    }
}
$updRoteiro = $pdo->prepare(
    'UPDATE sessao_roteiro SET texto_fala = :f, texto_instrucao = :i WHERE id = :id'
);
foreach ($pdo->query('SELECT id, texto_fala, texto_instrucao FROM sessao_roteiro') as $row) {
    $f = ConducaoTexto::semInstrucaoVotacaoFisica((string) ($row['texto_fala'] ?? ''));
    $i = ConducaoTexto::semInstrucaoVotacaoFisica((string) ($row['texto_instrucao'] ?? ''));
    if ($f !== (string) ($row['texto_fala'] ?? '') || $i !== (string) ($row['texto_instrucao'] ?? '')) {
        $updRoteiro->execute([
            'f' => $f,
            'i' => $i !== '' ? $i : null,
            'id' => (int) $row['id'],
        ]);
    }
}

$ataId = (int) $pdo->query("SELECT id FROM conducao_modelos WHERE slug = 'ata' LIMIT 1")->fetchColumn();
if ($ataId > 0) {
    $colocoIds = $pdo->query(
        "SELECT id FROM conducao_modelo_passos
         WHERE modelo_id = {$ataId}
           AND texto_template = 'Coloco em discussao e faco a votacao.'
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (count($colocoIds) > 1) {
        $manter = (int) $colocoIds[0];
        $remover = array_map('intval', array_slice($colocoIds, 1));
        $ph = implode(',', array_fill(0, count($remover), '?'));
        $pdo->prepare("DELETE FROM conducao_modelo_passos WHERE id IN ({$ph})")->execute($remover);
        $pdo->prepare(
            'UPDATE conducao_modelo_passos SET ordem = 2, gera_votacao = 1 WHERE id = :id'
        )->execute(['id' => $manter]);
        echo 'Passos duplicados Coloco (ata) removidos: ' . count($remover) . "\n";
    }
    $aprovadaIds = $pdo->query(
        "SELECT id FROM conducao_modelo_passos
         WHERE modelo_id = {$ataId}
           AND texto_template = 'Ata da sessao anterior aprovada.'
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (count($aprovadaIds) > 1) {
        $remover = array_map('intval', array_slice($aprovadaIds, 1));
        $ph = implode(',', array_fill(0, count($remover), '?'));
        $pdo->prepare("DELETE FROM conducao_modelo_passos WHERE id IN ({$ph})")->execute($remover);
        echo 'Passos duplicados Ata aprovada removidos: ' . count($remover) . "\n";
    }
}

$pdo->exec(
    "DELETE sr FROM sessao_roteiro sr
     INNER JOIN sessao_roteiro sr2
       ON sr.sessao_id = sr2.sessao_id
      AND sr.tipo_passo = 'INSTRUCAO'
      AND sr2.tipo_passo = 'INSTRUCAO'
      AND sr.texto_instrucao = sr2.texto_instrucao
      AND sr.texto_instrucao = 'Coloco em discussao e faco a votacao.'
      AND sr.id > sr2.id"
);

echo "Deduplicando passos dos modelos...\n";
echo 'Removidos: ' . ConducaoModelo::deduplicarPassosNoBanco() . "\n";

$stmtFav = $pdo->prepare(
    "DELETE FROM conducao_modelo_passos
     WHERE tipo_passo = 'INSTRUCAO'
       AND texto_template LIKE 'Favoraveis sentados%contrarios em pe.%'"
);
$stmtFav->execute();
$favModelos = $stmtFav->rowCount();
$stmtRoteiro = $pdo->prepare(
    "DELETE FROM sessao_roteiro
     WHERE tipo_passo = 'INSTRUCAO'
       AND (
            texto_instrucao LIKE '%Favoraveis sentados%contrarios em pe.%'
         OR texto_fala LIKE '%Favoraveis sentados%contrarios em pe.%'
       )"
);
$stmtRoteiro->execute();
$favRoteiro = $stmtRoteiro->rowCount();
if ($favModelos > 0 || $favRoteiro > 0) {
    echo "Instrucao favoraveis/contrarios removida: modelos={$favModelos}, roteiro={$favRoteiro}\n";
}

$pdo->exec(
    "UPDATE conducao_modelo_passos SET gera_votacao = 0
     WHERE texto_template LIKE '%por unanimidade.%'
        OR texto_template = 'Ata da sessao anterior aprovada.'
        OR texto_template LIKE 'Pedido de urgencia%'"
);
$pdo->exec(
    "UPDATE conducao_modelo_passos SET gera_votacao = 1
     WHERE texto_template = 'Coloco em discussao e faco a votacao.'
        OR texto_template = 'Coloco em votacao o pedido de urgencia do projeto.'
        OR texto_template LIKE 'Faco a votacao%'"
);
$movidos = (int) $pdo->query(
    "SELECT COUNT(*) FROM conducao_modelo_passos WHERE gera_votacao = 1"
)->fetchColumn();
echo "Passos com votacao no sistema (gera_votacao=1): {$movidos}\n";
$modeloIds = $pdo->query('SELECT DISTINCT modelo_id FROM conducao_modelo_passos')->fetchAll(PDO::FETCH_COLUMN);
$updOrdem = $pdo->prepare('UPDATE conducao_modelo_passos SET ordem = :ordem WHERE id = :id');
foreach ($modeloIds as $mid) {
    $mid = (int) $mid;
    if ($mid <= 0) {
        continue;
    }
    $rows = $pdo->prepare(
        'SELECT id FROM conducao_modelo_passos WHERE modelo_id = :m ORDER BY ordem ASC, id ASC'
    );
    $rows->execute(['m' => $mid]);
    $ordem = 1;
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $pid) {
        $updOrdem->execute(['ordem' => $ordem++, 'id' => (int) $pid]);
    }
}

$stmt = $pdo->query("SELECT m.id FROM conducao_modelos m WHERE m.slug = 'abertura' LIMIT 1");
$modeloId = (int) $stmt->fetchColumn();
if ($modeloId > 0) {
    $passos = $pdo->prepare(
        'SELECT id, ordem, texto_template FROM conducao_modelo_passos WHERE modelo_id = :id ORDER BY ordem ASC, id ASC'
    );
    $passos->execute(['id' => $modeloId]);
    $rows = $passos->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        $textos = array_map(static fn(array $r): string => trim((string) $r['texto_template']), $rows);
        $merged = implode("\n\n", array_filter($textos));
        $keepId = (int) $rows[0]['id'];
        $pdo->prepare('UPDATE conducao_modelo_passos SET ordem = 1, texto_template = :t WHERE id = :id')
            ->execute(['t' => $merged, 'id' => $keepId]);
        $ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
        $ids = array_filter($ids, static fn(int $id): bool => $id !== $keepId);
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM conducao_modelo_passos WHERE id IN ({$ph})")->execute(array_values($ids));
        }
        echo "Abertura unificada em 1 passo (modelo_id={$modeloId}).\n";
    } elseif (count($rows) === 1) {
        $t1 = trim((string) $rows[0]['texto_template']);
        if (!str_contains($t1, 'declaro aberta')) {
            $merged = $t1 . "\n\nInvocando a protecao divina sobre os nossos trabalhos, declaro aberta a presente sessao.";
            $pdo->prepare('UPDATE conducao_modelo_passos SET texto_template = :t WHERE id = :id')
                ->execute(['t' => $merged, 'id' => (int) $rows[0]['id']]);
            echo "Abertura atualizada com texto completo.\n";
        }
    }
}

$modelosNovos = [
    ['emenda', 'Emenda', 'Emenda'],
    ['mocao', 'Mocao', 'Mocao'],
];
foreach ($modelosNovos as [$slug, $nome, $tipo]) {
    $pdo->prepare(
        'INSERT IGNORE INTO conducao_modelos (slug, nome, categoria, tipo_materia_vinculo, ativo) VALUES (:slug, :nome, \'MATERIA\', :tipo, 1)'
    )->execute(['slug' => $slug, 'nome' => $nome, 'tipo' => $tipo]);
}

echo "Modelos emenda/mocao garantidos.\n";

$passosNovos = [
    'emenda' => [
        [1, 'FALA', 'Peco a leitura da {tipo} nº {numero}/{ano}.', 0],
        [2, 'INSTRUCAO', 'Peco a defesa do autor - Vereador {autor}.', 0],
        [3, 'FALA', 'Faco a votacao da {tipo} nº {numero}/{ano}.', 1],
        [4, 'FALA', '{tipo} {resultado} por unanimidade.', 0],
    ],
    'mocao' => [
        [1, 'FALA', 'Peco a leitura da {tipo} nº {numero}/{ano}.', 0],
        [2, 'FALA', 'Faco a votacao da {tipo} nº {numero}/{ano}.', 1],
        [3, 'FALA', '{tipo} {resultado} por unanimidade.', 0],
    ],
];
foreach ($passosNovos as $slug => $passos) {
    $mid = (int) $pdo->query("SELECT id FROM conducao_modelos WHERE slug = " . $pdo->quote($slug))->fetchColumn();
    if ($mid <= 0) {
        continue;
    }
    $count = (int) $pdo->query("SELECT COUNT(*) FROM conducao_modelo_passos WHERE modelo_id = {$mid}")->fetchColumn();
    if ($count > 0) {
        continue;
    }
    $ins = $pdo->prepare(
        'INSERT INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao) VALUES (:m, :o, :t, :x, :v)'
    );
    foreach ($passos as $p) {
        $ins->execute(['m' => $mid, 'o' => $p[0], 't' => $p[1], 'x' => $p[2], 'v' => $p[3]]);
    }
    echo "Passos criados para modelo {$slug}.\n";
}

echo "Concluido. Regenere o roteiro na sessao (Gerar conducao).\n";
