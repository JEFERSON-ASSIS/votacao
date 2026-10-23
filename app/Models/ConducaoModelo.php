<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\DbSchema;
use PDO;

final class ConducaoModelo
{
    public static function allAtivos(): array
    {
        if (!DbSchema::tableExists('conducao_modelos')) {
            return [];
        }

        $stmt = DB::conn()->query(
            'SELECT * FROM conducao_modelos WHERE ativo = 1 ORDER BY categoria ASC, nome ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM conducao_modelos WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findForTipoMateria(string $tipoMateria): ?array
    {
        $tipoMateria = self::tipoParaModelo($tipoMateria);
        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'SELECT * FROM conducao_modelos
             WHERE ativo = 1 AND categoria = :cat AND tipo_materia_vinculo = :tipo
             LIMIT 1'
        );
        $stmt->execute(['cat' => 'MATERIA', 'tipo' => $tipoMateria]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM conducao_modelos
             WHERE ativo = 1 AND slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => 'materia_generica']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Traduz o tipo da matéria (como vem do SAPL ou da pauta, com acento e
     * variações) para o tipo usado nos modelos de roteiro.
     */
    public static function tipoParaModelo(string $tipo): string
    {
        $t = strtr(trim($tipo), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
            'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C']);
        $regras = [
            'Projeto de Lei Complementar' => 'Projeto de Lei Complementar',
            'Projeto de Lei' => 'Projeto de Lei Ordinaria',
            'Projeto de Resolucao' => 'Projeto de Resolucao',
            'Indicacao' => 'Indicacao',
            'Requerimento' => 'Requerimento',
            'Mocao' => 'Mocao',
            'Emenda' => 'Emenda',
        ];
        foreach ($regras as $prefixo => $modelo) {
            if (stripos($t, $prefixo) === 0) {
                return $modelo;
            }
        }

        return $t;
    }

    public static function passos(int $modeloId): array
    {
        $stmt = DB::conn()->prepare(
            'SELECT * FROM conducao_modelo_passos WHERE modelo_id = :id ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(['id' => $modeloId]);
        return self::deduplicarPassos($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Remove passos duplicados (ex.: seed executado mais de uma vez). */
    public static function deduplicarPassos(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = (int) ($row['ordem'] ?? 0) . "\0" . trim((string) ($row['texto_template'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }
        return $out;
    }

    /** @return array<string, array> slug => modelo (um registro por slug) */
    public static function sessaoModelosBySlug(): array
    {
        $map = [];
        foreach (['abertura', 'ata', 'pauta_dia', 'tribuna', 'encerramento'] as $slug) {
            $row = self::findBySlug($slug);
            if ($row && (int) ($row['ativo'] ?? 0) === 1) {
                $map[$slug] = $row;
            }
        }
        return $map;
    }

    public static function deduplicarPassosNoBanco(): int
    {
        $pdo = DB::conn();
        $stmt = $pdo->query(
            'SELECT p1.id
             FROM conducao_modelo_passos p1
             INNER JOIN conducao_modelo_passos p2
               ON p1.modelo_id = p2.modelo_id
              AND p1.ordem = p2.ordem
              AND p1.texto_template = p2.texto_template
              AND p1.id > p2.id'
        );
        $ids = array_map(static fn(array $r): int => (int) $r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM conducao_modelo_passos WHERE id IN ({$placeholders})")->execute($ids);
        return count($ids);
    }
}
