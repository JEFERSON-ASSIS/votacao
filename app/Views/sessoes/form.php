<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$itens = $itens ?? [];
$editingItem = $editingItem ?? null;
$itemsSectionRows = $itemsSectionRows ?? [];
$activeTab = $activeTab ?? '';
$sessaoBloqueada = !empty($sessao) && sessao_esta_bloqueada($sessao);
$pautaEnabled = !empty($sessao['id']) && !$sessaoBloqueada;

function pauta_tipo_options_sessao(): array
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

function sessao_status_options(): array
{
    return [
        'RASCUNHO' => 'Rascunho',
        'ABERTA' => 'Aberta',
        'EM_VOTACAO' => 'Em Votação',
        'ENCERRADA' => 'Encerrada',
    ];
}

function pauta_extra_value_sessao(?array $item, string $key, string $default = ''): string
{
    if (!$item || empty($item['dados_extras_json'])) {
        return $default;
    }
    $decoded = json_decode((string) $item['dados_extras_json'], true);
    if (!is_array($decoded)) {
        return $default;
    }
    return (string) ($decoded[$key] ?? $default);
}
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label"><?= $sessao ? 'SESSÃO' : 'NOVA SESSÃO' ?></div>
        <div class="panel-value"><?= $sessao ? 'Editar sessão' : 'Criar nova sessão' ?></div>
        <div class="panel-meta">
            <?php if ($sessaoBloqueada): ?>
                <?= e(sessao_bloqueio_motivo($sessao)) ?>
            <?php elseif ($sessao): ?>
                Edite a sessão e monte a pauta completa nesta mesma página.
            <?php else: ?>
                Depois de salvar, você continua aqui montando toda a sessão e as matérias.
            <?php endif; ?>
        </div>
    </article>

    <?php if ($sessaoBloqueada): ?>
        <div class="sessao-bloqueio-banner" style="grid-column:1/-1;"><?= e(sessao_bloqueio_motivo($sessao)) ?></div>
    <?php endif; ?>

    <article class="panel-card" style="grid-column:1/-1;">
        <?php if (!empty($error)): ?><div class="auth-error" style="margin-bottom:16px;"><?= e($error) ?></div><?php endif; ?>
        <?php if (!$sessaoBloqueada): ?>
        <form method="post" class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
            <?= csrf_field() ?>
            <div><label class="panel-label">Título</label><input class="field" name="titulo" value="<?= e($sessao['titulo'] ?? '') ?>" required></div>
            <div><label class="panel-label">Número</label><input class="field" name="numero" type="number" value="<?= e($sessao['numero'] ?? 1) ?>"></div>
            <div><label class="panel-label">Ano</label><input class="field" name="ano" type="number" value="<?= e($sessao['ano'] ?? date('Y')) ?>"></div>
            <div style="grid-column:1/-1;">
                <label class="panel-label">Status</label>
                <select class="field" name="status">
                    <?php $sessaoStatus = (string) ($sessao['status'] ?? 'RASCUNHO'); ?>
                    <?php foreach (sessao_status_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $sessaoStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;" class="action-row">
                <button class="btn btn-primary" type="submit">Salvar sessão</button>
                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/index')) ?>">Voltar</a>
            </div>
        </form>
        <?php else: ?>
            <div class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                <div><span class="panel-label">Título</span><div class="panel-value" style="font-size:1rem;"><?= e($sessao['titulo'] ?? '') ?></div></div>
                <div><span class="panel-label">Número / Ano</span><div class="panel-value" style="font-size:1rem;"><?= e((string) ($sessao['numero'] ?? '')) ?>/<?= e((string) ($sessao['ano'] ?? '')) ?></div></div>
                <div style="grid-column:1/-1;"><span class="panel-label">Status</span><div class="panel-value" style="font-size:1rem;"><?= e(sessao_status_label((string) ($sessao['status'] ?? ''))) ?></div></div>
            </div>
            <div class="action-row" style="margin-top:12px;">
                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/index')) ?>">Voltar</a>
                <?php if ($sessao && user_can('sessao.exportarPauta')): ?>
                    <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/exportarPauta/' . (int) $sessao['id'])) ?>" target="_blank" rel="noopener">Exportar pauta</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>

    <?php if ($sessao): ?>
        <?php require __DIR__ . '/_ata_sessao.php'; ?>
        <?php require __DIR__ . '/_cerimonial.php'; ?>
    <?php endif; ?>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="panel-label">Montagem da pauta</div>
        <div class="panel-meta">
            <?= !empty($sessao) ? 'Abra uma matéria, preencha e salve.' : 'Crie ou carregue a sessão para montar as matérias nesta mesma tela.' ?>
        </div>
        <?php if (!empty($_GET['sapl_ok'])): ?>
            <div class="sapl-ok" style="margin-top:10px;">Matéria importada do SAPL. Revise os dados na linha aberta abaixo.</div>
        <?php endif; ?>

        <div class="pauta-list" style="margin-top:18px;">
            <?php if (!$pautaEnabled && $sessaoBloqueada): ?>
                <p class="panel-meta" style="margin-top:12px;">Pauta bloqueada — somente consulta das matérias abaixo.</p>
            <?php endif; ?>
            <details class="pauta-item-card" id="nova-materia"<?= $pautaEnabled ? '' : ' style="opacity:.75"' ?>>
                <summary class="pauta-item-head">
                    <div>
                        <div class="panel-label">Nova matéria</div>
                        <div class="panel-value" style="font-size:1rem;margin-top:4px;">Adicionar nova linha na pauta</div>
                        <div class="panel-meta">Abra, preencha e siga para a próxima — ou importe JSON do SAPL.</div>
                    </div>
                </summary>
                <div style="margin-top:18px;">
                    <?php if (!empty($_GET['sapl_error'])): ?>
                        <div class="auth-error" style="margin-bottom:12px;"><?= e((string) $_GET['sapl_error']) ?></div>
                    <?php endif; ?>
                    <div class="sapl-import-box">
                        <div class="panel-label">Importar do SAPL</div>
                        <div class="panel-meta">Cole o JSON da matéria (copiado da API ou do sistema SAPL).</div>
                        <textarea class="field" id="sapl-json-import" rows="8" placeholder='{"id": 465, "__str__": "Projeto de Resolucao nº 1/2026", ...}' style="margin-top:8px;font-family:Consolas,monospace;font-size:11px;"></textarea>
                        <div class="action-row" style="margin-top:8px;">
                            <button class="btn btn-soft" type="button" id="sapl-preencher">Preencher formulário</button>
                            <?php if ($pautaEnabled): ?>
                                <button class="btn btn-primary" type="button" id="sapl-salvar-direto">Importar e salvar</button>
                            <?php endif; ?>
                        </div>
                        <div id="sapl-import-msg" class="panel-meta" style="margin-top:8px;"></div>
                    </div>

                    <form id="sapl-import-form" method="post" action="<?= e(base_url('?r=pauta/importar/' . ($sessao['id'] ?? 0))) ?>" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sapl_json" id="sapl-json-hidden">
                    </form>

                    <form method="post" action="<?= e(base_url('?r=pauta/form/' . ($sessao['id'] ?? 0))) ?>" enctype="multipart/form-data" class="matter-form" id="matter-form-nova">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pauta_sections" value="1">
                        <input type="hidden" name="active_tab" value="" data-active-tab>
                        <fieldset <?= $pautaEnabled ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;">

                        <?php
                        $item = null;
                        $ordemDefault = count($itens) + 1;
                        $sectionRows = [];
                        require __DIR__ . '/../pauta/_form_materia_sapl.php';
                        ?>

                        <div class="wide-3" style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                            <button class="btn btn-soft" type="button" data-reset-form>Limpar</button>
                            <button class="btn btn-primary" type="submit">Salvar matéria</button>
                        </div>
                        </fieldset>
                    </form>
                </div>
            </details>

            <?php foreach ($itens as $index => $item): ?>
                <details class="pauta-item-card" <?= $editingItem && (int) $editingItem['id'] === (int) $item['id'] ? 'open' : '' ?>>
                    <summary class="pauta-item-head">
                        <div>
                            <div class="panel-label">Linha <?= e((string) ($index + 1)) ?></div>
                            <div class="panel-value" style="font-size:1rem;margin-top:4px;"><?= e($item['tipo_materia']) ?> - <?= e($item['titulo']) ?></div>
                            <div class="panel-meta"><?= e($item['ementa'] ?? '') ?></div>
                        </div>
                        <?php if ($pautaEnabled): ?>
                        <div class="action-row">
                            <form method="post" action="<?= e(base_url('?r=pauta/delete/' . ($sessao['id'] ?? 0) . '/' . (int) $item['id'])) ?>" onsubmit="return confirm('Excluir esta matéria?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-danger" type="submit" title="Excluir matéria">🗑</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </summary>
                    <div style="margin-top:18px;">
                        <form method="post" action="<?= e(base_url('?r=pauta/form/' . ($sessao['id'] ?? 0) . '/' . (int) $item['id'])) ?>" class="matter-form" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="pauta_sections" value="1">
                            <input type="hidden" name="active_tab" value="" data-active-tab>
                            <fieldset <?= $pautaEnabled ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;">
                            <?php
                            $ordemDefault = (int) $item['ordem'];
                            $sectionRows = $itemsSectionRows[(int) $item['id']] ?? [];
                            require __DIR__ . '/../pauta/_form_materia_sapl.php';
                            ?>

                            <div class="wide-3" style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
                                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . ($sessao['id'] ?? 0))) ?>">Cancelar</a>
                                <?php if ($pautaEnabled): ?>
                                <button class="btn btn-primary" type="submit">Salvar matéria</button>
                                <?php endif; ?>
                            </div>
                            </fieldset>
                        </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </article>

    <?php require __DIR__ . '/_roteiro.php'; ?>
