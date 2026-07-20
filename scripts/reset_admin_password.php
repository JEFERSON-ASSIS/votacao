<?php
declare(strict_types=1);

/**
 * Redefine senha do admin@local.test para admin123 (produção/SSH).
 * Uso: php scripts/reset_admin_password.php
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$email = 'admin@local.test';
$hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = DB::conn()->prepare('UPDATE users SET senha_hash = :h WHERE email = :e');
$stmt->execute(['h' => $hash, 'e' => $email]);

if ($stmt->rowCount() === 0) {
    $ins = DB::conn()->prepare(
        'INSERT INTO users (nome, email, senha_hash, role) VALUES (:nome, :email, :h, :role)'
    );
    $ins->execute([
        'nome' => 'Administrador',
        'email' => $email,
        'h' => $hash,
        'role' => 'ADMIN',
    ]);
    echo "Usuario admin criado.\n";
} else {
    echo "Senha do admin atualizada.\n";
}

echo "Login: {$email} / admin123\n";
