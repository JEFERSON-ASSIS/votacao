<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\SessaoEvento;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;

/** Linha do tempo unificada da sessão (eventos + rodadas encerradas). */
final class HistoricoSessao
{
    /** @return list<array<string, mixed>> */
    public static function timeline(int $sessaoId): array
    {
        $linhas = [];

        foreach (SessaoEvento::timelineBySessao($sessaoId) as $e) {
            $linhas[] = [
                'quando' => (string) ($e['created_at'] ?? ''),
                'categoria' => (string) ($e['tipo'] ?? 'evento'),
                'titulo' => (string) ($e['descricao'] ?? ''),
                'meta' => !empty($e['user_nome']) ? 'Por ' . $e['user_nome'] : '',
                'item_id' => !empty($e['item_id']) ? (int) $e['item_id'] : null,
            ];
        }

        foreach (SessaoVotacaoRodada::historico($sessaoId, 80) as $rodada) {
            if (($rodada['status'] ?? '') !== 'ENCERRADA') {
                continue;
            }
            $rid = (int) ($rodada['id'] ?? 0);
            $resumo = SessaoVoto::resumoParlamentaresPorRodada($rid);
            $linhas[] = [
                'quando' => (string) ($rodada['encerrada_em'] ?? $rodada['created_at'] ?? ''),
                'categoria' => 'votacao',
                'titulo' => 'Resultado: ' . (string) ($rodada['titulo'] ?? 'Votação')
                    . ' — ' . votacao_rotulo_resultado((string) ($rodada['resultado'] ?? '')),
                'meta' => sprintf(
                    'Sim %d · Não %d · Abstenção %d',
                    (int) ($resumo['SIM'] ?? 0),
                    (int) ($resumo['NAO'] ?? 0),
                    (int) ($resumo['ABSTENCAO'] ?? 0)
                ),
                'item_id' => !empty($rodada['item_id']) ? (int) $rodada['item_id'] : null,
            ];
        }

        usort($linhas, static function (array $a, array $b): int {
            return strcmp((string) ($a['quando'] ?? ''), (string) ($b['quando'] ?? ''));
        });

        return $linhas;
    }
}
