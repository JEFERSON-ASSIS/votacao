<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Helpers\UserPerfil;

$pdo = DB::conn();
$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('partido', $cols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN partido VARCHAR(80) NULL DEFAULT NULL AFTER role');
    echo "Coluna partido criada.\n";
}

if (!in_array('foto', $cols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL DEFAULT NULL AFTER partido');
    echo "Coluna foto criada.\n";
}

if (!in_array('cpf', $cols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN cpf VARCHAR(14) NULL DEFAULT NULL AFTER email');
    echo "Coluna cpf criada.\n";
}

if (!in_array('telefone', $cols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN telefone VARCHAR(20) NULL DEFAULT NULL AFTER cpf');
    echo "Coluna telefone criada.\n";
}

$pdo->exec("UPDATE users SET role = 'ADMINISTRADOR' WHERE UPPER(role) IN ('ADMIN', 'SECRETARIA')");
$pdo->exec("UPDATE users SET role = 'USUARIO' WHERE UPPER(role) = 'USER'");
$pdo->exec("UPDATE users SET role = 'PARLAMENTAR' WHERE UPPER(role) IN ('VEREADOR')");

$indexes = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_cpf'")->fetchAll();
if ($indexes === []) {
  try {
      $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_cpf (cpf)');
      echo "Índice único em cpf.\n";
  } catch (Throwable $e) {
      echo "Aviso cpf único: " . $e->getMessage() . "\n";
  }
}

$dir = dirname(__DIR__) . '/public/uploads/vereadores';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

echo "Perfis normalizados. Valores: " . implode(', ', array_keys(UserPerfil::options())) . "\n";
echo "Concluído.\n";
