<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Core\DB;

/** Registro de trilha de alterações (quem fez o quê). */
final class Auditoria
{
    public static function log(
        string $acao,
        string $entidade,
        ?int $entidadeId = null,
        ?string $detalhe = null
    ): void {
        try {
            $user = Auth::check() ? Auth::user() : null;
            $stmt = DB::conn()->prepare(
                'INSERT INTO auditoria_log (user_id, user_nome, acao, entidade, entidade_id, detalhe, ip)
                 VALUES (:user_id, :user_nome, :acao, :entidade, :entidade_id, :detalhe, :ip)'
            );
            $stmt->execute([
                'user_id' => $user ? (int) ($user['id'] ?? 0) : null,
                'user_nome' => $user ? (string) ($user['nome'] ?? '') : null,
                'acao' => $acao,
                'entidade' => $entidade,
                'entidade_id' => $entidadeId,
                'detalhe' => $detalhe,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            app_log('[Auditoria] ' . $e->getMessage());
        }
    }
}
