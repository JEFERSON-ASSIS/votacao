<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
<?php
$sessoes = $sessoes ?? [];
$podeCriarEditar = user_can('sessao.form');
$podeExcluir = user_can('sessao.delete');
$podeConduzir = user_can('presidencia.conduzir');
?>
<section class="panel-grid">
    <?php if (($_GET['conducao_encerrada'] ?? '') === '1'): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-meta">Sessão encerrada. A condução ficou disponível apenas para consulta administrativa.</div>
        </article>
    <?php endif; ?>

    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;">
            <div>
                <div class="panel-label">SESSÕES</div>
                <div class="panel-value"><?= $podeCriarEditar ? 'Cadastro e controle' : 'Sessões para condução' ?></div>
                <div class="panel-meta"><?= $podeCriarEditar ? 'Clique em nova sessão para abrir a página completa de montagem.' : 'Acompanhe as sessões preparadas e acesse a condução quando houver roteiro disponível.' ?></div>
            </div>
            <?php if ($podeCriarEditar): ?>
                <a class="btn btn-primary" href="<?= e(base_url('?r=sessao/form')) ?>">Nova sessão</a>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="table-wrap">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Referência</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessoes as $s): ?>
                        <?php $sessaoPodeConduzir = $podeConduzir && strtoupper((string) ($s['status'] ?? '')) !== 'ENCERRADA'; ?>
                        <tr>
                            <td><strong><?= e($s['titulo']) ?></strong></td>
                            <td>#<?= e($s['numero']) ?>/<?= e($s['ano']) ?></td>
                            <td><span class="status-chip <?= e(sessao_status_chip_class((string) $s['status'])) ?>"><?= e(sessao_status_label((string) $s['status'])) ?></span></td>
                            <td>
                                <div class="action-row">
                                    <?php if ($sessaoPodeConduzir): ?>
                                        <a class="btn btn-soft btn--sm" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $s['id'])) ?>">Conduzir</a>
                                    <?php endif; ?>
                                    <?php if ($podeCriarEditar): ?>
                                        <a class="btn btn-icon btn-soft" href="<?= e(base_url('?r=sessao/form/' . $s['id'])) ?>" aria-label="Abrir sessão" title="Abrir sessão">
                                            <span class="icon-glyph" aria-hidden="true">&#8599;</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($podeExcluir): ?>
                                        <form method="post" action="<?= e(base_url('?r=sessao/delete/' . $s['id'])) ?>" class="js-delete-session" data-session-title="<?= e($s['titulo']) ?>" style="display:inline-flex;">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-icon btn-danger" type="submit" aria-label="Excluir sessão" title="Excluir sessão">
                                                <span class="icon-glyph" aria-hidden="true">&#128465;</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php if ($podeExcluir): ?>
<div class="modal-overlay is-hidden" id="delete-session-modal" aria-hidden="true">
    <div class="modal-card modal-card-confirm" role="dialog" aria-modal="true" aria-labelledby="delete-session-title">
        <div class="modal-head">
            <div>
                <div class="panel-label">Excluir sessão</div>
                <div class="panel-value" id="delete-session-title" style="font-size:1.35rem;">Confirmar exclusão</div>
                <div class="panel-meta confirm-copy" id="delete-session-copy">Esta ação remove a sessão e toda a pauta vinculada.</div>
            </div>
        </div>
        <div class="action-row confirm-actions">
            <button class="btn btn-soft" type="button" id="delete-session-cancel">Cancelar</button>
            <button class="btn btn-danger" type="button" id="delete-session-confirm">Excluir sessão</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('delete-session-modal');
    const copy = document.getElementById('delete-session-copy');
    const confirmButton = document.getElementById('delete-session-confirm');
    const cancelButton = document.getElementById('delete-session-cancel');
    let activeForm = null;

    if (!modal || !copy || !confirmButton || !cancelButton) {
        return;
    }

    function closeModal() {
        modal.classList.add('is-hidden');
        modal.setAttribute('aria-hidden', 'true');
        activeForm = null;
    }

    document.querySelectorAll('.js-delete-session').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            activeForm = form;
            const sessionTitle = form.getAttribute('data-session-title') || 'esta sessão';
            copy.textContent = 'Você vai excluir "' + sessionTitle + '" e toda a pauta vinculada. Esta ação não pode ser desfeita.';
            modal.classList.remove('is-hidden');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('is-hidden')) {
            closeModal();
        }
    });
    confirmButton.addEventListener('click', function () {
        if (activeForm) {
            activeForm.submit();
        }
    });
});
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Sessões';
$pageTitle = 'Sessões';
require __DIR__ . '/../layouts/admin.php';
