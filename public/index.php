<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

try {
    Router::dispatch();
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log(
            'uncaught: ' . $e::class
            . ' — ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine()
        );
    } else {
        @error_log('Plenario uncaught: ' . $e->getMessage());
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro interno: ' . $e->getMessage() . "\n";
    echo 'Arquivo: ' . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "Veja também logs/app.log e error_log no servidor.\n";
}
