<?php
declare(strict_types=1);

$scripts = [
    'migrate_conducao.php',
    'migrate_roteiro_ativo.php',
    'migrate_votacao_rodadas.php',
    'migrate_materia_sapl_columns.php',
    'migrate_sessao_ata.php',
    'migrate_historico_auditoria.php',
    'migrate_sessao_publicada.php',
];

foreach ($scripts as $file) {
    $path = __DIR__ . '/' . $file;
    echo "=== {$file} ===\n";
    if (!is_file($path)) {
        fwrite(STDERR, "Arquivo nao encontrado: {$file}\n");
        exit(1);
    }
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
    passthru($php . ' ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        fwrite(STDERR, "Falha em {$file}\n");
        exit(1);
    }
    echo "\n";
}

echo "Todas as migracoes concluidas.\n";
