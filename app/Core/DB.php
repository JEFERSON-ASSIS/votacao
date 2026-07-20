<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = (string) getenv('DB_DRIVER') ?: 'mysql';
        $host = (string) getenv('DB_HOST') ?: '127.0.0.1';
        $port = (string) getenv('DB_PORT') ?: '3307';
        $name = (string) getenv('DB_NAME') ?: 'plenario';
        $user = (string) getenv('DB_USER') ?: 'root';
        $pass = (string) getenv('DB_PASS') ?: '';
        $charset = (string) getenv('DB_CHARSET') ?: 'utf8mb4';

        try {
            if ($driver === 'sqlite') {
                $path = (string) getenv('DB_PATH') ?: dirname(__DIR__, 2) . '/database/plenario.sqlite';
                self::$pdo = new PDO('sqlite:' . $path);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::ensureSqliteSchema(self::$pdo);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Falha ao conectar no banco configurado: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    private static function ensureSqliteSchema(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            return;
        }

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();

        try {
            self::rebuildUsersTable($pdo);
            self::rebuildSessoesTable($pdo);
            self::rebuildPautaItensTable($pdo);
            self::rebuildSessaoPresentesTable($pdo);
            self::rebuildSessaoVotosTable($pdo);
            self::rebuildSessaoEventosTable($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function rebuildUsersTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'users');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nome TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    senha_hash TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'USER',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )"
            );
            return;
        }

        $sql = (string) self::tableSql($pdo, 'users');
        if (!str_contains(strtoupper($sql), 'AUTO_INCREMENT') && isset($columns['id']) && strtoupper((string) $columns['id']['type']) === 'INTEGER') {
            return;
        }

        $pdo->exec(
            "CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                senha_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'USER',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "INSERT INTO users_new (id, nome, email, senha_hash, role, created_at)
             SELECT COALESCE(id, rowid), nome, email, senha_hash, COALESCE(role, 'USER'), created_at
             FROM users"
        );
        $pdo->exec('DROP TABLE users');
        $pdo->exec('ALTER TABLE users_new RENAME TO users');
    }

    private static function rebuildSessoesTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'sessoes');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE sessoes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    titulo TEXT NOT NULL,
                    tipo TEXT NOT NULL DEFAULT 'ORDINARIA',
                    numero INTEGER NOT NULL DEFAULT 1,
                    ano INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'RASCUNHO',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )"
            );
            return;
        }

        $sql = (string) self::tableSql($pdo, 'sessoes');
        if (!str_contains(strtoupper($sql), 'AUTO_INCREMENT') && isset($columns['id']) && strtoupper((string) $columns['id']['type']) === 'INTEGER') {
            return;
        }

        $pdo->exec(
            "CREATE TABLE sessoes_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo TEXT NOT NULL,
                tipo TEXT NOT NULL DEFAULT 'ORDINARIA',
                numero INTEGER NOT NULL DEFAULT 1,
                ano INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'RASCUNHO',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "INSERT INTO sessoes_new (id, titulo, tipo, numero, ano, status, created_at)
             SELECT COALESCE(id, rowid), titulo, COALESCE(tipo, 'ORDINARIA'), COALESCE(numero, 1), ano, COALESCE(status, 'RASCUNHO'), created_at
             FROM sessoes"
        );
        $pdo->exec('DROP TABLE sessoes');
        $pdo->exec('ALTER TABLE sessoes_new RENAME TO sessoes');
    }

    private static function rebuildPautaItensTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'pauta_itens');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE pauta_itens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sessao_id INTEGER NOT NULL,
                    ordem INTEGER NOT NULL DEFAULT 1,
                    tipo_materia TEXT NOT NULL DEFAULT 'Materia',
                    titulo TEXT NOT NULL,
                    ementa TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'PENDENTE',
                    texto_original TEXT NULL,
                    tipo_apresentacao TEXT NULL,
                    data_apresentacao TEXT NULL,
                    numero_protocolo TEXT NULL,
                    autor TEXT NULL,
                    regime_tramitacao TEXT NULL,
                    data_publicacao TEXT NULL,
                    materia_polemica TEXT NOT NULL DEFAULT 'Nao',
                    em_tramitacao TEXT NOT NULL DEFAULT 'Nao',
                    eh_complementar TEXT NOT NULL DEFAULT 'Nao',
                    dados_extras_json TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE
                )"
            );
            return;
        }

        $needsRebuild = str_contains(strtoupper((string) self::tableSql($pdo, 'pauta_itens')), 'AUTO_INCREMENT')
            || !isset($columns['tipo_materia'])
            || !isset($columns['ementa'])
            || !isset($columns['dados_extras_json']);

        if (!$needsRebuild) {
            return;
        }

        $hasDescricao = isset($columns['descricao']);

        $pdo->exec(
            "CREATE TABLE pauta_itens_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sessao_id INTEGER NOT NULL,
                ordem INTEGER NOT NULL DEFAULT 1,
                tipo_materia TEXT NOT NULL DEFAULT 'Materia',
                titulo TEXT NOT NULL,
                ementa TEXT NULL,
                status TEXT NOT NULL DEFAULT 'PENDENTE',
                texto_original TEXT NULL,
                tipo_apresentacao TEXT NULL,
                data_apresentacao TEXT NULL,
                numero_protocolo TEXT NULL,
                autor TEXT NULL,
                regime_tramitacao TEXT NULL,
                data_publicacao TEXT NULL,
                materia_polemica TEXT NOT NULL DEFAULT 'Nao',
                em_tramitacao TEXT NOT NULL DEFAULT 'Nao',
                eh_complementar TEXT NOT NULL DEFAULT 'Nao',
                dados_extras_json TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE
            )"
        );

        $ementaExpr = $hasDescricao ? 'descricao' : 'ementa';
        $tipoExpr = isset($columns['tipo_materia']) ? "COALESCE(tipo_materia, 'Materia')" : "'Materia'";
        $pdo->exec(
            "INSERT INTO pauta_itens_new (
                id, sessao_id, ordem, tipo_materia, titulo, ementa, status, texto_original, tipo_apresentacao,
                data_apresentacao, numero_protocolo, autor, regime_tramitacao, data_publicacao,
                materia_polemica, em_tramitacao, eh_complementar, dados_extras_json, created_at
            )
            SELECT
                COALESCE(id, rowid),
                sessao_id,
                COALESCE(ordem, 1),
                $tipoExpr,
                titulo,
                $ementaExpr,
                COALESCE(status, 'PENDENTE'),
                " . (isset($columns['texto_original']) ? 'texto_original' : 'NULL') . ",
                " . (isset($columns['tipo_apresentacao']) ? 'tipo_apresentacao' : 'NULL') . ",
                " . (isset($columns['data_apresentacao']) ? 'data_apresentacao' : 'NULL') . ",
                " . (isset($columns['numero_protocolo']) ? 'numero_protocolo' : 'NULL') . ",
                " . (isset($columns['autor']) ? 'autor' : 'NULL') . ",
                " . (isset($columns['regime_tramitacao']) ? 'regime_tramitacao' : 'NULL') . ",
                " . (isset($columns['data_publicacao']) ? 'data_publicacao' : 'NULL') . ",
                " . (isset($columns['materia_polemica']) ? "COALESCE(materia_polemica, 'Nao')" : "'Nao'") . ",
                " . (isset($columns['em_tramitacao']) ? "COALESCE(em_tramitacao, 'Nao')" : "'Nao'") . ",
                " . (isset($columns['eh_complementar']) ? "COALESCE(eh_complementar, 'Nao')" : "'Nao'") . ",
                " . (isset($columns['dados_extras_json']) ? 'dados_extras_json' : 'NULL') . ",
                created_at
            FROM pauta_itens"
        );
        $pdo->exec('DROP TABLE pauta_itens');
        $pdo->exec('ALTER TABLE pauta_itens_new RENAME TO pauta_itens');
    }

    private static function rebuildSessaoPresentesTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'sessao_presentes');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE sessao_presentes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sessao_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    presente INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (sessao_id, user_id),
                    FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )"
            );
            return;
        }

        if (!str_contains(strtoupper((string) self::tableSql($pdo, 'sessao_presentes')), 'AUTO_INCREMENT')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE sessao_presentes_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sessao_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                presente INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (sessao_id, user_id),
                FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );
        $pdo->exec(
            "INSERT INTO sessao_presentes_new (id, sessao_id, user_id, presente, created_at)
             SELECT COALESCE(id, rowid), sessao_id, user_id, COALESCE(presente, 1), created_at
             FROM sessao_presentes"
        );
        $pdo->exec('DROP TABLE sessao_presentes');
        $pdo->exec('ALTER TABLE sessao_presentes_new RENAME TO sessao_presentes');
    }

    private static function rebuildSessaoVotosTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'sessao_votos');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE sessao_votos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sessao_id INTEGER NOT NULL,
                    item_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    voto TEXT NOT NULL,
                    observacao TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (item_id, user_id),
                    FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                    FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )"
            );
            return;
        }

        if (!str_contains(strtoupper((string) self::tableSql($pdo, 'sessao_votos')), 'AUTO_INCREMENT')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE sessao_votos_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sessao_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                voto TEXT NOT NULL,
                observacao TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (item_id, user_id),
                FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );
        $pdo->exec(
            "INSERT INTO sessao_votos_new (id, sessao_id, item_id, user_id, voto, observacao, created_at)
             SELECT COALESCE(id, rowid), sessao_id, item_id, user_id, voto, observacao, created_at
             FROM sessao_votos"
        );
        $pdo->exec('DROP TABLE sessao_votos');
        $pdo->exec('ALTER TABLE sessao_votos_new RENAME TO sessao_votos');
    }

    private static function rebuildSessaoEventosTable(PDO $pdo): void
    {
        $columns = self::tableColumns($pdo, 'sessao_eventos');
        if ($columns === []) {
            $pdo->exec(
                "CREATE TABLE sessao_eventos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sessao_id INTEGER NOT NULL,
                    item_id INTEGER NULL,
                    tipo TEXT NOT NULL,
                    descricao TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                    FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL
                )"
            );
            return;
        }

        if (!str_contains(strtoupper((string) self::tableSql($pdo, 'sessao_eventos')), 'AUTO_INCREMENT')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE sessao_eventos_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sessao_id INTEGER NOT NULL,
                item_id INTEGER NULL,
                tipo TEXT NOT NULL,
                descricao TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL
            )"
        );
        $pdo->exec(
            "INSERT INTO sessao_eventos_new (id, sessao_id, item_id, tipo, descricao, created_at)
             SELECT COALESCE(id, rowid), sessao_id, item_id, tipo, descricao, created_at
             FROM sessao_eventos"
        );
        $pdo->exec('DROP TABLE sessao_eventos');
        $pdo->exec('ALTER TABLE sessao_eventos_new RENAME TO sessao_eventos');
    }

    private static function tableSql(PDO $pdo, string $table): ?string
    {
        $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1");
        $stmt->execute(['name' => $table]);
        $sql = $stmt->fetchColumn();
        return is_string($sql) ? $sql : null;
    }

    private static function tableColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $columns = [];
        foreach ($rows as $row) {
            $columns[(string) $row['name']] = $row;
        }
        return $columns;
    }
}
