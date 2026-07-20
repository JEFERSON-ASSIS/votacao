<?php
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SCRIPT_NAME'] = '/producao/votacao/public/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['r'] = $argv[1] ?? '';

chdir(dirname(__DIR__) . '/public');
require 'index.php';
