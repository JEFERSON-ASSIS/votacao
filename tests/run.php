<?php
declare(strict_types=1);

/**
 * Roda todos os testes. Precisa do Apache e do MySQL ligados.
 * Uso: C:\xampp\php\php.exe tests/run.php [url-da-raiz-do-projeto]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$falhou = [];
foreach (glob(__DIR__ . '/*Test.php') as $arquivo) {
    $nome = basename($arquivo);
    echo "== $nome" . PHP_EOL;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($arquivo);
    if (isset($argv[1])) {
        $cmd .= ' ' . escapeshellarg($argv[1]);
    }
    passthru($cmd, $codigo);
    if ($codigo !== 0) {
        $falhou[] = $nome;
    }
    echo PHP_EOL;
}

echo $falhou === [] ? "TODOS OS TESTES PASSARAM\n" : 'FALHARAM: ' . implode(', ', $falhou) . PHP_EOL;
exit($falhou === [] ? 0 : 1);
