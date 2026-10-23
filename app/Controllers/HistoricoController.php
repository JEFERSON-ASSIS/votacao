<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\HistoricoSessao;
use App\Helpers\Permissao;
use App\Models\SessaoCrud;

final class HistoricoController
{
    public function index(): void
    {
        Permissao::exigir('historico.index');
        View::render('historico/index', [
            'sessoes' => SessaoCrud::all(),
        ]);
    }

    public function sessao(int $sessaoId): void
    {
        Permissao::exigir('historico.sessao');
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        // Tudo o que ocorreu: presença e cada votação com resultado, placar e votos.
        $presentes = array_values(array_filter(
            \App\Models\SessaoPresenca::allBySessao($sessaoId),
            static fn (array $p): bool => (int) ($p['presente'] ?? 1) === 1
        ));
        $votacoes = [];
        foreach (array_reverse(\App\Models\SessaoVotacaoRodada::historico($sessaoId, 50)) as $rodada) {
            $estado = \App\Helpers\VotacaoPainelEstado::jsonRodada((int) $rodada['id']);
            $votacoes[] = [
                'rodada' => $rodada,
                'descricao' => \App\Services\ResultadoRoteiro::descricao((int) $rodada['id']),
                'parlamentares' => $estado['parlamentares'] ?? [],
                'resumo' => $estado['resumo'] ?? [],
            ];
        }

        View::render('historico/sessao', [
            'sessao' => $sessao,
            'timeline' => HistoricoSessao::timeline($sessaoId),
            'presentes' => $presentes,
            'votacoes' => $votacoes,
        ]);
    }
}
