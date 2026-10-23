<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\SessaoVoto;
use App\Models\SessaoVotoDesempate;
use PDO;

/**
 * Preenche no texto do roteiro o trecho "APROVADA / REPROVADA (por unanimidade)"
 * com o resultado real da votação do passo de votação mais próximo (o próprio
 * passo ou o anterior). O roteiro é gerado antes da votação, por isso o texto
 * gravado traz as duas opções; a troca acontece na hora de exibir.
 */
final class ResultadoRoteiro
{
    private const ASPAS = '(?:["“”]|&ldquo;|&rdquo;|&quot;|&#8220;|&#8221;)?';
    private const ESPACO = '(?:\s|&nbsp;)+';
    private const SEPARADOR = '(?:\s|&nbsp;|<[^>]*>)*';

    /** @param array<string, mixed> $passo linha de sessao_roteiro (id, sessao_id, ordem) */
    public static function aplicar(string $texto, array $passo): string
    {
        if ($texto === '' || stripos($texto, 'REPROVAD') === false) {
            return $texto;
        }
        $padrao = '/APROVAD([OA])' . self::SEPARADOR . '\/' . self::SEPARADOR . 'REPROVAD[OA]((?:<\/[^>]+>)*)'
            . '(?:' . self::ESPACO . 'por' . self::ESPACO . self::ASPAS . 'unanimidade' . self::ASPAS . ')?/iu';
        if (!preg_match($padrao, $texto)) {
            return $texto;
        }

        $rodada = self::rodadaDoPasso($passo);
        if ($rodada === null) {
            return $texto;
        }
        $resultado = strtoupper((string) ($rodada['resultado'] ?? ''));
        if (!in_array($resultado, ['APROVADO', 'REJEITADO'], true)) {
            return $texto;
        }
        $rodadaId = (int) $rodada['id'];
        $resumo = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
        $desempate = SessaoVotoDesempate::voto($rodadaId) !== null;

        return (string) preg_replace_callback(
            $padrao,
            static fn (array $m): string => self::frase($resumo, $resultado, $desempate, strtoupper($m[1])) . ($m[2] ?? ''),
            $texto
        );
    }

    /**
     * Frase do resultado, no gênero do texto do roteiro ("A" = APROVADA, "O" = APROVADO).
     *
     * @param array<string, int> $resumo SIM / NAO / ABSTENCAO
     */
    public static function frase(array $resumo, string $resultado, bool $desempate, string $genero): string
    {
        $g = $genero === 'O' ? 'O' : 'A';
        $sim = (int) ($resumo['SIM'] ?? 0);
        $nao = (int) ($resumo['NAO'] ?? 0);
        $abst = (int) ($resumo['ABSTENCAO'] ?? 0);
        $aprovado = $resultado === 'APROVADO';
        $palavra = ($aprovado ? 'APROVAD' : 'REPROVAD') . $g;

        if ($desempate) {
            return $palavra . ' com voto de desempate da Presidência, após empate de ' . $sim . ' a ' . $nao;
        }

        $principal = $aprovado ? $sim : $nao;
        $contra = $aprovado ? $nao : $sim;
        if ($contra === 0 && $abst === 0) {
            return $palavra . ' por unanimidade';
        }

        $partes = [$aprovado
            ? self::quantidade($principal, 'voto favorável', 'votos favoráveis')
            : self::quantidade($principal, 'voto contrário', 'votos contrários')];
        if ($contra > 0) {
            $partes[] = $aprovado
                ? self::quantidade($contra, 'contrário', 'contrários')
                : self::quantidade($contra, 'favorável', 'favoráveis');
        }
        if ($abst > 0) {
            $partes[] = self::quantidade($abst, 'abstenção', 'abstenções');
        }
        $ultima = array_pop($partes);
        $lista = implode(', ', $partes) . ' e ' . $ultima;

        return $palavra . ($contra > 0 ? ' por maioria' : '') . ', com ' . $lista;
    }

