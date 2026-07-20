<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\SessaoCrud;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;
use App\Models\User;

/** Estado da sessão para visão pública / telão (sem login, sem dados operacionais). */
final class PublicoSessaoEstado
{
    /** Passos de condução que não devem aparecer no telão público. */
    private const PASSOS_PRIVADOS = [
        'abertura da sessão',
        'ata da sessão anterior',
        'encerramento da sessão',
    ];

    /** @return array<string, mixed> */
    public static function estadoJson(int $sessaoId): array
    {
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao || strtoupper((string) ($sessao['status'] ?? '')) === 'RASCUNHO') {
            return ['ok' => false];
        }

        $statusRaw = strtoupper((string) ($sessao['status'] ?? ''));
        $passoAtual = SessaoRoteiro::passoAtual($sessaoId);
        $rodadaAberta = SessaoVotacaoRodada::findAberta($sessaoId);
        $stats = SessaoRoteiro::contagem($sessaoId);

        $rodadaAoVivo = $rodadaAberta ? self::rodadaPublica($rodadaAberta, true) : null;
        $historico = [];
        foreach (SessaoVotacaoRodada::historico($sessaoId, 50) as $rodada) {
            if (($rodada['status'] ?? '') !== 'ENCERRADA') {
                continue;
            }
            $historico[] = self::rodadaPublica($rodada, false);
        }
        $historico = array_reverse($historico);

