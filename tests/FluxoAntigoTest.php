<?php
declare(strict_types=1);

/**
 * O fluxo antigo de votação por matéria (operador registra o voto de cada
 * vereador) foi desativado: a votação é só pelo aparelho de cada vereador.
 *
 * Uso: C:\xampp\php\php.exe tests/FluxoAntigoTest.php
 */

require __DIR__ . '/_db.php';

use App\Core\View;

$pdo = preparar_banco_teste();

$admin = criar_usuario_teste($pdo, 'Admin Fluxo', 'ADMIN');
$vereador = criar_usuario_teste($pdo, 'Vereador Fluxo');
$userAdmin = ['id' => $admin, 'nome' => 'Admin Fluxo', 'email' => 'a@test', 'role' => 'ADMIN'];

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 9, 'CRIADO')")
    ->execute(['Sessao fluxo antigo ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO pauta_itens (sessao_id, titulo) VALUES (?, ?)')->execute([$sessaoId, 'Materia teste']);
$itemId = (int) $pdo->lastInsertId();

function status_sessao(PDO $pdo, int $id): string
{
    $s = $pdo->prepare('SELECT status FROM sessoes WHERE id = ?');
    $s->execute([$id]);

    return (string) $s->fetchColumn();
}

try {
    // Abrir votação por matéria não muda mais a sessão
    rodar_cenario(['user' => $userAdmin, 'metodo' => 'POST', 'get_r' => "votacao/abrir/$sessaoId"]);
    confere(status_sessao($pdo, $sessaoId) === 'CRIADO', 'abrir votacao por materia (fluxo antigo) foi desativado');

    // Registrar voto pelo operador não grava nada
    $pdo->prepare("UPDATE sessoes SET status = 'EM_VOTACAO' WHERE id = ?")->execute([$sessaoId]);
    $res = rodar_cenario([
        'user' => $userAdmin,
        'metodo' => 'POST',
        'get_r' => "votacao/votar/$sessaoId/$itemId",
        'post' => ['user_id' => $vereador, 'voto' => 'SIM'],
    ]);
    $votos = $pdo->prepare('SELECT COUNT(*) FROM sessao_votos WHERE item_id = ?');
    $votos->execute([$itemId]);
    confere((int) $votos->fetchColumn() === 0, 'registrar voto pelo operador foi desativado (http ' . $res['http'] . ')');

    // Encerrar continua funcionando para fechar sessões antigas
    rodar_cenario(['user' => $userAdmin, 'metodo' => 'POST', 'get_r' => "votacao/encerrar/$sessaoId"]);
    confere(status_sessao($pdo, $sessaoId) === 'ENCERRADA', 'encerrar sessao antiga continua funcionando');

    // A tela não mostra mais os formulários do fluxo antigo
    $pdo->prepare("UPDATE sessoes SET status = 'EM_VOTACAO' WHERE id = ?")->execute([$sessaoId]);
    $_SESSION['user'] = $userAdmin;
    $renderizar = static function (string $status) use ($sessaoId, $itemId): string {
        ob_start();
        View::render('votacao/index', [
            'sessao' => ['id' => $sessaoId, 'titulo' => 'Sessao', 'numero' => 9, 'ano' => 2026, 'status' => $status],
            'status' => $status,
            'itens' => [['id' => $itemId, 'ordem' => 1, 'titulo' => 'Materia teste']],
            'usuarios' => [['id' => 1, 'nome' => 'Vereador', 'email' => 'v@test', 'role' => 'PARLAMENTAR']],
            'presencas' => [],
            'eventos' => [],
            'rodadaAberta' => null,
            'historicoRodadas' => [],
            'roteiroStats' => ['total' => 0, 'concluidos' => 0],
            'usaRoteiro' => false,
        ]);

        return (string) ob_get_clean();
    };
    confere(!str_contains($renderizar('CRIADO'), 'votacao/abrir/'), 'tela nao oferece mais abrir votacao por materia');
    confere(!str_contains($renderizar('EM_VOTACAO'), 'votacao/votar/'), 'tela nao oferece mais registrar voto pelo operador');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    $pdo->prepare('DELETE FROM sessao_votos WHERE item_id = ?')->execute([$itemId]);
    $pdo->prepare('DELETE FROM pauta_itens WHERE id = ?')->execute([$itemId]);
    $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sessaoId]);
    $pdo->exec("DELETE FROM users WHERE id IN ($admin, $vereador)");
}

finalizar_testes();
