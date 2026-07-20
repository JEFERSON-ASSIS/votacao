<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();

$cols = $pdo->query("SHOW COLUMNS FROM sessoes LIKE 'publicada_em'")->fetchAll();
if ($cols !== []) {
    echo "Coluna publicada_em já existe.\n";
    exit(0);
}

$sql = file_get_contents(dirname(__DIR__) . '/database/sessao_publicada_schema.sql');
if ($sql === false) {
    fwrite(STDERR, "Não foi possível ler sessao_publicada_schema.sql\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt !== '') {
        $pdo->exec($stmt);
    }
}

echo "Migracao sessao publicada concluida.\n";
