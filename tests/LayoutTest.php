<?php
declare(strict_types=1);

/**
 * Layout das telas internas: menu recolhível no celular e item ativo marcado.
 *
 * Uso: C:\xampp\php\php.exe tests/LayoutTest.php
 */

require __DIR__ . '/_db.php';

$pdo = preparar_banco_teste();
$vereador = criar_usuario_teste($pdo, 'Vereador Layout');
$user = ['id' => $vereador, 'nome' => 'Vereador Layout', 'email' => 'v@test', 'role' => 'PARLAMENTAR'];

try {
    $res = rodar_cenario(['user' => $user, 'metodo' => 'GET', 'get_r' => 'dashboard/index']);
    $html = $res['saida'];
    confere(
        (bool) preg_match('#<button[^>]+class="[^"]*side-nav-toggle[^"]*"[^>]+aria-controls="side-nav"[^>]+aria-expanded="false"#', $html),
        'existe o botao Menu para o celular (aria-controls / aria-expanded)'
    );
    confere(str_contains($html, '<nav class="side-nav" id="side-nav"'), 'menu tem id para o botao controlar');
    confere(
        (bool) preg_match('#<a href="[^"]*dashboard%2Findex"[^>]*aria-current="page"#', $html)
            || (bool) preg_match('#<a href="[^"]*dashboard/index"[^>]*aria-current="page"#', $html),
        'item Dashboard aparece como pagina atual'
    );
    confere(substr_count($html, 'aria-current="page"') === 1, 'so um item marcado como atual');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . $e->getMessage());
} finally {
    $pdo->exec("DELETE FROM users WHERE id = $vereador");
}

finalizar_testes();
