<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();

foreach (['conducao_schema.sql', 'conducao_seed.sql'] as $file) {
    $path = __DIR__ . '/../database/' . $file;
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Nao foi possivel ler {$file}\n");
        exit(1);
    }
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql) ?: []));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    echo "Aplicado: {$file}\n";
}

use App\Models\ConducaoModelo;

require_once __DIR__ . '/fix_conducao_modelos.php';
