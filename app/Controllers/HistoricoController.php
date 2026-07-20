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

        View::render('historico/sessao', [
            'sessao' => $sessao,
            'timeline' => HistoricoSessao::timeline($sessaoId),
        ]);
    }
}
