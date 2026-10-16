<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\View;
use App\Helpers\Auditoria;
use App\Helpers\Permissao;
use App\Helpers\SessaoBloqueio;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoItem;
use App\Helpers\MateriaSaplFields;
use App\Services\SaplMateriaImport;
use PDO;

final class PautaController
{
    public function form(int $sessaoId, ?int $id = null): void
    {
        Permissao::exigir('pauta.form');
        app_log('[PautaController::form] start sessaoId=' . $sessaoId . ' id=' . ($id ?? 'null') . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            app_log('[PautaController::form] sessao not found sessaoId=' . $sessaoId);
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $item = $id ? SessaoItem::find($id) : null;
        app_log('[PautaController::form] item loaded=' . ($item ? 'yes' : 'no'));
        $error = null;
        $sectionRows = [
            'despachos' => [],
            'documentos' => [],
            'legislacoes' => [],
            'numeracoes' => [],
            'tramitacoes' => [],
            'relatorias' => [],
        ];
        if ($item && $id) {
            $sectionRows = SessaoItem::loadSectionRows($id);
            app_log('[PautaController::form] sectionRows loaded item_id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=sessao/form/' . $sessaoId));
            app_log('[PautaController::form] post received sessaoId=' . $sessaoId . ' id=' . ($id ?? 'null'));
            if (!csrf_check($_POST['csrf_token'] ?? null)) {
                $error = 'Token invalido.';
                app_log('[PautaController::form] csrf invalid');
            } else {
                $uploadDir = dirname(__DIR__, 2) . '/public/uploads/pauta';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $storeUpload = function (string $field) use ($uploadDir): string {
                    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        return '';
                    }
                    $name = basename((string) $_FILES[$field]['name']);
                    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?: ('arquivo_' . time());
                    $target = $uploadDir . '/' . date('Ymd_His') . '_' . $safe;
                    if (@move_uploaded_file((string) $_FILES[$field]['tmp_name'], $target)) {
                        return str_replace(dirname(__DIR__, 2) . '/', '', $target);
                    }
                    return '';
                };
                $data = $this->buildDataFromPost($_POST, $sessaoId, $storeUpload);
                if ($data['titulo'] === '') {
                    $error = 'Informe o titulo do item.';
                    app_log('[PautaController::form] validation failed: empty title');
                } else {
                    $dataFimPrazo = $data['data_fim_prazo'] ?? null;
                    $dataApresentacao = $data['data_apresentacao'] ?? null;
                    if ($dataFimPrazo !== null && $dataApresentacao !== null && $dataFimPrazo < $dataApresentacao) {
                        $error = 'A data de fim de prazo não pode ser anterior à data de apresentação.';
                        app_log('[PautaController::form] validation failed: data_fim_prazo < data_apresentacao');
                    }
                }
                if ($error === null) {
                    $pdo = DB::conn();
                    $pdo->beginTransaction();
                    try {
                        $savedId = SessaoItem::upsert($data, $id);
                        Auditoria::log($id ? 'editar' : 'criar', 'pauta_item', $savedId, $data['titulo']);
                        SessaoEvento::registrar(
                            $sessaoId,
                            'pauta',
                            ($id ? 'Matéria atualizada: ' : 'Matéria incluída na pauta: ') . $data['titulo'],
                            $savedId
                        );
                        app_log('[PautaController::form] saved item id=' . $savedId . ' sessaoId=' . $sessaoId);
                        $syncRows = function (string $table, array $rows, array $fields) use ($pdo, $savedId): void {
                            $pdo->prepare("DELETE FROM {$table} WHERE item_id = :id")->execute(['id' => $savedId]);
                            $order = 1;
                            foreach ($rows as $row) {
                                if (!is_array($row) || !$this->rowHasContent($row, $fields)) {
                                    continue;
                                }
                                $values = ['item_id' => $savedId, 'ordem' => $order++];
                                foreach ($fields as $field) {
                                    $value = trim((string) ($row[$field] ?? ''));
                                    $values[$field] = $this->normalizePautaValue($field, $value);
                                }
                                $cols = array_merge(['item_id', 'ordem'], $fields);
                                $placeholders = array_map(fn($field) => ':' . $field, $cols);
                                $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($values);
                                app_log('[PautaController::form] inserted row table=' . $table . ' item_id=' . $savedId . ' ordem=' . $values['ordem']);
                            }
                        };
                        $hasSections = !empty($_POST['pauta_sections']);
                        if ($hasSections) {
                            $numeracoes = $_POST['numeracoes'] ?? [];
                            if (!is_array($numeracoes) || $numeracoes === []) {
                                $numeracoes = $this->numeracoesFromPost($_POST, $data);
                            }
                            $syncRows('pauta_despachos', $_POST['despachos'] ?? [], ['comissao', 'situacao', 'observacao', 'arquivo']);
                            $syncRows('pauta_documentos', $_POST['documentos'] ?? [], ['nome', 'tipo', 'data_doc', 'autor', 'arquivo']);
                            $syncRows('pauta_legislacoes', $_POST['legislacoes'] ?? [], ['norma', 'disposicao', 'observacao', 'arquivo']);
                            $syncRows('pauta_numeracoes', $numeracoes, ['tipo_materia', 'numero', 'ano', 'data_doc']);
                            $syncRows('pauta_tramitacoes', $_POST['tramitacoes'] ?? [], ['data_tramitacao', 'unidade_local', 'unidade_destino', 'status', 'arquivo']);
                            $syncRows('pauta_relatorias', $_POST['relatorias'] ?? [], ['relator', 'data_relatoria', 'observacao', 'arquivo']);
                        }
                        $pdo->commit();
                    } catch (\Throwable $e) {
                        $pdo->rollBack();
                        app_log('[PautaController::form] transaction rolled back: ' . $e->getMessage());
                        throw $e;
                    }
                    if ($this->isAjaxRequest()) {
                        $saved = SessaoItem::find($savedId);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => true,
                            'item' => [
                                'id' => $savedId,
                                'ordem' => (int) ($saved['ordem'] ?? 1),
                                'tipo_materia' => (string) ($saved['tipo_materia'] ?? ''),
                                'titulo' => (string) ($saved['titulo'] ?? ''),
                                'ementa' => (string) ($saved['ementa'] ?? ''),
                                'edit_url' => base_url('?r=sessao/form/' . $sessaoId . '&edit_id=' . $savedId),
                                'delete_url' => base_url('?r=pauta/delete/' . $sessaoId . '/' . $savedId),
                            ],
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }

                    $activeTab = trim((string) ($_POST['active_tab'] ?? ''));
                    $redirect = '?r=sessao/form/' . $sessaoId . '&edit_id=' . $savedId;
                    if ($activeTab !== '') {
                        $redirect .= '&tab=' . rawurlencode($activeTab);
                    }
                    header('Location: ' . base_url($redirect));
                    exit;
                }
            }
        }

