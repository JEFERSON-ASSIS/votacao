<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\UserPerfil;
use PDO;

final class SessaoVoto
{
    public static function allByItem(int $itemId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT sv.*, u.nome, u.role, u.partido, u.foto
             FROM sessao_votos sv
             INNER JOIN users u ON u.id = sv.user_id
             WHERE sv.item_id = :item_id AND sv.rodada_id IS NULL
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function votoUsuarioRodada(int $rodadaId, int $userId): ?string
    {
        $stmt = DB::conn()->prepare(
            'SELECT voto FROM sessao_votos WHERE rodada_id = :rodada_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['rodada_id' => $rodadaId, 'user_id' => $userId]);
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    public static function allByRodada(int $rodadaId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT sv.*, u.nome, u.role, u.partido, u.foto
             FROM sessao_votos sv
             INNER JOIN users u ON u.id = sv.user_id
             WHERE sv.rodada_id = :rodada_id
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['rodada_id' => $rodadaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function vote(int $sessaoId, int $itemId, int $userId, string $voto, ?string $observacao = null): void
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO sessao_votos (sessao_id, item_id, user_id, voto, observacao)
             VALUES (:sessao_id, :item_id, :user_id, :voto, :observacao)
             ON DUPLICATE KEY UPDATE voto = VALUES(voto), observacao = VALUES(observacao)'
        );
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'item_id' => $itemId,
            'user_id' => $userId,
            'voto' => self::normalizarVoto($voto),
            'observacao' => $observacao,
        ]);
    }

    public static function voteRodada(
        int $sessaoId,
        int $rodadaId,
        ?int $itemId,
        int $userId,
        string $voto,
        ?string $observacao = null
    ): void {
        $pdo = DB::conn();
        $votoNorm = self::normalizarVoto($voto);
        $itemIdVal = $itemId > 0 ? $itemId : null;
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $lockSql = 'SELECT status FROM sessao_votacao_rodadas WHERE id = :id';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lockSql .= ' FOR UPDATE';
            }
            $lock = $pdo->prepare($lockSql);
            $lock->execute(['id' => $rodadaId]);
            if ((string) $lock->fetchColumn() !== 'ABERTA') {
                throw new \RuntimeException('A votação já foi encerrada.');
            }

            $stmt = $pdo->prepare(
            'SELECT id FROM sessao_votos WHERE rodada_id = :rodada_id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute(['rodada_id' => $rodadaId, 'user_id' => $userId]);
            $existenteId = $stmt->fetchColumn();

            if ($existenteId !== false) {
                $upd = $pdo->prepare(
                'UPDATE sessao_votos
                 SET sessao_id = :sessao_id, rodada_id = :rodada_id, item_id = :item_id,
                     voto = :voto, observacao = :observacao
                 WHERE id = :id'
                );
                $upd->execute([
                    'sessao_id' => $sessaoId,
                    'rodada_id' => $rodadaId,
                    'item_id' => $itemIdVal,
                    'voto' => $votoNorm,
                    'observacao' => $observacao,
                    'id' => (int) $existenteId,
                ]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO sessao_votos (sessao_id, rodada_id, item_id, user_id, voto, observacao)
                     VALUES (:sessao_id, :rodada_id, :item_id, :user_id, :voto, :observacao)'
                );
                $ins->execute([
                    'sessao_id' => $sessaoId,
                    'rodada_id' => $rodadaId,
                    'item_id' => $itemIdVal,
                    'user_id' => $userId,
                    'voto' => $votoNorm,
                    'observacao' => $observacao,
                ]);
            }

            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function resumoPorItem(int $itemId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT voto, COUNT(*) total
             FROM sessao_votos
             WHERE item_id = :item_id AND rodada_id IS NULL
             GROUP BY voto'
        );
        $stmt->execute(['item_id' => $itemId]);
        return self::mapResumo($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public static function resumoPorRodada(int $rodadaId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT voto, COUNT(*) total
             FROM sessao_votos
             WHERE rodada_id = :rodada_id
             GROUP BY voto'
        );
        $stmt->execute(['rodada_id' => $rodadaId]);
        return self::mapResumo($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Apenas votos de parlamentares (desempate não considera presidente). */
    public static function resumoParlamentaresPorRodada(int $rodadaId): array
    {
        $contagem = [];
        $eleitores = array_fill_keys(array_map(
            static fn (array $u): int => (int) $u['id'],
            RodadaEleitor::eleitores($rodadaId)
        ), true);
        foreach (self::allByRodada($rodadaId) as $v) {
            if ($eleitores !== [] && !isset($eleitores[(int) ($v['user_id'] ?? 0)])) {
                continue;
            }
            $tipo = self::normalizarVoto((string) ($v['voto'] ?? ''));
            $contagem[$tipo] = ($contagem[$tipo] ?? 0) + 1;
        }

        return self::mapResumo($contagem);
    }

    public static function totalPorItem(int $itemId): int
    {
        $stmt = DB::conn()->prepare(
            'SELECT COUNT(*) FROM sessao_votos WHERE item_id = :item_id AND rodada_id IS NULL'
        );
        $stmt->execute(['item_id' => $itemId]);
        return (int) $stmt->fetchColumn();
    }

    public static function totalPorRodada(int $rodadaId): int
    {
        $stmt = DB::conn()->prepare('SELECT COUNT(*) FROM sessao_votos WHERE rodada_id = :rodada_id');
        $stmt->execute(['rodada_id' => $rodadaId]);
        return (int) $stmt->fetchColumn();
    }

    public static function resultado(int $itemId): string
    {
        return self::resultadoFromResumo(self::resumoPorItem($itemId));
    }

    /** @param array<string, int> $resumo */
    public static function resultadoFromResumo(array $resumo): string
    {
        if ($resumo['SIM'] > $resumo['NAO']) {
            return 'APROVADO';
        }
        if ($resumo['NAO'] > $resumo['SIM']) {
            return 'REJEITADO';
        }
        return 'EMPATADO';
    }

    /** @param array<string|int, string|int> $rows */
    private static function mapResumo(array $rows): array
    {
        return [
            'SIM' => (int) ($rows['SIM'] ?? 0),
            'NAO' => (int) ($rows['NAO'] ?? 0),
            'ABSTENCAO' => (int) ($rows['ABSTENCAO'] ?? 0),
            'AUSENTE' => (int) ($rows['AUSENTE'] ?? 0),
        ];
    }

    private static function normalizarVoto(string $voto): string
    {
        return strtoupper(trim($voto));
    }
}
