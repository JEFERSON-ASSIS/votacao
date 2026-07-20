<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\DbSchema;
use PDO;

final class SessaoCerimonial
{
    public const TIPOS = [
        'TRIBUNA' => 'Tribuna livre',
        'CONVITE' => 'Convidado',
        'COMUNICADO' => 'Comunicado',
        'HINO' => 'Hino / momento',
        'OUTRO' => 'Outro',
    ];

    /** @return list<array<string, mixed>> */
    public static function allBySessao(int $sessaoId): array
    {
        if (!DbSchema::tableExists('sessao_cerimonial')) {
            return [];
        }

        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_cerimonial WHERE sessao_id = :sid ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(['sid' => $sessaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public static function save(int $sessaoId, array $data, ?int $id = null): int
    {
        $pdo = DB::conn();
        $payload = [
            'sessao_id' => $sessaoId,
            'ordem' => (int) ($data['ordem'] ?? 1),
            'tipo' => (string) ($data['tipo'] ?? 'TRIBUNA'),
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'nome_convidado' => trim((string) ($data['nome_convidado'] ?? '')),
            'cargo' => trim((string) ($data['cargo'] ?? '')),
            'tema' => trim((string) ($data['tema'] ?? '')),
            'tempo_min' => ($data['tempo_min'] ?? '') !== '' ? (int) $data['tempo_min'] : null,
            'observacao' => trim((string) ($data['observacao'] ?? '')),
        ];

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE sessao_cerimonial SET ordem=:ordem, tipo=:tipo, titulo=:titulo,
                 nome_convidado=:nome_convidado, cargo=:cargo, tema=:tema, tempo_min=:tempo_min, observacao=:observacao
                 WHERE id=:id AND sessao_id=:sessao_id'
            );
            $payload['id'] = $id;
            $stmt->execute($payload);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sessao_cerimonial (sessao_id, ordem, tipo, titulo, nome_convidado, cargo, tema, tempo_min, observacao)
             VALUES (:sessao_id, :ordem, :tipo, :titulo, :nome_convidado, :cargo, :tema, :tempo_min, :observacao)'
        );
        $stmt->execute($payload);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $sessaoId, int $id): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM sessao_cerimonial WHERE id = :id AND sessao_id = :sid');
        $stmt->execute(['id' => $id, 'sid' => $sessaoId]);
    }
}
