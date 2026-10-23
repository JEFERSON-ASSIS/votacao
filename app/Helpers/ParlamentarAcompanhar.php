<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\SessaoAtaUpload;
use App\Models\RodadaEleitor;
use App\Models\SessaoConducao;
use App\Models\SessaoCrud;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;
use App\Models\SessaoRoteiroAnexo;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;

final class ParlamentarAcompanhar
{
    /** @return array<string, mixed>|null */
    public static function contexto(int $sessaoId, int $userId): ?array
    {
        $sessao = SessaoCrud::find($sessaoId);
        $statusGate = strtoupper((string) ($sessao['status'] ?? ''));
        if (!$sessao || in_array($statusGate, ['RASCUNHO', 'CRIADO'], true)) {
            return null;
        }

        $passoAtual = SessaoRoteiro::passoAtual($sessaoId);
        $anexosPasso = $passoAtual
            ? array_map([SessaoRoteiroAnexo::class, 'publico'], SessaoRoteiroAnexo::allByPasso((int) $passoAtual['id']))
            : [];
        $rodadaAberta = SessaoVotacaoRodada::findAberta($sessaoId);
        $stats = SessaoRoteiro::contagem($sessaoId);
        $pauta = SessaoItem::allBySessao($sessaoId);

        $exibirAta = self::deveExibirAta($sessao, $passoAtual, $rodadaAberta);
        $ataDocumento = $exibirAta ? SessaoCrud::textoAtaParaLeitura($sessao) : '';
        $ataArquivoUrl = $exibirAta ? SessaoCrud::ataAnteriorArquivoUrl($sessao) : null;
        $ataArquivoPath = $exibirAta ? SessaoCrud::ataAnteriorArquivoPath($sessao) : '';
        $ataArquivoPdf = $ataArquivoPath !== '' && SessaoAtaUpload::isPdf($ataArquivoPath);

        $itemVotacao = null;
        $itemRodadaId = 0;
        if ($rodadaAberta && !empty($rodadaAberta['item_id'])) {
            $itemRodadaId = (int) $rodadaAberta['item_id'];
            $itemVotacao = SessaoItem::find($itemRodadaId);
        }

        $meuVoto = null;
        if ($rodadaAberta) {
            $meuVoto = SessaoVoto::votoUsuarioRodada((int) $rodadaAberta['id'], $userId);
        }

        $statusRaw = strtoupper((string) ($sessao['status'] ?? ''));
        $usuarioApto = $rodadaAberta
            ? RodadaEleitor::usuarioApto((int) $rodadaAberta['id'], $userId)
            : false;
        $podeVotar = $rodadaAberta
            && ($rodadaAberta['status'] ?? '') === 'ABERTA'
            && in_array($statusRaw, ['ABERTA', 'EM_VOTACAO'], true)
            && $usuarioApto;

        $modo = self::resolverModo($podeVotar, $meuVoto, $passoAtual, $exibirAta, $rodadaAberta);
        $jaVotou = $meuVoto !== null && $meuVoto !== '';
        $podeRegistrarVoto = $podeVotar && !$jaVotou;

        return [
            'sessao' => $sessao,
            'passoAtual' => $passoAtual,
            'anexosPasso' => $anexosPasso,
            'stats' => $stats,
            'pauta' => $pauta,
            'rodadaAberta' => $rodadaAberta,
            'itemVotacao' => $itemVotacao,
            'itemRodadaId' => $itemRodadaId,
            'exibirAta' => $exibirAta,
            'ataDocumento' => $ataDocumento,
            'ataSessaoRef' => $exibirAta ? SessaoCrud::metadadosSessaoAnterior($sessao) : null,
            'ataArquivoUrl' => $ataArquivoUrl,
            'ataArquivoPdf' => $ataArquivoPdf,
            'textoLeitura' => self::textoLeitura($passoAtual, $rodadaAberta),
            'tituloMomento' => self::tituloMomento($passoAtual, $rodadaAberta),
            'podeVotar' => $podeVotar,
            'usuarioApto' => $usuarioApto,
            'podeRegistrarVoto' => $podeRegistrarVoto,
            'jaVotou' => $jaVotou,
            'meuVoto' => $meuVoto,
            'modo' => $modo,
            'statusRaw' => $statusRaw,
        ];
    }

