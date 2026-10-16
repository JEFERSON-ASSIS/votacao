<?php declare(strict_types=1); ?>
<?php
$itens = $itens ?? [];
$item = $item ?? null;
$editingItem = $editingItem ?? null;
$sessao = $sessao ?? ['id' => 0, 'titulo' => ''];
$sectionRows = $sectionRows ?? [
    'despachos' => [],
    'documentos' => [],
    'legislacoes' => [],
    'numeracoes' => [],
    'tramitacoes' => [],
    'relatorias' => [],
];

function pauta_extra_value_form(?array $item, string $key, string $default = ''): string
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

function pauta_rows(array $rows, array $defaults = []): array
{
    return !empty($rows) ? $rows : [$defaults];
}
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;padding:8px 14px;">
        <div class="pauta-builder">
            <div>
                <span class="panel-label" style="display:inline;"><?= $item ? 'Editar matéria' : 'Nova matéria' ?></span>
                <span class="panel-meta" style="margin:0 0 0 8px;display:inline;"><?= e($sessao['titulo']) ?></span>
            </div>
            <?php if ($item): ?>
                <form method="post" action="<?= e(base_url('?r=pauta/delete/' . (int) $item['sessao_id'] . '/' . (int) $item['id'])) ?>" onsubmit="return confirm('Excluir esta matéria?');">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger" type="submit" title="Excluir matéria" aria-label="Excluir matéria">
                        <span class="icon-glyph" aria-hidden="true">&#128465;</span> Excluir
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;">
        <?php if (!empty($error)): ?><div class="auth-error" style="margin-bottom:16px;"><?= e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="matter-form">
            <?= csrf_field() ?>
            <input type="hidden" name="pauta_sections" value="1">
            <input type="hidden" name="active_tab" value="" data-active-tab>
            <?php
            $ordemDefault = (int) ($item['ordem'] ?? 1);
            require __DIR__ . '/_form_materia_sapl.php';
            ?>

            <div class="wide-3" style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;">
                <button class="btn btn-soft" type="button" data-reset-form>Limpar campos</button>
                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . $sessao['id'])) ?>">Voltar</a>
                <button class="btn btn-primary" type="submit">Salvar matéria</button>
            </div>
        </form>
    </article>
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

        function activate(target) {
            if (!target) return;
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
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Item de pauta';
$pageTitle = 'Item de pauta';
require __DIR__ . '/../layouts/admin.php';
