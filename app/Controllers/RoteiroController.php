<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Helpers\Auditoria;
use App\Helpers\ConducaoRichText;
use App\Helpers\Permissao;
use App\Helpers\RoteiroAnexoUpload;
use App\Helpers\SessaoBloqueio;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;
use App\Models\SessaoRoteiroAnexo;
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
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&roteiro_erro=' . rawurlencode('Não foi possível gerar o roteiro. Contate o suporte para configurar os modelos de condução.') . '#roteiro'));
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

    public function salvar(int $sessaoId, ?int $id = null): void
    {
        Permissao::exigir('roteiro.salvar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        if ($id === null && !empty($_POST['criar_novo'])) {
            $sessao = SessaoCrud::find($sessaoId);
            if (!$sessao) {
                http_response_code(404);
                echo 'Sessão não encontrada.';
                return;
            }

            SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=roteiro/montar/' . $sessaoId));

            $id = SessaoRoteiro::insert([
                'sessao_id' => $sessaoId,
                'ordem' => SessaoRoteiro::proximaOrdem($sessaoId),
                'tipo_item' => 'CONDUCAO',
                'titulo' => 'Novo passo',
                'texto_fala' => '',
                'texto_instrucao' => null,
                'tipo_passo' => 'FALA',
                'gera_votacao' => false,
                'ativo' => true,
            ]);

            Auditoria::log('criar', 'roteiro_passo', $id, 'Passo manual na sessão ' . $sessaoId);
            SessaoEvento::registrar($sessaoId, 'roteiro', 'Novo passo manual adicionado ao roteiro');

            header('Location: ' . base_url('?r=roteiro/montar/' . $sessaoId . '&passo=' . $id . '&novo=1'));
            exit;
        }

        if ($id === null) {
            http_response_code(400);
            echo 'Passo não informado.';
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
        $geraVotacao = !empty($_POST['gera_votacao']);
        if ($geraVotacao) {
            $tipoPasso = 'INSTRUCAO';
        }

        $ativo = array_key_exists('ativo', $_POST)
            ? !empty($_POST['ativo'])
            : SessaoRoteiro::isAtivo($passo);

        SessaoRoteiro::update($id, [
            'ordem' => (int) ($_POST['ordem'] ?? $passo['ordem']),
            'titulo' => array_key_exists('titulo', $_POST)
                ? trim((string) $_POST['titulo'])
                : (string) ($passo['titulo'] ?? ''),
            'texto_fala' => array_key_exists('texto_fala', $_POST)
                ? ConducaoRichText::sanitize((string) $_POST['texto_fala'])
                : (string) ($passo['texto_fala'] ?? ''),
            'texto_instrucao' => array_key_exists('texto_instrucao', $_POST)
                ? trim((string) $_POST['texto_instrucao'])
                : (string) ($passo['texto_instrucao'] ?? ''),
            'tipo_passo' => $tipoPasso,
            'gera_votacao' => $geraVotacao,
            'ativo' => $ativo,
        ]);

        // Matéria da pauta ligada ao passo (o autor dela aparece no roteiro).
        if (array_key_exists('pauta_item_id', $_POST)) {
            $itemId = (int) $_POST['pauta_item_id'];
            if ($itemId <= 0) {
                SessaoRoteiro::definirPautaItem($id, null);
            } else {
                $item = SessaoItem::find($itemId);
                if ($item && (int) $item['sessao_id'] === $sessaoId) {
                    SessaoRoteiro::definirPautaItem($id, $itemId);
                }
            }
        }

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
            'itensPauta' => SessaoItem::allBySessao($sessaoId),
            'roteiro' => $roteiro,
            'passoAtual' => $passoAtual,
            'anexosPasso' => $passoAtual ? SessaoRoteiroAnexo::allByPasso((int) $passoAtual['id']) : [],
            'roteiroStats' => SessaoRoteiro::contagem($sessaoId),
            'sessaoBloqueada' => SessaoBloqueio::isLocked($sessao),
        ]);
    }

    public function anexoSalvar(int $sessaoId, int $passoId): void
    {
        Permissao::exigir('roteiro.salvar');
        $voltar = base_url('?r=roteiro/montar/' . $sessaoId . '&passo=' . $passoId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        $passo = SessaoRoteiro::find($passoId);
        if (!$passo || (int) $passo['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Passo não encontrado.';
            return;
        }
        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), $voltar);

        $url = trim((string) ($_POST['url'] ?? ''));
        $titulo = mb_substr(trim((string) ($_POST['titulo_anexo'] ?? '')), 0, 180);
        $temArquivo = isset($_FILES['arquivo']) && (int) ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        try {
            if (($url === '') === !$temArquivo) {
                throw new \RuntimeException('Informe uma URL ou selecione um arquivo, apenas um por vez.');
            }
            $dadosArquivo = [
                'tipo' => 'URL', 'arquivo' => null, 'nome_original' => null,
                'mime_type' => null, 'tamanho_bytes' => null,
            ];
            if ($temArquivo) {
                $dadosArquivo = RoteiroAnexoUpload::salvar($_FILES['arquivo'], $sessaoId, $passoId);
                $url = '';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new \RuntimeException('Informe uma URL válida iniciada por http:// ou https://.');
            }
            try {
                $id = SessaoRoteiroAnexo::insert([
                    'sessao_id' => $sessaoId,
                    'roteiro_passo_id' => $passoId,
                    'tipo' => $dadosArquivo['tipo'],
                    'titulo' => $titulo !== '' ? $titulo : null,
                    'url' => $url !== '' ? $url : null,
                    'arquivo' => $dadosArquivo['arquivo'],
                    'nome_original' => $dadosArquivo['nome_original'],
                    'mime_type' => $dadosArquivo['mime_type'],
                    'tamanho_bytes' => $dadosArquivo['tamanho_bytes'],
                    'criado_por' => (int) (Auth::user()['id'] ?? 0) ?: null,
                ]);
            } catch (\Throwable $e) {
                RoteiroAnexoUpload::excluir($dadosArquivo['arquivo']);
                throw $e;
            }
            Auditoria::log('criar', 'roteiro_anexo', $id, 'Anexo incluído no passo ' . $passoId);
            SessaoEvento::registrar($sessaoId, 'roteiro', 'Anexo incluído no roteiro');
            header('Location: ' . $voltar . '&anexo_ok=1');
        } catch (\Throwable $e) {
            header('Location: ' . $voltar . '&anexo_erro=' . rawurlencode($e->getMessage()));
        }
        exit;
    }

    public function anexoExcluir(int $sessaoId, int $passoId, int $anexoId): void
    {
        Permissao::exigir('roteiro.excluir');
        $voltar = base_url('?r=roteiro/montar/' . $sessaoId . '&passo=' . $passoId);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }
        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), $voltar);
        $anexo = SessaoRoteiroAnexo::find($anexoId);
        if (!$anexo || (int) $anexo['sessao_id'] !== $sessaoId || (int) $anexo['roteiro_passo_id'] !== $passoId) {
            http_response_code(404);
            echo 'Anexo não encontrado.';
            return;
        }
        SessaoRoteiroAnexo::delete($anexoId);
        RoteiroAnexoUpload::excluir($anexo['arquivo'] ?? null);
        Auditoria::log('excluir', 'roteiro_anexo', $anexoId, 'Anexo removido do passo ' . $passoId);
        SessaoEvento::registrar($sessaoId, 'roteiro', 'Anexo removido do roteiro');
        header('Location: ' . $voltar . '&anexo_excluido=1');
        exit;
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

        $arquivosAnexos = SessaoRoteiroAnexo::allByPasso($id);
        $pdo = \App\Core\DB::conn();
        $stmt = $pdo->prepare('DELETE FROM sessao_roteiro WHERE id = :id AND sessao_id = :sessao_id');
        $stmt->execute(['id' => $id, 'sessao_id' => $sessaoId]);
        if ($stmt->rowCount() > 0) {
            foreach ($arquivosAnexos as $anexo) {
                RoteiroAnexoUpload::excluir($anexo['arquivo'] ?? null);
            }
        }

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

        // Votação aberta é cancelada: senão o telão e os vereadores continuam vendo "Em votação".
        if (\App\Models\SessaoVotacaoRodada::cancelarAberta($sessaoId)) {
            Auditoria::log('cancelar_votacao', 'sessao', $sessaoId, 'Votação aberta cancelada ao reiniciar a condução');
            SessaoEvento::registrar($sessaoId, 'votacao', 'Votação aberta cancelada ao reiniciar a condução');
        }
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