    /** @return array<string, mixed> */
    public static function estadoJson(int $sessaoId, int $userId, bool $comHtmlVoto = false): array
    {
        $ctx = self::contexto($sessaoId, $userId);
        if (!$ctx) {
            return ['ok' => false];
        }

        $rodada = $ctx['rodadaAberta'];
        $passo = $ctx['passoAtual'];
        $stats = $ctx['stats'];
        $total = (int) ($stats['total'] ?? 0);
        $concluidos = (int) ($stats['concluidos'] ?? 0);
        $item = $ctx['itemVotacao'];

        $estado = [
            'ok' => true,
            'ts' => time(),
            'modo' => $ctx['modo'],
            'status_sessao' => $ctx['statusRaw'],
            'status_label' => sessao_status_label($ctx['statusRaw']),
            'status_chip' => sessao_status_chip_class($ctx['statusRaw']),
            'pode_votar' => $ctx['podeVotar'],
            'pode_registrar_voto' => $ctx['podeRegistrarVoto'],
            'ja_votou' => !empty($ctx['jaVotou']),
            'meu_voto' => $ctx['meuVoto'],
            'meu_voto_label' => $ctx['meuVoto'] ? votacao_rotulo_voto($ctx['meuVoto']) : '',
            'meu_voto_classe' => $ctx['meuVoto'] ? votacao_classe_voto($ctx['meuVoto']) : '',
            'rodada_id' => $rodada ? (int) $rodada['id'] : 0,
            'rodada_titulo' => $rodada ? (string) ($rodada['titulo'] ?? '') : '',
            'item_id' => $ctx['itemRodadaId'],
            'item_titulo' => $item ? (string) ($item['titulo'] ?? '') : '',
            'passo_titulo' => $passo ? (string) ($passo['titulo'] ?? '') : '',
            'titulo_momento' => $ctx['tituloMomento'],
            'texto_leitura' => $ctx['textoLeitura'],
            'anexos' => $ctx['anexosPasso'],
            'exibir_ata' => $ctx['exibirAta'],
            'progresso_pct' => $total > 0 ? (int) round(($concluidos / $total) * 100) : 0,
            'progresso_label' => $concluidos . '/' . $total,
            'sou_condutor' => in_array($ctx['statusRaw'], ['ABERTA', 'EM_VOTACAO'], true)
                && SessaoConducao::usuarioPodeConduzir($sessaoId, $userId),
            'url_conduzir' => route_url('presidencia/conduzir/' . $sessaoId, ['conducao_ok' => 'assumida']),
        ];

        if ($comHtmlVoto && $rodada) {
            $estado['voto_html'] = $ctx['podeRegistrarVoto']
                ? self::renderVotoBoxHtml(
                    $ctx['sessao'],
                    $rodada,
                    $item,
                    $ctx['itemRodadaId'],
                    null
                )
                : '';
        }

        return $estado;
    }

    /**
     * Resumo para alerta global (lista de sessões, menu, etc.).
     *
     * @return array<string, mixed>
     */
    public static function votacaoAtivaResumo(int $userId): array
    {
        $sessoesResumo = [];
        $votacao = null;

        foreach (SessaoCrud::listParaParlamentar() as $sessao) {
            $sid = (int) $sessao['id'];
            $statusRaw = strtoupper((string) ($sessao['status'] ?? ''));
            $sessoesResumo[] = [
                'id' => $sid,
                'status' => $statusRaw,
                'status_label' => sessao_status_label($statusRaw),
                'status_chip' => sessao_status_chip_class($statusRaw),
            ];

            if (!in_array($statusRaw, ['ABERTA', 'EM_VOTACAO'], true)) {
                continue;
            }

            $estado = self::estadoJson($sid, $userId, false);
            if (!($estado['ok'] ?? false) || empty($estado['pode_registrar_voto'])) {
                continue;
            }

            if ($votacao === null || $sid > (int) $votacao['sessao_id']) {
                $votacao = [
                    'sessao_id' => $sid,
                    'sessao_titulo' => (string) ($sessao['titulo'] ?? ''),
                    'rodada_id' => (int) ($estado['rodada_id'] ?? 0),
                    'rodada_titulo' => (string) ($estado['rodada_titulo'] ?? $estado['titulo_momento'] ?? 'Votação'),
                    'modo' => (string) ($estado['modo'] ?? 'votacao'),
                    'pode_registrar_voto' => !empty($estado['pode_registrar_voto']),
                    'ja_votou' => !empty($estado['ja_votou']),
                    'meu_voto' => $estado['meu_voto'] ?? null,
                ];
            }
        }

        $out = [
            'ok' => true,
            'ts' => time(),
            'tem_votacao' => $votacao !== null,
            'sessoes' => $sessoesResumo,
        ];

        if ($votacao !== null) {
            $out['sessao_id'] = $votacao['sessao_id'];
            $out['sessao_titulo'] = $votacao['sessao_titulo'];
            $out['rodada_id'] = $votacao['rodada_id'];
            $out['rodada_titulo'] = $votacao['rodada_titulo'];
            $out['modo'] = $votacao['modo'];
            $out['pode_registrar_voto'] = $votacao['pode_registrar_voto'];
            $out['ja_votou'] = $votacao['ja_votou'];
            $out['meu_voto'] = $votacao['meu_voto'];
            $out['url_acompanhar'] = route_url('parlamentar/sessao/' . $votacao['sessao_id']);
            $out['url_stream'] = route_url('parlamentar/stream/' . $votacao['sessao_id']);
        }

        return $out;
    }

