<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\Permissao;
use App\Models\AuditoriaLog;

final class AuditoriaController
{
    public function index(): void
    {
        Permissao::exigir('auditoria.index');

        $filtro = trim((string) ($_GET['entidade'] ?? ''));
        $schemaOk = \App\Helpers\DbSchema::tableExists('auditoria_log');
        View::render('auditoria/index', [
            'registros' => AuditoriaLog::recent(250, $filtro !== '' ? $filtro : null),
            'filtro' => $filtro,
            'schemaOk' => $schemaOk,
        ]);
    }
}
