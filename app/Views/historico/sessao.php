<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$timeline = $timeline ?? [];
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Histórico da sessão</div>
                <div class="panel-value"><?= e((string) ($sessao['titulo'] ?? '')) ?></div>
                <div class="panel-meta">#<?= (int) ($sessao['numero'] ?? 0) ?>/<?= (int) ($sessao['ano'] ?? 0) ?></div>
            </div>
            <div class="action-row">
                <?php if (user_can('sessao.exportarPauta')): ?>
                    <a class="btn btn-soft" href="<?= e(route_url('sessao/exportarPauta/' . (int) $sessao['id'])) ?>" target="_blank" rel="noopener">Exportar pauta</a>
                <?php endif; ?>
                <a class="btn btn-soft" href="<?= e(route_url('historico/index')) ?>">Voltar</a>
            </div>
        </div>
    </article>
    <article class="panel-card" style="grid-column:1/-1;">
        <?php if ($timeline === []): ?>
            <p class="panel-meta">Nenhum evento registrado ainda nesta sessão.</p>
        <?php else: ?>
            <ul class="historico-timeline">
                <?php foreach ($timeline as $ev): ?>
                    <li class="historico-timeline-item historico-timeline-item--<?= e((string) ($ev['categoria'] ?? 'evento')) ?>">
                        <time class="historico-timeline-quando">
                            <?php
                            $q = (string) ($ev['quando'] ?? '');
                            echo $q !== '' ? e(date('d/m/Y H:i', strtotime($q) ?: time())) : '—';
                            ?>
                        </time>
                        <div class="historico-timeline-corpo">
                            <strong><?= e((string) ($ev['titulo'] ?? '')) ?></strong>
                            <?php if (!empty($ev['meta'])): ?>
                                <span class="panel-meta"><?= e((string) $ev['meta']) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Histórico — ' . ($sessao['titulo'] ?? '');
$pageTitle = 'Linha do tempo';
require __DIR__ . '/../layouts/admin.php';
