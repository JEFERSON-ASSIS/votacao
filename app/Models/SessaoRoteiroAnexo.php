<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class SessaoRoteiroAnexo
{
    /** @return array<int, array<string, mixed>> */
    public static function allByPasso(int $passoId): array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessao_roteiro_anexos WHERE roteiro_passo_id = :passo_id ORDER BY id');
        $stmt->execute(['passo_id' => $passoId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessao_roteiro_anexos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO sessao_roteiro_anexos
             (sessao_id, roteiro_passo_id, tipo, titulo, url, arquivo, nome_original, mime_type, tamanho_bytes, criado_por)
             VALUES (:sessao_id, :roteiro_passo_id, :tipo, :titulo, :url, :arquivo, :nome_original, :mime_type, :tamanho_bytes, :criado_por)'
        );
        $stmt->execute($data);
        return (int) DB::conn()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM sessao_roteiro_anexos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return array<string, mixed> */
    public static function publico(array $anexo): array
    {
        $href = trim((string) ($anexo['url'] ?? ''));
        if ($href === '' && !empty($anexo['arquivo'])) {
            $href = base_url((string) $anexo['arquivo']);
        }
        return [
            'id' => (int) $anexo['id'],
            'titulo' => trim((string) ($anexo['titulo'] ?? '')),
            'tipo' => (string) ($anexo['tipo'] ?? 'ARQUIVO'),
            'href' => $href,
            'nome' => (string) ($anexo['nome_original'] ?? ''),
            'mime_type' => (string) ($anexo['mime_type'] ?? ''),
            'imagem' => (string) ($anexo['tipo'] ?? '') === 'IMAGEM',
        ];
    }
}
