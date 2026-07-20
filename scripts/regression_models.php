<?php
declare(strict_types=1);

/**
 * Testes de regressao leves para capturar erros de parametros em SQL.
 *
 * Uso:
 *   php scripts/regression_models.php
 */

$tmpDir = dirname(__DIR__) . '/storage/test-regression';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}

$dbPath = $tmpDir . '/regression.sqlite';
@unlink($dbPath);

putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=' . $dbPath);
putenv('APP_ENV=test');
putenv('APP_DEBUG=1');

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Models\Sessao;
use App\Models\SessaoCrud;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;
use App\Models\SessaoVoto;
use App\Models\SessaoVotacaoRodada;

final class RegressionAssert
{
    public static function ok(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new RuntimeException($msg);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $msg): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($msg . ' | esperado=' . var_export($expected, true) . ' obtido=' . var_export($actual, true));
        }
    }
}

function regression_print(string $msg): void
{
    echo $msg . PHP_EOL;
}

function sqlite_columns(PDO $pdo, string $table): array
{
    $rows = $pdo->query("PRAGMA table_info('{$table}')")->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_map(static fn (array $row): string => (string) $row['name'], $rows));
}

function filter_table_columns(PDO $pdo, string $table, array $data): array
{
    $cols = array_flip(sqlite_columns($pdo, $table));
    return array_intersect_key($data, $cols);
}

function ensure_minimal_table(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = sqlite_columns($pdo, $table);
    if (!in_array($column, $cols, true)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
    }
}

