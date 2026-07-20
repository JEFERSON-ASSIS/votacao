<?php
declare(strict_types=1);

namespace App\Helpers;

final class MateriaSaplFields
{
    public const TIPO_SAPL = [
        1 => 'Projeto de Lei Ordinaria',
        2 => 'Projeto de Lei Complementar',
        3 => 'Projeto de Decreto Legislativo',
        4 => 'Projeto de Resolucao',
        5 => 'Projeto de Lei Ordinaria',
        6 => 'Requerimento',
        7 => 'Indicacao',
        8 => 'Mocao',
        9 => 'Emenda',
    ];

    public const REGIME_SAPL = [
        1 => 'Ordinario',
        2 => 'Urgencia',
        3 => 'Urgencia urgentissima',
        4 => 'Especial',
    ];

    public const APRESENTACAO_SAPL = [
        'E' => 'Escrita',
        'O' => 'Oral',
        'V' => 'Via eletronica',
    ];

    public static function tipoMateriaOptions(): array
    {
        return array_values(array_unique(self::TIPO_SAPL));
    }

    public static function extrasFromItem(?array $item): array
    {
        if (!$item || empty($item['dados_extras_json'])) {
            return [];
        }
        $decoded = json_decode((string) $item['dados_extras_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function val(?array $item, string $key, string $default = ''): string
    {
        if (!$item) {
            return $default;
        }
        $map = [
            'numero' => 'numero',
            'ano' => 'ano',
            'sapl_id' => 'sapl_id',
            'apelido' => 'apelido',
            'indexacao' => 'indexacao',
            'observacao' => 'observacao',
            'objeto' => 'objeto',
            'resultado' => 'resultado',
            'dias_prazo' => 'dias_prazo',
            'data_fim_prazo' => 'data_fim_prazo',
            'tipo_sapl' => 'tipo_sapl',
            'regime_tramitacao_id' => 'regime_tramitacao_id',
            'numero_origem_externa' => 'numero_origem_externa',
            'ano_origem_externa' => 'ano_origem_externa',
            'data_origem_externa' => 'data_origem_externa',
            'tipo_origem_externa' => 'tipo_origem_externa',
            'local_origem_externa' => 'local_origem_externa',
            'autores_ids' => 'autores_ids',
            'link_detail_backend' => 'link_detail_backend',
        ];
        if (isset($map[$key]) && array_key_exists($map[$key], $item) && $item[$map[$key]] !== null && $item[$map[$key]] !== '') {
            return (string) $item[$map[$key]];
        }
        $extras = self::extrasFromItem($item);
        if (array_key_exists($key, $extras)) {
            return (string) $extras[$key];
        }
        $legacy = [
            'origem_tipo' => 'tipo_origem_externa',
            'origem_numero' => 'numero_origem_externa',
            'origem_ano' => 'ano_origem_externa',
            'origem_local' => 'local_origem_externa',
            'origem_data' => 'data_origem_externa',
            'resultado_sapl' => 'resultado',
            'sapl_link' => 'link_detail_backend',
        ];
        if (isset($legacy[$key], $extras[$legacy[$key]])) {
            return (string) $extras[$legacy[$key]];
        }
        if (isset($item[$key])) {
            return (string) $item[$key];
        }
        return $default;
    }

    public static function checked(?array $item, string $field): bool
    {
        $v = $item[$field] ?? 'Nao';
        return $v === true || $v === 1 || $v === '1' || strtolower((string) $v) === 'sim';
    }

    /** @return array<string, mixed> */
    public static function itemFromPost(array $post): array
    {
        $tipoSapl = (int) ($post['tipo_sapl'] ?? 0);
        $tipoMateria = trim((string) ($post['tipo_materia'] ?? ''));
        if ($tipoMateria === '' && $tipoSapl > 0 && isset(self::TIPO_SAPL[$tipoSapl])) {
            $tipoMateria = self::TIPO_SAPL[$tipoSapl];
        }

        $numero = (int) ($post['numero'] ?? 0);
        $ano = (int) ($post['ano'] ?? 0);
        $titulo = trim((string) ($post['titulo'] ?? ''));
        if ($titulo === '' && $tipoMateria !== '' && $numero > 0 && $ano > 0) {
            $titulo = $tipoMateria . ' nº ' . $numero . '/' . $ano;
        }

        $regimeId = (int) ($post['regime_tramitacao_id'] ?? 0);
        $regimeLabel = $regimeId > 0 && isset(self::REGIME_SAPL[$regimeId])
            ? self::REGIME_SAPL[$regimeId]
            : trim((string) ($post['regime_tramitacao'] ?? ''));

        return [
            'ordem' => (int) ($post['ordem'] ?? 1),
            'tipo_materia' => $tipoMateria !== '' ? $tipoMateria : 'Materia',
            'tipo_sapl' => $tipoSapl > 0 ? $tipoSapl : null,
            'numero' => $numero > 0 ? $numero : null,
            'ano' => $ano > 0 ? $ano : null,
            'sapl_id' => (int) ($post['sapl_id'] ?? 0) ?: null,
            'titulo' => $titulo,
            'ementa' => trim((string) ($post['ementa'] ?? '')),
            'texto_original' => trim((string) ($post['texto_original'] ?? '')),
            'tipo_apresentacao' => trim((string) ($post['tipo_apresentacao'] ?? '')),
            'data_apresentacao' => self::dateOrNull($post['data_apresentacao'] ?? null),
            'numero_protocolo' => trim((string) ($post['numero_protocolo'] ?? '')),
            'autor' => trim((string) ($post['autor'] ?? '')),
            'autores_ids' => trim((string) ($post['autores_ids'] ?? '')),
            'regime_tramitacao' => $regimeLabel,
            'regime_tramitacao_id' => $regimeId > 0 ? $regimeId : null,
            'data_publicacao' => self::dateOrNull($post['data_publicacao'] ?? null),
            'materia_polemica' => self::checkboxSimNao($post, 'polemica', 'materia_polemica'),
            'em_tramitacao' => self::checkboxSimNao($post, 'em_tramitacao'),
            'eh_complementar' => self::checkboxSimNao($post, 'complementar', 'eh_complementar'),
            'apelido' => trim((string) ($post['apelido'] ?? '')),
            'indexacao' => trim((string) ($post['indexacao'] ?? '')),
            'observacao' => trim((string) ($post['observacao'] ?? '')),
            'objeto' => trim((string) ($post['objeto'] ?? '')),
            'resultado' => trim((string) ($post['resultado'] ?? '')),
            'dias_prazo' => (int) ($post['dias_prazo'] ?? 0) ?: null,
            'data_fim_prazo' => self::dateOrNull($post['data_fim_prazo'] ?? null),
            'tipo_origem_externa' => trim((string) ($post['tipo_origem_externa'] ?? '')),
            'numero_origem_externa' => trim((string) ($post['numero_origem_externa'] ?? '')),
            'ano_origem_externa' => (int) ($post['ano_origem_externa'] ?? 0) ?: null,
            'data_origem_externa' => self::dateOrNull($post['data_origem_externa'] ?? null),
            'local_origem_externa' => trim((string) ($post['local_origem_externa'] ?? '')),
            'link_detail_backend' => trim((string) ($post['link_detail_backend'] ?? '')),
        ];
    }

    /** Campos de workflow (abas extras) permanecem no JSON. */
    public static function extrasFromPost(array $post): array
    {
        return [
            'inicio' => trim((string) ($post['inicio'] ?? '')),
            'anexada' => trim((string) ($post['anexada'] ?? '')),
            'assunto' => trim((string) ($post['assunto'] ?? '')),
            'autoria' => trim((string) ($post['autoria'] ?? '')),
            'despacho_inicial' => trim((string) ($post['despacho_inicial'] ?? '')),
            'documento_acessorio' => trim((string) ($post['documento_acessorio'] ?? '')),
            'legislacao_citada' => trim((string) ($post['legislacao_citada'] ?? '')),
            'numeracao' => trim((string) ($post['numeracao'] ?? '')),
            'tramitacao' => trim((string) ($post['tramitacao'] ?? '')),
            'relatoria' => trim((string) ($post['relatoria'] ?? '')),
            'link_detail_backend' => trim((string) ($post['link_detail_backend'] ?? '')),
            '__str__' => trim((string) ($post['titulo'] ?? '')),
        ];
    }

    public static function mergeItemAndExtras(array $item): array
    {
        $row = $item;
        foreach (self::extrasFromItem($item) as $k => $v) {
            if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
                $row[$k] = $v;
            }
        }
        return $row;
    }

    private static function checkboxSimNao(array $post, string $primary, ?string $legacy = null): string
    {
        $keys = [$primary];
        if ($legacy) {
            $keys[] = $legacy;
        }
        foreach ($keys as $key) {
            if (!empty($post[$key]) && $post[$key] !== '0' && $post[$key] !== 'Nao') {
                return 'Sim';
            }
        }
        return 'Nao';
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }
        return null;
    }
}
