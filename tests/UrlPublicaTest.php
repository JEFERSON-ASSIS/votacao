<?php
declare(strict_types=1);

/**
 * Links de CSS/JS/imagens: com o servidor apontando direto para public/
 * (como na VPS), não pode acrescentar "/public" ao endereço.
 *
 * Uso: C:\xampp\php\php.exe tests/UrlPublicaTest.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

putenv('APP_BASE_URL=https://plenariovera.com.br');
$_ENV['APP_BASE_URL'] = 'https://plenariovera.com.br';

require_once __DIR__ . '/../app/bootstrap.php';

$falhas = 0;
function confere(bool $ok, string $descricao): void
{
    global $falhas;
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$ok) {
        $falhas++;
    }
}

// Servidor apontando para public/ (VPS)
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/public';
confere(public_base_url() === 'https://plenariovera.com.br', 'raiz do site em public/: assets sem "/public" (obtido ' . public_base_url() . ')');
confere(str_starts_with(asset_url('assets/css/app.css'), 'https://plenariovera.com.br/assets/css/app.css'), 'asset_url aponta para /assets/...');
confere(route_url('auth/login') === 'https://plenariovera.com.br/index.php?r=auth%2Flogin', 'rotas continuam em /index.php');

// Servidor apontando para a raiz do projeto (XAMPP com .htaccess da raiz)
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
confere(public_base_url() === 'https://plenariovera.com.br/public', 'raiz do site na pasta do projeto: mantém "/public"');

echo PHP_EOL . ($falhas === 0 ? 'Tudo certo.' : "$falhas falha(s).") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
