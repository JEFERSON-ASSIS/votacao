<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();

$sqlFile = dirname(__DIR__) . '/database/historico_auditoria_schema.sql';
if (is_file($sqlFile)) {
    foreach (array_filter(array_map('trim', explode(';', file_get_contents($sqlFile)))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
    echo "Tabelas auditoria_log e sessao_cerimonial verificadas.\n";
}

try {
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM sessao_eventos')->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $cols[$col['Field']] = true;
    }
    if ($cols === []) {
        $pdo->exec(
            'CREATE TABLE sessao_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sessao_id INT NOT NULL,
                item_id INT NULL,
                user_id INT NULL,
                tipo VARCHAR(40) NOT NULL,
                descricao TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_evento_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE
            )'
        );
        echo "Tabela sessao_eventos criada.\n";
    } elseif (!isset($cols['user_id'])) {
        $pdo->exec('ALTER TABLE sessao_eventos ADD COLUMN user_id INT NULL AFTER item_id');
        echo "Coluna sessao_eventos.user_id adicionada.\n";
    }
} catch (\Throwable $e) {
    echo "Aviso sessao_eventos: " . $e->getMessage() . "\n";
}

echo "Migracao historico/auditoria concluida.\n";
