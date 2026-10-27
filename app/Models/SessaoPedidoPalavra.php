<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Pedido de palavra: o vereador "levanta a mão" pelo sistema e o presidente
 * concede (vai para o telão) ou recusa.
 */
final class SessaoPedidoPalavra
{
    public const PENDENTE = 'PENDENTE';
    public const CONCEDIDO = 'CONCEDIDO';
    public const RECUSADO = 'RECUSADO';
    public const CANCELADO = 'CANCELADO';

    /** Por quanto tempo o vereador vê o aviso "não concedido". */
    public const AVISO_RECUSA_SEG = 60;

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessao_pedidos_palavra WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Registra o pedido. Se já existe um pendente do mesmo vereador, mantém o original (e a posição na fila). */
    public static function pedir(int $sessaoId, int $userId): int
    {
        $pendente = self::pendenteDoUsuario($sessaoId, $userId);
        if ($pendente) {
            return (int) $pendente['id'];
        }
        $pdo = DB::conn();
        $pdo->prepare(
            'INSERT INTO sessao_pedidos_palavra (sessao_id, user_id, status, created_at)
             VALUES (:sessao_id, :user_id, :status, :agora)'
        )->execute([
            'sessao_id' => $sessaoId,
            'user_id' => $userId,
            'status' => self::PENDENTE,
            'agora' => date('Y-m-d H:i:s'),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function cancelar(int $sessaoId, int $userId): void
    {
        DB::conn()->prepare(
            'UPDATE sessao_pedidos_palavra
             SET status = :cancelado, respondido_em = :agora, respondido_por = :user_id
             WHERE sessao_id = :sessao_id AND user_id = :user_id2 AND status = :pendente'
        )->execute([
            'cancelado' => self::CANCELADO,
            'agora' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'sessao_id' => $sessaoId,
            'user_id2' => $userId,
            'pendente' => self::PENDENTE,
        ]);
    }

    /**
     * Responde um pedido pendente (CONCEDIDO ou RECUSADO).
     * Devolve o pedido, ou null se ele não existe / já foi respondido.
     */
    public static function responder(int $pedidoId, int $sessaoId, string $status, int $respondidoPor): ?array
    {
        if (!in_array($status, [self::CONCEDIDO, self::RECUSADO], true)) {
            throw new \InvalidArgumentException('Status de resposta inválido.');
        }
        $pedido = self::find($pedidoId);
        if (!$pedido || (int) $pedido['sessao_id'] !== $sessaoId || $pedido['status'] !== self::PENDENTE) {
            return null;
        }
        $stmt = DB::conn()->prepare(
            'UPDATE sessao_pedidos_palavra
             SET status = :status, respondido_em = :agora, respondido_por = :por
             WHERE id = :id AND status = :pendente'
        );
        $stmt->execute([
            'status' => $status,
            'agora' => date('Y-m-d H:i:s'),
            'por' => $respondidoPor,
            'id' => $pedidoId,
            'pendente' => self::PENDENTE,
        ]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $pedido['status'] = $status;

        return $pedido;
    }

    /** Fecha todos os pedidos pendentes (ex.: sessão encerrada). */
    public static function cancelarPendentesDaSessao(int $sessaoId): void
    {
        DB::conn()->prepare(
            'UPDATE sessao_pedidos_palavra
             SET status = :cancelado, respondido_em = :agora
             WHERE sessao_id = :sessao_id AND status = :pendente'
        )->execute([
            'cancelado' => self::CANCELADO,
            'agora' => date('Y-m-d H:i:s'),
            'sessao_id' => $sessaoId,
            'pendente' => self::PENDENTE,
        ]);
    }

    public static function pendenteDoUsuario(int $sessaoId, int $userId): ?array
    {
        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_pedidos_palavra
             WHERE sessao_id = :sessao_id AND user_id = :user_id AND status = :pendente
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['sessao_id' => $sessaoId, 'user_id' => $userId, 'pendente' => self::PENDENTE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function ultimoDoUsuario(int $sessaoId, int $userId): ?array
    {
        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_pedidos_palavra
             WHERE sessao_id = :sessao_id AND user_id = :user_id
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['sessao_id' => $sessaoId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Fila de pedidos pendentes, do mais antigo para o mais novo.
     *
     * @return list<array<string, mixed>>
     */
    public static function pendentes(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT p.id, p.user_id, p.created_at, u.nome, u.partido, u.foto
             FROM sessao_pedidos_palavra p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.sessao_id = :sessao_id AND p.status = :pendente
             ORDER BY p.id ASC'
        );
        $stmt->execute(['sessao_id' => $sessaoId, 'pendente' => self::PENDENTE]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Pendentes no formato usado pela tela do presidente (HTML e JSON).
     *
     * @return list<array<string, mixed>>
     */
    public static function pendentesPayload(int $sessaoId): array
    {
        $agora = time();

        return array_map(static function (array $p) use ($agora): array {
            $criado = strtotime((string) ($p['created_at'] ?? '')) ?: $agora;

            return [
                'id' => (int) $p['id'],
                'user_id' => (int) $p['user_id'],
                'nome' => (string) ($p['nome'] ?? ''),
                'partido' => trim((string) ($p['partido'] ?? '')),
                'foto_url' => user_foto_url($p),
                'iniciais' => user_iniciais((string) ($p['nome'] ?? '')),
                'desde_seg' => max(0, $agora - $criado),
            ];
        }, self::pendentes($sessaoId));
    }

    /**
     * Situação do pedido do vereador, para a tela dele.
     * NENHUM | PENDENTE | CONCEDIDO (está no telão) | RECUSADO (aviso por alguns segundos)
     */
    public static function situacaoDoUsuario(int $sessaoId, int $userId, int $destaqueUserId): string
    {
        $ultimo = self::ultimoDoUsuario($sessaoId, $userId);
        if (!$ultimo) {
            return 'NENHUM';
        }
        $status = (string) $ultimo['status'];
        if ($status === self::PENDENTE) {
            return self::PENDENTE;
        }
        if ($status === self::CONCEDIDO && $destaqueUserId === $userId) {
            return self::CONCEDIDO;
        }
        if ($status === self::RECUSADO) {
            $em = strtotime((string) ($ultimo['respondido_em'] ?? '')) ?: 0;
            if ($em > 0 && (time() - $em) < self::AVISO_RECUSA_SEG) {
                return self::RECUSADO;
            }
        }

        return 'NENHUM';
    }
}
