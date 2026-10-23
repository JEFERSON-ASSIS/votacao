<?php
declare(strict_types=1);

/**
 * "Acesso negado" e "Página não encontrada" aparecem como página do sistema
 * (com saída para o início), e não como um texto solto numa tela branca.
 * Chamadas em segundo plano continuam recebendo JSON.
 *
 * Uso: C:\xampp\php\php.exe tests/PaginasErroTest.php
 */

require __DIR__ . '/_db.php';

$pdo = preparar_banco_teste();
$vereador = criar_usuario_teste($pdo, 'Vereador Erro');
$user = ['id' => $vereador, 'nome' => 'Vereador Erro', 'email' => 'v@test', 'role' => 'PARLAMENTAR'];

try {
    $res = rodar_cenario(['user' => $user, 'metodo' => 'GET', 'get_r' => 'presidencia/conduzir/999999']);
    confere($res['http'] === 403, 'acesso negado responde 403 (obtido ' . $res['http'] . ')');
    confere(str_contains($res['saida'], '<title>Acesso negado'), 'acesso negado e uma pagina com titulo');
    confere(str_contains($res['saida'], 'assets/css/app.css'), 'pagina de erro usa o visual do sistema');
    confere(str_contains($res['saida'], 'Ir para o início'), 'pagina de erro tem saida para o inicio');

    $res = rodar_cenario([
        'user' => $user,
        'metodo' => 'GET',
        'get_r' => 'presidencia/conduzir/999999',
        'headers' => ['HTTP_ACCEPT' => 'application/json'],
    ]);
    confere($res['http'] === 403 && str_contains($res['saida'], '"ok":false'), 'chamada JSON recebe erro em JSON');

    $res = rodar_cenario(['user' => $user, 'metodo' => 'GET', 'get_r' => 'naoexiste/coisa']);
    confere($res['http'] === 404, 'endereco inexistente responde 404 (obtido ' . $res['http'] . ')');
    confere(str_contains($res['saida'], '<title>Página não encontrada'), 'pagina nao encontrada e uma pagina com titulo');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . $e->getMessage());
} finally {
    $pdo->exec("DELETE FROM users WHERE id = $vereador");
}

finalizar_testes();
