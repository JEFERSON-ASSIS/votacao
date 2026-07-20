<?php declare(strict_types=1); ?>
<?php
$registros = $registros ?? [];
$filtro = $filtro ?? '';
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Auditoria</div>
        <div class="panel-value">Trilha de alterações</div>
        <div class="panel-meta">Registro de quem executou ações relevantes no sistema.</div>
    </article>
    <?php if (empty($schemaOk)): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="auth-error">
                Tabela de auditoria ainda não existe no banco. No SSH, execute:
                <code style="display:block;margin-top:8px;">php scripts/migrate_historico_auditoria.php</code>
                ou <code>php scripts/migrate_all.php</code>
            </div>
        </article>
    <?php endif; ?>
    <article class="panel-card" style="grid-column:1/-1;">
        <form method="get" class="action-row" style="margin-bottom:14px;">
            <input type="hidden" name="r" value="auditoria/index">
            <input class="field" name="entidade" value="<?= e($filtro) ?>" placeholder="Filtrar por entidade (ex: sessao, pauta_item)">
            <button class="btn btn-soft" type="submit">Filtrar</button>
        </form>
        <div class="table-wrap">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Entidade</th>
                        <th>Detalhe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) ($r['created_at'] ?? '')) ?: time())) ?></td>
                            <td><?= e((string) ($r['user_nome'] ?? '—')) ?></td>
                            <td><?= e((string) ($r['acao'] ?? '')) ?></td>
                            <td><?= e((string) ($r['entidade'] ?? '')) ?><?= !empty($r['entidade_id']) ? ' #' . (int) $r['entidade_id'] : '' ?></td>
                            <td><?= e((string) ($r['detalhe'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Auditoria';
$pageTitle = 'Auditoria';
require __DIR__ . '/../layouts/admin.php';
