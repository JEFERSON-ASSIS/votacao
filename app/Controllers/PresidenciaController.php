<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Helpers\Auditoria;
use App\Helpers\Permissao;
use App\Helpers\SessaoBloqueio;
use App\Models\Sessao;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoPedidoPalavra;
use App\Models\SessaoRoteiro;
use App\Models\SessaoPresenca;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoConducao;
use App\Models\User;

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

    public function iniciar(int $sessaoId): void
    {
        Permissao::exigir('presidencia.iniciar');
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

        $status = strtoupper((string) ($sessao['status'] ?? ''));
        if (in_array($status, ['ABERTA', 'EM_VOTACAO'], true)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId));
            exit;
        }

        if ($status !== 'CRIADO') {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_criado'));
            exit;
        }

        $stats = SessaoRoteiro::contagem($sessaoId);
        if ((int) ($stats['total'] ?? 0) <= 0) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=sem_roteiro'));
            exit;
        }

        $parlamentares = User::parlamentaresParaVotacao();
        $idsPermitidos = array_map(static fn (array $u): int => (int) $u['id'], $parlamentares);
        $presentesPost = $_POST['presentes'] ?? [];
        if (!is_array($presentesPost)) {
            $presentesPost = [];
        }
        $presentesIds = array_values(array_intersect(
            $idsPermitidos,
            array_values(array_unique(array_map('intval', $presentesPost)))
        ));
        if ($presentesIds === []) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=sem_presentes'));
            exit;
        }

        SessaoPresenca::substituirPresentes($sessaoId, $presentesIds);

        $titularId = (int) (Auth::user()['id'] ?? 0);
        SessaoPresenca::set($sessaoId, $titularId, true);
        SessaoConducao::iniciar($sessaoId, $titularId);

        Sessao::updateStatus($sessaoId, 'ABERTA');
        Auditoria::log('iniciar', 'sessao', $sessaoId, (string) ($sessao['titulo'] ?? '') . ' — ' . count($presentesIds) . ' parlamentar(es) presente(s)');
        SessaoEvento::registrar($sessaoId, 'status', 'Sessão iniciada pelo presidente com ' . count($presentesIds) . ' parlamentar(es) presente(s)');

        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_ok=iniciada'));
        exit;
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

        $statusAtual = strtoupper((string) ($sessao['status'] ?? ''));
        if (in_array($statusAtual, ['ABERTA', 'EM_VOTACAO'], true) && SessaoConducao::estado($sessaoId) === null) {
            $titularId = (int) (Auth::user()['id'] ?? 0);
            SessaoPresenca::set($sessaoId, $titularId, true);
            SessaoConducao::iniciar($sessaoId, $titularId);
        }

        // Presidência em exercício com outro parlamentar: titular acompanha pela visão parlamentar.
        $authIdConducao = (int) (Auth::user()['id'] ?? 0);
        if (in_array($statusAtual, ['ABERTA', 'EM_VOTACAO'], true)
            && SessaoConducao::estado($sessaoId) !== null
            && !SessaoConducao::usuarioPodeConduzir($sessaoId, $authIdConducao)) {
            header('Location: ' . base_url('?r=parlamentar/sessao/' . $sessaoId));
            exit;
        }

        if (strtoupper((string) ($sessao['status'] ?? '')) === 'ENCERRADA') {
            if (Permissao::usuarioAtualPode('sessao.form')) {
                header('Location: ' . base_url('?r=sessao/ata/' . $sessaoId . '&conducao_encerrada=1'));
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
            'parlamentares' => User::parlamentaresParaVotacao(),
            'presentesIds' => SessaoPresenca::idsPresentes($sessaoId),
            'conducaoEstado' => SessaoConducao::estado($sessaoId),
            'destaqueAtual' => self::destaqueAtualPayload($sessaoId),
            'pedidosPalavra' => SessaoPedidoPalavra::pendentesPayload($sessaoId),
        ]);
    }

    public function destacarParlamentar(int $sessaoId): void
    {
        Permissao::exigir('presidencia.conduzir');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        if (!$this->sessaoEstaIniciada($sessaoId)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        $userId = (int) ($_POST['parlamentar_id'] ?? 0);
        $tempoSeg = sessao_tempo_normalizar($_POST['tempo_seg'] ?? 0);
        try {
            SessaoConducao::setDestaque($sessaoId, $userId, $tempoSeg);
            // Quem estava na fila de pedidos e foi para o telão pela lista: pedido atendido.
            $pendente = SessaoPedidoPalavra::pendenteDoUsuario($sessaoId, $userId);
            if ($pendente) {
                SessaoPedidoPalavra::responder((int) $pendente['id'], $sessaoId, SessaoPedidoPalavra::CONCEDIDO, (int) (Auth::user()['id'] ?? 0));
            }
            $user = User::find($userId);
            $nome = (string) ($user['nome'] ?? 'Parlamentar');
            $tempoTxt = $tempoSeg > 0 ? ' (' . (sessao_tempo_opcoes()[$tempoSeg] ?? '') . ')' : '';
            Auditoria::log('destaque', 'sessao_conducao', $sessaoId, 'Telão: destaque de ' . $nome . $tempoTxt);
            SessaoEvento::registrar($sessaoId, 'destaque', 'Parlamentar em destaque no telão: ' . $nome . $tempoTxt);
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&destaque_ok=1'));
        } catch (\RuntimeException $e) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&destaque_erro=' . rawurlencode($e->getMessage())));
        }
        exit;
    }

    public function limparDestaque(int $sessaoId): void
    {
        Permissao::exigir('presidencia.conduzir');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        if (!$this->sessaoEstaIniciada($sessaoId)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        SessaoConducao::clearDestaque($sessaoId);
        Auditoria::log('destaque_limpar', 'sessao_conducao', $sessaoId, 'Destaque do telão removido');
        SessaoEvento::registrar($sessaoId, 'destaque', 'Destaque do telão removido');
        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&destaque_limpo=1'));
        exit;
    }

    /** JSON: fila de vereadores pedindo a palavra (a tela do presidente consulta a cada poucos segundos). */
    public function pedidosPalavra(int $sessaoId): void
    {
        Permissao::exigir('presidencia.pedidosPalavra');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => true,
            'pedidos' => SessaoPedidoPalavra::pendentesPayload($sessaoId),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Concede a palavra: marca o pedido e coloca o vereador no telão com o tempo de fala. */
    public function concederPalavra(int $sessaoId): void
    {
        Permissao::exigir('presidencia.concederPalavra');
        $this->responderPedidoPalavra($sessaoId, SessaoPedidoPalavra::CONCEDIDO);
    }

    public function recusarPalavra(int $sessaoId): void
    {
        Permissao::exigir('presidencia.recusarPalavra');
        $this->responderPedidoPalavra($sessaoId, SessaoPedidoPalavra::RECUSADO);
    }

    private function responderPedidoPalavra(int $sessaoId, string $status): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        $voltar = '?r=presidencia/conduzir/' . $sessaoId;
        if (!$this->sessaoEstaIniciada($sessaoId)) {
            header('Location: ' . base_url($voltar . '&sessao_erro=nao_iniciada'));
            exit;
        }

        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        $pedido = SessaoPedidoPalavra::find($pedidoId);
        if (!$pedido || (int) $pedido['sessao_id'] !== $sessaoId || $pedido['status'] !== SessaoPedidoPalavra::PENDENTE) {
            header('Location: ' . base_url($voltar . '&destaque_erro=' . rawurlencode('Esse pedido de palavra não está mais pendente.')));
            exit;
        }
        $userId = (int) $pedido['user_id'];
        $nome = (string) (User::find($userId)['nome'] ?? 'Parlamentar');
        $authId = (int) (Auth::user()['id'] ?? 0);

        if ($status === SessaoPedidoPalavra::CONCEDIDO) {
            $tempoSeg = sessao_tempo_normalizar($_POST['tempo_seg'] ?? 300);
            try {
                SessaoConducao::setDestaque($sessaoId, $userId, $tempoSeg);
            } catch (\RuntimeException $e) {
                header('Location: ' . base_url($voltar . '&destaque_erro=' . rawurlencode($e->getMessage())));
                exit;
            }
            SessaoPedidoPalavra::responder($pedidoId, $sessaoId, $status, $authId);
            $tempoTxt = $tempoSeg > 0 ? ' (' . (sessao_tempo_opcoes()[$tempoSeg] ?? '') . ')' : '';
            Auditoria::log('palavra_conceder', 'sessao_conducao', $sessaoId, 'Palavra concedida a ' . $nome . $tempoTxt);
            SessaoEvento::registrar($sessaoId, 'palavra', 'Palavra concedida a ' . $nome . $tempoTxt);
        } else {
            SessaoPedidoPalavra::responder($pedidoId, $sessaoId, $status, $authId);
            Auditoria::log('palavra_recusar', 'sessao_conducao', $sessaoId, 'Pedido de palavra de ' . $nome . ' não concedido');
            SessaoEvento::registrar($sessaoId, 'palavra', 'Pedido de palavra de ' . $nome . ' não concedido');
        }

        header('Location: ' . base_url($voltar));
        exit;
    }

    public function transferirConducao(int $sessaoId): void
    {
        Permissao::exigir('presidencia.transferirConducao');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        $autorId = (int) (Auth::user()['id'] ?? 0);
        $novoCondutorId = (int) ($_POST['parlamentar_id'] ?? 0);
        try {
            SessaoConducao::transferir($sessaoId, $novoCondutorId, $autorId);
            $novo = User::find($novoCondutorId);
            $nome = (string) ($novo['nome'] ?? 'Parlamentar');
            Auditoria::log('transferir', 'sessao_conducao', $sessaoId, 'Condução transferida para ' . $nome);
            SessaoEvento::registrar($sessaoId, 'conducao', 'Condução transferida para ' . $nome);
            header('Location: ' . base_url('?r=parlamentar/sessao/' . $sessaoId . '&conducao_ok=transferida'));
        } catch (\RuntimeException $e) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&conducao_erro=' . rawurlencode($e->getMessage())));
        }
        exit;
    }

    public function devolverConducao(int $sessaoId): void
    {
        Permissao::exigir('presidencia.devolverConducao');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        $autorId = (int) (Auth::user()['id'] ?? 0);
        try {
            $estado = SessaoConducao::estado($sessaoId);
            SessaoConducao::devolver($sessaoId, $autorId);
            $nome = (string) ($estado['titular_nome'] ?? 'Presidente titular');
            Auditoria::log('devolver', 'sessao_conducao', $sessaoId, 'Condução devolvida para ' . $nome);
            SessaoEvento::registrar($sessaoId, 'conducao', 'Condução devolvida para ' . $nome);
            header('Location: ' . base_url('?r=parlamentar/sessao/' . $sessaoId . '&conducao_ok=devolvida'));
        } catch (\RuntimeException $e) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&conducao_erro=' . rawurlencode($e->getMessage())));
        }
        exit;
    }

    public function retomarConducao(int $sessaoId): void
    {
        Permissao::exigir('presidencia.retomarConducao');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        $autorId = (int) (Auth::user()['id'] ?? 0);
        try {
            $estado = SessaoConducao::estado($sessaoId);
            SessaoConducao::retomar($sessaoId, $autorId);
            $nome = (string) ($estado['titular_nome'] ?? 'Presidente titular');
            Auditoria::log('retomar', 'sessao_conducao', $sessaoId, 'Presidência retomada por ' . $nome);
            SessaoEvento::registrar($sessaoId, 'conducao', 'Presidência retomada pelo titular ' . $nome);
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&conducao_ok=retomada'));
        } catch (\RuntimeException $e) {
            header('Location: ' . base_url('?r=parlamentar/sessao/' . $sessaoId . '&conducao_erro=' . rawurlencode($e->getMessage())));
        }
        exit;
    }

    /** JSON: quem conduz agora (a tela de condução troca sozinha quando perde a presidência). */
    public function estadoConducao(int $sessaoId): void
    {
        Permissao::exigir('presidencia.estadoConducao');
        header('Content-Type: application/json; charset=utf-8');
        $authId = (int) (Auth::user()['id'] ?? 0);
        echo json_encode([
            'ok' => true,
            'sou_condutor' => SessaoConducao::usuarioPodeConduzir($sessaoId, $authId),
            'url_parlamentar' => base_url('?r=parlamentar/sessao/' . $sessaoId . '&conducao_ok=retomada_titular'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Retira de pauta a matéria do passo atual (só quem conduz). */
    public function retirarPauta(int $sessaoId): void
    {
        Permissao::exigir('presidencia.retirarPauta');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            responder_erro(400, 'Requisição inválida', 'Recarregue a página e tente novamente.');
            return;
        }
        if (!$this->sessaoEstaIniciada($sessaoId)) {
            redirect_route('presidencia/conduzir/' . $sessaoId, ['sessao_erro' => 'nao_iniciada']);
        }
        $itemId = (int) ($_POST['pauta_item_id'] ?? 0);
        try {
            \App\Services\RetiradaPauta::retirar($sessaoId, $itemId, (string) ($_POST['motivo'] ?? ''), (int) (Auth::user()['id'] ?? 0));
        } catch (\DomainException $e) {
            redirect_route('presidencia/conduzir/' . $sessaoId, ['pauta_erro' => $e->getMessage()]);
        }
        redirect_route('presidencia/conduzir/' . $sessaoId, ['retirada' => $itemId]);
    }

    /** Devolve à pauta uma matéria retirada por engano. */
    public function desfazerRetirada(int $sessaoId): void
    {
        Permissao::exigir('presidencia.desfazerRetirada');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            responder_erro(400, 'Requisição inválida', 'Recarregue a página e tente novamente.');
            return;
        }
        try {
            \App\Services\RetiradaPauta::desfazer($sessaoId, (int) ($_POST['pauta_item_id'] ?? 0), (int) (Auth::user()['id'] ?? 0));
        } catch (\DomainException $e) {
            redirect_route('presidencia/conduzir/' . $sessaoId, ['pauta_erro' => $e->getMessage()]);
        }
        redirect_route('presidencia/conduzir/' . $sessaoId, ['retirada_desfeita' => 1]);
    }

    public function avancar(int $sessaoId): void
    {
        Permissao::exigir('presidencia.avancar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        if (!$this->sessaoEstaIniciada($sessaoId)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
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

        if (!$this->sessaoEstaIniciada($sessaoId)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        // Voltar com votação aberta deixaria o telão "Em votação" num passo que já passou.
        if (SessaoVotacaoRodada::findAberta($sessaoId)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=rodada_aberta_voltar'));
            exit;
        }

        $roteiro = SessaoRoteiro::allBySessao($sessaoId);
        $idxAtual = null;
        foreach ($roteiro as $i => $p) {
            if (!SessaoRoteiro::isAtivo($p)) {
                continue;
            }
            if (!in_array((string) ($p['status_execucao'] ?? ''), ['CONCLUIDO', 'RETIRADO'], true)) {
                $idxAtual = $i;
                break;
            }
        }
        if ($idxAtual !== null && $idxAtual > 0) {
            $anterior = null;
            for ($k = $idxAtual - 1; $k >= 0; $k--) {
                if (SessaoRoteiro::isAtivo($roteiro[$k]) && ($roteiro[$k]['status_execucao'] ?? '') !== 'RETIRADO') {
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

        $status = strtoupper((string) ($sessao['status'] ?? ''));
        if (!in_array($status, ['ABERTA', 'EM_VOTACAO'], true)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        SessaoConducao::clearDestaque($sessaoId);
        SessaoPedidoPalavra::cancelarPendentesDaSessao($sessaoId);
        Sessao::updateStatus($sessaoId, 'ENCERRADA');
        Auditoria::log('encerrar', 'sessao', $sessaoId, (string) ($sessao['titulo'] ?? ''));
        SessaoEvento::registrar($sessaoId, 'status', 'Sessão encerrada');

        if (Permissao::usuarioAtualPode('sessao.form')) {
            header('Location: ' . base_url('?r=sessao/ata/' . $sessaoId . '&sessao_ok=encerrada'));
        } elseif (Permissao::usuarioAtualPode('sessao.index')) {
            header('Location: ' . base_url('?r=sessao/index&conducao_encerrada=1'));
        } else {
            header('Location: ' . base_url('?r=dashboard/index&conducao_encerrada=1'));
        }
        exit;
    }

    /** @return array<string, mixed>|null */
    private function destaqueAtualPayload(int $sessaoId): ?array
    {
        $estado = SessaoConducao::estado($sessaoId);
        $userId = (int) ($estado['destaque_user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        return [
            'id' => $userId,
            'nome' => (string) ($user['nome'] ?? ''),
            'partido' => trim((string) ($user['partido'] ?? '')),
            'gabinete' => trim((string) ($user['gabinete'] ?? '')),
            'foto_url' => user_foto_url($user),
            'iniciais' => user_iniciais((string) ($user['nome'] ?? '')),
            'tempo' => sessao_tempo_payload(
                $estado['destaque_tempo_seg'] ?? 0,
                $estado['destaque_em'] ?? null
            ),
        ];
    }

    private function sessaoEstaIniciada(int $sessaoId): bool
    {
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            return false;
        }
        $status = strtoupper((string) ($sessao['status'] ?? ''));

        return in_array($status, ['ABERTA', 'EM_VOTACAO'], true);
    }
}
