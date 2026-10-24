<?php
declare(strict_types=1);

/**
 * Em produção o migrate.php não cria o admin padrão (admin@local.test /
 * admin123): o administrador inicial vem de ADMIN_EMAIL e ADMIN_PASSWORD.
 * Usa um banco descartável, criado e apagado pelo teste.
 *
 * Uso: C:\xampp\php\php.exe tests/MigrateAdminTest.php
 */

require __DIR__ . '/_db.php';

const BANCO_VAZIO = 'plenario_test_migrate';

$server = new PDO(
    sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3307'),
    (string) (getenv('DB_USER') ?: 'root'),
    (string) (getenv('DB_PASS') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function migrar_banco_vazio(PDO $server, array $env): array
{
    $server->exec('DROP DATABASE IF EXISTS `' . BANCO_VAZIO . '`');
    $server->exec('CREATE DATABASE `' . BANCO_VAZIO . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $proc = proc_open(
        [PHP_BINARY, __DIR__ . '/../scripts/migrate.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['DB_NAME' => BANCO_VAZIO], $env)
    );
    $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($proc);
    $usuarios = $server->query('SELECT email, senha_hash FROM `' . BANCO_VAZIO . '`.users')->fetchAll(PDO::FETCH_ASSOC);

    return [$saida, $usuarios];
}

try {
    [$saida, $usuarios] = migrar_banco_vazio($server, ['APP_ENV' => 'production', 'ADMIN_EMAIL' => '', 'ADMIN_PASSWORD' => '']);
    confere($usuarios === [], 'producao sem ADMIN_PASSWORD: nao cria o admin padrao');
    confere(str_contains($saida, 'ADMIN_EMAIL'), 'producao sem ADMIN_PASSWORD: avisa como criar o administrador');

    [$saida, $usuarios] = migrar_banco_vazio($server, ['APP_ENV' => 'production', 'ADMIN_EMAIL' => 'secretaria@plenariovera.com.br', 'ADMIN_PASSWORD' => 'SenhaForte#2026']);
    confere(count($usuarios) === 1 && $usuarios[0]['email'] === 'secretaria@plenariovera.com.br', 'producao: cria o administrador com ADMIN_EMAIL');
    confere(isset($usuarios[0]) && password_verify('SenhaForte#2026', $usuarios[0]['senha_hash']), 'producao: senha do ADMIN_PASSWORD');

    [$saida, $usuarios] = migrar_banco_vazio($server, ['APP_ENV' => 'production', 'ADMIN_EMAIL' => 'a@b.com', 'ADMIN_PASSWORD' => 'curta']);
    confere($usuarios === [], 'producao: recusa senha com menos de 10 caracteres');

    [$saida, $usuarios] = migrar_banco_vazio($server, ['APP_ENV' => 'local', 'ADMIN_EMAIL' => '', 'ADMIN_PASSWORD' => '']);
    confere(count($usuarios) === 1 && $usuarios[0]['email'] === 'admin@local.test', 'local: continua criando o admin de desenvolvimento');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . $e->getMessage());
} finally {
    $server->exec('DROP DATABASE IF EXISTS `' . BANCO_VAZIO . '`');
}

finalizar_testes();
