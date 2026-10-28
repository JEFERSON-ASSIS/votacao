<?php
declare(strict_types=1);

/**
 * Painel do vereador: o cartão "Sessão atual" se atualiza sozinho quando o
 * presidente inicia a sessão (sem recarregar a página).
 *
 * Uso: C:\xampp\php\php.exe tests/DashboardSessaoAoVivoTest.php
 */

require __DIR__ . '/_db.php';

$pdo = preparar_banco_teste();
$vereador = criar_usuario_teste($pdo, 'Vereador Ao Vivo');
$user = ['id' => $vereador, 'nome' => 'Vereador Ao Vivo', 'email' => 'v@test', 'role' => 'PARLAMENTAR'];
$sessaoId = 0;

try {
    $abertas = (int) $pdo->query("SELECT COUNT(*) FROM sessoes WHERE status IN ('ABERTA','EM_VOTACAO')")->fetchColumn();

    $res = rodar_cenario(['user' => $user, 'metodo' => 'GET', 'get_r' => 'dashboard/index']);
    $html = $res['saida'];
    confere(str_contains($html, 'id="dashboard-sessao-atual"'), 'cartão da sessão atual tem o marcador para atualizar');
    confere((bool) preg_match('~publico(/|\/|%2F)atual~', $html), 'cartão consulta a sessão em andamento');
    if ($abertas === 0) {
        confere(str_contains($html, 'data-chave="0"'), 'sem sessão em andamento: chave 0');
    }

    $pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 88, 'ABERTA')")
        ->execute(['Sessao ao vivo ' . bin2hex(random_bytes(3))]);
    $sessaoId = (int) $pdo->lastInsertId();
    $res = rodar_cenario(['user' => $user, 'metodo' => 'GET', 'get_r' => 'dashboard/index']);
    confere(
        (bool) preg_match('#id="dashboard-sessao-atual" data-chave="\d+:(ABERTA|EM_VOTACAO)"#', $res['saida']),
        'com sessão em andamento: a chave muda (o navegador troca o cartão)'
    );
    confere(str_contains($res['saida'], 'Acompanhar'), 'com sessão em andamento: botão Acompanhar aparece');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($sessaoId > 0) { $pdo->exec("DELETE FROM sessoes WHERE id = $sessaoId"); }
    $pdo->exec("DELETE FROM users WHERE id = $vereador");
}

finalizar_testes();
