<?php
declare(strict_types=1);

/**
 * O voto do parlamentar é definitivo: um segundo envio (ex.: dois toques
 * rápidos em botões diferentes) não pode trocar o voto já gravado.
 *
 * Usa um banco separado (plenario_test), criado e migrado automaticamente.
 * Uso: C:\xampp\php\php.exe tests/VotoParlamentarTest.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

const BANCO_TESTE = 'plenario_test';
putenv('DB_NAME=' . BANCO_TESTE);
$_ENV['DB_NAME'] = BANCO_TESTE;

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Models\SessaoVoto;

$falhas = 0;
function confere(bool $ok, string $descricao): void
{
    global $falhas;
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$ok) {
        $falhas++;
    }
}

// Prepara o banco de teste
$server = new PDO(
    sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3307'),
    (string) (getenv('DB_USER') ?: 'root'),
    (string) (getenv('DB_PASS') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . BANCO_TESTE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$proc = proc_open(
    [PHP_BINARY, __DIR__ . '/../scripts/migrate.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    array_merge(getenv(), ['DB_NAME' => BANCO_TESTE])
);
$saidaMigrate = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
if (proc_close($proc) !== 0) {
    echo "Falha ao migrar o banco de teste:\n$saidaMigrate\n";
    exit(1);
}

$pdo = DB::conn();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== BANCO_TESTE) {
    echo "Recusado: o teste so roda no banco " . BANCO_TESTE . PHP_EOL;
    exit(1);
}

// Dados mínimos: um vereador, uma sessão e uma votação aberta
$sufixo = bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO users (nome, email, senha_hash) VALUES (?, ?, ?)')
    ->execute(['Vereador Teste', "teste-$sufixo@local.test", 'x']);
$userId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO sessoes (titulo, ano) VALUES (?, ?)')->execute(["Sessao teste $sufixo", 2026]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, titulo, status) VALUES (?, ?, 'ABERTA')")
    ->execute([$sessaoId, 'Votacao teste']);
$rodadaId = (int) $pdo->lastInsertId();

try {
    // 1) Primeiro voto é gravado
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $userId, 'SIM', null, true);
    confere(SessaoVoto::votoUsuarioRodada($rodadaId, $userId) === 'SIM', 'primeiro voto (SIM) e gravado');

    // 2) Segundo envio é recusado
    $recusado = false;
    try {
        SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $userId, 'NAO', null, true);
    } catch (\DomainException $e) {
        $recusado = true;
    }
    confere($recusado, 'segundo envio (NAO) e recusado com DomainException');

    // 3) O voto original permanece
    confere(SessaoVoto::votoUsuarioRodada($rodadaId, $userId) === 'SIM', 'voto continua SIM apos o segundo envio');

    // 4) Sem a opção, o comportamento antigo (atualizar) continua para as outras telas
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $userId, 'NAO');
    confere(SessaoVoto::votoUsuarioRodada($rodadaId, $userId) === 'NAO', 'fluxo do operador continua podendo atualizar');
} finally {
    $pdo->prepare('DELETE FROM sessao_votos WHERE rodada_id = ?')->execute([$rodadaId]);
    $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE id = ?')->execute([$rodadaId]);
    $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}

echo PHP_EOL . ($falhas === 0 ? 'Tudo certo.' : "$falhas falha(s).") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
