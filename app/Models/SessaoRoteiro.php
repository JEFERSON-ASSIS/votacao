<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\DbSchema;
use PDO;

final class SessaoRoteiro
{
    public static function allBySessao(int $sessaoId): array
    {
        if (!DbSchema::tableExists('sessao_roteiro')) {
            return [];
        }

        $stmt = DB::conn()->prepare(
            'SELECT r.*, p.titulo AS pauta_titulo, p.tipo_materia AS pauta_tipo
             FROM sessao_roteiro r
             LEFT JOIN pauta_itens p ON p.id = r.pauta_item_id
             WHERE r.sessao_id = :id
             ORDER BY r.ordem ASC, r.id ASC'
        );
        $stmt->execute(['id' => $sessaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessao_roteiro WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function deleteBySessao(int $sessaoId): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM sessao_roteiro WHERE sessao_id = :id');
        $stmt->execute(['id' => $sessaoId]);
    }

    public static function isAtivo(array $passo): bool
    {
        return !isset($passo['ativo']) || (int) $passo['ativo'] === 1;
    }

    public static function setAtivo(int $id, bool $ativo): void
    {
        $stmt = DB::conn()->prepare('UPDATE sessao_roteiro SET ativo = :ativo WHERE id = :id');
        $stmt->execute(['id' => $id, 'ativo' => $ativo ? 1 : 0]);
    }

    public static function insert(array $data): int
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO sessao_roteiro
                (sessao_id, ordem, tipo_item, pauta_item_id, modelo_id, titulo, texto_fala, texto_instrucao,
                 tipo_passo, gera_votacao, ativo, status_execucao, dados_json)
             VALUES
                (:sessao_id, :ordem, :tipo_item, :pauta_item_id, :modelo_id, :titulo, :texto_fala, :texto_instrucao,
                 :tipo_passo, :gera_votacao, :ativo, :status_execucao, :dados_json)'
        );
        $stmt->execute([
            'sessao_id' => $data['sessao_id'],
            'ordem' => $data['ordem'],
            'tipo_item' => $data['tipo_item'] ?? 'CONDUCAO',
            'pauta_item_id' => $data['pauta_item_id'] ?? null,
            'modelo_id' => $data['modelo_id'] ?? null,
            'titulo' => $data['titulo'] ?? null,
            'texto_fala' => $data['texto_fala'],
            'texto_instrucao' => $data['texto_instrucao'] ?? null,
            'tipo_passo' => $data['tipo_passo'] ?? 'FALA',
            'gera_votacao' => !empty($data['gera_votacao']) ? 1 : 0,
            'ativo' => array_key_exists('ativo', $data) ? (!empty($data['ativo']) ? 1 : 0) : 1,
            'status_execucao' => $data['status_execucao'] ?? 'PENDENTE',
            'dados_json' => $data['dados_json'] ?? null,
        ]);
        return (int) DB::conn()->lastInsertId();
    }

    public static function proximaOrdem(int $sessaoId): int
    {
        $stmt = DB::conn()->prepare(
            'SELECT COALESCE(MAX(ordem), 0) + 1 FROM sessao_roteiro WHERE sessao_id = :sessao_id'
        );
        $stmt->execute(['sessao_id' => $sessaoId]);

        return max(1, (int) $stmt->fetchColumn());
    }

    /** Liga o passo a uma matéria da pauta (ou desliga, com null). */
    public static function definirPautaItem(int $id, ?int $pautaItemId): void
    {
        DB::conn()->prepare('UPDATE sessao_roteiro SET pauta_item_id = :item WHERE id = :id')
            ->execute(['item' => $pautaItemId, 'id' => $id]);
    }

    public static function update(int $id, array $data): void
    {
        $stmt = DB::conn()->prepare(
            'UPDATE sessao_roteiro SET
                ordem = :ordem,
                titulo = :titulo,
                texto_fala = :texto_fala,
                texto_instrucao = :texto_instrucao,
                tipo_passo = :tipo_passo,
                gera_votacao = :gera_votacao,
                ativo = :ativo,
                status_execucao = COALESCE(:status_execucao, status_execucao)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'ordem' => $data['ordem'],
            'titulo' => $data['titulo'] ?? null,
            'texto_fala' => $data['texto_fala'],
            'texto_instrucao' => $data['texto_instrucao'] ?? null,
            'tipo_passo' => $data['tipo_passo'] ?? 'FALA',
            'gera_votacao' => !empty($data['gera_votacao']) ? 1 : 0,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            // Sem status informado, mantém o atual (editar o texto não desfaz a execução).
            'status_execucao' => $data['status_execucao'] ?? null,
        ]);
    }

    public static function resetExecucao(int $sessaoId): void
    {
        $stmt = DB::conn()->prepare(
            "UPDATE sessao_roteiro SET status_execucao = 'PENDENTE' WHERE sessao_id = :id"
        );
        $stmt->execute(['id' => $sessaoId]);
    }

    /** @param list<int|string> $ids */
    public static function reorderByIds(int $sessaoId, array $ids): void
    {
        $pdo = DB::conn();
        $ordem = 1;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $stmt = $pdo->prepare(
                'UPDATE sessao_roteiro SET ordem = :ordem WHERE id = :id AND sessao_id = :sessao_id'
            );
            $stmt->execute(['ordem' => $ordem++, 'id' => $id, 'sessao_id' => $sessaoId]);
        }
    }

    public static function marcarStatus(int $id, string $status): void
    {
        $stmt = DB::conn()->prepare('UPDATE sessao_roteiro SET status_execucao = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function passoAtual(int $sessaoId): ?array
    {
        $stmt = DB::conn()->prepare(
            "SELECT r.*, p.titulo AS pauta_titulo, p.tipo_materia AS pauta_tipo
             FROM sessao_roteiro r
             LEFT JOIN pauta_itens p ON p.id = r.pauta_item_id
             WHERE r.sessao_id = :id AND r.ativo = 1
               AND r.status_execucao IN ('PENDENTE', 'EM_ANDAMENTO')
             ORDER BY r.ordem ASC, r.id ASC LIMIT 1"
        );
        $stmt->execute(['id' => $sessaoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function contagem(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT
                SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS total,
                SUM(CASE WHEN ativo = 1 AND status_execucao IN (:concluido, :retirado) THEN 1 ELSE 0 END) AS concluidos
             FROM sessao_roteiro WHERE sessao_id = :id'
        );
        $stmt->execute(['id' => $sessaoId, 'concluido' => 'CONCLUIDO', 'retirado' => 'RETIRADO']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total' => (int) ($row['total'] ?? 0),
            'concluidos' => (int) ($row['concluidos'] ?? 0),
        ];
    }
}
