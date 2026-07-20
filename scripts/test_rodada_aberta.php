<?php
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoCrud;
$r = SessaoVotacaoRodada::findAberta(11);
var_export($r);
echo PHP_EOL;
$s = SessaoCrud::find(11);
echo 'status=' . ($s['status'] ?? '') . PHP_EOL;
