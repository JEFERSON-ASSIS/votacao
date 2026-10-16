<?php declare(strict_types=1); ?>
<?php
$roteiroStats = $roteiroStats ?? ['total' => 0, 'concluidos' => 0];
$sessoesConducao = $sessoesConducao ?? [];
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Presidência</div>
                <div class="panel-value"><?= e($sessao['titulo'] ?? 'Nenhuma sessão aberta') ?></div>
                <div class="panel-meta">
                    <?php if ($sessao): ?>
                        #<?= e((string) $sessao['numero']) ?>/<?= e((string) $sessao['ano']) ?> — <?= e(sessao_status_label((string) $sessao['status'])) ?>
                    <?php else: ?>
                        Quando o admin marcar a sessão como Criado, inicie-a em Sessões para operar a plenária.
                    <?php endif; ?>
                </div>
            </div>
            <div class="action-row">
                <?php if ($sessao && ($roteiroStats['total'] ?? 0) > 0 && sessao_pode_conduzir($sessao)): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $sessao['id'])) ?>">
                        Conduzir sessão (<?= (int) $roteiroStats['concluidos'] ?>/<?= (int) $roteiroStats['total'] ?>)
                    </a>
                <?php elseif ($sessao && sessao_esta_bloqueada($sessao)): ?>
                    <span class="status-chip closed">Sessão concluída</span>
                <?php elseif ($sessao): ?>
                    <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#roteiro')) ?>">Gerar roteiro na sessão</a>
                <?php endif; ?>
                <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/index')) ?>">Presença e painel</a>
            </div>
        </div>
    </article>

    <?php if ($sessoesConducao !== []): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-label">Sessões com roteiro</div>
            <div class="panel-meta" style="margin-bottom:10px;">Condução passo a passo (votação por passo do roteiro).</div>
            <ul class="conducao-sessao-list">
                <?php foreach ($sessoesConducao as $row): ?>
                    <?php $s = $row['sessao']; $st = $row['stats']; ?>
                    <li class="conducao-sessao-list-item">
                        <span>
                            <strong>#<?= e((string) $s['numero']) ?>/<?= e((string) $s['ano']) ?></strong>
                            <?= e((string) $s['titulo']) ?>
                            <span class="status-chip <?= e(sessao_status_chip_class((string) $s['status'])) ?>" style="margin-left:6px;"><?= e(sessao_status_label((string) $s['status'])) ?></span>
                        </span>
                        <span class="action-row">
                            <span class="panel-meta"><?= (int) $st['concluidos'] ?>/<?= (int) $st['total'] ?> passos</span>
                            <?php if (sessao_pode_conduzir($s)): ?>
                                <a class="btn btn-primary" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $s['id'])) ?>">Conduzir</a>
                            <?php else: ?>
                                <span class="status-chip closed">Concluída</span>
                            <?php endif; ?>
                            <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . (int) $s['id'] . '#roteiro')) ?>">Roteiro</a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endif; ?>

    <?php if ($sessao): ?>
        <?php foreach ($pauta as $item): ?>
            <?php $resumo = pauta_resumo($item); ?>
            <article class="panel-card" style="grid-column:1/-1;">
                <div class="action-row" style="justify-content:space-between;align-items:flex-start;">
                    <div>
                        <div class="panel-label">Item <?= e((string) $item['ordem']) ?></div>
                        <div class="panel-value" style="font-size:1.25rem;"><?= e((string) $item['titulo']) ?></div>
                        <?php if ($resumo !== ''): ?>
                            <div class="panel-meta"><?= e($resumo) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-label">Estado</div>
            <div class="panel-value">Sem sessão aberta</div>
            <div class="panel-meta">
                Em <a href="<?= e(base_url('?r=sessao/index')) ?>">Sessões</a>, crie ou edite uma sessão e defina o status como <strong>Aberta</strong>.
            </div>
        </article>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Presidência';
$pageTitle = 'Presidência';
require __DIR__ . '/../layouts/admin.php';
