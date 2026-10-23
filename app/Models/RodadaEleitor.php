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

    /** Situações que o presidente pode definir durante a votação. */
    public const SITUACOES_ALTERAVEIS = ['PENDENTE', 'IMPEDIDO', 'AUSENTE'];

    /**
     * Marca um vereador da votação como impedido, ausente (saiu do plenário)
     * ou o devolve para a votação (PENDENTE). Só vale com a votação aberta
     * e para quem ainda não votou.
     *
     * @throws \DomainException com mensagem pronta para exibir
     */
    public static function alterarSituacao(
        int $rodadaId,
        int $userId,
        string $situacao,
        int $operadorId,
        ?string $justificativa = null
    ): void {
        $situacao = strtoupper(trim($situacao));
        if (!in_array($situacao, self::SITUACOES_ALTERAVEIS, true)) {
            throw new \DomainException('Situação inválida.');
        }

        $pdo = DB::conn();
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
                throw new \DomainException('A votação já foi encerrada.');
            }

            $eleitor = $pdo->prepare(
                'SELECT id FROM sessao_votacao_eleitores WHERE rodada_id = :rodada_id AND user_id = :user_id'
            );
            $eleitor->execute(['rodada_id' => $rodadaId, 'user_id' => $userId]);
            $eleitorId = $eleitor->fetchColumn();
            if ($eleitorId === false) {
                throw new \DomainException('Este vereador não está na lista desta votação.');
            }

            $voto = $pdo->prepare('SELECT 1 FROM sessao_votos WHERE rodada_id = :rodada_id AND user_id = :user_id');
            $voto->execute(['rodada_id' => $rodadaId, 'user_id' => $userId]);
            if ($voto->fetchColumn()) {
                throw new \DomainException('Este vereador já votou; a situação não pode ser alterada.');
            }

            $justificativa = $justificativa !== null ? trim($justificativa) : null;
            $pdo->prepare(
                'UPDATE sessao_votacao_eleitores
                 SET situacao = :situacao, alterado_em = CURRENT_TIMESTAMP,
                     alterado_por = :operador, justificativa = :justificativa
                 WHERE id = :id'
            )->execute([
                'situacao' => $situacao,
                'operador' => $operadorId > 0 ? $operadorId : null,
                'justificativa' => $justificativa !== '' ? mb_substr((string) $justificativa, 0, 500) : null,
                'id' => (int) $eleitorId,
            ]);

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

    /** @return list<string> nomes dos vereadores com a situação informada */
    public static function nomesPorSituacao(int $rodadaId, string $situacao): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT u.nome
             FROM sessao_votacao_eleitores re
             INNER JOIN users u ON u.id = re.user_id
             WHERE re.rodada_id = :rodada_id AND re.situacao = :situacao
             ORDER BY u.nome ASC'
        );
        $stmt->execute(['rodada_id' => $rodadaId, 'situacao' => $situacao]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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
