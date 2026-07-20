<?php
declare(strict_types=1);

/**
 * Diagnóstico rápido na Hostinger. Acesse: /public/ping.php
 * Apague este arquivo depois que o site estiver ok.
 */
header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . "\n";
echo "SAPI " . PHP_SAPI . "\n\n";

$configFile = dirname(__DIR__) . '/app/Config/config.php';
if (!is_file($configFile)) {
    echo "ERRO: app/Config/config.php não encontrado\n";
    exit(1);
}

try {
    require_once dirname(__DIR__) . '/app/Helpers/AppConfig.php';
    \App\Helpers\AppConfig::applyDatabaseEnv();
    $cfg = \App\Helpers\AppConfig::load();
    $db = $cfg['db'] ?? [];
    echo "env=" . ($cfg['env'] ?? '?') . "\n";
    echo "DB host=" . ($db['host'] ?? '?') . " port=" . ($db['port'] ?? '?') . "\n";
    echo "DB name=" . ($db['name'] ?? '?') . " user=" . ($db['user'] ?? '?') . "\n\n";

    require dirname(__DIR__) . '/app/Core/DB.php';
    $pdo = \App\Core\DB::conn();
    echo "OK: conexão MySQL funcionou.\n";
} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
