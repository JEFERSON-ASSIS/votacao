<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\Auditoria;
use App\Helpers\Permissao;
use App\Helpers\SessaoBloqueio;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoRoteiro;
use App\Services\RoteiroGerador;

final class RoteiroController
{
    public function gerar(int $sessaoId): void
    {
        Permissao::exigir('roteiro.gerar');
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

        SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=sessao/form/' . $sessaoId . '#roteiro'));

        $incluirTribuna = !empty($_POST['incluir_tribuna']);
        try {
            $count = RoteiroGerador::gerar($sessaoId, $incluirTribuna);
        } catch (\Throwable $e) {
            app_log('[RoteiroController::gerar] erro sessaoId=' . $sessaoId . ' msg=' . $e->getMessage());
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&roteiro_erro=' . rawurlencode('Não foi possível gerar o roteiro. Execute scripts/migrate_conducao.php.') . '#roteiro'));
            exit;
        }
        app_log('[RoteiroController::gerar] sessaoId=' . $sessaoId . ' passos=' . $count);
        Auditoria::log('gerar', 'roteiro', $sessaoId, (string) $count . ' passos');
        SessaoEvento::registrar($sessaoId, 'roteiro', 'Roteiro gerado (' . $count . ' passos)');

        if ($count > 0) {
            header('Location: ' . base_url('?r=roteiro/montar/' . $sessaoId . '&roteiro_ok=' . $count));
            exit;
        }
        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&roteiro_vazio=1#roteiro'));
        exit;
    }

    public function salvar(int $sessaoId, int $id): void
    {
        Permissao::exigir('roteiro.salvar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $passo = SessaoRoteiro::find($id);
        if (!$passo || (int) $passo['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Passo não encontrado.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=roteiro/montar/' . $sessaoId));

        $tipoPasso = strtoupper(trim((string) ($_POST['tipo_passo'] ?? 'FALA')));
        if (!in_array($tipoPasso, ['FALA', 'INSTRUCAO'], true)) {
            $tipoPasso = 'FALA';
        }

        $ativo = array_key_exists('ativo', $_POST)
            ? !empty($_POST['ativo'])
            : SessaoRoteiro::isAtivo($passo);

        SessaoRoteiro::update($id, [
            'ordem' => (int) ($_POST['ordem'] ?? $passo['ordem']),
            'titulo' => trim((string) ($_POST['titulo'] ?? '')),
            'texto_fala' => trim((string) ($_POST['texto_fala'] ?? '')),
            'texto_instrucao' => trim((string) ($_POST['texto_instrucao'] ?? '')),
            'tipo_passo' => $tipoPasso,
            'gera_votacao' => !empty($_POST['gera_votacao']),
            'ativo' => $ativo,
        ]);

        header('Location: ' . $this->redirectAposSalvar($sessaoId));
        exit;
    }

    public function montar(int $sessaoId): void
    {
        Permissao::exigir('roteiro.montar');
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $roteiro = SessaoRoteiro::allBySessao($sessaoId);
        $passoId = (int) ($_GET['passo'] ?? 0);
        $passoAtual = null;
        if ($passoId > 0) {
            foreach ($roteiro as $p) {
                if ((int) $p['id'] === $passoId) {
                    $passoAtual = $p;
                    break;
                }
            }
        }
        if (!$passoAtual && $roteiro !== []) {
            $passoAtual = $roteiro[0];
        }

        View::render('roteiro/montar', [
            'sessao' => $sessao,
            'roteiro' => $roteiro,
            'passoAtual' => $passoAtual,
            'roteiroStats' => SessaoRoteiro::contagem($sessaoId),
            'sessaoBloqueada' => SessaoBloqueio::isLocked($sessao),
        ]);
    }

    public function reordenar(int $sessaoId): void
    {
        Permissao::exigir('roteiro.reordenar');
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Token invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            echo json_encode(['ok' => false, 'error' => 'Sessão não encontrada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (SessaoBloqueio::isLocked($sessao)) {
            echo json_encode(['ok' => false, 'error' => 'Sessão bloqueada para edição.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        SessaoRoteiro::reorderByIds($sessaoId, $ids);
        app_log('[RoteiroController::reordenar] sessaoId=' . $sessaoId . ' count=' . count($ids));

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    public function toggleAtivo(int $sessaoId, int $id): void
    {
        Permissao::exigir('roteiro.toggleAtivo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $passo = SessaoRoteiro::find($id);
        if (!$passo || (int) $passo['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Passo não encontrado.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=roteiro/montar/' . $sessaoId));

        if (array_key_exists('ativo', $_POST)) {
            $ativo = !empty($_POST['ativo']);
        } else {
            $ativo = !SessaoRoteiro::isAtivo($passo);
        }
        SessaoRoteiro::setAtivo($id, $ativo);

        header('Location: ' . base_url('?r=roteiro/montar/' . $sessaoId . '&passo=' . $id));
        exit;
    }

    public function excluir(int $sessaoId, int $id): void
    {
        Permissao::exigir('roteiro.excluir');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=roteiro/montar/' . $sessaoId));

        $pdo = \App\Core\DB::conn();
        $stmt = $pdo->prepare('DELETE FROM sessao_roteiro WHERE id = :id AND sessao_id = :sessao_id');
        $stmt->execute(['id' => $id, 'sessao_id' => $sessaoId]);

        $return = trim((string) ($_POST['return_to'] ?? ''));
        if ($return === 'montar') {
            header('Location: ' . base_url('?r=roteiro/montar/' . $sessaoId));
        } else {
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '#roteiro'));
        }
        exit;
    }

    public function resetarExecucao(int $sessaoId): void
    {
        Permissao::exigir('roteiro.resetarExecucao');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=presidencia/conduzir/' . $sessaoId));

        SessaoRoteiro::resetExecucao($sessaoId);
        header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId));
        exit;
    }

    private function redirectAposSalvar(int $sessaoId): string
    {
        if (trim((string) ($_POST['return_to'] ?? '')) === 'montar') {
            $passoId = (int) ($_POST['passo_id'] ?? 0);
            $url = '?r=roteiro/montar/' . $sessaoId;
            if ($passoId > 0) {
                $url .= '&passo=' . $passoId . '&salvo=1';
            }
            return base_url($url);
        }

        return base_url('?r=sessao/form/' . $sessaoId . '#roteiro');
    }
}
