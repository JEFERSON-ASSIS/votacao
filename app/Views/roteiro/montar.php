<?php declare(strict_types=1); ?>
<?php
$roteiro = $roteiro ?? [];
$passoAtual = $passoAtual ?? null;
$sessao = $sessao ?? ['id' => 0, 'titulo' => ''];
$passoId = $passoAtual ? (int) $passoAtual['id'] : 0;
$passoNúmero = 0;
foreach ($roteiro as $i => $p) {
    if ((int) $p['id'] === $passoId) {
        $passoNúmero = $i + 1;
        break;
    }
}

function roteiro_grupo_label(array $passo): string
{
    $tipo = (string) ($passo['tipo_item'] ?? 'CONDUCAO');
    if ($tipo === 'SESSAO' || $tipo === 'TRIBUNA' || $tipo === 'ENCERRAMENTO') {
        return \App\Helpers\ConducaoTexto::acentuarTextoPresidente(trim((string) ($passo['titulo'] ?? $tipo)));
    }
    if (!empty($passo['pauta_titulo'])) {
        return (string) $passo['pauta_titulo'];
    }
    return trim((string) ($passo['titulo'] ?? 'Matéria'));
}

function roteiro_preview_texto(?array $passo): string
{
    if (!$passo) {
        return '';
    }
    $texto = '';
    if (($passo['tipo_passo'] ?? 'FALA') === 'INSTRUCAO') {
        $texto = trim((string) ($passo['texto_instrucao'] ?? $passo['texto_fala'] ?? ''));
    } else {
        $texto = trim((string) ($passo['texto_fala'] ?? ''));
    }

    return \App\Helpers\ConducaoTexto::acentuarTextoPresidente($texto);
}