    /**
     * Explicação do resultado em linguagem simples (caixa da votação encerrada).
     *
     * @param array<string, int> $resumo SIM / NAO / ABSTENCAO
     * @param string|null $votoDesempate SIM / NAO quando a Presidência desempatou
     */
    public static function explicacao(array $resumo, string $resultado, ?string $votoDesempate): string
    {
        $sim = (int) ($resumo['SIM'] ?? 0);
        $nao = (int) ($resumo['NAO'] ?? 0);
        $abst = (int) ($resumo['ABSTENCAO'] ?? 0);

        if ($votoDesempate !== null) {
            return 'Sim e Não empataram em ' . $sim . ' a ' . $nao . '. O presidente desempatou votando '
                . ($votoDesempate === 'SIM' ? 'Sim' : 'Não') . '.';
        }

        $aprovado = $resultado === 'APROVADO';
        $contra = $aprovado ? $nao : $sim;
        if ($contra === 0 && $abst === 0) {
            return 'Todos os vereadores que votaram foram ' . ($aprovado ? 'a favor.' : 'contra.');
        }
        if ($contra === 0) {
            return 'Ninguém votou ' . ($aprovado ? 'contra' : 'a favor') . '. As abstenções não entram na conta do resultado.';
        }

        $texto = $aprovado
            ? 'Houve mais votos a favor (' . $sim . ') do que contra (' . $nao . ').'
            : 'Houve mais votos contra (' . $nao . ') do que a favor (' . $sim . ').';

        return $texto . ($abst > 0 ? ' As abstenções não entram nessa conta.' : '');
    }

    /**
     * Resumo de uma votação encerrada para exibir: frase, explicação e placar.
     *
     * @return array{frase: string, explicacao: string, resumo: array<string, int>, resultado: string}|null
     */
    public static function descricao(int $rodadaId): ?array
    {
        $stmt = DB::conn()->prepare('SELECT status, resultado FROM sessao_votacao_rodadas WHERE id = :id');
        $stmt->execute(['id' => $rodadaId]);
        $rodada = $stmt->fetch(PDO::FETCH_ASSOC);
        $resultado = strtoupper((string) ($rodada['resultado'] ?? ''));
        if (!$rodada || ($rodada['status'] ?? '') !== 'ENCERRADA' || !in_array($resultado, ['APROVADO', 'REJEITADO'], true)) {
            return null;
        }

        $resumo = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
        $votoDesempate = SessaoVotoDesempate::voto($rodadaId);
        $frase = self::frase($resumo, $resultado, $votoDesempate !== null, 'O');
        // "APROVADO por ..." -> "Aprovado por ..." (no roteiro fica em maiúsculas, aqui em texto normal)
        $frase = preg_replace_callback('/^(APROVADO|REPROVADO)/', static fn (array $m): string => ucfirst(strtolower($m[1])), $frase);

        return [
            'frase' => (string) $frase,
            'explicacao' => self::explicacao($resumo, $resultado, $votoDesempate),
            'resumo' => $resumo,
            'resultado' => $resultado,
        ];
    }

    private static function quantidade(int $n, string $singular, string $plural): string
    {
        return $n . ' ' . ($n === 1 ? $singular : $plural);
    }

    /**
     * Última votação encerrada do passo de votação mais próximo (o próprio ou o anterior).
     *
     * @param array<string, mixed> $passo
     * @return array<string, mixed>|null
     */
    private static function rodadaDoPasso(array $passo): ?array
    {
        $sessaoId = (int) ($passo['sessao_id'] ?? 0);
        $ordem = (int) ($passo['ordem'] ?? 0);
        if ($sessaoId <= 0) {
            return null;
        }
        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'SELECT id FROM sessao_roteiro
             WHERE sessao_id = :sessao_id AND gera_votacao = 1 AND ordem <= :ordem
             ORDER BY ordem DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['sessao_id' => $sessaoId, 'ordem' => $ordem]);
        $passoVotacao = $stmt->fetchColumn();
        if ($passoVotacao === false) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM sessao_votacao_rodadas
             WHERE roteiro_passo_id = :passo ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['passo' => (int) $passoVotacao]);
        $rodada = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rodada || ($rodada['status'] ?? '') !== 'ENCERRADA') {
            return null;
        }

        return $rodada;
    }
}
