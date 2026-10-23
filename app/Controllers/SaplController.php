<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Permissao;
use App\Services\SaplCliente;
use App\Services\SaplImportacao;
use App\Services\SaplImportacaoPrevia;

/** Importação de sessões do SAPL (o SAPL só é lido; nada é enviado para lá). */
final class SaplController
{
    public function sessoes(): void
    {
        Permissao::exigir('sapl.sessoes');
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $erro = '';
        $lista = ['itens' => [], 'total' => 0, 'paginas' => 1];
        try {
            $lista = SaplCliente::sessoes($pagina);
        } catch (\RuntimeException $e) {
            $erro = $e->getMessage();
        }

        $importadas = [];
        foreach ($lista['itens'] as $s) {
            $id = SaplImportacao::sessaoImportada((int) $s['id']);
            if ($id !== null) {
                $importadas[(int) $s['id']] = $id;
            }
        }

        View::render('sapl/sessoes', [
            'lista' => $lista,
            'pagina' => $pagina,
            'erro' => $erro,
            'importadas' => $importadas,
            'saplUrl' => SaplCliente::baseUrl(),
        ]);
    }

    public function previa(int $id): void
    {
        Permissao::exigir('sapl.previa');
        $erro = '';
        $previa = null;
        try {
            $previa = SaplImportacaoPrevia::montar($id);
        } catch (\RuntimeException $e) {
            $erro = $e->getMessage();
        }

        View::render('sapl/previa', [
            'previa' => $previa,
            'erro' => $erro,
            'saplId' => $id,
            'sessaoImportadaId' => SaplImportacao::sessaoImportada($id),
            'saplUrl' => SaplCliente::baseUrl(),
        ]);
    }

    public function importar(int $id): void
    {
        Permissao::exigir('sapl.importar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            responder_erro(400, 'Requisição inválida', 'Recarregue a página e tente novamente.');
            return;
        }

        try {
            $sessaoId = SaplImportacao::importar($id, (int) (Auth::user()['id'] ?? 0));
        } catch (\DomainException | \RuntimeException $e) {
            redirect_route('sapl/previa/' . $id, ['sapl_erro' => $e->getMessage()]);
        }

        redirect_route('roteiro/montar/' . $sessaoId, ['sapl_ok' => 'importada']);
    }

    public function atualizar(int $sessaoId): void
    {
        Permissao::exigir('sapl.atualizar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            responder_erro(400, 'Requisição inválida', 'Recarregue a página e tente novamente.');
            return;
        }

        try {
            $relatorio = SaplImportacao::atualizar($sessaoId, (int) (Auth::user()['id'] ?? 0));
        } catch (\DomainException | \RuntimeException $e) {
            redirect_route('sessao/index', ['sapl_erro' => $e->getMessage()]);
        }

        $_SESSION['sapl_relatorio'] = ['sessao_id' => $sessaoId] + $relatorio;
        redirect_route('sessao/index', ['sapl_ok' => 'atualizada']);
    }
}
