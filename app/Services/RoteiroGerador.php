<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\ConducaoTexto;
use App\Models\ConducaoModelo;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;

final class RoteiroGerador
{
    public static function gerar(int $sessaoId, bool $incluirTribuna = true): int
    {
        SessaoRoteiro::deleteBySessao($sessaoId);
        $ordem = 1;
        $sessaoModelos = ConducaoModelo::sessaoModelosBySlug();
        $itens = SessaoItem::allBySessao($sessaoId);
        $inseridos = 0;

        // Blocos unicos da sessao (uma vez cada, independente da quantidade de materias).
        foreach (['abertura', 'ata', 'pauta_dia'] as $slug) {
            if (!isset($sessaoModelos[$slug])) {
                continue;
            }
            $inseridos += self::inserirPassosModelo($sessaoId, $sessaoModelos[$slug], null, $ordem, 'SESSAO', $slug);
        }

        foreach ($itens as $item) {
            $inseridos += self::inserirPassosMateria($sessaoId, $item, $ordem);
        }

        if ($incluirTribuna && isset($sessaoModelos['tribuna'])) {
            $inseridos += self::inserirPassosModelo($sessaoId, $sessaoModelos['tribuna'], null, $ordem, 'TRIBUNA', 'tribuna');
        }

        if (isset($sessaoModelos['encerramento'])) {
            $inseridos += self::inserirPassosModelo($sessaoId, $sessaoModelos['encerramento'], null, $ordem, 'ENCERRAMENTO', 'encerramento');
        }

        return $inseridos;
    }

    /**
     * Insere os passos do modelo de roteiro de uma matéria a partir da ordem
     * informada (avançando $ordem). Devolve quantos passos foram inseridos.
     *
     * @param array<string, mixed> $item linha de pauta_itens
     */
    public static function inserirPassosMateria(int $sessaoId, array $item, int &$ordem): int
    {
        $itemId = (int) $item['id'];
        $vars = self::variaveisMateria($item);
        $modelo = ConducaoModelo::findForTipoMateria((string) ($item['tipo_materia'] ?? ''));
        if (!$modelo) {
            return 0;
        }
        $inseridos = 0;
        foreach (ConducaoModelo::passos((int) $modelo['id']) as $passo) {
            $texto = ConducaoTexto::acentuarTextoPresidente(ConducaoTexto::semInstrucaoVotacaoFisica(
                self::aplicarVariaveis((string) $passo['texto_template'], $vars)
            ));
            $tipoPasso = (string) ($passo['tipo_passo'] ?? 'FALA');
            SessaoRoteiro::insert([
                'sessao_id' => $sessaoId,
                'ordem' => $ordem++,
                'tipo_item' => 'CONDUCAO',
                'pauta_item_id' => $itemId,
                'modelo_id' => (int) $modelo['id'],
                'titulo' => ConducaoTexto::acentuarTextoPresidente($vars['titulo']),
                'texto_fala' => $tipoPasso === 'FALA' ? $texto : '',
                'texto_instrucao' => $tipoPasso === 'INSTRUCAO' ? $texto : null,
                'tipo_passo' => $tipoPasso,
                'gera_votacao' => (int) ($passo['gera_votacao'] ?? 0),
            ]);
            $inseridos++;
        }

        return $inseridos;
    }

    private static function inserirPassosModelo(
        int $sessaoId,
        array $modelo,
        ?int $pautaItemId,
        int &$ordem,
        string $tipoItem,
        ?string $blocoSlug = null
    ): int {
        $count = 0;
        $passos = ConducaoModelo::passos((int) $modelo['id']);
        $vars = ['tipo' => '', 'numero' => '', 'ano' => '', 'autor' => '', 'comissoes' => '', 'titulo' => ''];
        $tituloBloco = (string) ($modelo['nome'] ?? '');
        foreach ($passos as $passo) {
            $texto = ConducaoTexto::acentuarTextoPresidente(ConducaoTexto::semInstrucaoVotacaoFisica(
                self::aplicarVariaveis((string) $passo['texto_template'], $vars)
            ));
            $tipoPasso = (string) ($passo['tipo_passo'] ?? 'FALA');
            SessaoRoteiro::insert([
                'sessao_id' => $sessaoId,
                'ordem' => $ordem++,
                'tipo_item' => $tipoItem,
                'pauta_item_id' => $pautaItemId,
                'modelo_id' => (int) $modelo['id'],
                'titulo' => ConducaoTexto::acentuarTextoPresidente(
                    $blocoSlug !== null ? $tituloBloco : (string) ($modelo['nome'] ?? '')
                ),
                'texto_fala' => $tipoPasso === 'FALA' ? $texto : '',
                'texto_instrucao' => $tipoPasso === 'INSTRUCAO' ? $texto : null,
                'tipo_passo' => $tipoPasso,
                'gera_votacao' => (int) ($passo['gera_votacao'] ?? 0),
            ]);
            $count++;
        }
        return $count;
    }

    /** @return array<string, string> */
    public static function variaveisMateria(array $item): array
    {
        $numeracoes = SessaoItem::loadSectionRows((int) $item['id'])['numeracoes'] ?? [];
        $numero = '';
        $ano = (string) date('Y');
        if (!empty($numeracoes[0])) {
            $numero = trim((string) ($numeracoes[0]['numero'] ?? ''));
            $ano = trim((string) ($numeracoes[0]['ano'] ?? $ano));
        }
        // Número e ano gravados na matéria (ex.: importada do SAPL) têm prioridade sobre o protocolo.
        if ($numero === '' && !empty($item['numero'])) {
            $numero = (string) (int) $item['numero'];
            $ano = !empty($item['ano']) ? (string) (int) $item['ano'] : $ano;
        }
        if ($numero === '' && !empty($item['numero_protocolo'])) {
            $numero = trim((string) $item['numero_protocolo']);
        }

        $despachos = SessaoItem::loadSectionRows((int) $item['id'])['despachos'] ?? [];
        $comissoes = [];
        foreach ($despachos as $d) {
            $c = trim((string) ($d['comissao'] ?? ''));
            if ($c !== '') {
                $comissoes[] = $c;
            }
        }
        if ($comissoes === []) {
            $comissoes = [
                'Constituição, Justiça e Redação',
                'Orçamento, Finanças e Tributação',
                'Educação, Saúde e Outros Serviços Públicos',
            ];
        }

        return [
            'tipo' => trim((string) ($item['tipo_materia'] ?? 'Materia')),
            'numero' => $numero !== '' ? $numero : '___',
            'ano' => $ano,
            'autor' => trim((string) ($item['autor'] ?? '___')),
            'titulo' => trim((string) ($item['titulo'] ?? '')),
            'comissoes' => implode('; ', $comissoes),
            'resultado' => 'APROVADO / REPROVADO',
            'resultado_urgencia' => 'APROVADO / REPROVADO',
            // Os modelos usam a chave com acento.
            'resultado_urgência' => 'APROVADO / REPROVADO',
        ];
    }

    /** @param array<string, string> $vars */
    public static function aplicarVariaveis(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', $value, $out);
        }
        return $out;
    }
}
