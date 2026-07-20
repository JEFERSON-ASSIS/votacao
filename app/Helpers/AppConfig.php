<?php
declare(strict_types=1);

namespace App\Helpers;

/** Lê app/Config/config.php (banco + URL + sessão). */
final class AppConfig
{
    private static ?array $config = null;

    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $file = dirname(__DIR__) . '/Config/config.php';
        $loaded = require $file;
        self::$config = is_array($loaded) ? $loaded : [];

        return self::$config;
    }

    public static function applyDatabaseEnv(): void
    {
        $config = self::load();
        if (empty($config['db']) || !is_array($config['db'])) {
            return;
        }

        $map = [
            'DB_HOST' => 'host',
            'DB_PORT' => 'port',
            'DB_NAME' => 'name',
            'DB_USER' => 'user',
            'DB_PASS' => 'pass',
            'DB_CHARSET' => 'charset',
        ];

        foreach ($map as $envKey => $dbKey) {
            if (!array_key_exists($dbKey, $config['db'])) {
                continue;
            }
            $value = (string) $config['db'][$dbKey];
            putenv($envKey . '=' . $value);
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
        }
    }
}
