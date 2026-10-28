<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\UserPerfil;
use PDO;

final class User
{
    /** @return list<string> */
    public static function camposPerfil(): array
    {
        return [
            'homepage',
            'gabinete',
            'biografia',
            'naturalidade',
            'data_nascimento',
            'estado_civil',
            'ocupacao',
            'cor',
            'genero',
            'identidade_genero',
            'orientacao_sexual',
            'grau_instrucao',
            'historico_politico',
        ];
    }

    /** @param array<string, mixed> $post */
    public static function dadosFromPost(array $post): array
    {
        $data = [
            'nome' => trim((string) ($post['nome'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'cpf' => trim((string) ($post['cpf'] ?? '')),
            'telefone' => trim((string) ($post['telefone'] ?? '')),
            'role' => trim((string) ($post['role'] ?? UserPerfil::USUARIO)),
            'partido' => trim((string) ($post['partido'] ?? '')),
            'homepage' => trim((string) ($post['homepage'] ?? '')),
            'gabinete' => trim((string) ($post['gabinete'] ?? '')),
            'biografia' => trim((string) ($post['biografia'] ?? '')),
            'naturalidade' => trim((string) ($post['naturalidade'] ?? '')),
            'data_nascimento' => self::parseDataNascimento(trim((string) ($post['data_nascimento'] ?? ''))),
            'estado_civil' => trim((string) ($post['estado_civil'] ?? '')),
            'ocupacao' => trim((string) ($post['ocupacao'] ?? '')),
            'cor' => trim((string) ($post['cor'] ?? '')),
            'genero' => trim((string) ($post['genero'] ?? '')),
            'identidade_genero' => trim((string) ($post['identidade_genero'] ?? '')),
            'orientacao_sexual' => trim((string) ($post['orientacao_sexual'] ?? '')),
            'grau_instrucao' => trim((string) ($post['grau_instrucao'] ?? '')),
            'historico_politico' => trim((string) ($post['historico_politico'] ?? '')),
        ];

        return self::normalizeDados($data);
    }

    /** @param array<string, mixed> $data */
    public static function normalizeDados(array $data): array
    {
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $data['role'] = UserPerfil::normalize((string) ($data['role'] ?? ''));
        foreach (self::camposPerfil() as $campo) {
            if ($campo === 'data_nascimento') {
                continue;
            }
            if (in_array($campo, ['biografia', 'historico_politico'], true)) {
                $data[$campo] = self::nullableText($data[$campo] ?? null);
            } else {
                $data[$campo] = self::nullableStr($data[$campo] ?? null);
            }
        }
        $dn = $data['data_nascimento'] ?? null;
        $data['data_nascimento'] = ($dn === null || $dn === '') ? null : (string) $dn;

        return $data;
    }

    public static function parseDataNascimento(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    public static function formatarDataNascimento(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        return date('d/m/Y', $ts);
    }

    public static function dataNascimentoValida(?string $isoDate, string $inputBr): bool
    {
        if ($inputBr === '') {
            return true;
        }

        return $isoDate !== null && $isoDate !== '';
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    public static function findByCpf(string $cpf, ?int $ignoreId = null): ?array
    {
        $digits = self::cpfDigits($cpf);
        if ($digits === '') {
            return null;
        }
        $sql = 'SELECT * FROM users WHERE cpf = :cpf';
        $params = ['cpf' => $digits];
        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }
        $stmt = DB::conn()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    public static function all(): array
    {
        $rows = DB::conn()->query(
            'SELECT * FROM users ORDER BY nome ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** Vereadores/parlamentares no painel de votação. */
    public static function parlamentaresParaVotacao(): array
    {
        $rows = DB::conn()->query('SELECT * FROM users ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter(
            array_map([self::class, 'hydrate'], $rows),
            static fn (array $u): bool => UserPerfil::ehVereador($u['role'] ?? '')
        ));
    }

    public static function create(array $data): int
    {
        $row = self::rowBindings($data);
        $row['senha_hash'] = (string) $data['senha_hash'];
        $cols = array_keys($row);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $cols);
        $sql = 'INSERT INTO users (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($row);

        return (int) DB::conn()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = self::rowBindings($data);
        $fields['id'] = $id;
        $sets = [];
        foreach (array_keys(self::rowBindings($data)) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $sql = 'UPDATE users SET ' . implode(', ', $sets);
        if (!empty($data['senha_hash'])) {
            $sql .= ', senha_hash = :senha_hash';
            $fields['senha_hash'] = (string) $data['senha_hash'];
        }
        $sql .= ' WHERE id = :id';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($fields);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function rowBindings(array $data): array
    {
        $data = self::normalizeDados($data);
        $row = [
            'nome' => trim((string) $data['nome']),
            'email' => (string) $data['email'],
            'cpf' => self::cpfDigits((string) $data['cpf']),
            'telefone' => self::telefoneDigits((string) ($data['telefone'] ?? '')),
            'role' => (string) $data['role'],
            'partido' => self::nullableStr($data['partido'] ?? null),
            'foto' => self::nullableStr($data['foto'] ?? null),
        ];
        foreach (self::camposPerfil() as $campo) {
            $row[$campo] = $data[$campo] ?? null;
        }

        return $row;
    }

    public static function delete(int $id): void
    {
        $stmt = DB::conn()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function cpfDigits(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function formatarCpf(string $cpf): string
    {
        $d = self::cpfDigits($cpf);
        if (strlen($d) !== 11) {
            return $cpf;
        }

        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public static function cpfValido(string $cpf): bool
    {
        $d = self::cpfDigits($cpf);
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $d[$i] * ($t + 1 - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $d[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function telefoneDigits(string $telefone): string
    {
        return preg_replace('/\D+/', '', $telefone) ?? '';
    }

    public static function formatarTelefone(string $telefone): string
    {
        $d = self::telefoneDigits($telefone);
        if (strlen($d) === 11) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
        }
        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
        }

        return $telefone;
    }

    private static function nullableStr(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private static function nullableText(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): array
    {
        $row['role'] = UserPerfil::normalize((string) ($row['role'] ?? ''));
        if (!empty($row['cpf'])) {
            $row['cpf_formatado'] = self::formatarCpf((string) $row['cpf']);
        }
        if (!empty($row['telefone'])) {
            $row['telefone_formatado'] = self::formatarTelefone((string) $row['telefone']);
        }
        if (!empty($row['data_nascimento'])) {
            $row['data_nascimento_formatada'] = self::formatarDataNascimento((string) $row['data_nascimento']);
        }

        return $row;
    }
}