$sessaoBloqueada = !empty($sessaoBloqueada) || (!empty($sessao) && sessao_esta_bloqueada($sessao));
?>
<?php ob_start(); ?>
<section class="roteiro-montar-page">
    <header class="roteiro-montar-top">
        <div>
            <div class="panel-label">Condução dos trabalhos — Montar roteiro</div>
            <div class="panel-value" style="font-size:1.05rem;"><?= e($sessao['titulo'] ?? '') ?></div>
            <div class="panel-meta">
                Arraste para ordenar. Clique para editar. Use <strong>Não usar</strong> (⊘) nos passos que não se aplicam
                (ex.: dispensa de comissões) — permanecem listados, mas a condução pula.
                <?php
                $totalPassos = count($roteiro);
                $passosAtivos = 0;
                foreach ($roteiro as $p) {
                    if (!isset($p['ativo']) || (int) $p['ativo'] === 1) {
                        $passosAtivos++;
                    }
                }
                ?>
                <?= $passosAtivos ?> de <?= $totalPassos ?> passo(s) na condução.
            </div>
        </div>
        <div class="action-row">
            <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#roteiro')) ?>">Voltar à sessão</a>
            <?php if ($roteiro !== [] && sessao_pode_conduzir($sessao)): ?>
                <a class="btn btn-primary" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $sessao['id'])) ?>">Conduzir sessão</a>
            <?php elseif ($sessaoBloqueada): ?>
                <span class="status-chip closed">Sessão concluída</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($_GET['roteiro_ok'])): ?>
        <div class="sapl-ok" style="margin-bottom:12px;">Roteiro gerado com <?= (int) $_GET['roteiro_ok'] ?> passo(s). Ajuste a ordem e os textos abaixo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['salvo'])): ?>
        <div class="sapl-ok" style="margin-bottom:12px;">Passo salvo.</div>
    <?php endif; ?>
    <?php if ($sessaoBloqueada): ?>
        <div class="sessao-bloqueio-banner" style="margin-bottom:12px;"><?= e(sessao_bloqueio_motivo($sessao)) ?> Somente visualização.</div>
    <?php endif; ?>

    <?php if ($roteiro === []): ?>
        <article class="panel-card">
            <p class="panel-meta">Nenhum passo no roteiro. Volte à sessão e clique em <strong>Gerar condução</strong>.</p>
            <a class="btn btn-primary" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#roteiro')) ?>" style="margin-top:12px;">Ir para a sessão</a>
        </article>
    <?php else: ?>
        <div class="roteiro-montar-layout">
            <aside class="roteiro-montar-aside">
                <div class="panel-label" style="margin-bottom:8px;">Ordem dos passos</div>
                <p class="panel-meta" style="margin:0 0 10px;font-size:11px;">Segure ⋮⋮ para reordenar. ⊘ = fora da condução. Textos iguais em blocos diferentes = uma cópia por matéria.</p>
                <ul class="roteiro-sortable<?= $sessaoBloqueada ? ' is-readonly' : '' ?>" id="roteiro-sortable"<?= $sessaoBloqueada ? '' : ' data-reorder-url="' . e(base_url('?r=roteiro/reordenar/' . (int) $sessao['id'])) . '"' ?>>
                    <?php $grupoAnterior = null; ?>
                    <?php foreach ($roteiro as $i => $passo): ?>
                        <?php
                        $id = (int) $passo['id'];
                        $isSelected = $passoId === $id;
                        $incluido = !isset($passo['ativo']) || (int) $passo['ativo'] === 1;
                        $preview = roteiro_preview_texto($passo);
                        $grupo = roteiro_grupo_label($passo);
                        ?>
                        <?php if ($grupo !== $grupoAnterior): ?>
                            <li class="roteiro-group-label"><?= e($grupo) ?></li>
                            <?php $grupoAnterior = $grupo; ?>
                        <?php endif; ?>
                        <li class="roteiro-sort-item<?= $isSelected ? ' is-active' : '' ?><?= !$incluido ? ' is-skipped' : '' ?>" data-id="<?= $id ?>">
                            <span class="roteiro-drag-handle" title="Arrastar" draggable="true">⋮⋮</span>
                            <a class="roteiro-sort-link" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'] . '&passo=' . $id)) ?>">
                                <span class="roteiro-sort-num"><?= $i + 1 ?></span>
                                <span class="roteiro-sort-body">
                                    <?php if (!empty($passo['pauta_titulo'])): ?>
                                        <span class="roteiro-sort-materia"><?= e((string) $passo['pauta_titulo']) ?></span>
                                    <?php elseif (!empty($passo['titulo'])): ?>
                                        <span class="roteiro-sort-materia"><?= e((string) $passo['titulo']) ?></span>
                                    <?php endif; ?>
                                    <span class="roteiro-sort-preview"><?= e(mb_strimwidth($preview, 0, 90, '…')) ?></span>
                                    <span class="roteiro-sort-tags">
                                        <?php if (!$incluido): ?>
                                            <span class="status-chip">Fora da condução</span>
                                        <?php endif; ?>
                                        <?php if (($passo['tipo_passo'] ?? '') === 'INSTRUCAO'): ?>
                                            <span class="status-chip">Instrução</span>
                                        <?php else: ?>
                                            <span class="status-chip open">Fala</span>
                                        <?php endif; ?>
                                        <?php if (!empty($passo['gera_votacao'])): ?>
                                            <span class="status-chip">Votação</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </a>
                            <?php if (!$sessaoBloqueada): ?>
                            <form class="roteiro-skip-form" method="post" action="<?= e(base_url('?r=roteiro/toggleAtivo/' . (int) $sessao['id'] . '/' . $id)) ?>" title="<?= $incluido ? 'Não usar nesta sessão' : 'Incluir na condução' ?>">
                                <?= csrf_field() ?>
                                <?php if ($incluido): ?>
                                    <input type="hidden" name="ativo" value="0">
                                    <button class="roteiro-skip-btn" type="submit" aria-label="Não usar">⊘</button>
                                <?php else: ?>
                                    <input type="hidden" name="ativo" value="1">
                                    <button class="roteiro-skip-btn roteiro-skip-btn--on" type="submit" aria-label="Incluir">✓</button>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="panel-meta roteiro-order-msg" id="roteiro-order-msg" style="margin-top:8px;min-height:1.2em;"></p>
                <input type="hidden" id="roteiro-csrf" value="<?= e(csrf_token()) ?>">
            </aside>

            <main class="roteiro-montar-main">
                <?php if ($passoAtual): ?>
                    <?php if ($sessaoBloqueada): ?>
                        <div class="roteiro-montar-toolbar">
                            <span class="panel-label">Passo <?= (int) $passoNúmero ?> de <?= count($roteiro) ?> (somente leitura)</span>
                        </div>
                        <article class="roteiro-preview-presidente">
                            <div class="panel-label">Texto na condução</div>
                            <?php if (!empty($passoAtual['pauta_titulo'])): ?>
                                <div class="conducao-materia-ref"><?= e((string) $passoAtual['pauta_titulo']) ?></div>
                            <?php endif; ?>
                            <?php if (trim((string) ($passoAtual['texto_fala'] ?? '')) !== ''): ?>
                                <p class="conducao-fala"><?= nl2br(e(roteiro_preview_texto($passoAtual))) ?></p>
                            <?php endif; ?>
                            <?php if (trim((string) ($passoAtual['texto_instrucao'] ?? '')) !== ''): ?>
                                <p class="conducao-instrucao"><?= nl2br(e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['texto_instrucao'] ?? '')))) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php else: ?>
                    <form method="post" action="<?= e(base_url('?r=roteiro/salvar/' . (int) $sessao['id'] . '/' . $passoId)) ?>" id="roteiro-edit-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="montar">
                        <input type="hidden" name="passo_id" value="<?= $passoId ?>">
                        <input type="hidden" name="ordem" value="<?= e((string) $passoAtual['ordem']) ?>">

                        <div class="roteiro-montar-toolbar">
                            <span class="panel-label">Passo <?= (int) $passoNúmero ?> de <?= count($roteiro) ?></span>
                            <label class="roteiro-toggle-preview">
                                <input type="checkbox" id="toggle-preview" checked> Preview
                            </label>
                        </div>

                        <div class="roteiro-meta-compact">
                            <div class="roteiro-meta-field roteiro-meta-field--grow">
                                <label class="panel-label">Título (opcional)</label>
                                <input class="field field--compact" name="titulo" value="<?= e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['titulo'] ?? ''))) ?>" placeholder="Abertura, Ata…">
                            </div>
                            <div class="roteiro-meta-field">
                                <label class="panel-label">Tipo</label>
                                <select class="field field--compact" name="tipo_passo" id="tipo_passo">
                                    <option value="FALA" <?= (($passoAtual['tipo_passo'] ?? '') === 'FALA') ? 'selected' : '' ?>>Fala</option>
                                    <option value="INSTRUCAO" <?= (($passoAtual['tipo_passo'] ?? '') === 'INSTRUCAO') ? 'selected' : '' ?>>Instrução</option>
                                </select>
                            </div>
                            <label class="roteiro-meta-check panel-label">
                                <input type="checkbox" name="gera_votacao" value="1" <?= !empty($passoAtual['gera_votacao']) ? 'checked' : '' ?>>
                                Votação
                            </label>
                            <?php $passoIncluido = !isset($passoAtual['ativo']) || (int) $passoAtual['ativo'] === 1; ?>
                            <label class="roteiro-meta-check panel-label roteiro-meta-check--highlight">
                                <input type="checkbox" name="ativo" value="1" <?= $passoIncluido ? 'checked' : '' ?>>
                                Na condução
                            </label>
                        </div>
                        <p class="panel-meta roteiro-incluir-hint">Desmarque se este passo não se aplica nesta sessão (ex.: &ldquo;Dispenso a reunião das Comissões&rdquo;). O texto permanece; a presidência não passa por ele.</p>

                        <div class="roteiro-doc-block" id="bloco-fala">
                            <label class="panel-label">Texto da fala</label>
                            <textarea class="field roteiro-doc-editor" name="texto_fala" id="texto_fala" rows="1" data-auto-height placeholder="O que o presidente lê em voz alta…"><?= e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['texto_fala'] ?? ''))) ?></textarea>
                        </div>

                        <div class="roteiro-doc-block" id="bloco-instrucao">
                            <label class="panel-label" id="label-instrucao">Instrução (não ler em voz alta)</label>
                            <textarea class="field roteiro-doc-editor roteiro-doc-editor--muted" name="texto_instrucao" id="texto_instrucao" rows="1" data-auto-height placeholder="Ex.: Coloco em votação. Registre no painel quem votou."><?= e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['texto_instrucao'] ?? ''))) ?></textarea>
                        </div>

                        <article class="roteiro-preview-presidente" id="preview-presidente">
                            <div class="panel-label">Preview — condução</div>
                            <?php if (!empty($passoAtual['pauta_titulo'])): ?>
                                <div class="conducao-materia-ref" id="preview-materia"><?= e((string) $passoAtual['pauta_titulo']) ?></div>
                            <?php endif; ?>
                            <p class="conducao-fala" id="preview-fala"></p>
                            <p class="conducao-instrucao" id="preview-instrucao"></p>
                        </article>
                    </form>
                    <?php endif; ?>

                    <?php
                    $idx = 0;
                    foreach ($roteiro as $i => $p) {
                        if ((int) $p['id'] === $passoId) {
                            $idx = $i;
                            break;
                        }
                    }
                    ?>
                    <footer class="action-row roteiro-form-actions">
                        <div class="action-row roteiro-form-actions-nav">
                            <?php if ($idx > 0): ?>
                                <a class="btn btn-icon btn-soft" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'] . '&passo=' . (int) $roteiro[$idx - 1]['id'])) ?>" aria-label="Passo anterior" title="Passo anterior">
                                    <span class="icon-glyph" aria-hidden="true">&#8592;</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($idx < count($roteiro) - 1): ?>
                                <a class="btn btn-icon btn-soft" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'] . '&passo=' . (int) $roteiro[$idx + 1]['id'])) ?>" aria-label="Próximo passo" title="Próximo passo">
                                    <span class="icon-glyph" aria-hidden="true">&#8594;</span>
                                </a>
                            <?php endif; ?>
                            <?php if (!$sessaoBloqueada): ?>
                            <form method="post" action="<?= e(base_url('?r=roteiro/excluir/' . (int) $sessao['id'] . '/' . $passoId)) ?>" class="roteiro-remove-form" onsubmit="return confirm('Remover esta etapa desta sessão? Ela deixará de existir no roteiro.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="montar">
                                <button class="btn btn-icon btn-danger" type="submit" aria-label="Remover desta sessão esta etapa" title="Remover desta sessão esta etapa">
                                    <span class="icon-glyph" aria-hidden="true">&#128465;</span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!$sessaoBloqueada): ?>
                        <button class="btn btn-icon btn-primary" type="submit" form="roteiro-edit-form" aria-label="Salvar alterações" title="Salvar alterações deste passo">
                            <span class="icon-glyph" aria-hidden="true">&#10003;</span>
                        </button>
                        <?php endif; ?>
                    </footer>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</section>
