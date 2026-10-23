<?php
declare(strict_types=1);

/**
 * Na lista de sessões, "Iniciar sessão" precisa levar à chamada de presença.
 * Antes ele enviava um formulário sem presentes[], e o servidor sempre recusava.
 *
 * Uso: C:\xampp\php\php.exe tests/ListaSessoesTest.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

putenv('DB_NAME=plenario_test');
$_ENV['DB_NAME'] = 'plenario_test';

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\View;

$falhas = 0;
function confere(bool $ok, string $descricao): void
{
    global $falhas;
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$ok) {
        $falhas++;
    }
}

$_SESSION['user'] = ['id' => 999001, 'nome' => 'Admin Teste', 'email' => 'admin@test', 'role' => 'ADMIN'];

$sessao = ['id' => 4242, 'titulo' => 'Sessao Teste', 'numero' => 1, 'ano' => 2026, 'status' => 'CRIADO'];

ob_start();
View::render('sessoes/index', ['sessoes' => [$sessao]]);
$html = (string) ob_get_clean();

confere(str_contains($html, 'Sessao Teste'), 'a lista renderiza a sessao');
confere(
    !str_contains($html, 'presidencia/iniciar/4242'),
    'nao envia formulario direto para presidencia/iniciar (sem presentes)'
);
confere(
    (bool) preg_match('#<a[^>]+presidencia/conduzir/4242&(amp;)?abrir_presenca=1[^>]*>\s*Iniciar sess#u', $html),
    'Iniciar sessao abre a conducao com a chamada de presenca'
);

echo PHP_EOL . ($falhas === 0 ? 'Tudo certo.' : "$falhas falha(s).") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
