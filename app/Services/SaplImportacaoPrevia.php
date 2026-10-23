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
        $materias = SaplCliente::materiasDaSessao($saplSessaoId);
        $avisos = [];

        foreach ($materias as &$m) {
            $m['autor_texto'] = self::autorTexto($m['autores']);
            $modelo = ConducaoModelo::findForTipoMateria(self::tipoModelo((string) $m['tipo']));
            $m['modelo'] = $modelo ? (string) $modelo['nome'] : '';
            if (!$modelo || ($modelo['slug'] ?? '') === 'materia_generica') {
                $avisos[] = '"' . $m['tipo'] . '" não tem modelo de roteiro próprio; foi usado o modelo de matéria genérica.';
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
            $partes[] = $cargo !== '' && stripos($cargo, 'vereador') === false
                ? $a['nome'] . ' (' . $cargo . ')'
                : $a['nome'];
        }

        return implode(', ', $partes);
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
        foreach ($materias as $m) {
            $modelo = ConducaoModelo::findForTipoMateria(self::tipoModelo((string) $m['tipo']));
            if (!$modelo) {
                continue;
            }
            $vars = [
                'tipo' => (string) $m['tipo'],
                'numero' => (string) $m['numero'],
                'ano' => (string) $m['ano'],
                'autor' => (string) ($m['autor_texto'] ?: '___'),
                'titulo' => (string) $m['titulo'],
                'comissoes' => 'Constituição, Justiça e Redação; Orçamento, Finanças e Tributação; Educação, Saúde e Outros Serviços Públicos',
                'resultado' => 'APROVADO / REPROVADO',
                'resultado_urgencia' => 'APROVADO / REPROVADO',
                // Os modelos usam a chave com acento.
                'resultado_urgência' => 'APROVADO / REPROVADO',
            ];
            self::adicionar($passos, $modelo, (string) $m['titulo'], $vars);
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
