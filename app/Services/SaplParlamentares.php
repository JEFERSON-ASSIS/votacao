<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use PDO;

/**
 * Liga os vereadores cadastrados aqui aos parlamentares do SAPL, pelo nome
 * completo. Assim o sistema sabe que "Jadão do Dogão" (nome parlamentar,
 * usado nas autorias do SAPL) é o usuário "JADER PAULO IZIDORIO".
 */
final class SaplParlamentares
{
    /** Palavras ignoradas na comparação de nomes. */
    private const PARTICULAS = ['DA', 'DE', 'DO', 'DAS', 'DOS', 'E'];

    /**
     * Busca os parlamentares ativos no SAPL e grava o vínculo nos usuários
     * vereadores/presidente. @return array{vinculados: int, sem_vinculo: list<string>}
     */
    public static function sincronizar(): array
    {
        $parlamentares = SaplCliente::get('parlamentares/parlamentar/', ['ativo' => 'True', 'page_size' => 100])['results'] ?? [];
        $pdo = DB::conn();
        $usuarios = $pdo->query("SELECT id, nome FROM users WHERE role IN ('PARLAMENTAR', 'PRESIDENTE')")->fetchAll(PDO::FETCH_ASSOC);
        $vinculos = self::vincular($usuarios, $parlamentares);

        $grava = $pdo->prepare('UPDATE users SET nome_parlamentar = :nome, sapl_parlamentar_id = :sapl WHERE id = :id');
        foreach ($vinculos as $userId => $p) {
            $grava->execute(['nome' => $p['nome_parlamentar'], 'sapl' => $p['id'], 'id' => $userId]);
        }
        $semVinculo = [];
        foreach ($usuarios as $u) {
            if (!isset($vinculos[(int) $u['id']])) {
                $semVinculo[] = (string) $u['nome'];
            }
        }

        return ['vinculados' => count($vinculos), 'sem_vinculo' => $semVinculo];
    }

    /**
     * @param list<array{id: int|string, nome: string}> $usuarios
     * @param list<array<string, mixed>> $parlamentares (id, nome_parlamentar, nome_completo)
     * @return array<int, array{id: int, nome_parlamentar: string}> por id do usuário
     */
    public static function vincular(array $usuarios, array $parlamentares): array
    {
        $porNome = [];
        foreach ($parlamentares as $p) {
            $porNome[self::chave((string) ($p['nome_completo'] ?? ''))] = $p;
        }
        $vinculos = [];
        foreach ($usuarios as $u) {
            $p = $porNome[self::chave((string) $u['nome'])] ?? null;
            if ($p !== null && trim((string) ($p['nome_parlamentar'] ?? '')) !== '') {
                $vinculos[(int) $u['id']] = ['id' => (int) $p['id'], 'nome_parlamentar' => trim((string) $p['nome_parlamentar'])];
            }
        }

        return $vinculos;
    }

    /** Nome normalizado para comparação: sem acento, maiúsculo, sem "da/de", em ordem alfabética. */
    public static function chave(string $nome): string
    {
        $nome = strtr(mb_strtoupper(trim($nome)), [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        ]);
        $palavras = array_values(array_filter(
            preg_split('/[^A-Z0-9]+/', $nome) ?: [],
            static fn (string $p): bool => $p !== '' && !in_array($p, self::PARTICULAS, true)
        ));
        sort($palavras);

        return implode(' ', $palavras);
    }
}
