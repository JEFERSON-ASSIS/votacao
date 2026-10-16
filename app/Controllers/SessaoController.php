<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Auditoria;
use App\Helpers\Permissao;
use App\Helpers\SessaoBloqueio;
use App\Helpers\UserPerfil;
use App\Models\ConducaoModelo;
use App\Helpers\SessaoAtaUpload;
use App\Models\SessaoCerimonial;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoItem;
use App\Models\SessaoRoteiro;
use App\Services\RoteiroGerador;
use App\Services\SessaoAtaMinuta;

final class SessaoController
{
    private const STATUS_OPTIONS = ['RASCUNHO', 'CRIADO', 'ABERTA', 'EM_VOTACAO', 'ENCERRADA'];

    public function index(): void
    {
        if (UserPerfil::ehParlamentar(Auth::user()['role'] ?? '') && Permissao::usuarioAtualPode('parlamentar.sessoes')) {
            redirect_route('parlamentar/sessoes');
        }
        Permissao::exigir('sessao.index');
        View::render('sessoes/index', ['sessoes' => SessaoCrud::all()]);
    }

    public function form(?int $id = null): void
    {
        Permissao::exigir('sessao.form');

        $sessao = $id ? SessaoCrud::find($id) : null;
        $error = null;

        // GET em sessão existente: form unificado legado não existe mais.
        // Ata tem tela própria; demais casos vão para Montar roteiro.
        if ($sessao && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $secao = strtolower(trim((string) ($_GET['secao'] ?? '')));
            if ($secao === 'ata') {
                header('Location: ' . base_url('?r=sessao/ata/' . (int) $sessao['id']));
                exit;
            }
            header('Location: ' . base_url('?r=roteiro/montar/' . (int) $sessao['id']));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($sessao && SessaoBloqueio::isLocked($sessao)) {
                SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=sessao/form/' . (int) $sessao['id']));
            }
            if (!csrf_check($_POST['csrf_token'] ?? null)) {
                $error = 'Token invalido.';
            } else {
                $data = [
                    'titulo' => trim((string) ($_POST['titulo'] ?? '')),
                    'tipo' => 'ORDINARIA',
                    'numero' => (int) ($_POST['numero'] ?? 1),
                    'ano' => (int) ($_POST['ano'] ?? date('Y')),
                    'status' => $this->normalizeStatus((string) ($_POST['status'] ?? ($id ? 'RASCUNHO' : 'CRIADO'))),
                    'sessao_anterior_id' => (int) ($_POST['sessao_anterior_id'] ?? 0),
                    'ata_sessao_anterior' => (string) ($_POST['ata_sessao_anterior'] ?? ''),
                    'ata_conteudo' => (string) ($_POST['ata_conteudo'] ?? ''),
                ];

                $hashCheck = trim((string) ($_POST['return_hash'] ?? ''));
                if ($data['titulo'] === '') {
                    $error = 'Informe o titulo.';
                } elseif ($hashCheck !== '#ata' && SessaoCrud::existeDuplicada($data['tipo'], $data['numero'], $data['ano'], $id)) {
                    $error = 'Já existe uma sessão ' . $data['tipo'] . ' número ' . $data['numero'] . '/' . $data['ano'] . '.';
                } else {
                    $hash = trim((string) ($_POST['return_hash'] ?? ''));
                    if ($hash === '#ata' && $id) {
                        $atual = SessaoCrud::find($id) ?? [];
                        $arqAnterior = SessaoAtaUpload::handleUpload(
                            $id,
                            'ata_anterior_arquivo',
                            (string) ($atual['ata_sessao_anterior_arquivo'] ?? ''),
                            !empty($_POST['remover_ata_anterior_arquivo'])
                        );
                        $arqConteudo = SessaoAtaUpload::handleUpload(
                            $id,
                            'ata_conteudo_arquivo',
                            (string) ($atual['ata_conteudo_arquivo'] ?? ''),
                            !empty($_POST['remover_ata_conteudo_arquivo'])
                        );
                        SessaoCrud::updateAtaFields(
                            $id,
                            (int) ($data['sessao_anterior_id'] ?? 0) > 0 ? (int) $data['sessao_anterior_id'] : null,
                            (string) ($data['ata_sessao_anterior'] ?? ''),
                            (string) ($data['ata_conteudo'] ?? ''),
                            $arqAnterior,
                            $arqConteudo
                        );
                        $savedId = $id;
                    } else {
                        $savedId = SessaoCrud::save($data, $id);
                        Auditoria::log($id ? 'editar' : 'criar', 'sessao', $savedId, $data['titulo']);
                        SessaoEvento::registrar($savedId, 'sessao', $id ? 'Sessão atualizada' : 'Sessão criada');
                    }
                    if (!$id && $hash !== '#ata') {
                        try {
                            $passosGerados = RoteiroGerador::gerar($savedId, true);
                            Auditoria::log('gerar', 'roteiro', $savedId, $passosGerados . ' passos gerados automaticamente');
                            SessaoEvento::registrar($savedId, 'roteiro', 'Roteiro inicial gerado automaticamente (' . $passosGerados . ' passos)');
                            if ($passosGerados > 0) {
                                header('Location: ' . base_url('?r=roteiro/montar/' . $savedId . '&roteiro_ok=' . $passosGerados . '&sessao_ok=criada'));
                            } else {
                                header('Location: ' . base_url('?r=sessao/form/' . $savedId . '&roteiro_vazio=1#roteiro'));
                            }
                        } catch (\Throwable $e) {
                            app_log('[SessaoController::form] falha ao gerar roteiro inicial sessaoId=' . $savedId . ' msg=' . $e->getMessage());
                            header('Location: ' . base_url('?r=sessao/form/' . $savedId . '&roteiro_erro=' . rawurlencode('A sessão foi criada, mas o roteiro inicial não pôde ser gerado.') . '#roteiro'));
                        }
                        exit;
                    }

                    if ($hash === '#ata') {
                        header('Location: ' . base_url('?r=sessao/ata/' . $savedId));
                        exit;
                    }
                    $redirectHash = $hash !== '' ? $hash : '';
                    header('Location: ' . base_url('?r=sessao/form/' . $savedId . $redirectHash));
                    exit;
                }
            }
        }

        $editingItem = null;
        $itens = $sessao ? SessaoItem::allBySessao((int) $sessao['id']) : [];
        $itemsSectionRows = [];
        if ($sessao) {
            $editId = (int) ($_GET['edit_id'] ?? 0);
            if ($editId > 0) {
                $candidate = SessaoItem::find($editId);
                if ($candidate && (int) $candidate['sessao_id'] === (int) $sessao['id']) {
                    $editingItem = $candidate;
                }
            }
            foreach ($itens as $item) {
                $itemsSectionRows[(int) $item['id']] = SessaoItem::loadSectionRows((int) $item['id']);
            }
        }

        $roteiro = $sessao ? SessaoRoteiro::allBySessao((int) $sessao['id']) : [];
        $roteiroStats = $sessao ? SessaoRoteiro::contagem((int) $sessao['id']) : ['total' => 0, 'concluidos' => 0];
        $conducaoConfigurada = count(ConducaoModelo::allAtivos()) > 0;

        $sessoesVinculo = SessaoCrud::listParaVinculo($sessao ? (int) $sessao['id'] : null);
        $sessaoAnteriorSugerida = $sessao ? SessaoCrud::findAnteriorSugerida((int) $sessao['id']) : null;
        $anoSugerido = (int) date('Y');
        $numeroSugerido = $sessao ? 1 : SessaoCrud::proximoNumero('ORDINARIA', $anoSugerido);

        View::render($sessao ? 'sessoes/form' : 'sessoes/criar', [
            'sessao' => $sessao,
            'error' => $error,
            'itens' => $itens,
            'editingItem' => $editingItem,
            'itemsSectionRows' => $itemsSectionRows,
            'activeTab' => trim((string) ($_GET['tab'] ?? '')),
            'roteiro' => $roteiro,
            'roteiroStats' => $roteiroStats,
            'conducaoConfigurada' => $conducaoConfigurada,
            'sessoesVinculo' => $sessoesVinculo,
            'sessaoAnteriorSugerida' => $sessaoAnteriorSugerida,
            'anoSugerido' => $anoSugerido,
            'numeroSugerido' => $numeroSugerido,
            'cerimonialItens' => $sessao ? SessaoCerimonial::allBySessao((int) $sessao['id']) : [],
        ]);
    }

