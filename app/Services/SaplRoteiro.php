<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Passos do roteiro do presidente para uma matéria importada do SAPL,
 * conforme o turno em que ela entra na sessão. Regras tiradas das atas da
 * Câmara de Vera (2025–2026):
 *  - D (Deliberação): apresentação e envio às Comissões Permanentes, sem votação;
 *  - A (votação única em regime de urgência): apresentação, votação do pedido
 *    de urgência, dispensa da reunião das comissões, depois leitura e votação;
 *  - B/P (1ª votação), C (2ª e 3ª), S (2ª), U/G/E (uma única votação);
 *  - projeto de vereador: leitura, defesa do autor e votação;
 *  - projeto de outro autor (Executivo, Mesa, Comissão…): leitura resumida,
 *    discussão do mérito e votação;
 *  - indicação, moção, requerimento: leitura, defesa (se vereador) e votação;
 *  - veto: leitura, discussão das razões do veto e votação;
 *  - emenda: leitura, discussão e votação, sempre em uma única votação;
 *  - votação nominal quando o SAPL indica (tipo de votação 2).
 */
final class SaplRoteiro
{
    /** Frase do turno na votação e no resultado. */
    private const UNICA = ['em única votação', 'em uma única votação'];
    private const TURNOS = [
        'B' => ['em primeira votação', 'em sua primeira votação'],
        'P' => ['em primeira votação', 'em sua primeira votação'],
        'C' => ['em segunda e última votação', 'em sua segunda e última votação'],
        'S' => ['em segunda votação', 'em sua segunda votação'],
        'U' => self::UNICA,
        'G' => self::UNICA,
        'E' => self::UNICA,
        'A' => self::UNICA,
    ];

    /** Tipos femininos (APROVADA / REPROVADA). */
    private const FEMININOS = ['indicação', 'moção', 'emenda', 'indicacao', 'mocao'];

    private const VOTACAO_NOMINAL = 2;

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
     * @param array<string, mixed> $item tipo_materia, numero, ano, autor, sapl_turno,
     *        sapl_tipo_votacao e sapl_autores_parlamentares (vereadores autores)
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
        $nominal = (int) ($item['sapl_tipo_votacao'] ?? 0) === self::VOTACAO_NOMINAL;

        $projeto = self::comeca($tipo, 'Projeto');
        $veto = self::comeca($tipo, 'Veto');
        $emenda = self::comeca($tipo, 'Emenda');
        $vereadores = array_key_exists('sapl_autores_parlamentares', $item)
            ? self::lista((string) $item['sapl_autores_parlamentares'])
            : self::autoresVereadoresPeloTexto($autor);

        $apresentacao = 'Apresento ' . self::artigoSimples($tipo) . ' ' . $nome
            . ($autor !== '' ? ', de autoria de ' . $autor : '')
            . ', e encaminho às Comissões Permanentes para análise e emissão de parecer.';

        if ($turno === 'D') {
            return [self::fala($apresentacao)];
        }

        $passos = [];
        if ($turno === 'A') {
            $passos[] = self::fala($apresentacao);
            $passos[] = self::fala('Coloco em votação o pedido de urgência (redução do interstício regimental) '
                . self::artigo($tipo) . ' ' . $nome . '.', true);
            $passos[] = self::fala('Pedido de urgência APROVADO / REPROVADO por unanimidade.');
            $passos[] = self::fala('Dispenso a reunião das Comissões.');
        }

        // Leitura: resumida nos projetos que não são de vereador (como nas atas).
        $resumida = $projeto && $vereadores === [];
        $passos[] = self::fala('Peço a leitura ' . ($resumida ? 'resumida ' : '') . self::artigo($tipo) . ' ' . $nome . '.');

        if ($vereadores !== [] && !$veto) {
            $passos[] = self::fala(self::textoDefesa($vereadores));
        }

        if ($veto) {
            $passos[] = self::fala('Coloco em discussão as razões do veto apresentadas pelo Poder Executivo.');
        } elseif ($emenda) {
            $passos[] = self::fala('Coloco em discussão ' . self::artigoSimples($tipo) . ' ' . $nome . '.');
        } elseif ($projeto && $vereadores === []) {
            $passos[] = self::fala('Coloco em discussão o mérito ' . self::artigo($tipo) . ' ' . $nome . '.');
        }

        [$naVotacao, $noResultado] = $emenda ? self::UNICA : (self::TURNOS[$turno] ?? ['', '']);
        $passos[] = self::fala(
            'Coloco em votação' . ($nominal ? ' nominal' : '')
            . ($naVotacao !== '' ? ', ' . $naVotacao . ',' : '')
            . ' ' . self::artigoSimples($tipo) . ' ' . $nome . '.',
            true
        );

        $resultado = self::feminino($tipo) ? 'APROVADA / REPROVADA' : 'APROVADO / REPROVADO';
        $passos[] = self::fala($nome . ' ' . $resultado . ' por unanimidade' . ($noResultado !== '' ? ' ' . $noResultado : '') . '.');

        return $passos;
    }

    /**
     * "Com a palavra o autor, Vereador X, para a defesa." Com mais de três
     * autores (ex.: matéria assinada por todos), não lista os nomes.
     *
     * @param list<string> $vereadores
     */
    private static function textoDefesa(array $vereadores): string
    {
        if (count($vereadores) === 1) {
            return 'Com a palavra o autor, Vereador ' . $vereadores[0] . ', para a defesa.';
        }
        if (count($vereadores) > 3) {
            return 'Com a palavra os autores, para a defesa.';
        }
        $ultimo = array_pop($vereadores);

        return 'Com a palavra os autores, Vereadores ' . implode(', ', $vereadores) . ' e ' . $ultimo . ', para a defesa.';
    }

    /**
     * Sem a lista do SAPL (matérias antigas): autores que vêm só com o nome são
     * tratados como vereadores; os demais trazem o cargo entre parênteses.
     *
     * @return list<string>
     */
    private static function autoresVereadoresPeloTexto(string $autor): array
    {
        $nomes = [];
        foreach (preg_split('/,\s*(?![^()]*\))/', $autor) ?: [] as $parte) {
            $parte = trim($parte);
            if ($parte !== '' && !str_contains($parte, '(')) {
                $nomes[] = $parte;
            }
        }

        return $nomes;
    }

    /** @return list<string> */
    private static function lista(string $nomes): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $nomes)), static fn (string $n): bool => $n !== ''));
    }

    private static function comeca(string $tipo, string $prefixo): bool
    {
        return stripos($tipo, $prefixo) === 0;
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
