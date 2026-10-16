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

        $status = strtoupper((string) ($sessao['status'] ?? ''));
        if (!in_array($status, ['ABERTA', 'EM_VOTACAO'], true)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        SessaoConducao::clearDestaque($sessaoId);
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
