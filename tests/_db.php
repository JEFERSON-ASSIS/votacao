<?php
declare(strict_types=1);

/**
 * Prepara o banco separado de testes (plenario_test) e oferece utilitários.
 * Incluído pelos testes que precisam de banco. Nunca toca o banco "plenario".
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

$GLOBALS['falhas'] = 0;

function confere(bool $ok, string $descricao): void
{
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$ok) {
        $GLOBALS['falhas']++;
    }
}

function finalizar_testes(): void
{
    $falhas = (int) $GLOBALS['falhas'];
    echo PHP_EOL . ($falhas === 0 ? 'Tudo certo.' : "$falhas falha(s).") . PHP_EOL;
    exit($falhas === 0 ? 0 : 1);
}

/** Cria o banco de teste (se faltar), aplica o schema e devolve a conexão. */
function preparar_banco_teste(): PDO
{
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
    $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    if (proc_close($proc) !== 0) {
        echo "Falha ao migrar o banco de teste:\n$saida\n";
        exit(1);
    }

    $pdo = DB::conn();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== BANCO_TESTE) {
        echo 'Recusado: o teste so roda no banco ' . BANCO_TESTE . PHP_EOL;
        exit(1);
    }

    return $pdo;
}

/** Cria um usuário de teste e devolve o id. */
function criar_usuario_teste(PDO $pdo, string $nome, string $role = 'PARLAMENTAR'): int
{
    $pdo->prepare('INSERT INTO users (nome, email, senha_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$nome, 'teste-' . bin2hex(random_bytes(5)) . '@local.test', 'x', $role]);

    return (int) $pdo->lastInsertId();
}

/**
 * Roda uma requisição simulada (tests/_cenario.php) em outro processo.
 *
 * @param array<string, mixed> $cfg
 * @return array{saida: string, http: int}
 */
function rodar_cenario(array $cfg): array
{
    $proc = proc_open(
        [PHP_BINARY, __DIR__ . '/_cenario.php', json_encode($cfg)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['DB_NAME' => BANCO_TESTE])
    );
    $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($proc);
    preg_match('/__HTTP__=(\d+)/', $saida, $m);

    return ['saida' => $saida, 'http' => (int) ($m[1] ?? 0)];
}
