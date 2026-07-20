<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\PublicoSessaoEstado;

$sid = (int) ($argv[1] ?? 11);
$e = PublicoSessaoEstado::estadoJson($sid);
echo json_encode($e, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
