<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\DbSchema;
use PDO;

final class AuditoriaLog
{
    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 200, ?string $entidade = null): array
    {
        if (!DbSchema::tableExists('auditoria_log')) {
            return [];
        }

        $sql = 'SELECT * FROM auditoria_log';
        $params = [];
        if ($entidade !== null && $entidade !== '') {
            $sql .= ' WHERE entidade = :entidade';
            $params['entidade'] = $entidade;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
