<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class SessaoVotacaoRodada
{
    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessao_votacao_rodadas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findAberta(int $sessaoId): ?array
    {
        $stmt = DB::conn()->prepare(
            "SELECT * FROM sessao_votacao_rodadas
             WHERE sessao_id = :id AND status = 'ABERTA'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['id' => $sessaoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findByPasso(int $roteiroPassoId): ?array
    {
        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_votacao_rodadas
             WHERE roteiro_passo_id = :passo
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['passo' => $roteiroPassoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function historico(int $sessaoId, int $limit = 20): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_votacao_rodadas
             WHERE sessao_id = :id
             ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute(['id' => $sessaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function abrir(
        int $sessaoId,
        string $titulo,
        ?int $roteiroPassoId = null,
        ?int $itemId = null,
        ?string $descricao = null,
        array $eleitoresIds = [],
        int $tempoLimiteSeg = 0
    ): int {
        $pdo = DB::conn();
        if ($eleitoresIds === []) {
            throw new \RuntimeException('Nenhum parlamentar presente foi registrado para esta sessão.');
        }

        $tempoLimiteSeg = function_exists('sessao_tempo_normalizar')
            ? sessao_tempo_normalizar($tempoLimiteSeg)
            : 0;

        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
        $lockSql = 'SELECT id FROM sessoes WHERE id = :id';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lockSql .= ' FOR UPDATE';
        }
        $lock = $pdo->prepare($lockSql);
        $lock->execute(['id' => $sessaoId]);
        if (!$lock->fetchColumn()) {
            throw new \RuntimeException('Sessão não encontrada.');
        }

        $aberta = self::findAberta($sessaoId);
        if ($aberta) {
            throw new \RuntimeException('Já existe uma votação aberta nesta sessão.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sessao_votacao_rodadas
                (sessao_id, roteiro_passo_id, item_id, titulo, descricao, status, tempo_limite_seg)
             VALUES
                (:sessao_id, :roteiro_passo_id, :item_id, :titulo, :descricao, \'ABERTA\', :tempo_limite_seg)'
        );
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'roteiro_passo_id' => $roteiroPassoId,
            'item_id' => $itemId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'tempo_limite_seg' => $tempoLimiteSeg > 0 ? $tempoLimiteSeg : null,
        ]);

        $rodadaId = (int) $pdo->lastInsertId();
        RodadaEleitor::criarFotografia($rodadaId, $eleitoresIds);

        Sessao::updateStatus($sessaoId, 'EM_VOTACAO');

        if ($ownTransaction) {
            $pdo->commit();
        }

        return $rodadaId;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{resultado: string, resumo: array<string, int>} */
    public static function encerrar(int $rodadaId): array
    {
        $rodada = self::find($rodadaId);
        if (!$rodada) {
            return ['resultado' => 'EMPATADO', 'resumo' => ['SIM' => 0, 'NAO' => 0, 'ABSTENCAO' => 0, 'AUSENTE' => 0]];
        }

        $resumo = SessaoVoto::resumoPorRodada($rodadaId);
        $resultado = SessaoVoto::resultadoFromResumo($resumo);
        if ($resultado === 'EMPATADO') {
            $desempate = SessaoVotoDesempate::voto($rodadaId);
            if ($desempate === 'SIM') {
                $resultado = 'APROVADO';
            } elseif ($desempate === 'NAO') {
                $resultado = 'REJEITADO';
            }
        }

        $stmt = DB::conn()->prepare(
            "UPDATE sessao_votacao_rodadas
             SET status = 'ENCERRADA', resultado = :resultado, encerrada_em = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute(['id' => $rodadaId, 'resultado' => $resultado]);

        $itemId = (int) ($rodada['item_id'] ?? 0);
        if ($itemId > 0) {
            $statusItem = $resultado === 'APROVADO' ? 'APROVADO' : ($resultado === 'REJEITADO' ? 'REJEITADO' : 'PENDENTE');
            $pdo = DB::conn();
            $pdo->prepare('UPDATE pauta_itens SET status = :status WHERE id = :id')
                ->execute(['status' => $statusItem, 'id' => $itemId]);
        }

        $sessaoId = (int) ($rodada['sessao_id'] ?? 0);
        if ($sessaoId > 0 && self::findAberta($sessaoId) === null) {
            Sessao::updateStatus($sessaoId, 'ABERTA');
        }

        return ['resultado' => $resultado, 'resumo' => $resumo];
    }
}
