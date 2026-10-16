<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Helpers\VotacaoPainelEstado;
use App\Models\SessaoVotacaoRodada;
use PDO;

final class VotacaoEncerramentoService
{
    /** @return array{permitido: bool, mensagem: string, pendentes: array, apuracao?: array} */
    public static function encerrar(int $sessaoId, int $rodadaId): array
    {
        $pdo = DB::conn();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $lockSql = 'SELECT * FROM sessao_votacao_rodadas WHERE id = :id AND sessao_id = :sessao_id';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lockSql .= ' FOR UPDATE';
            }
            $stmt = $pdo->prepare($lockSql);
            $stmt->execute(['id' => $rodadaId, 'sessao_id' => $sessaoId]);
            $rodada = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rodada || ($rodada['status'] ?? '') !== 'ABERTA') {
                throw new \RuntimeException('Rodada de votação não está aberta.');
            }

            $validacao = VotacaoPainelEstado::validarEncerramento($rodadaId);
            if (!$validacao['permitido']) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return $validacao;
            }

            $apuracao = SessaoVotacaoRodada::encerrar($rodadaId);
            if ($ownTransaction) {
                $pdo->commit();
            }

            return $validacao + ['apuracao' => $apuracao];
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