        if ($this->isAjaxRequest() && $error !== null) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }

        View::render('pauta/form', compact('sessao', 'item', 'error', 'sectionRows'));
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_POST['ajax']) || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** @param callable(string): string $storeUpload */
    private function buildDataFromPost(array $post, int $sessaoId, callable $storeUpload): array
    {
        $sapl = MateriaSaplFields::itemFromPost($post);
        $extras = MateriaSaplFields::extrasFromPost($post);
        $extras['despacho_inicial_arquivo'] = $storeUpload('despacho_inicial_arquivo');
        $extras['documento_acessorio_arquivo'] = $storeUpload('documento_acessorio_arquivo');
        $extras['legislacao_citada_arquivo'] = $storeUpload('legislacao_citada_arquivo');
        $extras['texto_original_arquivo'] = $storeUpload('texto_original_arquivo');
        $extras['tramitacao_arquivo'] = $storeUpload('tramitacao_arquivo');
        $extras['relatoria_arquivo'] = $storeUpload('relatoria_arquivo');

        return [
            'sessao_id' => $sessaoId,
            'ordem' => (int) ($sapl['ordem'] ?? 1),
            'tipo_materia' => $sapl['tipo_materia'],
            'numero' => $sapl['numero'],
            'ano' => $sapl['ano'],
            'sapl_id' => $sapl['sapl_id'],
            'apelido' => $sapl['apelido'],
            'indexacao' => $sapl['indexacao'],
            'observacao' => $sapl['observacao'],
            'objeto' => $sapl['objeto'],
            'resultado' => $sapl['resultado'],
            'dias_prazo' => $sapl['dias_prazo'],
            'data_fim_prazo' => $sapl['data_fim_prazo'],
            'tipo_sapl' => $sapl['tipo_sapl'],
            'regime_tramitacao_id' => $sapl['regime_tramitacao_id'],
            'numero_origem_externa' => $sapl['numero_origem_externa'],
            'ano_origem_externa' => $sapl['ano_origem_externa'],
            'data_origem_externa' => $sapl['data_origem_externa'],
            'tipo_origem_externa' => $sapl['tipo_origem_externa'],
            'local_origem_externa' => $sapl['local_origem_externa'],
            'titulo' => $sapl['titulo'],
            'ementa' => $sapl['ementa'],
            'status' => 'PENDENTE',
            'texto_original' => $sapl['texto_original'],
            'tipo_apresentacao' => $sapl['tipo_apresentacao'],
            'data_apresentacao' => $sapl['data_apresentacao'],
            'numero_protocolo' => $sapl['numero_protocolo'],
            'autor' => $sapl['autor'],
            'autores_ids' => $sapl['autores_ids'],
            'regime_tramitacao' => $sapl['regime_tramitacao'],
            'data_publicacao' => $sapl['data_publicacao'],
            'materia_polemica' => $sapl['materia_polemica'],
            'em_tramitacao' => $sapl['em_tramitacao'],
            'eh_complementar' => $sapl['eh_complementar'],
            'dados_extras_json' => json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

  /** @return array<int, array<string, string>> */
    private function numeracoesFromPost(array $post, array $data): array
    {
        $numero = trim((string) ($post['numero'] ?? $data['numero'] ?? ''));
        $ano = trim((string) ($post['ano'] ?? $data['ano'] ?? ''));
        if ($numero === '' && $ano === '') {
            return [];
        }
        return [[
            'tipo_materia' => (string) ($data['tipo_materia'] ?? ''),
            'numero' => $numero,
            'ano' => $ano,
            'data_doc' => (string) ($data['data_apresentacao'] ?? ''),
        ]];
    }

    private function rowHasContent(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function normalizePautaValue(string $field, string $value): ?string
    {
        $dateFields = [
            'data_doc',
            'data_tramitacao',
            'data_relatoria',
        ];
        $intFields = [
            'ano',
            'numero',
        ];

        if ($value === '' && in_array($field, $dateFields, true)) {
            return null;
        }

        if ($value === '' && in_array($field, $intFields, true)) {
            return null;
        }

        return $value;
    }

    public function parseSapl(): void
    {
        Permissao::exigir('pauta.parseSapl');
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Token invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $json = (string) ($_POST['sapl_json'] ?? '');
        echo json_encode(SaplMateriaImport::parse($json), JSON_UNESCAPED_UNICODE);
    }

    public function importar(int $sessaoId): void
    {
        Permissao::exigir('pauta.importar');
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

        SessaoBloqueio::abortSeBloqueada($sessao, base_url('?r=sessao/form/' . $sessaoId . '#nova-materia'));

        $parsed = SaplMateriaImport::parse((string) ($_POST['sapl_json'] ?? ''));
        if (!$parsed['ok']) {
            $msg = rawurlencode((string) ($parsed['error'] ?? 'Erro ao importar.'));
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&sapl_error=' . $msg . '#nova-materia'));
            exit;
        }

        $itens = SessaoItem::allBySessao($sessaoId);
        $postLike = $parsed['post'] ?? array_merge($parsed['item'], $parsed['extras']);
        $postLike['ordem'] = count($itens) + 1;
        $data = $this->buildDataFromPost($postLike, $sessaoId, static fn(): string => '');

        if ($data['titulo'] === '') {
            header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&sapl_error=' . rawurlencode('Título vazio após importação.') . '#nova-materia'));
            exit;
        }

        $savedId = SessaoItem::upsert($data, null);
        $numeracoes = $parsed['numeracoes'] ?? [];
        if ($numeracoes !== []) {
            $pdo = DB::conn();
            $order = 1;
            foreach ($numeracoes as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $values = [
                    'item_id' => $savedId,
                    'ordem' => $order++,
                    'tipo_materia' => trim((string) ($row['tipo_materia'] ?? '')),
                    'numero' => trim((string) ($row['numero'] ?? '')),
                    'ano' => trim((string) ($row['ano'] ?? '')) ?: null,
                    'data_doc' => $this->normalizePautaValue('data_doc', trim((string) ($row['data_doc'] ?? ''))),
                ];
                $stmt = $pdo->prepare(
                    'INSERT INTO pauta_numeracoes (item_id, ordem, tipo_materia, numero, ano, data_doc)
                     VALUES (:item_id, :ordem, :tipo_materia, :numero, :ano, :data_doc)'
                );
                $stmt->execute($values);
            }
        }

        app_log('[PautaController::importar] sessaoId=' . $sessaoId . ' item_id=' . $savedId . ' sapl_id=' . ($parsed['extras']['sapl_id'] ?? ''));

        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId . '&edit_id=' . $savedId . '&sapl_ok=1'));
        exit;
    }

    public function delete(int $sessaoId, int $id): void
    {
        Permissao::exigir('pauta.delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=sessao/form/' . $sessaoId));

        $item = SessaoItem::find($id);
        SessaoItem::delete($id);
        if ($item) {
            Auditoria::log('excluir', 'pauta_item', $id, (string) ($item['titulo'] ?? ''));
            SessaoEvento::registrar($sessaoId, 'pauta', 'Matéria removida: ' . (string) ($item['titulo'] ?? ''), $id);
        }
        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId));
    }

    public function reorder(int $sessaoId): void
    {
        Permissao::exigir('pauta.reorder');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        SessaoBloqueio::abortSeBloqueada(SessaoCrud::find($sessaoId), base_url('?r=sessao/form/' . $sessaoId));

        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            $ordem = 1;
            foreach ($ids as $rawId) {
                $itemId = (int) $rawId;
                if ($itemId > 0) {
                    $item = SessaoItem::find($itemId);
                    if ($item && (int) $item['sessao_id'] === $sessaoId) {
                        SessaoItem::upsert([
                            'sessao_id' => $sessaoId,
                            'ordem' => $ordem++,
                            'tipo_materia' => (string) ($item['tipo_materia'] ?? 'Matéria'),
                            'titulo' => (string) ($item['titulo'] ?? ''),
                            'ementa' => (string) ($item['ementa'] ?? ''),
                            'status' => 'PENDENTE',
                            'texto_original' => (string) ($item['texto_original'] ?? ''),
                            'tipo_apresentacao' => (string) ($item['tipo_apresentacao'] ?? ''),
                            'data_apresentacao' => $item['data_apresentacao'] ?? null,
                            'numero_protocolo' => (string) ($item['numero_protocolo'] ?? ''),
                            'autor' => (string) ($item['autor'] ?? ''),
                            'regime_tramitacao' => (string) ($item['regime_tramitacao'] ?? ''),
                            'data_publicacao' => $item['data_publicacao'] ?? null,
                            'materia_polemica' => (string) ($item['materia_polemica'] ?? 'Nao'),
                            'em_tramitacao' => (string) ($item['em_tramitacao'] ?? 'Nao'),
                            'eh_complementar' => (string) ($item['eh_complementar'] ?? 'Nao'),
                            'dados_extras_json' => $item['dados_extras_json'] ?? null,
                        ], $itemId);
                    }
                }
            }
        }

        header('Location: ' . base_url('?r=sessao/form/' . $sessaoId));
    }
}
