<?php
declare(strict_types=1);

/**
 * Instala ou atualiza o banco a partir de database/schema.sql.
 * Idempotente: cria tabelas, colunas e índices que faltarem e o admin inicial.
 *
 * Uso: php scripts/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script roda apenas pela linha de comando.');
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Este script suporta apenas MySQL/MariaDB.\n");
    exit(1);
}

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Nao foi possivel ler database/schema.sql\n");
    exit(1);
}

$statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema) ?: []));

// 1) Ajustes de bancos antigos que precisam rodar antes do schema
$tableExists = static fn (string $table): bool => (bool) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote($table)
)->fetchColumn();

if ($tableExists('sessao_votos')) {
    $pdo->exec('ALTER TABLE sessao_votos MODIFY COLUMN item_id INT NULL');
}

// 2) Tabelas e dados iniciais
foreach ($statements as $sql) {
    if (str_starts_with($sql, '--')) {
        $sql = trim((string) preg_replace('/^--.*$/m', '', $sql));
    }
    if ($sql !== '') {
        $pdo->exec($sql);
    }
}
echo "Tabelas verificadas.\n";

// 3) Colunas e índices que faltam em tabelas já existentes
foreach ($statements as $sql) {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)` \((.*)\)\s*ENGINE/s', $sql, $m)) {
        continue;
    }
    [$table, $body] = [$m[1], $m[2]];

    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[$col['Field']] = true;
    }
    $indexes = [];
    foreach ($pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $idx) {
        $indexes[$idx['Key_name']] = true;
    }

    foreach (preg_split('/,\s*\n/', trim($body)) ?: [] as $line) {
        $line = trim($line);
        if (preg_match('/^`(\w+)`/', $line, $c) && !isset($columns[$c[1]])) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$line}");
            echo "Coluna {$table}.{$c[1]} adicionada.\n";
        } elseif (preg_match('/^(UNIQUE )?KEY `(\w+)`/', $line, $k) && !isset($indexes[$k[2]])) {
            $pdo->exec("ALTER TABLE `{$table}` ADD {$line}");
            echo "Indice {$table}.{$k[2]} criado.\n";
        }
    }
}

// 4) Correções de dados legados
$indexes = array_column($pdo->query('SHOW INDEX FROM sessao_votos')->fetchAll(PDO::FETCH_ASSOC), 'Key_name', 'Key_name');
if (isset($indexes['uq_voto_item_user'])) {
    // Preserva votos de rodadas diferentes do mesmo item
    $pdo->exec('ALTER TABLE sessao_votos DROP INDEX uq_voto_item_user');
    echo "Indice legado uq_voto_item_user removido.\n";
}

$pdo->exec("UPDATE users SET role = 'ADMINISTRADOR' WHERE UPPER(role) IN ('ADMIN', 'SECRETARIA')");
$pdo->exec("UPDATE users SET role = 'USUARIO' WHERE UPPER(role) = 'USER'");
$pdo->exec("UPDATE users SET role = 'PARLAMENTAR' WHERE UPPER(role) = 'VEREADOR'");

// 5) Administrador inicial (só com o banco sem usuários)
if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    $producao = in_array(strtolower((string) getenv('APP_ENV')), ['production', 'prod'], true);
    $adminEmail = trim((string) getenv('ADMIN_EMAIL'));
    $adminSenha = (string) getenv('ADMIN_PASSWORD');

    if ($producao && ($adminEmail === '' || mb_strlen($adminSenha) < 10)) {
        // Em produção nunca cria o admin de desenvolvimento (admin@local.test / admin123).
        echo "Nenhum administrador criado: defina ADMIN_EMAIL e ADMIN_PASSWORD (10+ caracteres) no .env e rode de novo.\n";
    } else {
        $email = $producao ? $adminEmail : 'admin@local.test';
        $pdo->prepare('INSERT INTO users (nome, email, senha_hash, role) VALUES (:nome, :email, :senha_hash, :role)')
            ->execute([
                'nome' => 'Administrador',
                'email' => $email,
                'senha_hash' => $producao
                    ? password_hash($adminSenha, PASSWORD_DEFAULT)
                    : '$2y$10$RveCiNIn9n9IONaQHH4eFOtJo8JuxaVtpF12WPxVdI3ZFnTQFqycy',
                'role' => 'ADMINISTRADOR',
            ]);
        echo "Usuario {$email} criado.\n";
    }
}

echo "Migracao concluida.\n";
