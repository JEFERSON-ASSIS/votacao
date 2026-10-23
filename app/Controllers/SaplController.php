<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\Permissao;
use App\Services\SaplCliente;
use App\Services\SaplImportacaoPrevia;

/** PROTÓTIPO: importação de sessões do SAPL (somente leitura, nada é gravado). */
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

        View::render('sapl/sessoes', [
            'lista' => $lista,
            'pagina' => $pagina,
            'erro' => $erro,
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
            'saplUrl' => SaplCliente::baseUrl(),
        ]);
    }
}
