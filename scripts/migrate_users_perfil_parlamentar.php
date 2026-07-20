<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$pdo = DB::conn();
$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

$additions = [
    'homepage' => 'VARCHAR(255) NULL DEFAULT NULL AFTER foto',
    'gabinete' => 'VARCHAR(30) NULL DEFAULT NULL AFTER homepage',
    'biografia' => 'TEXT NULL DEFAULT NULL AFTER gabinete',
    'naturalidade' => 'VARCHAR(120) NULL DEFAULT NULL AFTER biografia',
    'data_nascimento' => 'DATE NULL DEFAULT NULL AFTER naturalidade',
    'estado_civil' => 'VARCHAR(40) NULL DEFAULT NULL AFTER data_nascimento',
    'ocupacao' => 'VARCHAR(200) NULL DEFAULT NULL AFTER estado_civil',
    'cor' => 'VARCHAR(40) NULL DEFAULT NULL AFTER ocupacao',
    'genero' => 'VARCHAR(40) NULL DEFAULT NULL AFTER cor',
    'identidade_genero' => 'VARCHAR(60) NULL DEFAULT NULL AFTER genero',
    'orientacao_sexual' => 'VARCHAR(60) NULL DEFAULT NULL AFTER identidade_genero',
    'grau_instrucao' => 'VARCHAR(200) NULL DEFAULT NULL AFTER orientacao_sexual',
    'historico_politico' => 'TEXT NULL DEFAULT NULL AFTER grau_instrucao',
];

foreach ($additions as $name => $definition) {
    if (!in_array($name, $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN {$name} {$definition}");
        echo "Coluna {$name} criada.\n";
    }
}

echo "Perfil parlamentar: concluído.\n";
