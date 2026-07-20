<?php
declare(strict_types=1);

namespace App\Helpers;

final class ConducaoTexto
{
    /** Remove instrução legada “favoráveis sentados; contrários em pé”. */
    public static function semInstrucaoVotacaoFisica(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        $patterns = [
            '/\s*\(?\s*favor[aáà]veis\s+sentados\s*;\s*contr[aáà]rios\s+em\s+p[eé]\.?\s*\)?\s*$/iu',
            '/\s*\(?\s*favoraveis\s+sentados\s*;\s*contrarios\s+em\s+pe\.?\s*\)?\s*$/iu',
        ];
        foreach ($patterns as $pattern) {
            $texto = preg_replace($pattern, '', $texto) ?? $texto;
        }

        return trim($texto);
    }

    public static function contemInstrucaoVotacaoFisica(string $texto): bool
    {
        return self::semInstrucaoVotacaoFisica($texto) !== trim($texto);
    }

    /** Corrige grafia sem acento nos textos de condução (modelos e roteiro gerado). */
    public static function acentuarTextoPresidente(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        $map = [
            'Constituicao, Justica e Redacao' => 'Constituição, Justiça e Redação',
            'Orcamento, Financas e Tributacao' => 'Orçamento, Finanças e Tributação',
            'Educacao, Saude e Outros Servicos Publicos' => 'Educação, Saúde e Outros Serviços Públicos',
            'Ata da sessao anterior aprovada' => 'Ata da sessão anterior aprovada',
            'Peco a leitura da ata da sessao anterior' => 'Peço a leitura da ata da sessão anterior',
            'Coloco em discussao e faco a votacao' => 'Coloco em discussão e faço a votação',
            'declaro aberta a presente sessao' => 'declaro aberta a presente sessão',
            'declaro encerrada a sessao' => 'declaro encerrada a sessão',
            'Encerramento da sessao' => 'Encerramento da sessão',
            'Abertura da sessao' => 'Abertura da sessão',
            'Ata da sessao anterior' => 'Ata da sessão anterior',
            'Indicacao - votacao unica' => 'Indicação — votação única',
            'Materia generica' => 'Matéria genérica',
            'Projeto de Resolucao' => 'Projeto de Resolução',
            'Unica votacao' => 'Única votação',
            'dispenso a reuniao das Comissoes' => 'dispenso a reunião das Comissões',
            'Dispenso a reuniao das Comissoes' => 'Dispenso a reunião das Comissões',
            'Encaminho para as Comissoes' => 'Encaminho para as Comissões',
            'discussao do merito' => 'discussão do mérito',
            'votacao do pedido de urgencia' => 'votação do pedido de urgência',
            'Pedido de urgencia' => 'Pedido de urgência',
            'Coloco em votacao' => 'Coloco em votação',
            'Faco a votacao' => 'Faço a votação',
            'Peco a apresentacao' => 'Peço a apresentação',
            'Peco a leitura' => 'Peço a leitura',
            'Peco a defesa' => 'Peço a defesa',
            'agradeco a presenca' => 'agradeço a presência',
            'agradece a presenca' => 'agradece a presência',
            'protecao divina' => 'proteção divina',
            'Nao havendo' => 'Não havendo',
            'Faco a votacao da materia' => 'Faço a votação da matéria',
            'Materia {resultado}' => 'Matéria {resultado}',
            'Faco a votacao da' => 'Faço a votação da',
            'Faco a votacao do' => 'Faço a votação do',
            'Peco a leitura da' => 'Peço a leitura da',
            'Peco a leitura do' => 'Peço a leitura do',
            'sessao anterior' => 'sessão anterior',
            'presente sessao' => 'presente sessão',
            'discussao' => 'discussão',
            'votacao' => 'votação',
            'apresentacao' => 'apresentação',
            'presenca' => 'presência',
            'protecao' => 'proteção',
            'Comissoes' => 'Comissões',
            'merito' => 'mérito',
            'urgencia' => 'urgência',
            'reuniao' => 'reunião',
            'Materia' => 'Matéria',
            'Indicacao' => 'Indicação',
            'Resolucao' => 'Resolução',
            'Mocao' => 'Moção',
            'faco' => 'faço',
            'Faco' => 'Faço',
            'Peco' => 'Peço',
        ];

        return str_replace(array_keys($map), array_values($map), $texto);
    }
}
