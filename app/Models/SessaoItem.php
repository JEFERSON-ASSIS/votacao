<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\DbSchema;
use PDO;

final class SessaoItem
{
    private const COLUMNS = [
        'sessao_id', 'ordem', 'tipo_materia', 'numero', 'ano', 'sapl_id', 'apelido', 'indexacao', 'observacao',
        'objeto', 'resultado', 'dias_prazo', 'data_fim_prazo', 'tipo_sapl', 'regime_tramitacao_id',
        'numero_origem_externa', 'ano_origem_externa', 'data_origem_externa', 'tipo_origem_externa', 'local_origem_externa',
        'titulo', 'ementa', 'status', 'texto_original', 'tipo_apresentacao', 'data_apresentacao',
        'numero_protocolo', 'autor', 'autores_ids', 'regime_tramitacao', 'data_publicacao',
        'materia_polemica', 'em_tramitacao', 'eh_complementar', 'dados_extras_json',
    ];

    public static function allBySessao(int $sessaoId): array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM pauta_itens WHERE sessao_id = :id ORDER BY ordem ASC, id ASC');
        $stmt->execute(['id' => $sessaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM pauta_itens WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function upsert(array $data, ?int $id = null): int
    {
        $pdo = DB::conn();
        $payload = [];
        foreach (self::COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $payload[$col] = $data[$col];
            }
        }

        if ($id) {
            $sets = [];
            foreach ($payload as $col => $_) {
                if ($col === 'sessao_id') {
                    continue;
                }
                $sets[] = $col . '=:' . $col;
            }
            $sql = 'UPDATE pauta_itens SET ' . implode(', ', $sets) . ' WHERE id=:id';
            $params = $payload;
            unset($params['sessao_id']);
            $params['id'] = $id;
            $pdo->prepare($sql)->execute($params);
            return $id;
        }

        $cols = array_keys($payload);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $cols);
        $sql = 'INSERT INTO pauta_itens (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $pdo->prepare($sql)->execute($payload);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM pauta_itens WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function loadSectionRows(int $itemId): array
    {
        $pdo = DB::conn();
        $load = function (string $table) use ($pdo, $itemId): array {
            if (!DbSchema::tableExists($table)) {
                return [];
            }
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE item_id = :id ORDER BY ordem ASC, id ASC");
            $stmt->execute(['id' => $itemId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        return [
            'despachos' => $load('pauta_despachos'),
            'documentos' => $load('pauta_documentos'),
            'legislacoes' => $load('pauta_legislacoes'),
            'numeracoes' => $load('pauta_numeracoes'),
            'tramitacoes' => $load('pauta_tramitacoes'),
            'relatorias' => $load('pauta_relatorias'),
        ];
    }
}
