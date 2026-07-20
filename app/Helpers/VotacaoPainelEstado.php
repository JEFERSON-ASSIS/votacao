<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;
use App\Models\User;

final class VotacaoPainelEstado
{
    /** @return array<string, mixed> */
    public static function jsonRodada(int $rodadaId): array
    {
        $rodada = SessaoVotacaoRodada::find($rodadaId);
        if (!$rodada) {
            return ['ok' => false];
        }

        $aberta = ($rodada['status'] ?? '') === 'ABERTA';
        $resumoTotal = SessaoVoto::resumoPorRodada($rodadaId);
        $resumoParl = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
        $empate = self::empateParlamentares($resumoParl);

        $votosPorUser = [];
        foreach (SessaoVoto::allByRodada($rodadaId) as $v) {
            $votosPorUser[(int) $v['user_id']] = $v;
        }

        $parlamentares = [];
        foreach (User::parlamentaresParaVotacao() as $u) {
            $uid = (int) $u['id'];
            $vReg = $votosPorUser[$uid] ?? null;
            $voto = $vReg ? (string) ($vReg['voto'] ?? '') : '';
            $parlamentares[] = [
                'id' => $uid,
                'nome' => (string) ($u['nome'] ?? ''),
                'partido' => trim((string) ($u['partido'] ?? '')),
                'foto_url' => user_foto_url($u),
                'iniciais' => user_iniciais((string) ($u['nome'] ?? '')),
                'voto' => $voto !== '' ? $voto : null,
                'voto_label' => $voto !== '' ? votacao_rotulo_voto($voto) : '',
                'voto_classe' => $voto !== '' ? votacao_classe_voto($voto) : '',
                'aguardando' => $voto === '',
            ];
        }

        $auth = Auth::user();
        $authId = (int) ($auth['id'] ?? 0);
        $authRole = (string) ($auth['role'] ?? '');
        $ehPresidente = UserPerfil::matches($authRole, UserPerfil::PRESIDENTE);
        $presidenteVoto = $authId > 0 ? SessaoVoto::votoUsuarioRodada($rodadaId, $authId) : null;
        $presidentePodeVotar = $aberta && $empate && $ehPresidente;
        $presidenteVotouDesempate = self::presidenteVotouDesempate($rodadaId);
        $encerramento = self::validarEncerramento($rodadaId);

        return [
            'ok' => true,
            'ts' => time(),
            'rodada_id' => $rodadaId,
            'rodada_status' => (string) ($rodada['status'] ?? ''),
            'aberta' => $aberta,
            'resultado' => (string) ($rodada['resultado'] ?? ''),
            'resumo' => $resumoTotal,
            'resumo_parlamentares' => $resumoParl,
            'empate' => $empate,
            'parlamentares' => $parlamentares,
            'presidente_pode_votar' => $presidentePodeVotar,
            'presidente_voto' => $presidenteVoto,
            'presidente_voto_label' => $presidenteVoto ? votacao_rotulo_voto($presidenteVoto) : '',
            'presidente_voto_classe' => $presidenteVoto ? votacao_classe_voto($presidenteVoto) : '',
            'presidente_nome' => (string) ($auth['nome'] ?? 'Presidente'),
            'presidente_votou_desempate' => $presidenteVotouDesempate,
            'pode_encerrar' => $encerramento['permitido'],
            'encerrar_mensagem' => $encerramento['mensagem'],
        ];
    }

    /** @param array<string, int> $resumoParl */
    public static function empateParlamentares(array $resumoParl): bool
    {
        $sim = (int) ($resumoParl['SIM'] ?? 0);
        $nao = (int) ($resumoParl['NAO'] ?? 0);

        return $sim === $nao && ($sim + $nao) > 0;
    }

    public static function presidenteVotouDesempate(int $rodadaId): bool
    {
        $stmt = \App\Core\DB::conn()->prepare(
            "SELECT 1 FROM sessao_votos sv
             INNER JOIN users u ON u.id = sv.user_id
             WHERE sv.rodada_id = :rid
               AND UPPER(TRIM(u.role)) = :perfil
               AND sv.voto IN ('SIM', 'NAO')
             LIMIT 1"
        );
        $stmt->execute([
            'rid' => $rodadaId,
            'perfil' => UserPerfil::PRESIDENTE,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{permitido: bool, mensagem: string, empate: bool, presidente_votou: bool}
     */
    public static function validarEncerramento(int $rodadaId): array
    {
        $resumoParl = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
        $empate = self::empateParlamentares($resumoParl);
        $presidenteVotou = self::presidenteVotouDesempate($rodadaId);

        if (!$empate) {
            return [
                'permitido' => true,
                'mensagem' => '',
                'empate' => false,
                'presidente_votou' => $presidenteVotou,
            ];
        }

        if ($presidenteVotou) {
            return [
                'permitido' => true,
                'mensagem' => '',
                'empate' => true,
                'presidente_votou' => true,
            ];
        }

        return [
            'permitido' => false,
            'mensagem' => 'Empate entre Sim e Não: o presidente precisa registrar o voto de desempate antes de encerrar.',
            'empate' => true,
            'presidente_votou' => false,
        ];
    }

    public static function hashEstado(array $estado): string
    {
        $copia = $estado;
        unset($copia['ts']);

        return md5(json_encode($copia, JSON_UNESCAPED_UNICODE));
    }
}
