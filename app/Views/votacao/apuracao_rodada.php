<?php declare(strict_types=1); ?>
<?php
$resultadoClass = $resultado === 'APROVADO' ? 'vote' : ($resultado === 'REJEITADO' ? 'closed' : 'warn');
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Apuração da votação</div>
                <div class="panel-value"><?= e((string) $rodada['titulo']) ?></div>
                <div class="panel-meta"><?= e($sessao['titulo'] ?? '') ?> — <?= e((string) ($rodada['status'] ?? '')) ?></div>
                <?php if (!empty($rodada['descricao'])): ?>
                    <div class="panel-meta" style="margin-top:8px;"><?= e((string) $rodada['descricao']) ?></div>
                <?php endif; ?>
            </div>
            <span class="status-chip <?= e($resultadoClass) ?>"><?= e(votacao_rotulo_resultado($resultado)) ?></span>
        </div>
    </article>

    <article class="panel-card"><div class="panel-label">Sim</div><div class="panel-value voto-sim-text"><?= e($resumo['SIM']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Não</div><div class="panel-value voto-nao-text"><?= e($resumo['NAO']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Abstenção</div><div class="panel-value"><?= e($resumo['ABSTENCAO']) ?></div></article>
    <article class="panel-card"><div class="panel-label">Total</div><div class="panel-value"><?= e($total) ?></div></article>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="panel-label">Votos por vereador</div>
        <ul class="votacao-vereador-grid votacao-vereador-grid--registrados" style="margin-top:12px;">
            <?php foreach ($votos as $voto): ?>
                <?php
                $uCard = [
                    'nome' => $voto['nome'] ?? '',
                    'partido' => $voto['partido'] ?? '',
                    'foto' => $voto['foto'] ?? '',
                ];
                $fotoUrl = user_foto_url($uCard);
                ?>
                <li class="votacao-vereador-card votacao-vereador-card--votou">
                    <div class="votacao-vereador-foto">
                        <?php if ($fotoUrl): ?>
                            <img src="<?= e($fotoUrl) ?>" alt="" width="56" height="56" loading="lazy">
                        <?php else: ?>
                            <span class="votacao-vereador-iniciais"><?= e(user_iniciais((string) $uCard['nome'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="votacao-vereador-info">
                        <strong class="votacao-vereador-nome"><?= e((string) $uCard['nome']) ?></strong>
                        <span class="votacao-vereador-partido"><?= e(trim((string) $uCard['partido']) !== '' ? (string) $uCard['partido'] : '—') ?></span>
                    </div>
                    <span class="voto-chip votacao-vereador-voto <?= e(votacao_classe_voto((string) $voto['voto'])) ?>">
                        <?= e(votacao_rotulo_voto((string) $voto['voto'])) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="action-row" style="margin-top:14px;">
            <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/painel/' . (int) ($sessao['id'] ?? 0) . '&rodada=' . (int) $rodada['id'])) ?>">Voltar ao painel</a>
            <?php if (sessao_pode_conduzir($sessao ?? null)): ?>
                <a class="btn btn-soft" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) ($sessao['id'] ?? 0))) ?>">Conduzir sessão</a>
            <?php endif; ?>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Apuração';
$pageTitle = 'Apuração — ' . ($rodada['titulo'] ?? '');
require __DIR__ . '/../layouts/admin.php';
