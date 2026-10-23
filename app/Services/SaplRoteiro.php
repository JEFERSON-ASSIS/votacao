<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Passos do roteiro do presidente para uma matéria importada do SAPL,
 * conforme o turno em que ela entra na sessão. Textos tirados das atas da
 * Câmara de Vera:
 *  - D (Deliberação): "apresentado e encaminhado para as Comissões
 *    Permanentes para análise e emissão de parecer", sem votação;
 *  - B (1ª Votação), C (2ª e 3ª Votações), U (Único): leitura (resumida nos
 *    projetos), defesa só quando o autor é vereador, discussão do mérito nos
 *    projetos, votação e resultado com o turno.
 */
final class SaplRoteiro
{
    /** Frase do turno na votação e no resultado. */
    private const TURNOS = [
        'B' => ['em primeira votação', 'em sua primeira votação'],
        'C' => ['em segunda e última votação', 'em sua segunda e última votação'],
        'U' => ['em única votação', 'em uma única votação'],
    ];

    /** Tipos femininos (APROVADA / REPROVADA). */
    private const FEMININOS = ['indicação', 'moção', 'emenda', 'indicacao', 'mocao'];

    /**
     * Turno da matéria na data da sessão: a última tramitação até essa data,
     * ignorando "F" (registro feito depois da votação).
     *
     * @param list<array{data_tramitacao: string, turno: string}> $tramitacoes mais recentes primeiro
     */
    public static function turnoNaData(array $tramitacoes, string $dataSessao): string
    {
        foreach ($tramitacoes as $t) {
            $turno = strtoupper(trim((string) ($t['turno'] ?? '')));
            if ($turno === '' || $turno === 'F') {
                continue;
            }
            if ($dataSessao === '' || (string) ($t['data_tramitacao'] ?? '') <= $dataSessao) {
                return $turno;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item tipo_materia, numero, ano, autor, sapl_turno
     * @return list<array{texto: string, tipo_passo: string, gera_votacao: bool}>
     */
    public static function passos(array $item): array
    {
        $tipo = trim((string) ($item['tipo_materia'] ?? 'Matéria'));
        $numero = trim((string) ($item['numero'] ?? ''));
        $ano = trim((string) ($item['ano'] ?? ''));
        $nome = $tipo . ($numero !== '' ? ' nº ' . $numero . ($ano !== '' ? '/' . $ano : '') : '');
        $autor = trim((string) ($item['autor'] ?? ''));
        $turno = strtoupper(trim((string) ($item['sapl_turno'] ?? '')));
        $projeto = stripos($tipo, 'Projeto') === 0;

        if ($turno === 'D') {
            return [self::fala(
                'Apresento o ' . $nome . ($autor !== '' ? ', de autoria de ' . $autor : '')
                . ', e encaminho às Comissões Permanentes para análise e emissão de parecer.'
            )];
        }

        $passos = [];
        $passos[] = self::fala('Peço a leitura ' . ($projeto ? 'resumida ' : '') . self::artigo($tipo) . ' ' . $nome . '.');

        $vereadores = self::autoresVereadores($autor);
        if ($vereadores !== []) {
            $passos[] = self::fala(count($vereadores) === 1
                ? 'Com a palavra o autor, Vereador ' . $vereadores[0] . ', para a defesa.'
                : 'Com a palavra os autores, Vereadores ' . implode(', ', $vereadores) . ', para a defesa.');
        }
        if ($projeto) {
            $passos[] = self::fala('Coloco em discussão o mérito ' . self::artigo($tipo) . ' ' . $nome . '.');
        }

        [$naVotacao, $noResultado] = self::TURNOS[$turno] ?? ['', ''];
        $passos[] = self::fala(
            'Coloco em votação' . ($naVotacao !== '' ? ', ' . $naVotacao . ',' : '') . ' ' . self::artigoSimples($tipo) . ' ' . $nome . '.',
            true
        );

        $resultado = self::feminino($tipo) ? 'APROVADA / REPROVADA' : 'APROVADO / REPROVADO';
        $passos[] = self::fala($nome . ' ' . $resultado . ' por unanimidade' . ($noResultado !== '' ? ' ' . $noResultado : '') . '.');

        return $passos;
    }

    /**
     * Autores vereadores: os que vêm só com o nome (os demais trazem o cargo
     * entre parênteses, ex.: "Fulano (Prefeito Municipal)").
     *
     * @return list<string>
     */
    private static function autoresVereadores(string $autor): array
    {
        if ($autor === '') {
            return [];
        }
        $nomes = [];
        foreach (preg_split('/,\s*(?![^()]*\))/', $autor) ?: [] as $parte) {
            $parte = trim($parte);
            if ($parte !== '' && !str_contains($parte, '(')) {
                $nomes[] = $parte;
            }
        }

        return $nomes;
    }

    private static function feminino(string $tipo): bool
    {
        $primeira = mb_strtolower(explode(' ', trim($tipo))[0] ?? '');

        return in_array($primeira, self::FEMININOS, true);
    }

    /** "do" / "da" conforme o tipo. */
    private static function artigo(string $tipo): string
    {
        return self::feminino($tipo) ? 'da' : 'do';
    }

    /** "o" / "a" conforme o tipo. */
    private static function artigoSimples(string $tipo): string
    {
        return self::feminino($tipo) ? 'a' : 'o';
    }

    /** @return array{texto: string, tipo_passo: string, gera_votacao: bool} */
    private static function fala(string $texto, bool $votacao = false): array
    {
        return ['texto' => $texto, 'tipo_passo' => 'FALA', 'gera_votacao' => $votacao];
    }
}
