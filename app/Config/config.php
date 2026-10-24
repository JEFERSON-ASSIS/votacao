<?php

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
};

$projectRoot = dirname(__DIR__, 2);

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
if ($docRoot !== '' && str_starts_with($scriptName, $docRoot)) {
    $scriptName = substr($scriptName, strlen($docRoot));
}
if ($scriptName === '' || $scriptName[0] !== '/') {
    $scriptName = '/' . ltrim($scriptName, '/');
}

$basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
if (!str_ends_with(strtolower($basePath), '/public') && is_file($projectRoot . '/public/index.php')) {
    $basePath = $basePath . '/public';
    $basePath = rtrim(str_replace('//', '/', $basePath), '/');
}

$derivedBaseUrl = $scheme . '://' . $httpHost . $basePath;

// Domínio de produção (ex.: votacao.exemplo.com.br), sem protocolo — vem do .env
$prodDomain = strtolower(trim((string) $env('APP_PROD_DOMAIN', ''), " /"));
$prodDomain = (string) preg_replace('#^https?://#', '', $prodDomain);

$appEnv = strtolower((string) $env('APP_ENV', ''));
if (in_array($appEnv, ['production', 'prod'], true)) {
    $isLocalhost = false;
} elseif (in_array($appEnv, ['local', 'development', 'dev'], true)) {
    $isLocalhost = true;
} else {
    $isLocalhost = in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
        || str_contains($httpHost, 'localhost');

    if (PHP_SAPI === 'cli') {
        $pathHint = $projectRoot . '|' . (getcwd() ?: '');
        if (str_contains($pathHint, '/home2/')
            || ($prodDomain !== '' && str_contains($pathHint, $prodDomain))) {
            $isLocalhost = false;
        }
    }
}

if (!$isLocalhost && $env('APP_BASE_URL') === null && $prodDomain !== '') {
    $derivedBaseUrl = 'https://' . $prodDomain . '/public';
}

if ($isLocalhost) {
    $db = [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => $env('DB_PORT', '3307'),
        'name' => $env('DB_NAME', 'plenario'),
        'user' => $env('DB_USER', 'root'),
        'pass' => $env('DB_PASS', ''),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
    ];
} else {
    // Produção — credenciais vêm obrigatoriamente do .env (hPanel/Hostinger)
    $onHostinger = str_contains($projectRoot, '/home2/')
        || ($prodDomain !== '' && str_contains(strtolower($httpHost), $prodDomain));
    // PHP no mesmo servidor: localhost. Fora dele, usa DB_REMOTE_HOST do .env (acesso remoto).
    $dbHost = $onHostinger ? 'localhost' : (string) $env('DB_REMOTE_HOST', 'localhost');

    $db = [
        'host' => $env('DB_HOST', $dbHost),
        'port' => $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', ''),
        'user' => $env('DB_USER', ''),
        'pass' => $env('DB_PASS', ''),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
    ];
}

return [
    'app_name' => $env('APP_NAME', 'Plenário Vera'),
    'base_url' => $env('APP_BASE_URL', $derivedBaseUrl),
    'timezone' => $env('APP_TIMEZONE', 'America/Cuiaba'),
    'env' => $isLocalhost ? 'local' : 'production',
    'debug' => $isLocalhost || $env('APP_DEBUG', '0') === '1',
    'is_dev' => $isLocalhost,
    'app_key' => $env('APP_KEY', $isLocalhost ? 'DEV_SECRET_KEY_local' : ''),
    'db' => $db,
    'session' => [
        'name' => $env('SESSION_NAME', 'plenario_session'),
        'timeout_minutes' => (int) $env('SESSION_TIMEOUT_MINUTES', $isLocalhost ? '240' : '30'),
    ],
];
