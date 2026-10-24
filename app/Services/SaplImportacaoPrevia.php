<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\ConducaoTexto;
use App\Models\ConducaoModelo;

/**
 * PROTÓTIPO: monta, sem gravar nada, o que seria importado de uma sessão do
 * SAPL (dados da sessão, matérias com autores e o roteiro do presidente
 * gerado com os modelos atuais).
 */
final class SaplImportacaoPrevia
{
    /** @return array{sessao: array<string, mixed>, materias: list<array<string, mixed>>, roteiro: list<array<string, mixed>>, avisos: list<string>} */
    public static function montar(int $saplSessaoId): array
    {
        $sessao = SaplCliente::sessao($saplSessaoId);
        $materias = SaplCliente::materiasDaSessao($saplSessaoId, (string) ($sessao['data_inicio'] ?? ''));
        $avisos = [];

        foreach ($materias as &$m) {
            $m['autor_texto'] = self::autorTexto($m['autores']);
            $m['turno_texto'] = self::turnoTexto((string) ($m['turno'] ?? ''));
            if (($m['turno'] ?? '') === '') {
                $avisos[] = $m['titulo'] . ': o SAPL não informa o turno; o roteiro traz uma votação simples.';
            }
            if ($m['autores'] === []) {
                $avisos[] = $m['titulo'] . ' está sem autor no SAPL.';
            }
        }
        unset($m);

        return [
            'sessao' => $sessao,
            'materias' => $materias,
            'roteiro' => self::roteiro($materias),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * "Vereador Fulano" para vereadores; para outros autores, nome e cargo
     * ("Yago Pezarico Giacomelli (Prefeito Municipal)").
     *
     * @param list<array{nome: string, cargo: string, primeiro: bool}> $autores
     */
    public static function autorTexto(array $autores): string
    {
        $partes = [];
        foreach ($autores as $a) {
            if ($a['nome'] === '') {
                continue;
            }
            $cargo = $a['cargo'];
            $parlamentar = $a['parlamentar'] ?? ($cargo === '' || stripos($cargo, 'vereador') !== false);
            $partes[] = !$parlamentar && $cargo !== ''
                ? $a['nome'] . ' (' . $cargo . ')'
                : $a['nome'];
        }

        return implode(', ', $partes);
    }

    /**
     * Nomes dos autores que são vereadores (os únicos que fazem a defesa).
     *
     * @param list<array<string, mixed>> $autores
     */
    public static function autoresParlamentares(array $autores): string
    {
        $nomes = [];
        foreach ($autores as $a) {
            $parlamentar = $a['parlamentar'] ?? ((string) ($a['cargo'] ?? '') === '');
            if ($parlamentar && trim((string) ($a['nome'] ?? '')) !== '') {
                $nomes[] = trim((string) $a['nome']);
            }
        }

        return implode(', ', $nomes);
    }

    /** Nome do turno como aparece na pauta do SAPL. */
    public static function turnoTexto(string $turno): string
    {
        return match (strtoupper($turno)) {
            'D' => 'Deliberação (apresentação e envio às comissões)',
            'A' => 'Votação única em regime de urgência',
            'B', 'P' => '1ª votação',
            'C' => '2ª e 3ª votações',
            'S' => '2ª votação',
            'G' => '1ª e 2ª votações (em uma única votação)',
            'E' => '1ª, 2ª e 3ª votações (em uma única votação)',
            'U' => 'Votação única',
            '' => 'Não informado',
            default => 'Turno ' . strtoupper($turno),
        };
    }

    /** Traduz o tipo do SAPL para o tipo usado nos modelos de roteiro. */
    public static function tipoModelo(string $tipoSapl): string
    {
        return ConducaoModelo::tipoParaModelo($tipoSapl);
    }

    /**
     * @param list<array<string, mixed>> $materias
     * @return list<array{bloco: string, texto: string, tipo_passo: string, votacao: bool}>
     */
    private static function roteiro(array $materias): array
    {
        $passos = [];
        $modelosSessao = ConducaoModelo::sessaoModelosBySlug();
        $vazias = ['tipo' => '', 'numero' => '', 'ano' => '', 'autor' => '', 'comissoes' => '', 'titulo' => ''];

        foreach (['abertura', 'ata', 'pauta_dia'] as $slug) {
            if (isset($modelosSessao[$slug])) {
                self::adicionar($passos, $modelosSessao[$slug], (string) $modelosSessao[$slug]['nome'], $vazias);
            }
        }
        // Mesmo roteiro por turno usado na importação (SaplRoteiro).
        foreach ($materias as $m) {
            $item = [
                'tipo_materia' => (string) $m['tipo'],
                'numero' => (string) $m['numero'],
                'ano' => (string) $m['ano'],
                'autor' => (string) $m['autor_texto'],
                'sapl_turno' => (string) ($m['turno'] ?? ''),
                'sapl_tipo_votacao' => (int) ($m['tipo_votacao'] ?? 0),
                'sapl_autores_parlamentares' => self::autoresParlamentares($m['autores']),
            ];
            foreach (SaplRoteiro::passos($item) as $p) {
                $passos[] = [
                    'bloco' => (string) $m['titulo'],
                    'texto' => $p['texto'],
                    'tipo_passo' => $p['tipo_passo'],
                    'votacao' => $p['gera_votacao'],
                ];
            }
        }
        foreach (['tribuna', 'encerramento'] as $slug) {
            if (isset($modelosSessao[$slug])) {
                self::adicionar($passos, $modelosSessao[$slug], (string) $modelosSessao[$slug]['nome'], $vazias);
            }
        }

        return $passos;
    }

    /**
     * @param list<array<string, mixed>> $passos
     * @param array<string, mixed> $modelo
     * @param array<string, string> $vars
     */
    private static function adicionar(array &$passos, array $modelo, string $bloco, array $vars): void
    {
        foreach (ConducaoModelo::passos((int) $modelo['id']) as $p) {
            $passos[] = [
                'bloco' => ConducaoTexto::acentuarTextoPresidente($bloco),
                'texto' => ConducaoTexto::acentuarTextoPresidente(ConducaoTexto::semInstrucaoVotacaoFisica(
                    RoteiroGerador::aplicarVariaveis((string) $p['texto_template'], $vars)
                )),
                'tipo_passo' => (string) ($p['tipo_passo'] ?? 'FALA'),
                'votacao' => !empty($p['gera_votacao']),
            ];
        }
    }
}
