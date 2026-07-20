<?php
declare(strict_types=1);

$cases = [
    '/producao/votacao/public/index.php',
    '/producao/votacao/index.php',
];

foreach ($cases as $script) {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['HTTPS'] = 'off';
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
    $cfg = require dirname(__DIR__) . '/app/Config/config.php';
    echo $script . ' => ' . $cfg['base_url'] . PHP_EOL;
}
