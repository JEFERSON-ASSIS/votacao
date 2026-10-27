<?php
declare(strict_types=1);

if (defined('PLENARIO_BOOTSTRAP_LOADED')) {
    return;
}
define('PLENARIO_BOOTSTRAP_LOADED', true);

require_once __DIR__ . '/Helpers/AppConfig.php';
\App\Helpers\AppConfig::loadDotEnv();
\App\Helpers\AppConfig::applyDatabaseEnv();

$config = \App\Helpers\AppConfig::load();

date_default_timezone_set($config['timezone'] ?? 'America/Cuiaba');
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
error_reporting(!empty($config['debug']) ? E_ALL : 0);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $err['message']
        . ' in ' . $err['file'] . ':' . $err['line'] . PHP_EOL;
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
});

$sessionName = (string) (($config['session']['name'] ?? null) ?: 'plenario_session');
session_name($sessionName);
// Login no Redis com trava: uma tela ao vivo faz várias consultas ao mesmo tempo.
// Com espera curta (40 ms) a consulta que não pegava a trava "perdia" o login e a
// tela mostrava "Sua sessão expirou". Espera até 5 s.
if (ini_get('session.save_handler') === 'redis') {
    @ini_set('redis.session.lock_retries', '100');
    @ini_set('redis.session.lock_wait_time', '50000');
}
session_start();

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Helpers/functions.php';

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        require_once __DIR__ . '/Helpers/AppConfig.php';
        $config = \App\Helpers\AppConfig::load();
    }

    return $config[$key] ?? $default;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

/** URL do front controller (index.php). */
function app_index_url(): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    $indexFile = dirname(__DIR__) . '/public/index.php';

    return is_file($indexFile) ? $base . '/index.php' : $base;
}

/** URL interna (?r=rota) — sempre via index.php e query codificada (redirect seguro). */
function route_url(string $route, array $query = []): string
{
    $route = trim($route, '/');
    $query = array_merge(['r' => $route], $query);

    return app_index_url() . '?' . http_build_query($query);
}

/** Redireciona para rota interna e encerra. */
function redirect_route(string $route, array $query = []): never
{
    header('Location: ' . route_url($route, $query), true, 302);
    exit;
}

/** Base URL da pasta public/ (CSS, JS, imagens, uploads). */
function public_base_url(): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    if ($base === '') {
        return '';
    }
    if (str_ends_with(strtolower($base), '/public')) {
        return $base;
    }
    // Servidor já aponta para public/ (ex.: Docker na VPS): não acrescenta "/public".
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot !== false && $docRoot === realpath(dirname(__DIR__) . '/public')) {
        return $base;
    }
    if (is_file(dirname(__DIR__) . '/public/index.php')) {
        return $base . '/public';
    }

    return $base;
}

function asset_url(string $path): string
{
    $relative = ltrim($path, '/');
    $absolute = dirname(__DIR__) . '/public/' . $relative;
    $url = public_base_url() . '/' . $relative;
    if (is_file($absolute)) {
        $url .= '?v=' . filemtime($absolute);
    }

    return $url;
}

function favicon_url(): string
{
    return asset_url('assets/img/favicon.svg');
}

function app_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logDir . '/app.log', $line . PHP_EOL, FILE_APPEND);
    // Também vai para o error_log do Apache/Hostinger (mais fácil de ver no painel).
    @error_log('Plenario: ' . $message);
}
