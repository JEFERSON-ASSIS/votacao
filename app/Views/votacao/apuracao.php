<?php declare(strict_types=1); ?>
<?php $resultadoClass = $resultado === 'APROVADO' ? 'vote' : ($resultado === 'REJEITADO' ? 'closed' : 'warn'); ?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;">
            <div>
                <div class="panel-label">Apuração</div>
                <div class="panel-value"><?= e($item['titulo']) ?></div>
                <div class="panel-meta">Situação da matéria: <?= e($item['status']) ?></div>
            </div>
            <span class="status-chip <?= e($resultadoClass) ?>"><?= e($resultado) ?></span>
        </div>
    </article>

    <article class="panel-card"><div class="panel-label">Sim</div><div class="panel-value"><?= e($resumo['SIM']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Não</div><div class="panel-value"><?= e($resumo['NAO']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Abstenção</div><div class="panel-value"><?= e($resumo['ABSTENCAO']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Total</div><div class="panel-value"><?= e($total) ?></div></article>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="panel-label">Votos lançados</div>
        <div class="table-wrap" style="margin-top:14px;">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Vereador</th>
                        <th>Perfil</th>
                        <th>Voto</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($votos as $voto): ?>
                        <tr>
                            <td><?= e($voto['nome']) ?></td>
                            <td><?= e($voto['role']) ?></td>
                            <td><strong><?= e($voto['voto']) ?></strong></td>
                            <td><?= e($voto['observacao'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Apuração';
$pageTitle = 'Apuração';
require __DIR__ . '/../layouts/admin.php';
?>
