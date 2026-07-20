<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\PermissaoCatalog;
use PDO;

final class UserPermissao
{
    public static function usuarioTemPersonalizado(int $userId): bool
    {
        $stmt = DB::conn()->prepare(
            'SELECT 1 FROM user_permissoes WHERE user_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<string> */
    public static function chavesDoUsuario(int $userId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT permissao FROM user_permissoes WHERE user_id = :id ORDER BY permissao'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** @param list<string> $chaves */
    public static function salvar(int $userId, array $chaves): void
    {
        $validas = array_flip(PermissaoCatalog::todasChaves());
        $chaves = array_values(array_unique(array_filter(
            $chaves,
            static fn (string $c) => isset($validas[$c])
        )));

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM user_permissoes WHERE user_id = :id');
            $del->execute(['id' => $userId]);

            if ($chaves !== []) {
                $ins = $pdo->prepare(
                    'INSERT INTO user_permissoes (user_id, permissao) VALUES (:user_id, :permissao)'
                );
                foreach ($chaves as $chave) {
                    $ins->execute(['user_id' => $userId, 'permissao' => $chave]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function limpar(int $userId): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM user_permissoes WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
    }

    /** Concede todas as permissões (ex.: administrador). */
    public static function concederTodas(int $userId): void
    {
        self::salvar($userId, PermissaoCatalog::todasChaves());
    }
}
