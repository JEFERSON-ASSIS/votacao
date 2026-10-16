<?php declare(strict_types=1); ?>
<?php
$anoSugerido = (int) ($anoSugerido ?? date('Y'));
$numeroSugerido = (int) ($numeroSugerido ?? 1);
$tituloSugerido = $numeroSugerido . 'ª SESSÃO ORDINÁRIA';
?>
<?php ob_start(); ?>
<section class="panel-grid sessao-criar-clara">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Nova sessão</div>
        <div class="panel-value">Criar nova sessão</div>
        <div class="panel-meta">
            Os dados seguem a sequência atual. Depois de criar, o roteiro padrão será gerado e aberto para edição.
        </div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;">
        <?php if (!empty($error)): ?>
            <div class="auth-error" role="alert" style="margin-bottom:16px;"><?= e((string) $error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(route_url('sessao/form')) ?>" class="panel-grid sessao-criar-clara-form" id="sessao-criar-form" data-history-return="<?= e(route_url('sessao/index')) ?>">
            <?= csrf_field() ?>

            <div>
                <label class="panel-label" for="sessao-titulo">Título</label>
                <input
                    class="field"
                    id="sessao-titulo"
                    name="titulo"
                    value="<?= e((string) ($_POST['titulo'] ?? $tituloSugerido)) ?>"
                    required
                    autofocus
                >
            </div>

            <div>
                <label class="panel-label" for="sessao-numero">Número</label>
                <input
                    class="field"
                    id="sessao-numero"
                    name="numero"
                    type="number"
                    min="1"
                    value="<?= e((string) ($_POST['numero'] ?? $numeroSugerido)) ?>"
                    required
                >
            </div>

            <div>
                <label class="panel-label" for="sessao-ano">Ano</label>
                <input
                    class="field"
                    id="sessao-ano"
                    name="ano"
                    type="number"
                    min="2000"
                    value="<?= e((string) ($_POST['ano'] ?? $anoSugerido)) ?>"
                    required
                >
            </div>

            <div>
                <label class="panel-label" for="sessao-status">Status</label>
                <input class="field" id="sessao-status" value="Criada — pronta para iniciar" readonly>
            </div>

            <div class="action-row sessao-criar-clara-actions">
                <button class="btn btn-primary" type="submit">Criar sessão</button>
                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/index')) ?>">Voltar</a>
            </div>
        </form>
    </article>
</section>
<script>
(function () {
    var form = document.getElementById('sessao-criar-form');
    if (!form || !window.history || !window.history.replaceState) return;
    form.addEventListener('submit', function () {
        window.history.replaceState(null, '', form.getAttribute('data-history-return'));
    });
})();
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Sessão';
$pageTitle = 'Sessão — Nova sessão';
require __DIR__ . '/../layouts/admin.php';
