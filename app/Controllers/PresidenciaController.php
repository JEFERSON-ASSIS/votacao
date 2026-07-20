<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\Auditoria;
use App\Helpers\Permissao;
use App\Helpers\SessaoBloqueio;
use App\Models\Sessao;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoRoteiro;
use App\Models\SessaoVotacaoRodada;

final class PresidenciaController
{
    public function index(): void
    {
        Permissao::exigir('presidencia.index');
        $sessao = Sessao::atual();
        $sessoesConducao = [];
        foreach (SessaoCrud::all() as $row) {
            $sid = (int) $row['id'];
            $stats = SessaoRoteiro::contagem($sid);
            if (($stats['total'] ?? 0) <= 0) {
                continue;
            }
            $sessoesConducao[] = [
                'sessao' => $row,
                'stats' => $stats,
            ];
        }

        View::render('presidencia/index', [
            'sessao' => $sessao,
            'pauta' => $sessao ? Sessao::pauta((int) $sessao['id']) : [],
            'roteiroStats' => $sessao ? SessaoRoteiro::contagem((int) $sessao['id']) : ['total' => 0, 'concluidos' => 0],
            'sessoesConducao' => $sessoesConducao,
        ]);
    }

    public function conduzir(int $sessaoId): void
    {
        Permissao::exigir('presidencia.conduzir');
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        if (strtoupper((string) ($sessao['status'] ?? '')) === 'ENCERRADA') {
            if (Permissao::usuarioAtualPode('sessao.form')) {
                header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&conducao_encerrada=1#roteiro'));
            } elseif (Permissao::usuarioAtualPode('sessao.index')) {
                header('Location: ' . base_url('?r=sessao/index&conducao_encerrada=1'));
            } else {
                header('Location: ' . base_url('?r=dashboard/index&conducao_encerrada=1'));
            }
            exit;
        }

        $roteiro = SessaoRoteiro::allBySessao($sessaoId);
        $roteiroAtivos = array_values(array_filter(
            $roteiro,
            static fn (array $p): bool => SessaoRoteiro::isAtivo($p)
        ));
        $passoAtual = SessaoRoteiro::passoAtual($sessaoId);
        if ($passoAtual && ($passoAtual['status_execucao'] ?? '') === 'PENDENTE') {
            SessaoRoteiro::marcarStatus((int) $passoAtual['id'], 'EM_ANDAMENTO');
            $passoAtual['status_execucao'] = 'EM_ANDAMENTO';
        }

        $indiceAtual = 0;
        if ($passoAtual) {
            foreach ($roteiroAtivos as $i => $p) {
                if ((int) $p['id'] === (int) $passoAtual['id']) {
                    $indiceAtual = $i;
                    break;
                }
            }
        }

        $rodadaPasso = null;
        if ($passoAtual && !empty($passoAtual['gera_votacao'])) {
            $rodadaPasso = SessaoVotacaoRodada::findByPasso((int) $passoAtual['id']);
            if ($rodadaPasso && ($rodadaPasso['status'] ?? '') !== 'ABERTA') {
                $rodadaAberta = SessaoVotacaoRodada::findAberta($sessaoId);
                if ($rodadaAberta) {
                    $rodadaPasso = $rodadaAberta;
                }
            }
        }

        $exibirAta = $passoAtual && SessaoCrud::isPassoBlocoAta($passoAtual);
        $ataDocumento = $exibirAta ? SessaoCrud::textoAtaParaLeitura($sessao) : '';
        $ataSessaoRef = $exibirAta ? SessaoCrud::metadadosSessaoAnterior($sessao) : null;
        $ataArquivoUrl = $exibirAta ? SessaoCrud::ataAnteriorArquivoUrl($sessao) : null;
        $ataArquivoPath = $exibirAta ? SessaoCrud::ataAnteriorArquivoPath($sessao) : '';
        $ataArquivoPdf = $ataArquivoPath !== '' && \App\Helpers\SessaoAtaUpload::isPdf($ataArquivoPath);

        View::render('presidencia/conduzir', [
            'sessao' => $sessao,
            'roteiro' => $roteiro,
            'roteiroAtivos' => $roteiroAtivos,
            'passoAtual' => $passoAtual,
            'indiceAtual' => $indiceAtual,
            'stats' => SessaoRoteiro::contagem($sessaoId),
            'rodadaPasso' => $rodadaPasso,
            'rodadaAberta' => SessaoVotacaoRodada::findAberta($sessaoId),
            'exibirAta' => $exibirAta,
            'ataDocumento' => $ataDocumento,
            'ataSessaoRef' => $ataSessaoRef,
            'ataArquivoUrl' => $ataArquivoUrl,
            'ataArquivoPdf' => $ataArquivoPdf,
        ]);
    }

    public function avancar(int $sessaoId): void
    {
        Permissao::exigir('presidencia.avancar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $passo = SessaoRoteiro::passoAtual($sessaoId);
        if ($passo) {
            SessaoRoteiro::marcarStatus((int) $passo['id'], 'CONCLUIDO');
            SessaoEvento::registrar(
                $sessaoId,
                'roteiro',
                'Passo concluído: ' . (string) ($passo['titulo'] ?? 'Etapa')
            );
        }

        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId));
        exit;
    }

    public function voltar(int $sessaoId): void
    {
        Permissao::exigir('presidencia.voltar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $roteiro = SessaoRoteiro::allBySessao($sessaoId);
        $idxAtual = null;
        foreach ($roteiro as $i => $p) {
            if (!SessaoRoteiro::isAtivo($p)) {
                continue;
            }
            if (($p['status_execucao'] ?? '') !== 'CONCLUIDO') {
                $idxAtual = $i;
                break;
            }
        }
        if ($idxAtual !== null && $idxAtual > 0) {
            $anterior = null;
            for ($k = $idxAtual - 1; $k >= 0; $k--) {
                if (SessaoRoteiro::isAtivo($roteiro[$k])) {
                    $anterior = $roteiro[$k];
                    break;
                }
            }
            if ($anterior) {
                SessaoRoteiro::marcarStatus((int) $anterior['id'], 'EM_ANDAMENTO');
                for ($j = $idxAtual; $j < count($roteiro); $j++) {
                    SessaoRoteiro::marcarStatus((int) $roteiro[$j]['id'], 'PENDENTE');
                }
            }
        }

        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId));
        exit;
    }

    public function encerrarSessao(int $sessaoId): void
    {
        Permissao::exigir('presidencia.encerrarSessao');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $rodadaAberta = SessaoVotacaoRodada::findAberta($sessaoId);
        if ($rodadaAberta) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=rodada_aberta'));
            exit;
        }

        Sessao::updateStatus($sessaoId, 'ENCERRADA');
        Auditoria::log('encerrar', 'sessao', $sessaoId, (string) ($sessao['titulo'] ?? ''));
        SessaoEvento::registrar($sessaoId, 'status', 'Sessão encerrada');

        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_ok=encerrada'));
        exit;
    }
}
