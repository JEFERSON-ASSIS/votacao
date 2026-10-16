<?php declare(strict_types=1); ?>
<?php
use App\Helpers\ConducaoRichText;
use App\Models\SessaoRoteiroAnexo;

$roteiro = $roteiro ?? [];
$passoAtual = $passoAtual ?? null;
$anexosPasso = $anexosPasso ?? [];
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

    return ConducaoRichText::plain($texto);
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
            <?php if ($roteiro !== [] && sessao_pode_conduzir($sessao)): ?>
                <a class="btn btn-primary" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $sessao['id'])) ?>">Conduzir sessão</a>
            <?php elseif ($sessaoBloqueada): ?>
                <span class="status-chip closed">Sessão concluída</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($_GET['roteiro_ok'])): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Roteiro gerado com <?= (int) $_GET['roteiro_ok'] ?> passo(s). Ajuste a ordem e os textos abaixo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_ok']) && $_GET['sessao_ok'] === 'criada'): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Sessão criada. Revise e ajuste o roteiro antes de conduzir.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['anexo_ok'])): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Anexo incluído neste passo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['anexo_excluido'])): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Anexo removido deste passo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['anexo_erro'])): ?>
        <div class="sapl-error" style="margin-bottom:12px;"><?= e((string) $_GET['anexo_erro']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['salvo'])): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Passo salvo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['novo'])): ?>
        <div class="sapl-ok roteiro-auto-dismiss" data-auto-dismiss="5000" style="margin-bottom:12px;">Novo passo adicionado. Preencha o conteúdo e salve.</div>
    <?php endif; ?>
    <?php if ($sessaoBloqueada): ?>
        <div class="sessao-bloqueio-banner" style="margin-bottom:12px;"><?= e(sessao_bloqueio_motivo($sessao)) ?> Somente visualização.</div>
    <?php endif; ?>

    <?php if ($roteiro === []): ?>
        <article class="panel-card">
            <p class="panel-meta">Nenhum passo disponível no roteiro.</p>
            <a class="btn btn-primary" href="<?= e(base_url('?r=sessao/index')) ?>" style="margin-top:12px;">Voltar às sessões</a>
        </article>
    <?php else: ?>
        <div class="roteiro-montar-layout">
            <aside class="roteiro-montar-aside">
                <div class="panel-label" style="margin-bottom:8px;">Ordem dos passos</div>
                <p class="panel-meta" style="margin:0 0 10px;font-size:11px;">Segure ⋮⋮ para reordenar. ⊘ = fora da condução. Textos iguais em blocos diferentes = uma cópia por matéria.</p>
                <?php if (!$sessaoBloqueada): ?>
                    <form method="post" action="<?= e(base_url('?r=roteiro/salvar/' . (int) $sessao['id'])) ?>" class="roteiro-add-step-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="criar_novo" value="1">
                        <button class="btn btn-primary btn-has-icon" type="submit">
                            <span class="icon-glyph" aria-hidden="true">+</span>
                            <span>Novo passo</span>
                        </button>
                    </form>
                <?php endif; ?>
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
                                    <?php if (!empty($passo['titulo']) || !empty($passo['pauta_titulo'])): ?>
                                        <span
                                            class="roteiro-sort-materia"
                                            <?= $isSelected ? 'id="passo-titulo-lateral" data-titulo-original="' . e((string) ($passo['pauta_titulo'] ?? '')) . '"' : '' ?>
                                        ><?= e((string) ($passo['titulo'] ?: $passo['pauta_titulo'])) ?></span>
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
                            <div class="roteiro-card-actions">
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
                                <form class="roteiro-delete-card-form" method="post" action="<?= e(base_url('?r=roteiro/excluir/' . (int) $sessao['id'] . '/' . $id)) ?>" onsubmit="return confirm('Excluir definitivamente este passo do roteiro?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="montar">
                                    <button class="roteiro-delete-card-btn" type="submit" aria-label="Excluir passo" title="Excluir passo">
                                        <span aria-hidden="true">&#128465;</span>
                                    </button>
                                </form>
                            </div>
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
                                <div class="conducao-fala"><?= ConducaoRichText::render((string) ($passoAtual['texto_fala'] ?? '')) ?></div>
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
                                <input type="checkbox" id="toggle-preview" checked> Pré-visualização
                            </label>
                        </div>

                        <div class="roteiro-meta-compact">
                            <div class="roteiro-meta-field roteiro-meta-field--grow">
                                <label class="panel-label">Título (opcional)</label>
                                <input class="field field--compact" name="titulo" id="passo-titulo" value="<?= e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['titulo'] ?? ''))) ?>" placeholder="Abertura, Ata…">
                            </div>
                            <div class="roteiro-meta-field">
                                <label class="panel-label">Tipo</label>
                                <select class="field field--compact" name="tipo_passo" id="tipo_passo">
                                    <option value="FALA" <?= (($passoAtual['tipo_passo'] ?? '') === 'FALA') ? 'selected' : '' ?>>Fala</option>
                                    <option value="INSTRUCAO" <?= (($passoAtual['tipo_passo'] ?? '') === 'INSTRUCAO') ? 'selected' : '' ?>>Instrução</option>
                                </select>
                            </div>
                            <label class="roteiro-meta-check panel-label">
                                <input type="checkbox" name="gera_votacao" id="gera-votacao" value="1" <?= !empty($passoAtual['gera_votacao']) ? 'checked' : '' ?>>
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
                            <div class="roteiro-rich-toolbar" role="toolbar" aria-label="Formatação do texto da fala">
                                <button type="button" data-rich-command="bold" aria-label="Negrito" title="Negrito"><strong>N</strong></button>
                                <button type="button" data-rich-command="italic" aria-label="Itálico" title="Itálico"><em>I</em></button>
                                <button type="button" data-rich-command="underline" aria-label="Sublinhado" title="Sublinhado"><u>S</u></button>
                                <span class="roteiro-rich-separator" aria-hidden="true"></span>
                                <button type="button" data-rich-command="insertUnorderedList" aria-label="Lista com marcadores" title="Lista com marcadores">• Lista</button>
                                <button type="button" data-rich-command="insertOrderedList" aria-label="Lista numerada" title="Lista numerada">1. Lista</button>
                                <span class="roteiro-rich-separator" aria-hidden="true"></span>
                                <button type="button" data-rich-command="justifyLeft" aria-label="Alinhar à esquerda" title="Alinhar à esquerda">Esquerda</button>
                                <button type="button" data-rich-command="justifyCenter" aria-label="Centralizar" title="Centralizar">Centro</button>
                                <button type="button" data-rich-command="justifyRight" aria-label="Alinhar à direita" title="Alinhar à direita">Direita</button>
                                <span class="roteiro-rich-separator" aria-hidden="true"></span>
                                <button type="button" data-rich-command="removeFormat" aria-label="Remover formatação" title="Remover formatação">Limpar formato</button>
                            </div>
                            <div
                                class="field roteiro-doc-editor roteiro-rich-editor"
                                id="texto_fala_editor"
                                contenteditable="true"
                                role="textbox"
                                aria-multiline="true"
                                data-placeholder="O que o presidente lê em voz alta…"
                            ><?= ConducaoRichText::editorHtml((string) ($passoAtual['texto_fala'] ?? '')) ?></div>
                            <textarea name="texto_fala" id="texto_fala" hidden><?= e((string) ($passoAtual['texto_fala'] ?? '')) ?></textarea>
                        </div>

                        <div class="roteiro-doc-block" id="bloco-instrucao">
                            <label class="panel-label" id="label-instrucao">Instrução (não ler em voz alta)</label>
                            <textarea class="field roteiro-doc-editor roteiro-doc-editor--muted" name="texto_instrucao" id="texto_instrucao" rows="1" data-auto-height placeholder="Ex.: Coloco em votação. Registre no painel quem votou."><?= e(\App\Helpers\ConducaoTexto::acentuarTextoPresidente((string) ($passoAtual['texto_instrucao'] ?? ''))) ?></textarea>
                        </div>

                        <article class="roteiro-preview-presidente" id="preview-presidente">
                            <div class="panel-label">Pré-visualização — condução</div>
                            <?php if (!empty($passoAtual['pauta_titulo'])): ?>
                                <div class="conducao-materia-ref" id="preview-materia"><?= e((string) $passoAtual['pauta_titulo']) ?></div>
                            <?php endif; ?>
                            <p class="conducao-fala" id="preview-fala"></p>
                            <p class="conducao-instrucao" id="preview-instrucao"></p>
                        </article>
                    </form>
                    <?php endif; ?>

                    <section class="roteiro-anexos" aria-labelledby="roteiro-anexos-titulo">
                        <div class="roteiro-anexos-head">
                            <div>
                                <h3 id="roteiro-anexos-titulo">Documentos e links do passo</h3>
                                <p class="panel-meta">O parlamentar verá estes materiais quando este passo estiver ativo.</p>
                            </div>
                            <span class="md-badge"><?= count($anexosPasso) ?> anexo(s)</span>
                        </div>
                        <?php if ($anexosPasso !== []): ?>
                            <div class="roteiro-anexos-lista">
                                <?php foreach ($anexosPasso as $anexo): ?>
                                    <?php $pub = SessaoRoteiroAnexo::publico($anexo); ?>
                                    <div class="roteiro-anexo-item">
                                        <?php if ($pub['imagem']): ?>
                                            <a href="<?= e($pub['href']) ?>" target="_blank" rel="noopener"><img src="<?= e($pub['href']) ?>" alt="<?= e($pub['titulo'] ?: $pub['nome']) ?>"></a>
                                        <?php endif; ?>
                                        <div class="roteiro-anexo-info">
                                            <strong><?= e($pub['titulo'] ?: ($pub['nome'] ?: 'Link externo')) ?></strong>
                                            <span><?= e($pub['tipo'] === 'URL' ? 'URL' : ($pub['imagem'] ? 'Imagem' : 'Documento')) ?></span>
                                        </div>
                                        <a class="btn btn-soft btn--sm" href="<?= e($pub['href']) ?>" target="_blank" rel="noopener">Abrir</a>
                                        <?php if (!$sessaoBloqueada): ?>
                                            <form method="post" action="<?= e(route_url('roteiro/anexoExcluir/' . (int) $sessao['id'] . '/' . $passoId . '/' . (int) $anexo['id'])) ?>" onsubmit="return confirm('Excluir este anexo?');">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-danger btn--sm" type="submit">Excluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$sessaoBloqueada): ?>
                            <form class="roteiro-anexo-form" method="post" enctype="multipart/form-data" action="<?= e(route_url('roteiro/anexoSalvar/' . (int) $sessao['id'] . '/' . $passoId)) ?>">
                                <?= csrf_field() ?>
                                <label><span class="panel-label">Título opcional</span><input class="field" name="titulo_anexo" maxlength="180" placeholder="Ex.: Projeto de Lei completo"></label>
                                <label><span class="panel-label">URL</span><input class="field" type="url" name="url" placeholder="https://..."></label>
                                <span class="roteiro-anexo-ou">ou</span>
                                <label><span class="panel-label">Documento ou imagem</span><input class="field" type="file" name="arquivo" accept=".pdf,.doc,.docx,.txt,.odt,.rtf,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.gif"></label>
                                <button class="btn btn-primary" type="submit">Adicionar</button>
                            </form>
                            <p class="panel-meta">Até 15 MB. Aceita PDF, Word, planilha, apresentação e imagens.</p>
                        <?php endif; ?>
                    </section>

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
                        <button class="btn btn-icon btn-primary" type="button" id="roteiro-salvar-passo" aria-label="Salvar alterações" title="Salvar alterações deste passo">
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
    document.querySelectorAll('.roteiro-auto-dismiss[data-auto-dismiss]').forEach(function (alerta) {
        var delay = parseInt(alerta.getAttribute('data-auto-dismiss'), 10) || 5000;
        window.setTimeout(function () {
            alerta.classList.add('is-hiding');
            window.setTimeout(function () {
                alerta.remove();
            }, 250);
        }, delay);
    });

    var list = document.getElementById('roteiro-sortable');
    var msg = document.getElementById('roteiro-order-msg');
    var csrfEl = document.getElementById('roteiro-csrf');
    var dragEl = null;
    var aside = document.querySelector('.roteiro-montar-aside');
    var routeParams = new URLSearchParams(window.location.search);
    var roteiroRoute = routeParams.get('r') || window.location.pathname;
    var scrollKey = 'roteiro-montar-aside-scroll:' + roteiroRoute;

    if (aside) {
        var savedScroll = sessionStorage.getItem(scrollKey);
        if (savedScroll !== null) {
            aside.scrollTop = parseInt(savedScroll, 10) || 0;
        }
        aside.addEventListener('scroll', function () {
            sessionStorage.setItem(scrollKey, String(aside.scrollTop));
        });
        aside.addEventListener('click', function (event) {
            if (event.target.closest('.roteiro-sort-link, form')) {
                sessionStorage.setItem(scrollKey, String(aside.scrollTop));
            }
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
    var falaEditor = document.getElementById('texto_fala_editor');
    var instr = document.getElementById('texto_instrucao');
    var blocoFala = document.getElementById('bloco-fala');
    var blocoInst = document.getElementById('bloco-instrucao');
    var prevFala = document.getElementById('preview-fala');
    var prevInst = document.getElementById('preview-instrucao');
    var prevBox = document.getElementById('preview-presidente');
    var togglePrev = document.getElementById('toggle-preview');
    var geraVotacao = document.getElementById('gera-votacao');
    var montarMain = document.querySelector('.roteiro-montar-main');
    var passoTitulo = document.getElementById('passo-titulo');
    var passoTituloLateral = document.getElementById('passo-titulo-lateral');

    if (passoTitulo && passoTituloLateral) {
        passoTitulo.addEventListener('input', function () {
            passoTituloLateral.textContent = passoTitulo.value.trim()
                || passoTituloLateral.getAttribute('data-titulo-original')
                || 'Passo sem título';
        });
    }

    function atualizarPreview() {
        if (!prevFala || !prevInst) return;
        var modo = tipo && tipo.value === 'INSTRUCAO' ? 'INSTRUCAO' : 'FALA';
        var txtFala = fala ? fala.value.trim() : '';
        var txtInst = instr ? instr.value.trim() : '';
        if (modo === 'INSTRUCAO') {
            prevFala.textContent = '';
            prevFala.style.display = 'none';
            prevInst.textContent = txtInst || (falaEditor ? falaEditor.innerText.trim() : txtFala);
            prevInst.style.display = txtInst || txtFala ? 'block' : 'none';
        } else {
            prevFala.innerHTML = falaEditor ? falaEditor.innerHTML : '';
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

    function atualizarModoVotacao() {
        var votacao = geraVotacao && geraVotacao.checked;
        if (montarMain) montarMain.classList.toggle('is-votacao', Boolean(votacao));
        if (tipo) tipo.disabled = Boolean(votacao);
        if (!votacao || !tipo) return;

        tipo.value = 'INSTRUCAO';
        if (togglePrev) togglePrev.checked = false;
        if (prevBox && togglePrev) prevBox.style.display = togglePrev.checked ? 'block' : 'none';
        atualizarBlocos();
    }

    if (tipo) tipo.addEventListener('change', atualizarBlocos);
    if (geraVotacao) {
        geraVotacao.addEventListener('change', function () {
            atualizarModoVotacao();
        });
    }
    function sincronizarFala() {
        if (fala && falaEditor) {
            fala.value = falaEditor.innerHTML.trim();
        }
    }

    if (falaEditor) {
        falaEditor.addEventListener('input', function () {
            sincronizarFala();
            atualizarPreview();
        });
        falaEditor.addEventListener('paste', function (event) {
            event.preventDefault();
            var texto = (event.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, texto);
        });
        document.querySelectorAll('[data-rich-command]').forEach(function (button) {
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                falaEditor.focus();
                document.execCommand(button.getAttribute('data-rich-command'), false, null);
                sincronizarFala();
                atualizarPreview();
            });
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
    atualizarModoVotacao();
    atualizarBlocos();
    sincronizarFala();
    var roteiroForm = document.getElementById('roteiro-edit-form');
    if (roteiroForm) roteiroForm.addEventListener('submit', sincronizarFala);
    var salvarPasso = document.getElementById('roteiro-salvar-passo');
    if (salvarPasso && roteiroForm) {
        salvarPasso.addEventListener('click', function () {
            sincronizarFala();
            if (typeof roteiroForm.requestSubmit === 'function') {
                roteiroForm.requestSubmit();
            } else {
                roteiroForm.submit();
            }
        });
    }
    window.addEventListener('resize', autoResizeAll);
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Montar roteiro';
$pageTitle = 'Montar roteiro — ' . ($sessao['titulo'] ?? '');
require __DIR__ . '/../layouts/admin.php';
