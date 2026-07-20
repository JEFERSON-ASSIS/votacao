<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\DB;
use PDO;

final class DbSchema
{
    private static array $cache = [];

    public static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$cache)) {
            return self::$cache[$table];
        }

        try {
            $stmt = DB::conn()->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
            );
            $stmt->execute(['t' => $table]);
            self::$cache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$cache[$table] = false;
        }

        return self::$cache[$table];
    }
}
