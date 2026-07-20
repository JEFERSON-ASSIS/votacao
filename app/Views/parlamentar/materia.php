<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? [];
$item = $item ?? [];
$sections = $sections ?? [];
$podeVotar = (bool) ($podeVotar ?? false);
$meuVoto = $meuVoto ?? null;
$rodadaAberta = $rodadaAberta ?? null;
$votoOk = (bool) ($votoOk ?? false);
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent parl-materia-hero" style="grid-column:1/-1;">
        <div class="parl-materia-hero-top">
            <div>
                <div class="panel-label">Item <?= (int) $item['ordem'] ?> · Sessão</div>
                <div class="panel-value parl-materia-hero-title"><?= e((string) $item['titulo']) ?></div>
                <div class="parl-materia-hero-badges">
                    <?php if (!empty($item['tipo_materia'])): ?>
                        <span class="md-badge md-badge--tipo"><?= e((string) $item['tipo_materia']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['numero']) && !empty($item['ano'])): ?>
                        <span class="md-badge md-badge--numero">nº <?= (int) $item['numero'] ?>/<?= (int) $item['ano'] ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['autor'])): ?>
                        <span class="md-badge"><?= e((string) $item['autor']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn-soft" href="<?= e(route_url('parlamentar/sessao/' . (int) $sessao['id'])) ?>">← Acompanhar sessão</a>
        </div>
    </article>

    <?php if ($votoOk): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-meta" style="color:var(--accent-2);">Seu voto foi registrado: <strong><?= e(votacao_rotulo_voto((string) $meuVoto)) ?></strong></div>
        </article>
    <?php endif; ?>

    <?php
    $itemRodadaId = (int) $item['id'];
    $itemVotacao = $item;
    $votoOk = $votoOk ?? false;
    require __DIR__ . '/../partials/_parlamentar_voto_box.php';
    ?>

    <?php require __DIR__ . '/../partials/materia_detalhe_completo.php'; ?>
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
    document.querySelectorAll('[data-materia-doc-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-materia-doc-toggle');
            var panel = document.getElementById('materia-doc-' + id);
            if (!panel) return;
            panel.classList.toggle('is-hidden');
            var aberto = !panel.classList.contains('is-hidden');
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            btn.textContent = aberto ? 'Ocultar' : 'Ver aqui';
        });
    });
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = (string) $item['titulo'];
$pageTitle = 'Matéria';
require __DIR__ . '/../layouts/admin.php';
