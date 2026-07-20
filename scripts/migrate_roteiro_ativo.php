<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM sessao_roteiro')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $cols[$col['Field']] = true;
}
if (!isset($cols['ativo'])) {
    $pdo->exec('ALTER TABLE sessao_roteiro ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER gera_votacao');
    echo "Coluna ativo adicionada.\n";
} else {
    echo "Coluna ativo ja existe.\n";
}
