<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class SessaoVotoDesempate
{
    public static function registrar(int $sessaoId, int $rodadaId, int $condutorId, string $voto): void
    {
        $pdo = DB::conn();
        $sql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO sessao_votos_desempate (sessao_id, rodada_id, condutor_user_id, voto)
               VALUES (:sessao_id, :rodada_id, :condutor, :voto)
               ON CONFLICT(rodada_id) DO UPDATE SET condutor_user_id = excluded.condutor_user_id,
                   voto = excluded.voto, created_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO sessao_votos_desempate (sessao_id, rodada_id, condutor_user_id, voto)
               VALUES (:sessao_id, :rodada_id, :condutor, :voto)
               ON DUPLICATE KEY UPDATE condutor_user_id = VALUES(condutor_user_id),
                   voto = VALUES(voto), created_at = CURRENT_TIMESTAMP';
        $pdo->prepare($sql)->execute([
            'sessao_id' => $sessaoId,
            'rodada_id' => $rodadaId,
            'condutor' => $condutorId,
            'voto' => $voto,
        ]);
    }

    public static function voto(int $rodadaId): ?string
    {
        $stmt = DB::conn()->prepare('SELECT voto FROM sessao_votos_desempate WHERE rodada_id = :rodada_id LIMIT 1');
        $stmt->execute(['rodada_id' => $rodadaId]);
        $voto = $stmt->fetchColumn();
        return $voto === false ? null : (string) $voto;
    }

    public static function existe(int $rodadaId): bool
    {
        return self::voto($rodadaId) !== null;
    }
}
