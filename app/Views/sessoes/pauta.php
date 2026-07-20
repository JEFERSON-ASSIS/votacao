<?php declare(strict_types=1); ?>
<?php
$editingItem = $editingItem ?? null;

function pauta_extra_value(?array $item, string $key, string $default = ''): string
{
    if (!$item) {
        return $default;
    }
    $extras = [];
    if (!empty($item['dados_extras_json'])) {
        $decoded = json_decode((string) $item['dados_extras_json'], true);
        if (is_array($decoded)) {
            $extras = $decoded;
        }
    }
    return (string) ($extras[$key] ?? $default);
}

function pauta_tipo_options(): array
{
    return [
        'Projeto de Lei Ordinaria',
        'Projeto de Lei Complementar',
        'Projeto de Decreto Legislativo',
        'Projeto de Resolucao',
        'Requerimento',
        'Indicacao',
        'Mocao',
        'Mocao de Aplauso',
        'Mocao de Apoio',
        'Emenda',
    ];
}
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="pauta-builder">
            <div>
                <div class="panel-label">Sessão</div>
                <div class="panel-value"><?= e($sessao['titulo']) ?></div>
                <div class="panel-meta">#<?= e($sessao['numero']) ?>/<?= e($sessao['ano']) ?> — <?= e(sessao_status_label((string) $sessao['status'])) ?></div>
            </div>
            <div class="action-row">
                <a class="btn btn-primary" href="#nova-materia">Nova matéria</a>
                <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/index')) ?>">Painel de votação</a>
            </div>
        </div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="panel-label">Montagem da pauta</div>
        <div class="panel-meta">Abra uma linha, preencha e salve. Depois siga para a próxima matéria.</div>
        <div class="pauta-list" style="margin-top:18px;">
            <?php foreach ($itens as $index => $item): ?>
                <details class="pauta-item-card" <?= $editingItem && (int) $editingItem['id'] === (int) $item['id'] ? 'open' : '' ?>>
                    <summary class="pauta-item-head">
                        <div>
                            <div class="panel-label">Linha <?= e((string) ($index + 1)) ?></div>
                            <div class="panel-value" style="font-size:1rem;margin-top:4px;"><?= e($item['tipo_materia']) ?> - <?= e($item['titulo']) ?></div>
                            <div class="panel-meta"><?= e($item['ementa'] ?? '') ?></div>
                        </div>
                        <div class="action-row">
                            <span class="status-chip open"><?= e($item['em_tramitacao'] ?? 'Nao') ?></span>
                        </div>
                    </summary>

                    <div style="margin-top:18px;">
                        <form method="post" action="<?= e(base_url('?r=pauta/form/' . $sessao['id'] . '/' . (int) $item['id'])) ?>" class="matter-form" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="tabbar" data-tabbar>
                                <button type="button" class="tab-btn is-active" data-target="identificacao">Identificação básica</button>
                                <button type="button" class="tab-btn" data-target="outras">Outras informacoes</button>
                                <button type="button" class="tab-btn" data-target="despacho">Despacho inicial</button>
                                <button type="button" class="tab-btn" data-target="documentos">Documento acessório</button>
                                <button type="button" class="tab-btn" data-target="legislacao">Legislação citada</button>
                                <button type="button" class="tab-btn" data-target="numeracao">Numeração</button>
                                <button type="button" class="tab-btn" data-target="tramitacao">Tramitação</button>
                                <button type="button" class="tab-btn" data-target="relatoria">Relatoria</button>
                                <button type="button" class="tab-btn" data-target="origem">Origem externa</button>
                                <button type="button" class="tab-btn" data-target="arquivos">Arquivos</button>
                            </div>
                            <div class="tab-panels">
                                <div class="tab-panel is-active" data-panel="identificacao">
                                    <div class="modal-grid">
                                <div>
                                    <label class="panel-label">Ordem</label>
                                    <input class="field" name="ordem" type="number" value="<?= e($item['ordem']) ?>">
                                </div>
                                <div>
                                    <label class="panel-label">Tipo de matéria</label>
                                    <select class="field" name="tipo_materia">
                                        <?php foreach (pauta_tipo_options() as $opt): ?>
                                            <option value="<?= e($opt) ?>" <?= (($item['tipo_materia'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="wide-2">
                                    <label class="panel-label">Título</label>
                                    <input class="field" name="titulo" value="<?= e($item['titulo'] ?? '') ?>" required>
                                </div>
                                <div class="wide-3">
                                    <label class="panel-label">Ementa</label>
                                    <textarea class="field" name="ementa" rows="4"><?= e($item['ementa'] ?? '') ?></textarea>
                                </div>

                                <div><label class="panel-label">Tipo de apresentação</label><input class="field" name="tipo_apresentacao" value="<?= e($item['tipo_apresentacao'] ?? '') ?>"></div>
                                <div><label class="panel-label">Número do protocolo</label><input class="field" name="numero_protocolo" value="<?= e($item['numero_protocolo'] ?? '') ?>"></div>
                                <div><label class="panel-label">Data de apresentação</label><input class="field" type="date" name="data_apresentacao" value="<?= e($item['data_apresentacao'] ?? '') ?>"></div>
                                <div class="wide-2"><label class="panel-label">Autor</label><input class="field" name="autor" value="<?= e($item['autor'] ?? '') ?>"></div>
                                <div><label class="panel-label">Texto original</label><input class="field" name="texto_original" value="<?= e($item['texto_original'] ?? '') ?>"></div>
                                <div><label class="panel-label">Anexada</label><input class="field" name="anexada" value="<?= e(pauta_extra_value($item, 'anexada')) ?>"></div>
                                <div><label class="panel-label">Inicio</label><input class="field" type="date" name="inicio" value="<?= e(pauta_extra_value($item, 'inicio')) ?>"></div>
                                <div><label class="panel-label">Regime tramitação</label><input class="field" name="regime_tramitacao" value="<?= e($item['regime_tramitacao'] ?? '') ?>"></div>
                                <div><label class="panel-label">Data de publicação</label><input class="field" type="date" name="data_publicacao" value="<?= e($item['data_publicacao'] ?? '') ?>"></div>
                                </div>
                                <div class="tab-panel" data-panel="outras">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Matéria polêmica?</label><input class="field" name="materia_polemica" value="<?= e($item['materia_polemica'] ?? 'Nao') ?>"></div>
                                        <div><label class="panel-label">Em tramitação?</label><input class="field" name="em_tramitacao" value="<?= e($item['em_tramitacao'] ?? 'Nao') ?>"></div>
                                        <div><label class="panel-label">É complementar?</label><input class="field" name="eh_complementar" value="<?= e($item['eh_complementar'] ?? 'Nao') ?>"></div>
                                        <div><label class="panel-label">Assunto</label><input class="field" name="assunto" value="<?= e(pauta_extra_value($item, 'assunto')) ?>"></div>
                                        <div><label class="panel-label">Autoria</label><input class="field" name="autoria" value="<?= e(pauta_extra_value($item, 'autoria')) ?>"></div>
                                        <div><label class="panel-label">Despacho inicial</label><textarea class="field" name="despacho_inicial" rows="3"><?= e(pauta_extra_value($item, 'despacho_inicial')) ?></textarea></div>
                                        <div><label class="panel-label">Documento acessório</label><input class="field" name="documento_acessorio" value="<?= e(pauta_extra_value($item, 'documento_acessorio')) ?>"></div>
                                        <div><label class="panel-label">Legislação citada</label><input class="field" name="legislacao_citada" value="<?= e(pauta_extra_value($item, 'legislacao_citada')) ?>"></div>
                                        <div><label class="panel-label">Numeração</label><input class="field" name="numeracao" value="<?= e(pauta_extra_value($item, 'numeracao')) ?>"></div>
                                        <div><label class="panel-label">Tramitação</label><input class="field" name="tramitacao" value="<?= e(pauta_extra_value($item, 'tramitacao')) ?>"></div>
                                        <div><label class="panel-label">Relatoria</label><input class="field" name="relatoria" value="<?= e(pauta_extra_value($item, 'relatoria')) ?>"></div>
                                        <div class="wide-3"><label class="panel-label">Objeto</label><textarea class="field" name="objeto" rows="3"><?= e(pauta_extra_value($item, 'objeto')) ?></textarea></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="despacho">
                                    <div class="modal-grid">
                                        <div class="wide-3"><label class="panel-label">Despacho inicial</label><textarea class="field" name="despacho_inicial" rows="6"><?= e(pauta_extra_value($item, 'despacho_inicial')) ?></textarea></div>
                                        <div class="wide-3"><label class="panel-label">Arquivo do despacho inicial</label><input class="field" type="file" name="despacho_inicial_arquivo"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="documentos">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Documento acessório</label><input class="field" name="documento_acessorio" value="<?= e(pauta_extra_value($item, 'documento_acessorio')) ?>"></div>
                                        <div><label class="panel-label">Autor do documento</label><input class="field" name="documento_acessorio_autor" value="<?= e(pauta_extra_value($item, 'documento_acessorio_autor')) ?>"></div>
                                        <div><label class="panel-label">Data do documento</label><input class="field" type="date" name="documento_acessorio_data" value="<?= e(pauta_extra_value($item, 'documento_acessorio_data')) ?>"></div>
                                        <div class="wide-3"><label class="panel-label">Arquivo do documento acessório</label><input class="field" type="file" name="documento_acessorio_arquivo"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="legislacao">
                                    <div class="modal-grid">
                                        <div class="wide-3"><label class="panel-label">Legislação citada</label><textarea class="field" name="legislacao_citada" rows="5"><?= e(pauta_extra_value($item, 'legislacao_citada')) ?></textarea></div>
                                        <div class="wide-3"><label class="panel-label">Arquivo da legislação citada</label><input class="field" type="file" name="legislacao_citada_arquivo"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="numeracao">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Numeração</label><input class="field" name="numeracao" value="<?= e(pauta_extra_value($item, 'numeracao')) ?>"></div>
                                        <div><label class="panel-label">Tipo numeracao</label><input class="field" name="numeracao_tipo" value="<?= e(pauta_extra_value($item, 'numeracao_tipo')) ?>"></div>
                                        <div><label class="panel-label">Data numeracao</label><input class="field" type="date" name="numeracao_data" value="<?= e(pauta_extra_value($item, 'numeracao_data')) ?>"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="tramitacao">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Tramitação</label><input class="field" name="tramitacao" value="<?= e(pauta_extra_value($item, 'tramitacao')) ?>"></div>
                                        <div><label class="panel-label">Data tramitacao</label><input class="field" type="date" name="tramitacao_data" value="<?= e(pauta_extra_value($item, 'tramitacao_data')) ?>"></div>
                                        <div><label class="panel-label">Unidade origem</label><input class="field" name="tramitacao_origem" value="<?= e(pauta_extra_value($item, 'tramitacao_origem')) ?>"></div>
                                        <div><label class="panel-label">Unidade destino</label><input class="field" name="tramitacao_destino" value="<?= e(pauta_extra_value($item, 'tramitacao_destino')) ?>"></div>
                                        <div class="wide-3"><label class="panel-label">Arquivo da tramitacao</label><input class="field" type="file" name="tramitacao_arquivo"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="relatoria">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Relatoria</label><input class="field" name="relatoria" value="<?= e(pauta_extra_value($item, 'relatoria')) ?>"></div>
                                        <div><label class="panel-label">Relator</label><input class="field" name="relatoria_nome" value="<?= e(pauta_extra_value($item, 'relatoria_nome')) ?>"></div>
                                        <div><label class="panel-label">Data relatoria</label><input class="field" type="date" name="relatoria_data" value="<?= e(pauta_extra_value($item, 'relatoria_data')) ?>"></div>
                                        <div class="wide-3"><label class="panel-label">Arquivo da relatoria</label><input class="field" type="file" name="relatoria_arquivo"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="origem">
                                    <div class="modal-grid">
                                        <div><label class="panel-label">Origem tipo</label><input class="field" name="origem_tipo" value="<?= e(pauta_extra_value($item, 'origem_tipo')) ?>"></div>
                                        <div><label class="panel-label">Origem (número)</label><input class="field" name="origem_numero" value="<?= e(pauta_extra_value($item, 'origem_numero')) ?>"></div>
                                        <div><label class="panel-label">Origem ano</label><input class="field" name="origem_ano" value="<?= e(pauta_extra_value($item, 'origem_ano')) ?>"></div>
                                        <div class="wide-2"><label class="panel-label">Origem local</label><input class="field" name="origem_local" value="<?= e(pauta_extra_value($item, 'origem_local')) ?>"></div>
                                        <div><label class="panel-label">Origem data</label><input class="field" type="date" name="origem_data" value="<?= e(pauta_extra_value($item, 'origem_data')) ?>"></div>
                                    </div>
                                </div>
                                <div class="tab-panel" data-panel="arquivos">
                                    <div class="modal-grid">
                                        <div class="wide-3"><label class="panel-label">Documento acessório</label><input class="field" name="documento_acessorio_arquivo" type="file"></div>
                                        <div class="wide-3"><label class="panel-label">Texto original arquivo</label><input class="field" name="texto_original_arquivo" type="file"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="wide-3" style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/pauta/' . $sessao['id'])) ?>">Cancelar</a>
                                <button class="btn btn-primary" type="submit">Salvar matéria</button>
                            </div>
                        </form>
                        <div style="margin-top:12px;display:flex;gap:10px;justify-content:flex-end;">
                            <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/apuracao/' . $item['id'])) ?>">Apuração</a>
                            <form method="post" action="<?= e(base_url('?r=pauta/delete/' . $sessao['id'] . '/' . (int) $item['id'])) ?>" onsubmit="return confirm('Excluir esta matéria?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;" id="nova-materia">
        <details class="pauta-item-card" open>
            <summary class="pauta-item-head">
                <div>
                    <div class="panel-label">Nova matéria</div>
                    <div class="panel-value" style="font-size:1rem;margin-top:4px;">Adicionar nova linha na pauta</div>
                    <div class="panel-meta">Abra, preencha e siga para a proxima</div>
                </div>
            </summary>
            <div style="margin-top:18px;">
                <form method="post" action="<?= e(base_url('?r=pauta/form/' . $sessao['id'])) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="modal-grid">
                        <div>
                            <label class="panel-label">Ordem</label>
                            <input class="field" name="ordem" type="number" value="<?= e((string) (count($itens) + 1)) ?>">
                        </div>
                        <div>
                            <label class="panel-label">Tipo de matéria</label>
                            <select class="field" name="tipo_materia">
                                <?php foreach (pauta_tipo_options() as $opt): ?>
                                    <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="wide-2"><label class="panel-label">Título</label><input class="field" name="titulo" required></div>
                        <div class="wide-3"><label class="panel-label">Ementa</label><textarea class="field" name="ementa" rows="4"></textarea></div>

                        <div class="wide-3" style="margin-top:4px;"><div class="panel-label">Identificação básica</div></div>
                        <div><label class="panel-label">Tipo de apresentação</label><input class="field" name="tipo_apresentacao"></div>
                        <div><label class="panel-label">Número do protocolo</label><input class="field" name="numero_protocolo"></div>
                        <div><label class="panel-label">Data de apresentação</label><input class="field" type="date" name="data_apresentacao"></div>
                        <div class="wide-2"><label class="panel-label">Autor</label><input class="field" name="autor"></div>
                        <div><label class="panel-label">Texto original</label><input class="field" name="texto_original"></div>
                        <div><label class="panel-label">Anexada</label><input class="field" name="anexada"></div>
                        <div><label class="panel-label">Inicio</label><input class="field" type="date" name="inicio"></div>
                        <div><label class="panel-label">Regime tramitação</label><input class="field" name="regime_tramitacao"></div>
                        <div><label class="panel-label">Data de publicação</label><input class="field" type="date" name="data_publicacao"></div>

                        <div class="wide-3" style="margin-top:4px;"><div class="panel-label">Outras informacoes</div></div>
                        <div><label class="panel-label">Matéria polêmica?</label><input class="field" name="materia_polemica" value="Nao"></div>
                        <div><label class="panel-label">Em tramitação?</label><input class="field" name="em_tramitacao" value="Nao"></div>
                        <div><label class="panel-label">É complementar?</label><input class="field" name="eh_complementar" value="Nao"></div>
                        <div><label class="panel-label">Assunto</label><input class="field" name="assunto"></div>
                        <div><label class="panel-label">Autoria</label><input class="field" name="autoria"></div>
                        <div><label class="panel-label">Despacho inicial</label><textarea class="field" name="despacho_inicial" rows="3"></textarea></div>
                        <div><label class="panel-label">Documento acessório</label><input class="field" name="documento_acessorio"></div>
                        <div><label class="panel-label">Legislação citada</label><input class="field" name="legislacao_citada"></div>
                        <div><label class="panel-label">Numeração</label><input class="field" name="numeracao"></div>
                        <div><label class="panel-label">Tramitação</label><input class="field" name="tramitacao"></div>
                        <div><label class="panel-label">Relatoria</label><input class="field" name="relatoria"></div>
                        <div class="wide-3"><label class="panel-label">Objeto</label><textarea class="field" name="objeto" rows="3"></textarea></div>
                        <div><label class="panel-label">Origem tipo</label><input class="field" name="origem_tipo"></div>
                        <div><label class="panel-label">Origem (número)</label><input class="field" name="origem_numero"></div>
                        <div><label class="panel-label">Origem ano</label><input class="field" name="origem_ano"></div>
                        <div class="wide-2"><label class="panel-label">Origem local</label><input class="field" name="origem_local"></div>
                        <div><label class="panel-label">Origem data</label><input class="field" type="date" name="origem_data"></div>
                        <div class="wide-3" style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                            <button class="btn btn-primary" type="submit">Salvar matéria</button>
                        </div>
                    </div>
                </form>
            </div>
        </details>
    </article>
</section>
<script>
(function () {
    document.querySelectorAll('[data-tabbar]').forEach(function (tabbar) {
        var panelRoot = tabbar.parentElement.querySelector('.tab-panels');
        if (!panelRoot) return;
        var buttons = tabbar.querySelectorAll('[data-target]');
        var panels = panelRoot.querySelectorAll('[data-panel]');
        function activate(target) {
            buttons.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-target') === target);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
            });
        }
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activate(btn.getAttribute('data-target'));
            });
        });
    });
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Pauta';
$pageTitle = 'Pauta';
require __DIR__ . '/../layouts/admin.php';
