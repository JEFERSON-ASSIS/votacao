<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\SessaoConducao;
use App\Models\User;
use PDO;

/**
 * Pelo Regimento (e como registram as atas), quando o presidente é autor da
 * matéria em discussão ele passa a presidência e a retoma depois. Esta
 * classe diz quando a condução deve sugerir passar ou devolver.
 */
final class AutoriaPresidencia
{
    /**
     * @param array<string, mixed> $item linha de pauta_itens
     * @param array<string, mixed> $usuario linha de users (nome, nome_parlamentar)
     */
    public static function ehAutor(array $item, array $usuario): bool
    {
        $nomesUsuario = array_filter([
            SaplParlamentares::chave((string) ($usuario['nome_parlamentar'] ?? '')),
            SaplParlamentares::chave((string) ($usuario['nome'] ?? '')),
        ]);
        if ($nomesUsuario === []) {
            return false;
        }

        $autores = trim((string) ($item['sapl_autores_parlamentares'] ?? ''));
        if ($autores === '') {
            $autores = (string) ($item['autor'] ?? '');
        }
        foreach (preg_split('/,\s*(?![^()]*\))/', $autores) ?: [] as $autor) {
            // "Fulano (Prefeito Municipal)": o cargo não faz parte do nome.
            $nome = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $autor));
            if ($nome !== '' && in_array(SaplParlamentares::chave($nome), $nomesUsuario, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "passar": o presidente titular conduz uma matéria de que é autor.
     * "devolver": um substituto conduz e a matéria do titular acabou de terminar.
     *
     * @param array<string, mixed>|null $passo passo atual do roteiro
     */
    public static function sugestao(int $sessaoId, ?array $passo): ?string
    {
        $estado = SessaoConducao::estado($sessaoId);
        if ($estado === null || $passo === null) {
            return null;
        }
        $titular = User::find((int) $estado['titular_user_id']);
        if (!$titular) {
            return null;
        }
        $materia = RetiradaPauta::materiaDoPasso($passo);
        $titularAutorAgora = $materia !== null && self::ehAutor($materia, $titular);

        if ((int) $estado['condutor_user_id'] === (int) $estado['titular_user_id']) {
            return $titularAutorAgora ? 'passar' : null;
        }
        if ($titularAutorAgora) {
            return null;
        }

        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessao_roteiro WHERE sessao_id = :s AND ordem < :ordem AND ativo = 1 ORDER BY ordem DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['s' => $sessaoId, 'ordem' => (int) $passo['ordem']]);
        $anterior = $stmt->fetch(PDO::FETCH_ASSOC);
        $materiaAnterior = $anterior ? RetiradaPauta::materiaDoPasso($anterior) : null;

        return $materiaAnterior !== null && self::ehAutor($materiaAnterior, $titular) ? 'devolver' : null;
    }
}
