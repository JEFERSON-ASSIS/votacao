<?php
declare(strict_types=1);

/**
 * Executa UMA requisição simulada pelo Router, no banco de teste.
 * Chamado por rodar_cenario() (tests/_db.php) em um processo separado,
 * porque as ações terminam com exit/header.
 *
 * Argumento: JSON {user: {...}, metodo, get_r?, path_info?, post: {...}}
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

putenv('DB_NAME=plenario_test');
$_ENV['DB_NAME'] = 'plenario_test';

$cfg = json_decode((string) ($argv[1] ?? '{}'), true) ?: [];

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

$_SESSION['user'] = $cfg['user'] ?? null;
$_SERVER['REQUEST_METHOD'] = $cfg['metodo'] ?? 'GET';
$_GET = [];
$_POST = $cfg['post'] ?? [];
$_POST['csrf_token'] = csrf_token();
if (!empty($cfg['get_r'])) {
    $_GET['r'] = $cfg['get_r'];
}
if (!empty($cfg['path_info'])) {
    $_SERVER['PATH_INFO'] = $cfg['path_info'];
}

register_shutdown_function(static function (): void {
    echo "\n__HTTP__=" . (http_response_code() ?: 200);
});

Router::dispatch();
