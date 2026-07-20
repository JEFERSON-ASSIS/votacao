<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\MateriaSaplFields;

final class SaplMateriaImport
{
    /**
     * @return array{ok: bool, error?: string, item?: array, extras?: array, numeracoes?: array<int, array>}
     */
    public static function parse(string $rawJson): array
    {
        $rawJson = trim($rawJson);
        if ($rawJson === '') {
            return ['ok' => false, 'error' => 'Cole o JSON da materia do SAPL.'];
        }

        $data = json_decode($rawJson, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'JSON invalido. Verifique o formato.'];
        }

        $tipoSapl = (int) ($data['tipo'] ?? 0);
        $tipoMateria = self::resolverTipoMateria($data, $tipoSapl);
        $numero = (string) (int) ($data['numero'] ?? 0);
        $ano = (string) (int) ($data['ano'] ?? 0);
        if ($numero === '0') {
            $numero = '';
        }
        if ($ano === '0') {
            $ano = '';
        }

        $autores = $data['autores'] ?? [];
        $autoresIds = is_array($autores) ? implode(', ', array_map('strval', $autores)) : '';

        $post = [
            'tipo_sapl' => $tipoSapl > 0 ? (string) $tipoSapl : '',
            'tipo_materia' => $tipoMateria,
            'numero' => $numero,
            'ano' => $ano,
            'sapl_id' => self::stringOrEmpty($data['id'] ?? ''),
            'titulo' => self::resolverTitulo($data, $tipoMateria, $numero, $ano),
            'ementa' => self::stringOrEmpty($data['ementa'] ?? ''),
            'texto_original' => self::stringOrEmpty($data['texto_original'] ?? ''),
            'tipo_apresentacao' => self::stringOrEmpty($data['tipo_apresentacao'] ?? ''),
            'data_apresentacao' => self::normalizarData($data['data_apresentacao'] ?? null) ?? '',
            'numero_protocolo' => self::stringOrEmpty($data['numero_protocolo'] ?? ''),
            'regime_tramitacao_id' => self::stringOrEmpty($data['regime_tramitacao'] ?? ''),
            'data_publicacao' => self::normalizarData($data['data_publicacao'] ?? null) ?? '',
            'apelido' => self::stringOrEmpty($data['apelido'] ?? ''),
            'indexacao' => self::stringOrEmpty($data['indexacao'] ?? ''),
            'observacao' => self::stringOrEmpty($data['observacao'] ?? ''),
            'objeto' => self::stringOrEmpty($data['objeto'] ?? ''),
            'resultado' => self::stringOrEmpty($data['resultado'] ?? ''),
            'dias_prazo' => self::stringOrEmpty($data['dias_prazo'] ?? ''),
            'data_fim_prazo' => self::normalizarData($data['data_fim_prazo'] ?? null) ?? '',
            'tipo_origem_externa' => self::stringOrEmpty($data['tipo_origem_externa'] ?? ''),
            'numero_origem_externa' => self::stringOrEmpty($data['numero_origem_externa'] ?? ''),
            'ano_origem_externa' => self::stringOrEmpty($data['ano_origem_externa'] ?? ''),
            'data_origem_externa' => self::normalizarData($data['data_origem_externa'] ?? null) ?? '',
            'local_origem_externa' => self::stringOrEmpty($data['local_origem_externa'] ?? ''),
            'link_detail_backend' => self::stringOrEmpty($data['link_detail_backend'] ?? ''),
            'autores_ids' => $autoresIds,
            'autoria' => $autoresIds !== '' ? 'Autores SAPL (IDs): ' . $autoresIds : '',
            'em_tramitacao' => !empty($data['em_tramitacao']) ? '1' : '',
            'polemica' => !empty($data['polemica']) ? '1' : '',
            'complementar' => !empty($data['complementar']) ? '1' : '',
        ];

        $built = MateriaSaplFields::itemFromPost($post);
        $extras = MateriaSaplFields::extrasFromPost($post);
        $extras['sapl_id'] = $post['sapl_id'];
        $extras['link_detail_backend'] = $post['link_detail_backend'];

        $numeracoes = [];
        if ($numero !== '' || $ano !== '') {
            $numeracoes[] = [
                'tipo_materia' => $tipoMateria,
                'numero' => $numero,
                'ano' => $ano,
                'data_doc' => $post['data_apresentacao'],
            ];
        }

        return [
            'ok' => true,
            'item' => array_merge($built, [
                'sapl_id' => $built['sapl_id'] ?? ($post['sapl_id'] !== '' ? (int) $post['sapl_id'] : null),
            ]),
            'extras' => $extras,
            'numeracoes' => $numeracoes,
            'post' => $post,
        ];
    }

    private static function resolverTipoMateria(array $data, int $tipoSapl): string
    {
        if ($tipoSapl > 0 && isset(MateriaSaplFields::TIPO_SAPL[$tipoSapl])) {
            return MateriaSaplFields::TIPO_SAPL[$tipoSapl];
        }
        $str = self::stringOrEmpty($data['__str__'] ?? '');
        if ($str !== '') {
            $normalizado = self::normalizarSemAcento(mb_strtolower($str));
            foreach (MateriaSaplFields::TIPO_SAPL as $label) {
                $chave = self::normalizarSemAcento(mb_strtolower($label));
                if (str_starts_with($normalizado, $chave)) {
                    return $label;
                }
            }
        }
        return 'Projeto de Lei Ordinaria';
    }

    private static function resolverTitulo(array $data, string $tipo, string $numero, string $ano): string
    {
        $str = trim((string) ($data['__str__'] ?? ''));
        if ($str !== '') {
            return $str;
        }
        if ($numero !== '' && $ano !== '') {
            return $tipo . ' nº ' . $numero . '/' . $ano;
        }
        $ementa = trim((string) ($data['ementa'] ?? ''));
        if ($ementa !== '') {
            return mb_strimwidth($ementa, 0, 200, '…');
        }
        $saplId = (int) ($data['id'] ?? 0);
        return $saplId > 0 ? 'Materia SAPL #' . $saplId : 'Materia importada';
    }

    private static function normalizarData(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === 'null') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function stringOrEmpty(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }
        return trim((string) $value);
    }

    private static function normalizarSemAcento(string $texto): string
    {
        $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'];
        return strtr($texto, $map);
    }
}
