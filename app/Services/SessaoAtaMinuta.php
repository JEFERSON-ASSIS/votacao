<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\HistoricoSessao;
use App\Models\RodadaEleitor;
use App\Models\SessaoCrud;
use App\Models\SessaoItem;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;

/** Gera minuta de ata a partir dos registros da sessão. */
final class SessaoAtaMinuta
{
    public static function gerar(int $sessaoId): string
    {
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            return '';
        }

        $linhas = [];
        $linhas[] = 'ATA — ' . (string) ($sessao['titulo'] ?? 'Sessão plenária');
        $linhas[] = 'Sessão nº ' . (int) ($sessao['numero'] ?? 0) . '/' . (int) ($sessao['ano'] ?? 0);
        $linhas[] = 'Gerada automaticamente em ' . date('d/m/Y H:i');
        $linhas[] = str_repeat('—', 48);
        $linhas[] = '';

        $linhas[] = 'PAUTA';
        foreach (SessaoItem::allBySessao($sessaoId) as $i => $item) {
            $linhas[] = ($i + 1) . '. ' . (string) ($item['tipo_materia'] ?? 'Matéria')
                . ' — ' . (string) ($item['titulo'] ?? '');
            if (strtoupper((string) ($item['status'] ?? '')) === 'RETIRADA') {
                $linhas[] = '   ' . (string) ($item['titulo'] ?? '') . ' — retirado de pauta'
                    . (!empty($item['retirada_motivo']) ? ' (' . $item['retirada_motivo'] . ')' : '');
            }
            $ementa = trim((string) ($item['ementa'] ?? ''));
            if ($ementa !== '') {
                $linhas[] = '   ' . mb_strimwidth($ementa, 0, 200, '…');
            }
        }
        $linhas[] = '';

        $linhas[] = 'ANDAMENTO E VOTAÇÕES';
        foreach (HistoricoSessao::timeline($sessaoId) as $ev) {
            $quando = (string) ($ev['quando'] ?? '');
            $hora = $quando !== '' ? date('d/m/Y H:i', strtotime($quando) ?: time()) : '—';
            $linhas[] = '[' . $hora . '] ' . (string) ($ev['titulo'] ?? '');
            $meta = trim((string) ($ev['meta'] ?? ''));
            if ($meta !== '') {
                $linhas[] = '   ' . $meta;
            }
        }

        foreach (SessaoVotacaoRodada::historico($sessaoId, 50) as $rodada) {
            if (($rodada['status'] ?? '') !== 'ENCERRADA') {
                continue;
            }
            $rid = (int) ($rodada['id'] ?? 0);
            $resumo = SessaoVoto::resumoParlamentaresPorRodada($rid);
            $linhas[] = '';
            $linhas[] = 'Votação nominal: ' . (string) ($rodada['titulo'] ?? '');
            $linhas[] = 'Resultado: ' . votacao_rotulo_resultado((string) ($rodada['resultado'] ?? ''));
            $linhas[] = sprintf(
                'Placar — Sim: %d | Não: %d | Abstenção: %d',
                (int) ($resumo['SIM'] ?? 0),
                (int) ($resumo['NAO'] ?? 0),
                (int) ($resumo['ABSTENCAO'] ?? 0)
            );
            $impedidos = RodadaEleitor::nomesPorSituacao($rid, 'IMPEDIDO');
            if ($impedidos !== []) {
                $linhas[] = 'Impedidos: ' . implode(', ', $impedidos);
            }
            $ausentes = RodadaEleitor::nomesPorSituacao($rid, 'AUSENTE');
            if ($ausentes !== []) {
                $linhas[] = 'Ausentes na votação: ' . implode(', ', $ausentes);
            }
        }

        $linhas[] = '';
        $linhas[] = str_repeat('—', 48);
        $linhas[] = 'Minuta gerada pelo sistema. Revise e complemente antes da publicação oficial.';

        return implode("\n", $linhas);
    }
}
