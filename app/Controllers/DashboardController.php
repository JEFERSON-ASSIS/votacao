<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Permissao;
use App\Helpers\UserPerfil;
use App\Models\Sessao;
use App\Models\SessaoItem;
use App\Models\SessaoVotacaoRodada;

final class DashboardController
{
    public function index(): void
    {
        Permissao::exigir('dashboard.index');

        $sessaoAtual = Sessao::atual();
        $materiaEmVotacao = null;
        $votacaoAberta = false;
        $rodadaTitulo = '';
        if ($sessaoAtual && UserPerfil::ehParlamentar(Auth::user()['role'] ?? '')) {
            $sid = (int) $sessaoAtual['id'];
            $rodada = SessaoVotacaoRodada::findAberta($sid);
            if ($rodada && strtoupper((string) ($sessaoAtual['status'] ?? '')) === 'EM_VOTACAO') {
                $votacaoAberta = true;
                $rodadaTitulo = (string) ($rodada['titulo'] ?? 'Votação');
                $itemId = !empty($rodada['item_id']) ? (int) $rodada['item_id'] : 0;
                if ($itemId > 0) {
                    $materiaEmVotacao = SessaoItem::find($itemId);
                }
            }
        }

        View::render('dashboard/index', [
            'user' => Auth::user(),
            'sessaoAtual' => $sessaoAtual,
            'materiaEmVotacao' => $materiaEmVotacao,
            'votacaoAberta' => $votacaoAberta,
            'rodadaTitulo' => $rodadaTitulo,
        ]);
    }
}
