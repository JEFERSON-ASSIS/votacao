<?php
declare(strict_types=1);

/**
 * Corrige dump SQL do MySQL 8 (utf8mb4_0900_ai_ci) para Hostinger/MariaDB.
 * Uso: php scripts/fix_dump_collation.php [arquivo.sql]
 */

$collate = 'utf8mb4_unicode_ci';
$file = $argv[1] ?? (__DIR__ . '/../storage/migrate/plenario_dump.sql');

if (!is_file($file)) {
    fwrite(STDERR, "Arquivo nao encontrado: {$file}\n");
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Nao foi possivel ler o arquivo.\n");
    exit(1);
}

$sql = str_replace(
    ['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin'],
    $collate,
    $sql
);

$out = preg_replace('/\.sql$/i', '_hostinger.sql', $file) ?: ($file . '_hostinger.sql');
file_put_contents($out, $sql);

echo "Arquivo corrigido: {$out}\n";
echo "Importe este no phpMyAdmin da Hostinger (ou: php scripts/migrate_dados_completo.php import {$out})\n";
