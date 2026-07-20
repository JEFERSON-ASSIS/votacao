<?php declare(strict_types=1); ?>
<?php $sessoes = $sessoes ?? []; ?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Histórico</div>
        <div class="panel-value">Linha do tempo das sessões</div>
        <div class="panel-meta">Eventos operacionais, votações encerradas e andamento registrado pelo sistema.</div>
    </article>
    <article class="panel-card" style="grid-column:1/-1;">
        <div class="table-wrap">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Sessão</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessoes as $s): ?>
                        <tr>
                            <td><?= e((string) $s['titulo']) ?> <span class="panel-meta">#<?= (int) $s['numero'] ?>/<?= (int) $s['ano'] ?></span></td>
                            <td><?= e(sessao_status_label((string) ($s['status'] ?? ''))) ?></td>
                            <td><a class="btn btn-soft btn--sm" href="<?= e(route_url('historico/sessao/' . (int) $s['id'])) ?>">Ver histórico</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Histórico';
$pageTitle = 'Histórico da sessão';
require __DIR__ . '/../layouts/admin.php';
