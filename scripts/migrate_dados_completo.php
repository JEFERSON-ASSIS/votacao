<?php
declare(strict_types=1);

/**
 * Migração completa: estrutura + todos os dados do banco atual.
 *
 * No PC (XAMPP / banco local plenario):
 *   php scripts/migrate_dados_completo.php export
 *
 * Envie o arquivo gerado para o servidor e importe:
 *   php scripts/migrate_dados_completo.php import storage/migrate/plenario_dump.sql
 *
 * O import apaga os dados atuais das tabelas do dump e recarrega tudo,
 * depois roda migrate_all.php (colunas/tabelas extras).
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

final class MigrateDadosCompleto
{
    private const BATCH_ROWS = 80;
    private const DUMP_DIR = __DIR__ . '/../storage/migrate';

    /** Collation do MySQL 8 local; Hostinger/MariaDB costuma nao ter. */
    private const COLLATE_HOSTINGER = 'utf8mb4_unicode_ci';

    public static function run(array $argv): void
    {
        $cmd = strtolower(trim((string) ($argv[1] ?? '')));
        if ($cmd === 'export') {
            self::export();
            return;
        }
        if ($cmd === 'import') {
            $file = (string) ($argv[2] ?? self::defaultDumpPath());
            self::import($file);
            return;
        }

        self::usage();
        exit(1);
    }

    private static function usage(): void
    {
        fwrite(STDERR, <<<TXT
Uso:
  php scripts/migrate_dados_completo.php export
  php scripts/migrate_dados_completo.php import [arquivo.sql]

TXT);
    }

    public static function defaultDumpPath(): string
    {
        return self::DUMP_DIR . '/plenario_dump.sql';
    }

    private static function pdo(): PDO
    {
        return DB::conn();
    }

    /** @return list<string> */
    private static function listTables(PDO $pdo): array
    {
        $tables = [];
        $stmt = $pdo->query('SHOW TABLES');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = (string) $row[0];
        }

        return self::sortTablesForImport($tables);
    }

    /** Ordem segura para TRUNCATE/INSERT (pais antes dos filhos). */
    private static function sortTablesForImport(array $tables): array
    {
        $priority = [
            'users',
            'user_permissoes',
            'sessoes',
            'pauta_itens',
            'pauta_despachos',
            'pauta_documentos',
            'pauta_legislacoes',
            'pauta_numeracoes',
            'pauta_tramitacoes',
            'pauta_relatorias',
            'sessao_presentes',
            'sessao_eventos',
            'conducao_modelos',
            'conducao_modelo_passos',
            'sessao_roteiro',
            'sessao_votacao_rodadas',
            'sessao_votos',
            'auditoria_log',
            'sessao_cerimonial',
        ];

        $rank = array_flip($priority);
        usort($tables, static function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? 999;
            $rb = $rank[$b] ?? 999;
            if ($ra === $rb) {
                return strcmp($a, $b);
            }

            return $ra <=> $rb;
        });

        return $tables;
    }

    private static function export(): void
    {
        if (!is_dir(self::DUMP_DIR)) {
            mkdir(self::DUMP_DIR, 0775, true);
        }

        $path = self::defaultDumpPath();
        $pdo = self::pdo();
        $tables = self::listTables($pdo);

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Nao foi possivel criar ' . $path);
        }

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $header = '-- Plenario dump completo' . PHP_EOL
            . '-- Banco: ' . $dbName . PHP_EOL
            . '-- Gerado: ' . date('Y-m-d H:i:s') . PHP_EOL
            . '-- Charset/collation: compativel Hostinger (utf8mb4_unicode_ci)' . PHP_EOL
            . 'SET NAMES utf8mb4 COLLATE ' . self::COLLATE_HOSTINGER . ';' . PHP_EOL
            . 'SET FOREIGN_KEY_CHECKS=0;' . PHP_EOL . PHP_EOL;
        fwrite($fh, $header);

        foreach ($tables as $table) {
            echo "Exportando {$table}...\n";
            self::writeCreateTable($pdo, $fh, $table);
            self::writeTableData($pdo, $fh, $table);
        }

        fwrite($fh, 'SET FOREIGN_KEY_CHECKS=1;' . PHP_EOL);
        fclose($fh);

        $size = round(filesize($path) / 1024, 1);
        echo PHP_EOL . "Export concluido: {$path} ({$size} KB)" . PHP_EOL;
        echo "Envie este arquivo ao servidor e rode:" . PHP_EOL;
        echo "  php scripts/migrate_dados_completo.php import storage/migrate/plenario_dump.sql" . PHP_EOL;
    }

    private static function writeCreateTable(PDO $pdo, $fh, string $table): void
    {
        $stmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['Create Table'])) {
            return;
        }

        fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . '`;' . PHP_EOL);
        $create = self::normalizeCreateTableSql((string) $row['Create Table']);
        fwrite($fh, $create . ';' . PHP_EOL . PHP_EOL);
    }

    /** Troca collations do MySQL 8 por utf8mb4_unicode_ci (MariaDB / MySQL 5.7 na Hostinger). */
    public static function normalizeCreateTableSql(string $sql): string
    {
        $from = [
            'utf8mb4_0900_ai_ci',
            'utf8mb4_0900_as_ci',
            'utf8mb4_0900_as_cs',
            'utf8mb4_0900_bin',
        ];
        $sql = str_replace($from, self::COLLATE_HOSTINGER, $sql);

        if (!preg_match('/DEFAULT\s+CHARSET\s*=/i', $sql)) {
            $sql = rtrim($sql) . ' DEFAULT CHARSET=utf8mb4 COLLATE=' . self::COLLATE_HOSTINGER;
        }

        return $sql;
    }

    private static function writeTableData(PDO $pdo, $fh, string $table): void
    {
        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            fwrite($fh, '-- ' . $table . ': vazio' . PHP_EOL . PHP_EOL);
            return;
        }

        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns));
        $chunks = array_chunk($rows, self::BATCH_ROWS);

        foreach ($chunks as $chunk) {
            $valuesSql = [];
            foreach ($chunk as $row) {
                $vals = [];
                foreach ($columns as $col) {
                    $vals[] = self::sqlLiteral($row[$col] ?? null);
                }
                $valuesSql[] = '(' . implode(', ', $vals) . ')';
            }
            $sql = 'INSERT INTO `' . $table . '` (' . $colList . ') VALUES' . PHP_EOL
                . implode(',' . PHP_EOL, $valuesSql) . ';' . PHP_EOL . PHP_EOL;
            fwrite($fh, $sql);
        }

        echo '  -> ' . count($rows) . " registro(s)\n";
    }

    private static function sqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return self::pdo()->quote((string) $value);
    }

    private static function import(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo nao encontrado: ' . $path);
        }

        $pdo = self::pdo();
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Dump vazio ou ilegivel.');
        }

        echo "Importando {$path} ...\n";
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET NAMES utf8mb4');

        foreach (self::splitStatements($sql) as $i => $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                $preview = mb_substr(str_replace(["\r", "\n"], ' ', $statement), 0, 120);
                throw new RuntimeException(
                    'Erro no statement #' . ($i + 1) . ': ' . $e->getMessage() . ' | ' . $preview,
                    0,
                    $e
                );
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        echo "Dados importados.\n\nRodando migrate_all (schema extra)...\n";

        $migrateAll = __DIR__ . '/migrate_all.php';
        $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        passthru($php . ' ' . escapeshellarg($migrateAll), $code);
        if ($code !== 0) {
            throw new RuntimeException('migrate_all.php falhou.');
        }

        echo "\nMigracao completa concluida.\n";
    }

    /** @return list<string> */
    private static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $sql) ?: [];
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $stmt = trim($buffer);
                $buffer = '';
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}

try {
    MigrateDadosCompleto::run($argv);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
