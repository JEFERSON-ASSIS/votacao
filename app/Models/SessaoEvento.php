<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class SessaoEvento
{
    public static function registrar(int $sessaoId, string $tipo, string $descricao, ?int $itemId = null): void
    {
        $uid = null;
        if (Auth::check()) {
            $uid = (int) (Auth::user()['id'] ?? 0) ?: null;
        }
        self::create($sessaoId, $tipo, $descricao, $itemId, $uid);
    }

    public static function create(
        int $sessaoId,
        string $tipo,
        string $descricao,
        ?int $itemId = null,
        ?int $userId = null
    ): void {
        $stmt = DB::conn()->prepare(
            'INSERT INTO sessao_eventos (sessao_id, item_id, user_id, tipo, descricao)
             VALUES (:sessao_id, :item_id, :user_id, :tipo, :descricao)'
        );
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'item_id' => $itemId,
            'user_id' => $userId,
            'tipo' => $tipo,
            'descricao' => $descricao,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function allBySessao(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT e.*, u.nome AS user_nome
             FROM sessao_eventos e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.sessao_id = :sessao_id
             ORDER BY e.id DESC'
        );
        $stmt->execute(['sessao_id' => $sessaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public static function timelineBySessao(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT e.*, u.nome AS user_nome
             FROM sessao_eventos e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.sessao_id = :sessao_id
             ORDER BY e.id ASC'
        );
        $stmt->execute(['sessao_id' => $sessaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