        return [
            'ok' => true,
            'ts' => time(),
            'sessao_id' => $sessaoId,
            'sessao_titulo' => (string) ($sessao['titulo'] ?? ''),
            'sessao_numero' => (int) ($sessao['numero'] ?? 0),
            'sessao_ano' => (int) ($sessao['ano'] ?? 0),
            'sessao_tipo' => (string) ($sessao['tipo'] ?? ''),
            'status_sessao' => $statusRaw,
            'status_label' => sessao_status_label($statusRaw),
            'status_chip' => sessao_status_chip_class($statusRaw),
            'momento_titulo' => self::tituloMomento($passoAtual, $rodadaAberta),
            'texto_momento' => self::textoMomento($passoAtual, $rodadaAberta),
            'progresso_label' => ((int) ($stats['concluidos'] ?? 0)) . '/' . ((int) ($stats['total'] ?? 0)),
            'progresso_pct' => (int) ($stats['total'] ?? 0) > 0
                ? (int) round(((int) ($stats['concluidos'] ?? 0) / (int) $stats['total']) * 100)
                : 0,
            'em_votacao' => $rodadaAoVivo !== null,
            'rodada_aberta' => $rodadaAoVivo,
            'historico' => $historico,
        ];
    }

    /** @param array<string, mixed> $rodada */
    /** @return array<string, mixed> */
    public static function rodadaPublica(array $rodada, bool $aoVivo): array
    {
        $rodadaId = (int) ($rodada['id'] ?? 0);
        $itemId = (int) ($rodada['item_id'] ?? 0);
        $item = $itemId > 0 ? SessaoItem::find($itemId) : null;
        $resumo = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
        $status = (string) ($rodada['status'] ?? '');
        $resultado = (string) ($rodada['resultado'] ?? '');

        return [
            'id' => $rodadaId,
            'titulo' => (string) ($rodada['titulo'] ?? 'Votação'),
            'descricao' => trim((string) ($rodada['descricao'] ?? '')),
            'tipo_label' => self::tipoLabelRodada($rodada, $item),
            'item_id' => $itemId,
            'item_titulo' => $item ? (string) ($item['titulo'] ?? '') : '',
            'status' => $status,
            'aberta' => $status === 'ABERTA',
            'ao_vivo' => $aoVivo,
            'resultado' => $resultado,
            'resultado_label' => $status === 'ENCERRADA' ? votacao_rotulo_resultado($resultado) : '',
            'resumo' => [
                'SIM' => (int) ($resumo['SIM'] ?? 0),
                'NAO' => (int) ($resumo['NAO'] ?? 0),
                'ABSTENCAO' => (int) ($resumo['ABSTENCAO'] ?? 0),
                'AUSENTE' => (int) ($resumo['AUSENTE'] ?? 0),
            ],
            'total_votos' => (int) ($resumo['SIM'] ?? 0) + (int) ($resumo['NAO'] ?? 0) + (int) ($resumo['ABSTENCAO'] ?? 0),
            'parlamentares' => self::parlamentaresRodada($rodadaId),
            'encerrada_em' => (string) ($rodada['encerrada_em'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $rodada */
    /** @param array<string, mixed>|null $item */
    public static function tipoLabelRodada(array $rodada, ?array $item): string
    {
        $titulo = mb_strtolower((string) ($rodada['titulo'] ?? ''));
        $desc = mb_strtolower((string) ($rodada['descricao'] ?? ''));

        if (str_contains($titulo, 'ata') || str_contains($desc, 'ata')) {
            return 'Votação — Ata da sessão anterior';
        }

        if ($item) {
            return 'Votação — ' . (string) ($item['titulo'] ?? 'Matéria da pauta');
        }

        if (empty($rodada['item_id'])) {
            return 'Votação nominal';
        }

        return 'Votação — ' . (string) ($rodada['titulo'] ?? 'Matéria');
    }

    /** @return list<array<string, mixed>> */
    public static function parlamentaresRodada(int $rodadaId): array
    {
        $votosPorUser = [];
        foreach (SessaoVoto::allByRodada($rodadaId) as $v) {
            if (!UserPerfil::ehParlamentar((string) ($v['role'] ?? ''))) {
                continue;
            }
            $votosPorUser[(int) $v['user_id']] = $v;
        }

        $lista = [];
        foreach (User::parlamentaresParaVotacao() as $u) {
            $uid = (int) $u['id'];
            $reg = $votosPorUser[$uid] ?? null;
            $voto = $reg ? (string) ($reg['voto'] ?? '') : '';
            $lista[] = [
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

        usort($lista, static function (array $a, array $b): int {
            if ($a['aguardando'] !== $b['aguardando']) {
                return $a['aguardando'] ? 1 : -1;
            }

            return strcasecmp($a['nome'], $b['nome']);
        });

        return $lista;
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    public static function tituloMomento(?array $passo, ?array $rodada): string
    {
        if ($rodada && ($rodada['titulo'] ?? '') !== '') {
            return (string) $rodada['titulo'];
        }
        if (self::passoEhPrivado($passo)) {
            return 'Sessão plenária';
        }
        if ($passo && ($passo['titulo'] ?? '') !== '') {
            return (string) $passo['titulo'];
        }

        return 'Sessão plenária';
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    public static function textoMomento(?array $passo, ?array $rodada): string
    {
        if ($rodada && trim((string) ($rodada['descricao'] ?? '')) !== '') {
            return ConducaoTexto::acentuarTextoPresidente((string) $rodada['descricao']);
        }
        if (self::passoEhPrivado($passo)) {
            return '';
        }
        if (!$passo) {
            return '';
        }
        if (($passo['tipo_passo'] ?? '') === 'INSTRUCAO') {
            return ConducaoTexto::acentuarTextoPresidente(
                trim((string) ($passo['texto_instrucao'] ?? $passo['texto_fala'] ?? ''))
            );
        }

        return ConducaoTexto::acentuarTextoPresidente(trim((string) ($passo['texto_fala'] ?? '')));
    }

    /** @param array<string, mixed>|null $passo */
    private static function passoEhPrivado(?array $passo): bool
    {
        if (!$passo) {
            return false;
        }

        $titulo = mb_strtolower(trim((string) ($passo['titulo'] ?? '')));
        return in_array($titulo, self::PASSOS_PRIVADOS, true);
    }

    public static function hashEstado(array $estado): string
    {
        $copia = $estado;
        unset($copia['ts']);

        return md5(json_encode($copia, JSON_UNESCAPED_UNICODE));
    }
}
