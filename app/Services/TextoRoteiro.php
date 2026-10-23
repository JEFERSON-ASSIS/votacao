<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Prepara o texto de um passo do roteiro para exibir na condução e para os
 * vereadores, preenchendo o que o roteiro deixou em aberto:
 *  - "Vereador ????" / "Vereador ___" / {autor}: autor da matéria da pauta;
 *  - "APROVADA / REPROVADA": resultado da votação (ResultadoRoteiro).
 * A matéria vem do próprio passo ou do passo anterior mais próximo ligado a
 * uma matéria. Nada é gravado: a troca acontece na hora de exibir.
 */
final class TextoRoteiro
{
    /** @param array<string, mixed> $passo linha de sessao_roteiro */
    public static function exibir(string $texto, array $passo): string
    {
        return ResultadoRoteiro::aplicar(self::aplicarAutor($texto, $passo), $passo);
    }

    /** @param array<string, mixed> $passo */
    public static function aplicarAutor(string $texto, array $passo): string
    {
        $padrao = '/\{autor\}|(Vereador(?:a)?(?:\s|&nbsp;)+)(?:\?{2,}|_{3,})/iu';
        if ($texto === '' || !preg_match($padrao, $texto)) {
            return $texto;
        }
        $autor = self::autorDoPasso($passo);
        if ($autor === '') {
            return $texto;
        }

        return (string) preg_replace_callback(
            $padrao,
            static fn (array $m): string => ($m[1] ?? '') . $autor,
            $texto
        );
    }

    /** @param array<string, mixed> $passo */
    private static function autorDoPasso(array $passo): string
    {
        $sessaoId = (int) ($passo['sessao_id'] ?? 0);
        if ($sessaoId <= 0) {
            return '';
        }
        $stmt = DB::conn()->prepare(
            'SELECT p.autor
             FROM sessao_roteiro r
             INNER JOIN pauta_itens p ON p.id = r.pauta_item_id AND p.sessao_id = r.sessao_id
             WHERE r.sessao_id = :sessao_id AND r.ordem <= :ordem AND r.pauta_item_id IS NOT NULL
             ORDER BY r.ordem DESC, r.id DESC LIMIT 1'
        );
        $stmt->execute(['sessao_id' => $sessaoId, 'ordem' => (int) ($passo['ordem'] ?? 0)]);
        $autor = $stmt->fetchColumn();

        return $autor === false ? '' : trim((string) $autor);
    }
}
