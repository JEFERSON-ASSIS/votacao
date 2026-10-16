<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class RodadaEleitor
{
    /** @param list<int> $userIds */
    public static function criarFotografia(int $rodadaId, array $userIds): int
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'INSERT INTO sessao_votacao_eleitores (rodada_id, user_id, situacao, origem)
             VALUES (:rodada_id, :user_id, :situacao, :origem)'
        );
        $total = 0;
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            if ($userId <= 0) {
                continue;
            }
            $stmt->execute([
                'rodada_id' => $rodadaId,
                'user_id' => $userId,
                'situacao' => 'PENDENTE',
                'origem' => 'PRESENCA_SESSAO',
            ]);
            $total++;
        }

        return $total;
    }

    public static function total(int $rodadaId): int
    {
        $stmt = DB::conn()->prepare('SELECT COUNT(*) FROM sessao_votacao_eleitores WHERE rodada_id = :rodada_id');
        $stmt->execute(['rodada_id' => $rodadaId]);
        return (int) $stmt->fetchColumn();
    }

    public static function usuarioApto(int $rodadaId, int $userId): bool
    {
        $stmt = DB::conn()->prepare(
            "SELECT 1 FROM sessao_votacao_eleitores
             WHERE rodada_id = :rodada_id AND user_id = :user_id AND situacao = 'PENDENTE' LIMIT 1"
        );
        $stmt->execute(['rodada_id' => $rodadaId, 'user_id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public static function eleitores(int $rodadaId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT re.id AS eleitor_id,
                    re.user_id AS id,
                    re.user_id,
                    re.situacao,
                    re.origem,
                    re.incluido_em,
                    re.alterado_em,
                    re.alterado_por,
                    re.justificativa,
                    u.nome,
                    u.partido,
                    u.foto,
                    sv.voto
             FROM sessao_votacao_eleitores re
             INNER JOIN users u ON u.id = re.user_id
             LEFT JOIN sessao_votos sv ON sv.rodada_id = re.rodada_id AND sv.user_id = re.user_id
             WHERE re.rodada_id = :rodada_id
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['rodada_id' => $rodadaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public static function pendentes(int $rodadaId): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT re.user_id, u.nome
             FROM sessao_votacao_eleitores re
             INNER JOIN users u ON u.id = re.user_id
             LEFT JOIN sessao_votos sv ON sv.rodada_id = re.rodada_id AND sv.user_id = re.user_id
             WHERE re.rodada_id = :rodada_id
               AND re.situacao = 'PENDENTE'
               AND sv.id IS NULL
             ORDER BY u.nome ASC"
        );
        $stmt->execute(['rodada_id' => $rodadaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
