<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM sessoes')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $cols[$col['Field']] = true;
}

if (!isset($cols['sessao_anterior_id'])) {
    $pdo->exec('ALTER TABLE sessoes ADD COLUMN sessao_anterior_id INT NULL AFTER status');
    echo "Coluna sessao_anterior_id adicionada.\n";
}
if (!isset($cols['ata_sessao_anterior'])) {
    $pdo->exec('ALTER TABLE sessoes ADD COLUMN ata_sessao_anterior TEXT NULL AFTER sessao_anterior_id');
    echo "Coluna ata_sessao_anterior adicionada.\n";
}
if (!isset($cols['ata_conteudo'])) {
    $pdo->exec('ALTER TABLE sessoes ADD COLUMN ata_conteudo TEXT NULL AFTER ata_sessao_anterior');
    echo "Coluna ata_conteudo adicionada.\n";
}
if (!isset($cols['ata_sessao_anterior_arquivo'])) {
    $pdo->exec('ALTER TABLE sessoes ADD COLUMN ata_sessao_anterior_arquivo VARCHAR(255) NULL AFTER ata_sessao_anterior');
    echo "Coluna ata_sessao_anterior_arquivo adicionada.\n";
}
if (!isset($cols['ata_conteudo_arquivo'])) {
    $pdo->exec('ALTER TABLE sessoes ADD COLUMN ata_conteudo_arquivo VARCHAR(255) NULL AFTER ata_conteudo');
    echo "Coluna ata_conteudo_arquivo adicionada.\n";
}
echo "Migracao ata concluida.\n";
