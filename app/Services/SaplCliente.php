<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Leitura da API pública do SAPL da Câmara (somente leitura).
 * Endereço em SAPL_URL no .env (padrão: https://sapl.vera.mt.leg.br).
 */
final class SaplCliente
{
    /** @var array<string, array<string, mixed>|null> respostas já lidas nesta requisição */
    private static array $cache = [];

    public static function baseUrl(): string
    {
        $url = trim((string) (getenv('SAPL_URL') ?: 'https://sapl.vera.mt.leg.br'));

        return rtrim($url, '/');
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     * @throws \RuntimeException quando o SAPL não responde ou responde algo inválido
     */
    public static function get(string $caminho, array $query = []): array
    {
        $url = self::baseUrl() . '/api/' . ltrim($caminho, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        if (array_key_exists($url, self::$cache)) {
            $guardado = self::$cache[$url];
            if ($guardado === null) {
                throw new \RuntimeException('O SAPL não respondeu.');
            }

            return $guardado;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Plenario/1.0 (+importacao de sessoes)',
        ]);
        $corpo = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        $dados = is_string($corpo) ? json_decode($corpo, true) : null;
        if ($status !== 200 || !is_array($dados)) {
            self::$cache[$url] = null;
            throw new \RuntimeException(
                'O SAPL não respondeu' . ($erro !== '' ? ' (' . $erro . ')' : ($status > 0 ? ' (HTTP ' . $status . ')' : '')) . '.'
            );
        }
        self::$cache[$url] = $dados;

        return $dados;
    }

    /** @return array{itens: list<array<string, mixed>>, total: int, paginas: int} */
    public static function sessoes(int $pagina = 1, int $porPagina = 15): array
    {
        $dados = self::get('sessao/sessaoplenaria/', ['o' => '-data_inicio', 'page' => max(1, $pagina), 'page_size' => $porPagina]);

        return [
            'itens' => $dados['results'] ?? [],
            'total' => (int) ($dados['pagination']['total_entries'] ?? 0),
            'paginas' => (int) ($dados['pagination']['total_pages'] ?? 1),
        ];
    }

    /** @return array<string, mixed> */
    public static function sessao(int $id): array
    {
        return self::get('sessao/sessaoplenaria/' . $id . '/');
    }

    /**
     * Matérias da sessão (expediente e ordem do dia), com dados da matéria e autores.
     *
     * @return list<array<string, mixed>>
     */
    public static function materiasDaSessao(int $sessaoId): array
    {
        $tipos = [];
        foreach (self::get('materia/tipomaterialegislativa/', ['page_size' => 100])['results'] ?? [] as $t) {
            $tipos[(int) $t['id']] = (string) ($t['descricao'] ?? '');
        }

        $lista = [];
        foreach (['expedientemateria' => 'Expediente', 'ordemdia' => 'Ordem do Dia'] as $recurso => $parte) {
            $itens = self::get('sessao/' . $recurso . '/', ['sessao_plenaria' => $sessaoId, 'page_size' => 100])['results'] ?? [];
            usort($itens, static fn (array $a, array $b): int => (int) ($a['numero_ordem'] ?? 0) <=> (int) ($b['numero_ordem'] ?? 0));
            foreach ($itens as $item) {
                $materiaId = (int) ($item['materia'] ?? 0);
                if ($materiaId <= 0) {
                    continue;
                }
                $materia = self::get('materia/materialegislativa/' . $materiaId . '/');
                $lista[] = [
                    'parte' => $parte,
                    'numero_ordem' => (int) ($item['numero_ordem'] ?? 0),
                    'sapl_materia_id' => $materiaId,
                    'tipo' => $tipos[(int) ($materia['tipo'] ?? 0)] ?? 'Matéria',
                    'numero' => (string) ($materia['numero'] ?? ''),
                    'ano' => (string) ($materia['ano'] ?? ''),
                    'titulo' => (string) ($materia['__str__'] ?? ''),
                    'ementa' => trim((string) ($materia['ementa'] ?? '')),
                    'protocolo' => (string) ($materia['numero_protocolo'] ?? ''),
                    'texto_original' => (string) ($materia['texto_original'] ?? ''),
                    'tipo_votacao' => (int) ($item['tipo_votacao'] ?? 0),
                    'observacao' => trim((string) ($item['observacao'] ?? '')),
                    'autores' => self::autoresDaMateria($materiaId),
                ];
            }
        }

        return $lista;
    }

    /** @return list<array{nome: string, cargo: string, primeiro: bool}> */
    public static function autoresDaMateria(int $materiaId): array
    {
        $autores = [];
        foreach (self::get('materia/autoria/', ['materia' => $materiaId, 'page_size' => 50])['results'] ?? [] as $autoria) {
            $autor = self::get('base/autor/' . (int) ($autoria['autor'] ?? 0) . '/');
            $autores[] = [
                'nome' => trim((string) ($autor['nome'] ?? '')),
                'cargo' => trim((string) ($autor['cargo'] ?? '')),
                'primeiro' => !empty($autoria['primeiro_autor']),
            ];
        }
        usort($autores, static fn (array $a, array $b): int => (int) $b['primeiro'] <=> (int) $a['primeiro']);

        return $autores;
    }
}