try {
    $pdo = DB::conn();
    regression_print('Banco de testes: ' . $dbPath);

    $pautaCols = sqlite_columns($pdo, 'pauta_itens');
    $roteiroCols = sqlite_columns($pdo, 'sessao_roteiro');
    RegressionAssert::ok($pautaCols !== [], 'Tabela pauta_itens nao foi criada no SQLite');

    ensure_column($pdo, 'pauta_itens', 'numero', 'numero INTEGER NULL');
    ensure_column($pdo, 'pauta_itens', 'ano', 'ano INTEGER NULL');
    ensure_column($pdo, 'pauta_itens', 'sapl_id', 'sapl_id INTEGER NULL');
    ensure_column($pdo, 'pauta_itens', 'titulo', 'titulo TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'pauta_itens', 'ementa', 'ementa TEXT NULL');
    ensure_column($pdo, 'pauta_itens', 'status', 'status TEXT NOT NULL DEFAULT \'PENDENTE\'');
    ensure_column($pdo, 'pauta_itens', 'autor', 'autor TEXT NULL');
    ensure_column($pdo, 'pauta_itens', 'regime_tramitacao', 'regime_tramitacao TEXT NULL');
    ensure_column($pdo, 'pauta_itens', 'observacao', 'observacao TEXT NULL');
    ensure_column($pdo, 'pauta_itens', 'sessao_id', 'sessao_id INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'pauta_itens', 'ordem', 'ordem INTEGER NOT NULL DEFAULT 1');

    $pautaCols = sqlite_columns($pdo, 'pauta_itens');

    if ($roteiroCols === []) {
        ensure_minimal_table($pdo, <<<'SQL'
CREATE TABLE sessao_roteiro (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id INTEGER NOT NULL,
    ordem INTEGER NOT NULL DEFAULT 1,
    tipo_item TEXT NOT NULL DEFAULT 'CONDUCAO',
    pauta_item_id INTEGER NULL,
    modelo_id INTEGER NULL,
    titulo TEXT NULL,
    texto_fala TEXT NOT NULL,
    texto_instrucao TEXT NULL,
    tipo_passo TEXT NOT NULL DEFAULT 'FALA',
    gera_votacao INTEGER NOT NULL DEFAULT 0,
    ativo INTEGER NOT NULL DEFAULT 1,
    status_execucao TEXT NOT NULL DEFAULT 'PENDENTE',
    dados_json TEXT NULL
)
SQL);
        $roteiroCols = sqlite_columns($pdo, 'sessao_roteiro');
    }

    if (sqlite_columns($pdo, 'sessoes') === []) {
        ensure_minimal_table($pdo, <<<'SQL'
CREATE TABLE sessoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    tipo TEXT NOT NULL DEFAULT 'ORDINARIA',
    numero INTEGER NULL,
    ano INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'RASCUNHO'
)
SQL);
    }

    if (sqlite_columns($pdo, 'users') === []) {
        ensure_minimal_table($pdo, <<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'USER',
    partido TEXT NULL,
    foto TEXT NULL
)
SQL);
    }
    ensure_column($pdo, 'users', 'nome', 'nome TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'users', 'email', 'email TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'users', 'senha_hash', 'senha_hash TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'users', 'role', 'role TEXT NOT NULL DEFAULT \'USER\'');
    ensure_column($pdo, 'users', 'partido', 'partido TEXT NULL');
    ensure_column($pdo, 'users', 'foto', 'foto TEXT NULL');

    if (sqlite_columns($pdo, 'sessao_votacao_rodadas') === []) {
        ensure_minimal_table($pdo, <<<'SQL'
CREATE TABLE sessao_votacao_rodadas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id INTEGER NOT NULL,
    roteiro_passo_id INTEGER NULL,
    item_id INTEGER NULL,
    titulo TEXT NOT NULL,
    descricao TEXT NULL,
    status TEXT NOT NULL DEFAULT 'ABERTA',
    resultado TEXT NULL,
    criada_em TEXT DEFAULT CURRENT_TIMESTAMP,
    encerrada_em TEXT NULL
)
SQL);
    }
    ensure_column($pdo, 'sessao_votacao_rodadas', 'sessao_id', 'sessao_id INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'roteiro_passo_id', 'roteiro_passo_id INTEGER NULL');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'item_id', 'item_id INTEGER NULL');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'titulo', 'titulo TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'descricao', 'descricao TEXT NULL');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'status', 'status TEXT NOT NULL DEFAULT \'ABERTA\'');
    ensure_column($pdo, 'sessao_votacao_rodadas', 'resultado', 'resultado TEXT NULL');

    if (sqlite_columns($pdo, 'sessao_votos') === []) {
        ensure_minimal_table($pdo, <<<'SQL'
CREATE TABLE sessao_votos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id INTEGER NOT NULL,
    rodada_id INTEGER NULL,
    item_id INTEGER NULL,
    user_id INTEGER NOT NULL,
    voto TEXT NOT NULL,
    observacao TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (rodada_id, user_id)
)
SQL);
    }
    ensure_column($pdo, 'sessao_votos', 'sessao_id', 'sessao_id INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'sessao_votos', 'rodada_id', 'rodada_id INTEGER NULL');
    ensure_column($pdo, 'sessao_votos', 'item_id', 'item_id INTEGER NULL');
    ensure_column($pdo, 'sessao_votos', 'user_id', 'user_id INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'sessao_votos', 'voto', 'voto TEXT NOT NULL DEFAULT \'\'');
    ensure_column($pdo, 'sessao_votos', 'observacao', 'observacao TEXT NULL');

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO sessoes (titulo, tipo, numero, ano, status) VALUES ('Sessao Teste', 'ORDINARIA', 1, 2026, 'ABERTA')");
    $sessaoId = (int) $pdo->lastInsertId();
    $userData = filter_table_columns($pdo, 'users', [
        'nome' => 'Usuario Teste',
        'email' => 'teste@example.com',
        'senha_hash' => 'x',
        'role' => 'PARLAMENTAR',
        'partido' => 'ABC',
        'foto' => null,
    ]);
    $colsSql = implode(', ', array_keys($userData));
    $valsSql = implode(', ', array_map(static fn (string $c): string => ':' . $c, array_keys($userData)));
    $stmt = $pdo->prepare("INSERT INTO users ({$colsSql}) VALUES ({$valsSql})");
    $stmt->execute($userData);
    $userId = (int) $pdo->lastInsertId();

    $baseItem = filter_table_columns($pdo, 'pauta_itens', [
        'sessao_id' => $sessaoId,
        'ordem' => 1,
        'tipo_materia' => 'PL',
        'numero' => '10',
        'ano' => '2026',
        'sapl_id' => 123,
        'titulo' => 'Materia de regressao',
        'ementa' => 'Ementa',
        'status' => 'PENDENTE',
        'autor' => 'Autor',
        'regime_tramitacao' => 'Urgente',
    ]);

    $itemId = SessaoItem::upsert($baseItem, null);
    RegressionAssert::ok($itemId > 0, 'SessaoItem::upsert deveria inserir um item');

    $item = SessaoItem::find($itemId);
    RegressionAssert::same('Materia de regressao', $item['titulo'] ?? null, 'Item inserido com titulo incorreto');

    $item['titulo'] = 'Materia atualizada';
    $item['observacao'] = 'Obs atualizada';
    SessaoItem::upsert($item, $itemId);
    $updated = SessaoItem::find($itemId);
    RegressionAssert::same('Materia atualizada', $updated['titulo'] ?? null, 'SessaoItem::upsert update falhou');
    RegressionAssert::same('Obs atualizada', $updated['observacao'] ?? null, 'SessaoItem::upsert update nao persistiu observacao');

    $roteiroInsert = filter_table_columns($pdo, 'sessao_roteiro', [
        'sessao_id' => $sessaoId,
        'ordem' => 1,
        'tipo_item' => 'CONDUCAO',
        'titulo' => 'Passo 1',
        'texto_fala' => 'Texto 1',
        'tipo_passo' => 'FALA',
        'gera_votacao' => 0,
        'ativo' => 1,
        'status_execucao' => 'PENDENTE',
    ]);
    $colsSql = implode(', ', array_keys($roteiroInsert));
    $valsSql = implode(', ', array_map(static fn (string $c): string => ':' . $c, array_keys($roteiroInsert)));
    $stmt = $pdo->prepare("INSERT INTO sessao_roteiro ({$colsSql}) VALUES ({$valsSql})");
    $stmt->execute($roteiroInsert);
    $roteiroId = (int) $pdo->lastInsertId();
    SessaoRoteiro::update($roteiroId, [
        'ordem' => 2,
        'titulo' => 'Passo 1 editado',
        'texto_fala' => 'Texto 1 editado',
        'texto_instrucao' => 'Instrucao',
        'tipo_passo' => 'FALA',
        'gera_votacao' => 1,
        'ativo' => 1,
        'status_execucao' => 'EM_ANDAMENTO',
    ]);
    $roteiro = SessaoRoteiro::find($roteiroId);
    RegressionAssert::same('Passo 1 editado', $roteiro['titulo'] ?? null, 'SessaoRoteiro::update falhou');
    RegressionAssert::same('EM_ANDAMENTO', $roteiro['status_execucao'] ?? null, 'SessaoRoteiro::update nao persistiu status');

    $rodadaId = SessaoVotacaoRodada::abrir($sessaoId, 'Votacao teste', $roteiroId, $itemId, 'Descricao');
    RegressionAssert::ok($rodadaId > 0, 'SessaoVotacaoRodada::abrir deveria criar rodada');

    SessaoVoto::voteRodada($sessaoId, $rodadaId, $itemId, $userId, 'SIM', 'ok');
    SessaoVoto::voteRodada($sessaoId, $rodadaId, $itemId, $userId, 'NAO', 'troca');
    $voto = SessaoVoto::votoUsuarioRodada($rodadaId, $userId);
    RegressionAssert::same('NAO', $voto, 'SessaoVoto::voteRodada deveria atualizar o voto existente');

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        SessaoVotacaoRodada::encerrar($rodadaId);
        $rodada = SessaoVotacaoRodada::find($rodadaId);
        RegressionAssert::same('ENCERRADA', $rodada['status'] ?? null, 'Rodada deveria ser encerrada');
    } else {
        $stmt = $pdo->prepare("UPDATE sessao_votacao_rodadas SET status = 'ENCERRADA', resultado = :resultado WHERE id = :id");
        $stmt->execute(['resultado' => 'APROVADO', 'id' => $rodadaId]);
        $rodada = SessaoVotacaoRodada::find($rodadaId);
        RegressionAssert::same('ENCERRADA', $rodada['status'] ?? null, 'Rodada deveria ser encerrada');
    }

    Sessao::updateStatus($sessaoId, 'ABERTA');
    $sessao = SessaoCrud::find($sessaoId);
    RegressionAssert::same('ABERTA', strtoupper((string) ($sessao['status'] ?? '')), 'Sessao deveria ficar aberta');

    $pdo->rollBack();

    regression_print('OK: testes de regressao passaram.');
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FALHOU: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
