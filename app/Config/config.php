<?php
declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
};

$projectRoot = dirname(__DIR__, 2);
$envFile = $projectRoot . '/.env';
$hasEnvFile = is_file($envFile);

/** DB: usa .env se existir; senão só os defaults abaixo (evita DB_USER=root do shell da Hostinger). */
$dbSetting = static function (string $key, string $default) use ($env, $hasEnvFile): string {
    if (!$hasEnvFile) {
        return $default;
    }

    return (string) $env($key, $default);
};

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
    $basePath = ($basePath !== '' ? $basePath : '') . '/public';
    $basePath = rtrim(str_replace('//', '/', $basePath), '/');
}

$derivedBaseUrl = $scheme . '://' . $httpHost . ($basePath !== '' ? $basePath : '');

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
            || str_contains($pathHint, 'agendaclique.com.br')
            || str_contains($pathHint, 'votacao.agendaclique')) {
            $isLocalhost = false;
        }
    }
}

if (!$isLocalhost && $env('APP_BASE_URL') === null) {
    $derivedBaseUrl = 'https://votacao.agendaclique.com.br/public';
}

if ($isLocalhost) {
    $db = [
        'host' => $dbSetting('DB_HOST', '127.0.0.1'),
        'port' => $dbSetting('DB_PORT', '3307'),
        'name' => $dbSetting('DB_NAME', 'plenario'),
        'user' => $dbSetting('DB_USER', 'root'),
        'pass' => $dbSetting('DB_PASS', ''),
        'charset' => $dbSetting('DB_CHARSET', 'utf8mb4'),
    ];
} else {
    // Produção — credenciais vêm obrigatoriamente do .env (hPanel/Hostinger)
    $onHostinger = str_contains($projectRoot, '/home2/')
        || str_contains($httpHost, 'agendaclique.com.br');
    // PHP no mesmo servidor: localhost. IP 162.x = acesso remoto (outro PC).
    $dbHost = $onHostinger ? 'localhost' : '162.241.63.71';

    $db = [
        'host' => $dbSetting('DB_HOST', $dbHost),
        'port' => $dbSetting('DB_PORT', '3306'),
        'name' => $dbSetting('DB_NAME', ''),
        'user' => $dbSetting('DB_USER', ''),
        'pass' => $dbSetting('DB_PASS', ''),
        'charset' => $dbSetting('DB_CHARSET', 'utf8mb4'),
    ];
}

return [
    'app_name' => $env('APP_NAME', 'Plenario'),
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