    public static function hashResumoVotacao(array $resumo): string
    {
        $copia = $resumo;
        unset($copia['ts'], $copia['sessoes']);

        return md5(json_encode($copia, JSON_UNESCAPED_UNICODE));
    }

    public static function hashEstado(array $estado): string
    {
        $copia = $estado;
        unset($copia['ts'], $copia['voto_html']);

        return md5(json_encode($copia, JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $sessao */
    /** @param array<string, mixed> $rodada */
    /** @param array<string, mixed>|null $itemVotacao */
    public static function renderVotoBoxHtml(
        array $sessao,
        array $rodada,
        ?array $itemVotacao,
        int $itemRodadaId,
        ?string $meuVoto
    ): string {
        $podeVotar = true;
        $rodadaAberta = $rodada;
        $votoOk = false;
        ob_start();
        require dirname(__DIR__) . '/Views/partials/_parlamentar_voto_box.php';

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    public static function deveExibirAta(array $sessao, ?array $passo, ?array $rodada): bool
    {
        if ($passo && SessaoCrud::isPassoBlocoAta($passo)) {
            return true;
        }
        if ($rodada) {
            $tituloRodada = mb_strtolower((string) ($rodada['titulo'] ?? ''));
            if (str_contains($tituloRodada, 'ata')) {
                return true;
            }
            $passoRodadaId = (int) ($rodada['roteiro_passo_id'] ?? 0);
            if ($passoRodadaId > 0) {
                $passoRodada = SessaoRoteiro::find($passoRodadaId);
                if ($passoRodada && SessaoCrud::isPassoBlocoAta($passoRodada)) {
                    return true;
                }
            }
            if (empty($rodada['item_id']) && $passo && !empty($passo['gera_votacao'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    private static function textoLeitura(?array $passo, ?array $rodada): string
    {
        if ($rodada && ($rodada['descricao'] ?? '') !== '') {
            return ConducaoTexto::acentuarTextoPresidente((string) $rodada['descricao']);
        }
        if (!$passo) {
            return '';
        }
        if (($passo['tipo_passo'] ?? '') === 'INSTRUCAO') {
            $t = trim((string) ($passo['texto_instrucao'] ?? $passo['texto_fala'] ?? ''));

            return ConducaoRichText::plain($t);
        }
        $principal = trim((string) ($passo['texto_fala'] ?? ''));

        return ConducaoRichText::plain($principal);
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    private static function tituloMomento(?array $passo, ?array $rodada): string
    {
        if ($rodada && ($rodada['status'] ?? '') === 'ABERTA') {
            return trim((string) ($rodada['titulo'] ?? 'Votação em andamento'));
        }
        if (!$passo) {
            return 'Sessão em andamento';
        }
        $titulo = trim((string) ($passo['titulo'] ?? ''));
        if ($titulo !== '') {
            return ConducaoTexto::acentuarTextoPresidente($titulo);
        }
        if (!empty($passo['pauta_titulo'])) {
            return (string) $passo['pauta_titulo'];
        }

        return 'Momento da sessão';
    }

    /** @param array<string, mixed>|null $passo */
    /** @param array<string, mixed>|null $rodada */
    private static function resolverModo(
        bool $podeVotar,
        ?string $meuVoto,
        ?array $passo,
        bool $exibirAta,
        ?array $rodada
    ): string {
        if ($podeVotar) {
            return $meuVoto ? 'voto_registrado' : 'votacao';
        }
        if ($passo || $exibirAta || $rodada) {
            return 'leitura';
        }

        return 'aguardando';
    }
}