</section>
<script>
(function () {
    var urlTab = new URLSearchParams(window.location.search).get('tab') || '';
    var tabAliases = { identificacao: 'sapl', outras: 'sapl' };
    if (tabAliases[urlTab]) urlTab = tabAliases[urlTab];

    document.querySelectorAll('[data-tabbar]').forEach(function (tabbar) {
        var form = tabbar.closest('form');
        var panelRoot = tabbar.parentElement.querySelector('.tab-panels');
        if (!panelRoot) return;
        var buttons = tabbar.querySelectorAll('[data-target]');
        var panels = panelRoot.querySelectorAll('[data-panel]');
        var first = buttons[0] ? buttons[0].getAttribute('data-target') : null;
        var current = first;

        function activate(target) {
            if (!target) return;
            current = target;
            buttons.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-target') === target);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
            });
            if (form) {
                var hidden = form.querySelector('[data-active-tab]');
                if (hidden) hidden.value = target;
            }
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activate(btn.getAttribute('data-target'));
            });
        });

        var initial = urlTab;
        if (initial && tabbar.querySelector('[data-target="' + initial + '"]')) {
            activate(initial);
        } else if (first) {
            activate(first);
        }
    });

    function renumberRepeatList(list) {
        Array.from(list.querySelectorAll('.repeat-item')).forEach(function (item, index) {
            item.querySelectorAll('input, textarea, select').forEach(function (el) {
                var name = el.getAttribute('name');
                if (!name) return;
                el.setAttribute('name', name.replace(/\[\d+\]/, '[' + index + ']'));
            });
        });
    }

    document.querySelectorAll('[data-repeat-list]').forEach(function (list) {
        list.addEventListener('click', function (ev) {
            var add = ev.target.closest('[data-add-row]');
            var remove = ev.target.closest('[data-remove-row]');
            if (add) {
                var row = list.querySelector('.repeat-item');
                if (!row) return;
                var clone = row.cloneNode(true);
                clone.querySelectorAll('input, textarea, select').forEach(function (el) {
                    el.value = '';
                });
                list.appendChild(clone);
                renumberRepeatList(list);
            }
            if (remove) {
                var item = remove.closest('.repeat-item');
                if (!item) return;
                if (list.querySelectorAll('.repeat-item').length <= 1) {
                    item.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
                    return;
                }
                item.remove();
                renumberRepeatList(list);
            }
        });
    });

    document.querySelectorAll('[data-reset-form]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            if (form) form.reset();
        });
    });

    var novaMateriaForm = document.getElementById('matter-form-nova');
    var saplTextarea = document.getElementById('sapl-json-import');
    var saplMsg = document.getElementById('sapl-import-msg');
    var parseUrl = <?= json_encode(base_url('?r=pauta/parseSapl'), JSON_UNESCAPED_UNICODE) ?>;

    function setSaplMsg(text, isError) {
        if (!saplMsg) return;
        saplMsg.textContent = text;
        saplMsg.style.color = isError ? '#ffd6dc' : '#afbdd5';
    }

    function setField(form, name, value) {
        if (!form || value === undefined || value === null) return;
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return;
        if (el.tagName === 'SELECT') {
            var found = false;
            Array.from(el.options).forEach(function (opt) {
                if (opt.value === value) {
                    opt.selected = true;
                    found = true;
                }
            });
            if (!found && value) {
                var opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                opt.selected = true;
                el.appendChild(opt);
            }
        } else {
            el.value = value;
        }
    }

    function setCheckbox(form, name, on) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el && el.type === 'checkbox') el.checked = !!on;
    }

    function preencherFormularioSapl(data) {
        if (!novaMateriaForm || !data.item) return;
        var post = data.post || {};
        var item = data.item;
        var saplFields = [
            'tipo_sapl', 'tipo_materia', 'numero', 'ano', 'sapl_id', 'titulo', 'ementa',
            'numero_protocolo', 'data_apresentacao', 'tipo_apresentacao', 'regime_tramitacao_id',
            'data_publicacao', 'apelido', 'indexacao', 'observacao', 'objeto', 'resultado',
            'texto_original', 'link_detail_backend', 'dias_prazo', 'data_fim_prazo',
            'tipo_origem_externa', 'numero_origem_externa', 'ano_origem_externa',
            'data_origem_externa', 'local_origem_externa', 'autor', 'autores_ids', 'autoria'
        ];
        saplFields.forEach(function (name) {
            var val = post[name];
            if (val === undefined || val === null) {
                val = item[name];
            }
            if (val !== undefined && val !== null && val !== '') {
                setField(novaMateriaForm, name, String(val));
            }
        });
        setCheckbox(novaMateriaForm, 'em_tramitacao', post.em_tramitacao === '1' || item.em_tramitacao === 'Sim');
        setCheckbox(novaMateriaForm, 'polemica', post.polemica === '1' || item.materia_polemica === 'Sim');
        setCheckbox(novaMateriaForm, 'complementar', post.complementar === '1' || item.eh_complementar === 'Sim');
        if (data.numeracoes && data.numeracoes[0]) {
            var n = data.numeracoes[0];
            setField(novaMateriaForm, 'numeracoes[0][tipo_materia]', n.tipo_materia);
            setField(novaMateriaForm, 'numeracoes[0][numero]', n.numero);
            setField(novaMateriaForm, 'numeracoes[0][ano]', n.ano);
            setField(novaMateriaForm, 'numeracoes[0][data_doc]', n.data_doc || '');
        }
        var details = document.getElementById('nova-materia');
        if (details) details.open = true;
    }

    function parseSaplJson(callback) {
        var raw = saplTextarea ? saplTextarea.value.trim() : '';
        if (!raw) {
            setSaplMsg('Cole o JSON da matéria.', true);
            return;
        }
        var tokenInput = novaMateriaForm ? novaMateriaForm.querySelector('input[name="csrf_token"]') : null;
        var body = new FormData();
        body.append('sapl_json', raw);
        body.append('csrf_token', tokenInput ? tokenInput.value : '');
        setSaplMsg('Processando...', false);
        fetch(parseUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    setSaplMsg(data.error || 'Erro ao interpretar JSON.', true);
                    return;
                }
                setSaplMsg('Dados reconhecidos: ' + (data.item.titulo || 'matéria'), false);
                callback(data);
            })
            .catch(function () {
                setSaplMsg('Falha na comunicacao com o servidor.', true);
            });
    }

    var btnPreencher = document.getElementById('sapl-preencher');
    if (btnPreencher) {
        btnPreencher.addEventListener('click', function () {
            parseSaplJson(preencherFormularioSapl);
        });
    }

    var btnSalvar = document.getElementById('sapl-salvar-direto');
    var formImport = document.getElementById('sapl-import-form');
    var hiddenJson = document.getElementById('sapl-json-hidden');
    if (btnSalvar && formImport && hiddenJson) {
        btnSalvar.addEventListener('click', function () {
            var raw = saplTextarea ? saplTextarea.value.trim() : '';
            if (!raw) {
                setSaplMsg('Cole o JSON antes de salvar.', true);
                return;
            }
            if (!confirm('Criar matéria na pauta com os dados do SAPL?')) return;
            hiddenJson.value = raw;
            formImport.submit();
        });
    }

    if (window.location.hash === '#nova-materia') {
        var d = document.getElementById('nova-materia');
        if (d) d.open = true;
    }
    <?php if (!empty($_GET['sapl_error'])): ?>
    (function () {
        var d = document.getElementById('nova-materia');
        if (d) d.open = true;
    })();
    <?php endif; ?>
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Sessão';
$pageTitle = 'Sessão — ' . ($sessao['titulo'] ?? 'Nova sessão');
require __DIR__ . '/../layouts/admin.php';
