<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Helpers\ConducaoTexto;

$pdo = DB::conn();

function acentuarColuna(PDO $pdo, string $table, string $column, string $where = '1=1'): int
{
    $stmt = $pdo->query("SELECT id, {$column} AS val FROM {$table} WHERE {$where} AND {$column} IS NOT NULL AND {$column} <> ''");
    $upd = $pdo->prepare("UPDATE {$table} SET {$column} = :v WHERE id = :id");
    $n = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orig = (string) $row['val'];
        $novo = ConducaoTexto::acentuarTextoPresidente($orig);
        if ($novo !== $orig) {
            $upd->execute(['v' => $novo, 'id' => (int) $row['id']]);
            $n++;
        }
    }
    return $n;
}

echo "Acentuando conducao_modelos.nome...\n";
echo acentuarColuna($pdo, 'conducao_modelos', 'nome') . " linha(s)\n";

echo "Acentuando conducao_modelo_passos.texto_template...\n";
echo acentuarColuna($pdo, 'conducao_modelo_passos', 'texto_template') . " linha(s)\n";

echo "Acentuando sessao_roteiro.titulo...\n";
echo acentuarColuna($pdo, 'sessao_roteiro', 'titulo') . " linha(s)\n";

echo "Acentuando sessao_roteiro.texto_fala...\n";
echo acentuarColuna($pdo, 'sessao_roteiro', 'texto_fala') . " linha(s)\n";

echo "Acentuando sessao_roteiro.texto_instrucao...\n";
echo acentuarColuna($pdo, 'sessao_roteiro', 'texto_instrucao') . " linha(s)\n";

echo "Concluído. Textos de modelos e roteiros existentes atualizados.\n";
