<?php
declare(strict_types=1);

namespace App\Helpers;

/** Lê .env + app/Config/config.php (banco + URL + sessão). */
final class AppConfig
{
    private static ?array $config = null;
    private static bool $envLoaded = false;

    /** Carrega chave=valor do arquivo .env para getenv/$_ENV (sem sobrescrever o que já existe). */
    public static function loadDotEnv(?string $path = null): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        $file = $path ?? dirname(__DIR__, 2) . '/.env';
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            // Já definido no ambiente do servidor (hPanel) tem prioridade.
            $existing = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($existing !== false && $existing !== null && $existing !== '') {
                continue;
            }

            $value = trim(substr($line, $pos + 1));
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::loadDotEnv();

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
