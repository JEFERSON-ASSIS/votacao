<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Helpers\UserPerfil;
use App\Models\UserPermissao;

$pdo = DB::conn();
$tables = $pdo->query("SHOW TABLES LIKE 'user_permissoes'")->fetchAll();

if ($tables === []) {
    $pdo->exec(
        'CREATE TABLE user_permissoes (
            user_id INT NOT NULL,
            permissao VARCHAR(80) NOT NULL,
            PRIMARY KEY (user_id, permissao),
            CONSTRAINT fk_user_permissoes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "Tabela user_permissoes criada.\n";
} else {
    echo "Tabela user_permissoes já existe.\n";
}

echo "Usuários sem personalização usam o perfil padrão até salvar na tela Permissões.\n";
echo "Administradores podem personalizar em ?r=permissoes/index\n";
echo "Concluído.\n";