<script>
(function () {
    var list = document.getElementById('roteiro-sortable');
    var msg = document.getElementById('roteiro-order-msg');
    var csrfEl = document.getElementById('roteiro-csrf');
    var dragEl = null;
    var aside = document.querySelector('.roteiro-montar-aside');
    var scrollKey = 'roteiro-montar-aside-scroll:' + window.location.pathname + window.location.search;

    if (aside) {
        var savedScroll = sessionStorage.getItem(scrollKey);
        if (savedScroll !== null) {
            aside.scrollTop = parseInt(savedScroll, 10) || 0;
        }
        aside.addEventListener('scroll', function () {
            sessionStorage.setItem(scrollKey, String(aside.scrollTop));
        });
    }

    if (list && !list.classList.contains('is-readonly')) {
        list.querySelectorAll('.roteiro-skip-form').forEach(function (form) {
            form.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });
        list.addEventListener('dragstart', function (e) {
            if (!e.target.closest('.roteiro-drag-handle')) {
                e.preventDefault();
                return;
            }
            var item = e.target.closest('.roteiro-sort-item');
            if (!item) {
                e.preventDefault();
                return;
            }
            dragEl = item;
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        list.addEventListener('dragend', function () {
            if (dragEl) dragEl.classList.remove('is-dragging');
            dragEl = null;
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('.roteiro-sort-item');
            if (!over || !dragEl || over === dragEl) return;
            var rect = over.getBoundingClientRect();
            var after = e.clientY > rect.top + rect.height / 2;
            list.insertBefore(dragEl, after ? over.nextSibling : over);
        });
        list.addEventListener('drop', function (e) {
            e.preventDefault();
            var ids = Array.from(list.querySelectorAll('.roteiro-sort-item')).map(function (el) {
                return el.getAttribute('data-id');
            });
            Array.from(list.querySelectorAll('.roteiro-sort-num')).forEach(function (num, i) {
                num.textContent = String(i + 1);
            });
            if (!msg) return;
            msg.textContent = 'Salvando ordem...';
            var body = new FormData();
            body.append('csrf_token', csrfEl ? csrfEl.value : '');
            ids.forEach(function (id) { body.append('ids[]', id); });
            fetch(list.getAttribute('data-reorder-url'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    msg.textContent = data.ok ? 'Ordem salva.' : (data.error || 'Erro ao salvar ordem.');
                    msg.style.color = data.ok ? '#afbdd5' : '#ffd6dc';
                })
                .catch(function () {
                    msg.textContent = 'Falha ao salvar a ordem.';
                    msg.style.color = '#ffd6dc';
                });
        });
    }

    var tipo = document.getElementById('tipo_passo');
    var fala = document.getElementById('texto_fala');
    var instr = document.getElementById('texto_instrucao');
    var blocoFala = document.getElementById('bloco-fala');
    var blocoInst = document.getElementById('bloco-instrucao');
    var prevFala = document.getElementById('preview-fala');
    var prevInst = document.getElementById('preview-instrucao');
    var prevBox = document.getElementById('preview-presidente');
    var togglePrev = document.getElementById('toggle-preview');

    function atualizarPreview() {
        if (!prevFala || !prevInst) return;
        var modo = tipo && tipo.value === 'INSTRUCAO' ? 'INSTRUCAO' : 'FALA';
        var txtFala = fala ? fala.value.trim() : '';
        var txtInst = instr ? instr.value.trim() : '';
        if (modo === 'INSTRUCAO') {
            prevFala.textContent = '';
            prevFala.style.display = 'none';
            prevInst.textContent = txtInst || txtFala;
            prevInst.style.display = txtInst || txtFala ? 'block' : 'none';
        } else {
            prevFala.textContent = txtFala;
            prevFala.style.display = txtFala ? 'block' : 'none';
            prevInst.textContent = txtInst;
            prevInst.style.display = txtInst ? 'block' : 'none';
        }
    }

    var labelInst = document.getElementById('label-instrucao');

    function autoResizeTextarea(el) {
        if (!el || !el.hasAttribute('data-auto-height')) return;
        el.style.height = 'auto';
        var max = Math.max(120, Math.floor(window.innerHeight * 0.32));
        el.style.height = Math.min(el.scrollHeight + 2, max) + 'px';
        el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
    }

    function autoResizeAll() {
        autoResizeTextarea(fala);
        autoResizeTextarea(instr);
    }

    function atualizarBlocos() {
        if (!tipo || !blocoFala || !blocoInst) return;
        var inst = tipo.value === 'INSTRUCAO';
        blocoFala.style.display = inst ? 'none' : 'block';
        blocoInst.style.display = 'block';
        if (labelInst) {
            labelInst.textContent = inst
                ? 'Texto da instrução (presidente vê)'
                : 'Instrução (não ler em voz alta)';
        }
        atualizarPreview();
        autoResizeAll();
    }

    if (tipo) tipo.addEventListener('change', atualizarBlocos);
    if (fala) {
        fala.addEventListener('input', function () {
            autoResizeTextarea(fala);
            atualizarPreview();
        });
    }
    if (instr) {
        instr.addEventListener('input', function () {
            autoResizeTextarea(instr);
            atualizarPreview();
        });
    }
    if (togglePrev && prevBox) {
        togglePrev.addEventListener('change', function () {
            prevBox.style.display = togglePrev.checked ? 'block' : 'none';
        });
    }
    atualizarBlocos();
    window.addEventListener('resize', autoResizeAll);
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Montar roteiro';
$pageTitle = 'Montar roteiro — ' . ($sessao['titulo'] ?? '');
require __DIR__ . '/../layouts/admin.php';