    public function exportarPauta(int $sessaoId): void
    {
        Permissao::exigir('sessao.exportarPauta');
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $roteiro = array_values(array_filter(
            SessaoRoteiro::allBySessao($sessaoId),
            static fn (array $p): bool => SessaoRoteiro::isAtivo($p)
        ));

        View::render('sessoes/pauta_impressao', [
            'sessao' => $sessao,
            'itens' => SessaoItem::allBySessao($sessaoId),
            'roteiro' => $roteiro,
            'cerimonial' => SessaoCerimonial::allBySessao($sessaoId),
        ]);
    }

    public function ata(int $id): void
    {
        Permissao::exigir('sessao.form');

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        View::render('sessoes/ata', [
            'sessao' => $sessao,
            'sessoesVinculo' => SessaoCrud::listParaVinculo($id),
            'sessaoAnteriorSugerida' => SessaoCrud::findAnteriorSugerida($id),
        ]);
    }

    public function salvarAta(int $id): void
    {
        Permissao::exigir('sessao.form');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $ataUrl = base_url('?r=sessao/ata/' . $id);

        $arqAnterior = SessaoAtaUpload::handleUpload(
            $id,
            'ata_anterior_arquivo',
            (string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''),
            !empty($_POST['remover_ata_anterior_arquivo'])
        );
        $arqConteudo = SessaoAtaUpload::handleUpload(
            $id,
            'ata_conteudo_arquivo',
            (string) ($sessao['ata_conteudo_arquivo'] ?? ''),
            !empty($_POST['remover_ata_conteudo_arquivo'])
        );

        $vinculo = (int) ($_POST['sessao_anterior_id'] ?? 0);
        SessaoCrud::updateAtaFields(
            $id,
            $vinculo > 0 ? $vinculo : null,
            (string) ($_POST['ata_sessao_anterior'] ?? ''),
            (string) ($_POST['ata_conteudo'] ?? ''),
            $arqAnterior,
            $arqConteudo
        );

        Auditoria::log('editar', 'sessao_ata', $id);
        SessaoEvento::registrar($id, 'ata', 'Dados da ata atualizados');

        header('Location: ' . $ataUrl . '&salvo=1');
        exit;
    }

