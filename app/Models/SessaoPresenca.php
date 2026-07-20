<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class SessaoPresenca
{
    public static function allBySessao(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT sp.*, u.nome, u.role
             FROM sessao_presentes sp
             INNER JOIN users u ON u.id = sp.user_id
             WHERE sp.sessao_id = :sessao_id
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['sessao_id' => $sessaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function set(int $sessaoId, int $userId, bool $presente): void
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO sessao_presentes (sessao_id, user_id, presente)
             VALUES (:sessao_id, :user_id, :presente)
             ON DUPLICATE KEY UPDATE presente = VALUES(presente)'
        );
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'user_id' => $userId,
            'presente' => $presente ? 1 : 0,
        ]);
    }

    public static function countPresentes(int $sessaoId): int
    {
        $stmt = DB::conn()->prepare('SELECT COUNT(*) FROM sessao_presentes WHERE sessao_id = :sessao_id AND presente = 1');
        $stmt->execute(['sessao_id' => $sessaoId]);
        return (int) $stmt->fetchColumn();
    }
}
