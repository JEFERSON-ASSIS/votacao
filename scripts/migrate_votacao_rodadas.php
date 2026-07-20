<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS sessao_votacao_rodadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sessao_id INT NOT NULL,
        roteiro_passo_id INT NULL,
        item_id INT NULL,
        titulo VARCHAR(200) NOT NULL,
        descricao TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT \'ABERTA\',
        resultado VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        encerrada_em TIMESTAMP NULL,
        CONSTRAINT fk_rodada_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
        CONSTRAINT fk_rodada_passo FOREIGN KEY (roteiro_passo_id) REFERENCES sessao_roteiro(id) ON DELETE SET NULL,
        CONSTRAINT fk_rodada_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL
    )'
);
echo "Tabela sessao_votacao_rodadas OK\n";

$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM sessao_votos')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $cols[$col['Field']] = true;
}
if (!isset($cols['rodada_id'])) {
    $pdo->exec('ALTER TABLE sessao_votos ADD COLUMN rodada_id INT NULL AFTER item_id');
    echo "Coluna rodada_id adicionada\n";
}
if (isset($cols['item_id'])) {
    $pdo->exec('ALTER TABLE sessao_votos MODIFY COLUMN item_id INT NULL');
    echo "item_id agora permite NULL\n";
}

$indexes = [];
foreach ($pdo->query('SHOW INDEX FROM sessao_votos')->fetchAll(PDO::FETCH_ASSOC) as $idx) {
    $indexes[$idx['Key_name']] = true;
}
if (!isset($indexes['uq_voto_rodada_user'])) {
    try {
        $pdo->exec('ALTER TABLE sessao_votos ADD UNIQUE KEY uq_voto_rodada_user (rodada_id, user_id)');
        echo "Indice uq_voto_rodada_user criado\n";
    } catch (Throwable $e) {
        echo "Indice rodada (pode exigir limpeza): " . $e->getMessage() . "\n";
    }
}

echo "Migracao de rodadas de votacao concluida.\n";
