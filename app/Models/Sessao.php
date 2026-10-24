<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class Sessao
{
    public static function atual(): ?array
    {
        $stmt = DB::conn()->query("SELECT * FROM sessoes WHERE status IN ('ABERTA','EM_VOTACAO') ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Próxima sessão marcada (hoje em diante), para o aviso da tela de login. */
    public static function proxima(): ?array
    {
        $stmt = DB::conn()->query(
            "SELECT * FROM sessoes
             WHERE status NOT IN ('RASCUNHO','ENCERRADA')
               AND data_sessao IS NOT NULL
               AND data_sessao >= CURDATE()
             ORDER BY data_sessao ASC, hora_inicio ASC, id ASC
             LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function pauta(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM pauta_itens WHERE sessao_id = :id ORDER BY ordem ASC, id ASC');
        $stmt->execute(['id' => $sessaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function updateStatus(int $sessaoId, string $status): void
    {
        $status = strtoupper(trim($status));
        if ($status === 'ENCERRADA') {
            $stmt = DB::conn()->prepare(
                'UPDATE sessoes
                 SET status = :status,
                     publicada_em = COALESCE(publicada_em, NOW())
                 WHERE id = :id'
            );
        } else {
            $stmt = DB::conn()->prepare('UPDATE sessoes SET status = :status WHERE id = :id');
        }
        $stmt->execute([
            'status' => $status,
            'id' => $sessaoId,
        ]);
    }

    public static function isAberta(array $sessao): bool
    {
        return in_array(strtoupper((string) ($sessao['status'] ?? '')), ['ABERTA', 'EM_VOTACAO'], true);
    }
}
