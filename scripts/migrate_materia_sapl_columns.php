<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
$columns = [
    'numero' => 'INT NULL',
    'ano' => 'INT NULL',
    'sapl_id' => 'INT NULL',
    'apelido' => 'VARCHAR(120) NULL',
    'indexacao' => 'TEXT NULL',
    'observacao' => 'TEXT NULL',
    'objeto' => 'TEXT NULL',
    'resultado' => 'VARCHAR(120) NULL',
    'dias_prazo' => 'INT NULL',
    'data_fim_prazo' => 'DATE NULL',
    'tipo_sapl' => 'INT NULL',
    'regime_tramitacao_id' => 'INT NULL',
    'numero_origem_externa' => 'VARCHAR(80) NULL',
    'ano_origem_externa' => 'INT NULL',
    'data_origem_externa' => 'DATE NULL',
    'tipo_origem_externa' => 'VARCHAR(120) NULL',
    'local_origem_externa' => 'VARCHAR(180) NULL',
    'autores_ids' => 'VARCHAR(255) NULL',
];

$existing = [];
$stmt = $pdo->query('SHOW COLUMNS FROM pauta_itens');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $existing[$col['Field']] = true;
}

foreach ($columns as $name => $definition) {
    if (!isset($existing[$name])) {
        $pdo->exec("ALTER TABLE pauta_itens ADD COLUMN {$name} {$definition}");
        echo "Coluna adicionada: {$name}\n";
    }
}

echo "Migracao de colunas SAPL concluida.\n";