    public function gerarMinutaAta(int $sessaoId): void
    {
        Permissao::exigir('sessao.gerarMinutaAta');
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

        $ataUrl = base_url('?r=sessao/ata/' . $sessaoId);

        $minuta = SessaoAtaMinuta::gerar($sessaoId);
        $atual = trim((string) ($sessao['ata_conteudo'] ?? ''));
        $novo = $atual !== '' ? $atual . "\n\n---\n\n" . $minuta : $minuta;

        SessaoCrud::updateAtaFields(
            $sessaoId,
            (int) ($sessao['sessao_anterior_id'] ?? 0) > 0 ? (int) $sessao['sessao_anterior_id'] : null,
            (string) ($sessao['ata_sessao_anterior'] ?? ''),
            $novo,
            (string) ($sessao['ata_sessao_anterior_arquivo'] ?? '') ?: null,
            (string) ($sessao['ata_conteudo_arquivo'] ?? '') ?: null
        );

        Auditoria::log('gerar_minuta', 'sessao_ata', $sessaoId);
        SessaoEvento::registrar($sessaoId, 'ata', 'Minuta de ata gerada automaticamente');

        header('Location: ' . $ataUrl . '&minuta_ok=1');
        exit;
    }

    public function publicar(int $id): void
    {
        Permissao::exigir('sessao.publicar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $stats = SessaoRoteiro::contagem($id);
        if (!SessaoBloqueio::podePublicar($sessao, $stats)) {
            header('Location: ' . base_url('?r=sessao/form/' . $id . '&publicar_erro=1#roteiro'));
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        SessaoCrud::marcarPublicada($id, $userId > 0 ? $userId : null);
        Auditoria::log('publicar', 'sessao', $id, (string) ($sessao['titulo'] ?? ''));
        SessaoEvento::registrar($id, 'sessao', 'Sessão publicada — edição da pauta e do roteiro bloqueada');

        header('Location: ' . base_url('?r=sessao/form/' . $id . '&publicada_ok=1#roteiro'));
        exit;
    }

    public function cerimonialSave(int $sessaoId): void
    {
        Permissao::exigir('sessao.cerimonialSave');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($sessaoId);
        SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=sessao/form/' . $sessaoId . '#cerimonial'));

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&cer_erro=1#cerimonial'));
            exit;
        }

        $id = (int) ($_POST['cerimonial_id'] ?? 0) ?: null;
        SessaoCerimonial::save($sessaoId, $_POST, $id);
        Auditoria::log($id ? 'editar' : 'criar', 'cerimonial', $sessaoId, $titulo);
        SessaoEvento::registrar($sessaoId, 'cerimonial', ($id ? 'Cerimonial atualizado: ' : 'Cerimonial: ') . $titulo);

        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&cer_ok=1#cerimonial'));
        exit;
    }

    public function cerimonialDelete(int $sessaoId, int $itemId): void
    {
        Permissao::exigir('sessao.cerimonialDelete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=sessao/form/' . $sessaoId . '#cerimonial'));

        SessaoCerimonial::delete($sessaoId, $itemId);
        Auditoria::log('excluir', 'cerimonial', $itemId);
        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '#cerimonial'));
        exit;
    }

    public function vincularAnteriorSugerida(int $id): void
    {
        Permissao::exigir('sessao.vincularAnteriorSugerida');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $ataUrl = base_url('?r=sessao/ata/' . $id);

        $sug = SessaoCrud::findAnteriorSugerida($id);
        if ($sug) {
            SessaoCrud::updateAtaFields(
                $id,
                (int) $sug['id'],
                (string) ($sessao['ata_sessao_anterior'] ?? ''),
                (string) ($sessao['ata_conteudo'] ?? ''),
                (string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''),
                (string) ($sessao['ata_conteudo_arquivo'] ?? '')
            );
        }

        header('Location: ' . $ataUrl . '&ata_vinculo=1');
        exit;
    }

    public function carregarAtaAnterior(int $id): void
    {
        Permissao::exigir('sessao.carregarAtaAnterior');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $ataUrl = base_url('?r=sessao/ata/' . $id);

        $antId = (int) ($_POST['sessao_anterior_id'] ?? $sessao['sessao_anterior_id'] ?? 0);
        if ($antId <= 0) {
            $sug = SessaoCrud::findAnteriorSugerida($id);
            $antId = $sug ? (int) $sug['id'] : 0;
        }

        $ataTexto = '';
        $ataArquivo = null;
        if ($antId > 0) {
            $anterior = SessaoCrud::find($antId);
            $ataTexto = trim((string) ($anterior['ata_conteudo'] ?? ''));
            $copia = SessaoAtaUpload::copyFromPath(
                (string) ($anterior['ata_conteudo_arquivo'] ?? ''),
                $id,
                'ata_anterior'
            );
            if ($copia !== null) {
                $antigo = trim((string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''));
                if ($antigo !== '') {
                    SessaoAtaUpload::deleteFile($antigo);
                }
                $ataArquivo = $copia;
            }
        }

        $arqPersistir = $ataArquivo;
        if ($arqPersistir === null) {
            $existente = trim((string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''));
            $arqPersistir = $existente !== '' ? $existente : null;
        }

        SessaoCrud::updateAtaFields(
            $id,
            $antId > 0 ? $antId : null,
            $ataTexto,
            (string) ($sessao['ata_conteudo'] ?? ''),
            $arqPersistir,
            trim((string) ($sessao['ata_conteudo_arquivo'] ?? '')) !== ''
                ? (string) $sessao['ata_conteudo_arquivo']
                : null
        );

        $q = ($ataTexto !== '' || $ataArquivo !== null) ? 'ata_ok=1' : 'ata_vazia=1';
        header('Location: ' . $ataUrl . '&' . $q);
        exit;
    }

    public function pauta(int $id): void
    {
        Permissao::exigir('sessao.pauta');
        header('Location: ' . base_url('?r=sessao/form/' . $id));
        exit;
    }

    public function delete(int $id): void
    {
        Permissao::exigir('sessao.delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            header('Location: ' . base_url('?r=sessao/index&sessao_erro=nao_encontrada'));
            exit;
        }

        try {
            Auditoria::log('excluir', 'sessao', $id, (string) ($sessao['titulo'] ?? ''));
            SessaoCrud::delete($id);
            header('Location: ' . base_url('?r=sessao/index&sessao_ok=excluida'));
        } catch (\Throwable $e) {
            if (function_exists('app_log')) {
                app_log('sessao.delete falhou id=' . $id . ' — ' . $e->getMessage());
            }
            header('Location: ' . base_url('?r=sessao/index&sessao_erro=excluir'));
        }
        exit;
    }

    /** Reseta a condução da sessão para ensaiar de novo sem recriar pauta/roteiro. */
    public function resetar(int $id): void
    {
        Permissao::exigir('sessao.resetar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($id);
        if (!$sessao) {
            header('Location: ' . base_url('?r=sessao/index&sessao_erro=nao_encontrada'));
            exit;
        }

        try {
            SessaoCrud::resetarAndamento($id);
            Auditoria::log('resetar', 'sessao', $id, (string) ($sessao['titulo'] ?? '') . ' — andamento apagado (status anterior: ' . (string) ($sessao['status'] ?? '') . ')');
            header('Location: ' . base_url('?r=sessao/index&sessao_ok=resetada'));
        } catch (\Throwable $e) {
            if (function_exists('app_log')) {
                app_log('sessao.resetar falhou id=' . $id . ' — ' . $e->getMessage());
            }
            header('Location: ' . base_url('?r=sessao/index&sessao_erro=resetar'));
        }
        exit;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        return in_array($status, self::STATUS_OPTIONS, true) ? $status : 'RASCUNHO';
    }
}
