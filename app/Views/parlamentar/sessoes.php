<?php declare(strict_types=1); ?>
<?php $sessoes = $sessoes ?? []; ?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Parlamentar</div>
        <div class="panel-value">Sessões plenárias</div>
        <div class="panel-meta">Acompanhe a sessão em tempo real: leitura da ata, pauta e votações quando o presidente abrir.</div>
    </article>
    <article class="panel-card" style="grid-column:1/-1;">
        <div class="table-wrap">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Sessão</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="parl-sessoes-tbody">
                    <?php if ($sessoes === []): ?>
                        <tr><td colspan="4" class="panel-meta">Nenhuma sessão disponível.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessoes as $s): ?>
                        <tr data-sessao-id="<?= (int) $s['id'] ?>">
                            <td><strong><?= e($s['titulo']) ?></strong><br><span class="panel-meta">#<?= (int) $s['numero'] ?>/<?= (int) $s['ano'] ?></span></td>
                            <td><?= e($s['tipo'] ?? '') ?></td>
                            <td>
                                <span
                                    class="status-chip <?= e(sessao_status_chip_class($s['status'] ?? '')) ?>"
                                    data-sessao-status="<?= (int) $s['id'] ?>"
                                ><?= e(sessao_status_label($s['status'] ?? '')) ?></span>
                            </td>
                            <td><a class="btn btn-primary btn--sm" href="<?= e(route_url('parlamentar/sessao/' . (int) $s['id'])) ?>">Acompanhar</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php $title = 'Sessões'; $pageTitle = 'Sessões'; require __DIR__ . '/../layouts/admin.php'; ?>
