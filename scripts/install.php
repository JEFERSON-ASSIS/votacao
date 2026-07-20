<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$seed = file_get_contents(__DIR__ . '/../database/seed.sql');

if ($schema === false || $seed === false) {
    fwrite(STDERR, "Nao foi possivel ler os arquivos SQL.\n");
    exit(1);
}

$statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema) ?: []));
foreach ($statements as $sql) {
    if ($sql !== '') {
        $pdo->exec($sql);
    }
}

$seedStatements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $seed) ?: []));
foreach ($seedStatements as $sql) {
    if ($sql !== '') {
        $pdo->exec($sql);
    }
}

$extraSql = [
    'historico_auditoria_schema.sql',
    'sessao_publicada_schema.sql',
];
foreach ($extraSql as $file) {
    $path = __DIR__ . '/../database/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $extra = file_get_contents($path);
    if ($extra === false) {
        continue;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $extra) ?: [])) as $sql) {
        if ($sql !== '') {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                if (!str_contains($e->getMessage(), 'Duplicate column')) {
                    throw $e;
                }
            }
        }
    }
    echo "Aplicado: {$file}\n";
}

try {
    $userExists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@local.test'")->fetchColumn();
    if ($userExists === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (nome, email, senha_hash, role) VALUES (:nome, :email, :senha_hash, :role)');
        $stmt->execute([
            'nome' => 'Administrador',
            'email' => 'admin@local.test',
            'senha_hash' => '$2y$10$RveCiNIn9n9IONaQHH4eFOtJo8JuxaVtpF12WPxVdI3ZFnTQFqycy',
            'role' => 'ADMIN',
        ]);
    }

    $sessionExists = (int) $pdo->query("SELECT COUNT(*) FROM sessoes WHERE titulo = 'Sessao Inicial'")->fetchColumn();
    if ($sessionExists === 0) {
        $year = (int) date('Y');
        $stmt = $pdo->prepare('INSERT INTO sessoes (titulo, tipo, numero, ano, status) VALUES (:titulo, :tipo, :numero, :ano, :status)');
        $stmt->execute([
            'titulo' => 'Sessao Inicial',
            'tipo' => 'ORDINARIA',
            'numero' => 1,
            'ano' => $year,
            'status' => 'ABERTA',
        ]);

        $sessaoId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO pauta_itens (sessao_id, ordem, tipo_materia, titulo, ementa, status) VALUES (:sessao_id, :ordem, :tipo_materia, :titulo, :ementa, :status)');
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'ordem' => 1,
            'tipo_materia' => 'Projeto de Lei Ordinaria',
            'titulo' => 'Leitura da ata anterior',
            'ementa' => 'Item inicial da pauta.',
            'status' => 'PENDENTE',
        ]);

        $stmt = $pdo->prepare('INSERT INTO sessao_presentes (sessao_id, user_id, presente) VALUES (:sessao_id, :user_id, 1)');
        $stmt->execute([
            'sessao_id' => $sessaoId,
            'user_id' => 1,
        ]);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Falha ao inserir dados iniciais: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Instalacao concluida.\n";
