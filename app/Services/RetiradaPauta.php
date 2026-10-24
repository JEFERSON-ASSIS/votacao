<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Helpers\Auditoria;
use App\Models\SessaoEvento;
use PDO;

/**
 * Retirada de pauta durante a condução: a matéria sai da sessão, os passos
 * dela no roteiro ficam como RETIRADO (a condução segue para a próxima) e o
 * fato fica na linha do tempo, na auditoria e na ata. Pode ser desfeita.
 */
final class RetiradaPauta
{
    /** Tipos de bloco que não pertencem a nenhuma matéria. */
    private const BLOCOS_DA_SESSAO = ['SESSAO', 'TRIBUNA', 'ENCERRAMENTO'];

    /**
     * Matéria do passo: a do próprio passo ou a do passo anterior mais próximo
     * ligado a uma matéria (os passos seguintes herdam a matéria).
     *
     * @param array<string, mixed>|null $passo
     * @return array<string, mixed>|null linha de pauta_itens
     */
    public static function materiaDoPasso(?array $passo): ?array
    {
        if (!$passo || in_array((string) ($passo['tipo_item'] ?? ''), self::BLOCOS_DA_SESSAO, true)) {
            return null;
        }
        $stmt = DB::conn()->prepare(
            'SELECT p.*
             FROM sessao_roteiro r
             INNER JOIN pauta_itens p ON p.id = r.pauta_item_id AND p.sessao_id = r.sessao_id
             WHERE r.sessao_id = :sessao AND r.ordem <= :ordem AND r.pauta_item_id IS NOT NULL
             ORDER BY r.ordem DESC, r.id DESC LIMIT 1'
        );
        $stmt->execute(['sessao' => (int) $passo['sessao_id'], 'ordem' => (int) $passo['ordem']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /**
     * Passos da matéria: os ligados a ela e os passos de condução sem matéria
     * logo depois deles, até a próxima matéria ou o próximo bloco da sessão.
     *
     * @return list<int>
     */
    public static function passosDaMateria(int $sessaoId, int $itemId): array
    {
        $stmt = DB::conn()->prepare('SELECT id, tipo_item, pauta_item_id FROM sessao_roteiro WHERE sessao_id = :s ORDER BY ordem, id');
        $stmt->execute(['s' => $sessaoId]);
        $ids = [];
        $dentro = false;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $itemDoPasso = (int) ($p['pauta_item_id'] ?? 0);
            if ($itemDoPasso === $itemId) {
                $dentro = true;
            } elseif ($itemDoPasso > 0 || in_array((string) $p['tipo_item'], self::BLOCOS_DA_SESSAO, true)) {
                $dentro = false;
            }
            if ($dentro) {
                $ids[] = (int) $p['id'];
            }
        }

        return $ids;
    }

    public static function retirar(int $sessaoId, int $itemId, string $motivo, int $usuarioId): void
    {
        $pdo = DB::conn();
        $item = self::item($sessaoId, $itemId);
        $passos = self::passosDaMateria($sessaoId, $itemId);
        if (self::temVotacaoAberta($sessaoId, $itemId, $passos)) {
            throw new \DomainException('Há votação aberta desta matéria. Encerre a votação antes de retirá-la de pauta.');
        }

        $motivo = mb_substr(trim($motivo), 0, 255);
        $pdo->beginTransaction();
        try {
            if ($passos !== []) {
                $pdo->exec("UPDATE sessao_roteiro SET status_execucao = 'RETIRADO'
                            WHERE status_execucao IN ('PENDENTE', 'EM_ANDAMENTO') AND id IN (" . implode(',', $passos) . ')');
            }
            $pdo->prepare("UPDATE pauta_itens SET status = 'RETIRADA', retirada_motivo = :motivo, retirada_em = CURRENT_TIMESTAMP WHERE id = :id")
                ->execute(['motivo' => $motivo !== '' ? $motivo : null, 'id' => $itemId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $descricao = (string) $item['titulo'] . ' retirado de pauta' . ($motivo !== '' ? ' (' . $motivo . ')' : '');
        SessaoEvento::registrar($sessaoId, 'pauta', $descricao, $itemId);
        Auditoria::log('retirar_pauta', 'pauta_item', $itemId, $descricao);
    }

    public static function desfazer(int $sessaoId, int $itemId, int $usuarioId): void
    {
        $pdo = DB::conn();
        $item = self::item($sessaoId, $itemId);
        $passos = self::passosDaMateria($sessaoId, $itemId);
        if ($passos !== []) {
            $pdo->exec("UPDATE sessao_roteiro SET status_execucao = 'PENDENTE'
                        WHERE status_execucao = 'RETIRADO' AND id IN (" . implode(',', $passos) . ')');
        }
        $pdo->prepare("UPDATE pauta_itens SET status = 'PENDENTE', retirada_motivo = NULL, retirada_em = NULL WHERE id = :id")
            ->execute(['id' => $itemId]);

        $descricao = (string) $item['titulo'] . ' voltou para a pauta';
        SessaoEvento::registrar($sessaoId, 'pauta', $descricao, $itemId);
        Auditoria::log('desfazer_retirada', 'pauta_item', $itemId, $descricao);
    }

    /** @return array<string, mixed> */
    private static function item(int $sessaoId, int $itemId): array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM pauta_itens WHERE id = :id AND sessao_id = :s');
        $stmt->execute(['id' => $itemId, 's' => $sessaoId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new \DomainException('Matéria não encontrada nesta sessão.');
        }

        return $item;
    }

    /** @param list<int> $passos */
    private static function temVotacaoAberta(int $sessaoId, int $itemId, array $passos): bool
    {
        $sql = "SELECT COUNT(*) FROM sessao_votacao_rodadas WHERE sessao_id = :s AND status = 'ABERTA' AND (item_id = :item";
        if ($passos !== []) {
            $sql .= ' OR roteiro_passo_id IN (' . implode(',', $passos) . ')';
        }
        $stmt = DB::conn()->prepare($sql . ')');
        $stmt->execute(['s' => $sessaoId, 'item' => $itemId]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
