<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();

$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('partido', $cols, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN partido VARCHAR(80) NULL DEFAULT NULL AFTER role");
    echo "Coluna users.partido criada.\n";
}

if (!in_array('foto', $cols, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL DEFAULT NULL AFTER partido");
    echo "Coluna users.foto criada.\n";
}

$dir = dirname(__DIR__) . '/public/uploads/vereadores';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Pasta public/uploads/vereadores criada.\n";
}

echo "Concluído.\n";
