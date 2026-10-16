<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$cerimonialItens = $cerimonialItens ?? [];
if (!$sessao || empty($sessao['id'])) {
    return;
}
$sessaoId = (int) $sessao['id'];
$sessaoBloqueada = sessao_esta_bloqueada($sessao);
$cerFlash = !empty($_GET['cer_ok']) || !empty($_GET['cer_erro']);
$qtdCerimonial = count($cerimonialItens);
?>
<article class="panel-card" id="cerimonial" style="grid-column:1/-1;">
    <details class="sessao-section-toggle" id="cerimonial-panel"<?= $cerFlash ? ' open' : '' ?>>
        <summary class="sessao-section-summary">
            <span class="sessao-section-icon" aria-hidden="true">▸</span>
            <span class="sessao-section-copy">
                <span class="sessao-section-title">Cerimonial da sessão</span>
                <span class="sessao-section-hint">
                    Clique para maximizar ou ocultar — tribuna, convidados e itens não legislativos
                    <?= $qtdCerimonial > 0 ? ' · ' . $qtdCerimonial . ' item(ns)' : '' ?>
                </span>
            </span>
        </summary>

        <div class="sessao-section-body">
            <div class="action-row" style="justify-content:space-between;align-items:flex-start;flex-wrap:wrap;">
                <div class="panel-meta">Tribuna, convidados e demais itens que não são matéria legislativa.</div>
                <?php if (user_can('sessao.exportarPauta')): ?>
                    <a class="btn btn-soft" href="<?= e(route_url('sessao/exportarPauta/' . $sessaoId)) ?>" target="_blank" rel="noopener">Exportar pauta</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($_GET['cer_ok'])): ?>
                <div class="sapl-ok" style="margin-top:10px;">Item cerimonial salvo.</div>
            <?php endif; ?>
            <?php if (!empty($_GET['cer_erro'])): ?>
                <div class="auth-error" style="margin-top:10px;">Informe o título do item cerimonial.</div>
            <?php endif; ?>

            <?php if ($cerimonialItens !== []): ?>
                <ul class="cerimonial-list" style="margin-top:14px;">
                    <?php foreach ($cerimonialItens as $c): ?>
                        <li class="cerimonial-list-item">
                            <strong><?= e((string) ($c['titulo'] ?? '')) ?></strong>
                            <span class="panel-meta"><?= e(\App\Models\SessaoCerimonial::TIPOS[$c['tipo'] ?? ''] ?? '') ?></span>
                            <?php if (!empty($c['nome_convidado'])): ?>
                                <div class="panel-meta"><?= e((string) $c['nome_convidado']) ?></div>
                            <?php endif; ?>
                            <?php if (!$sessaoBloqueada && user_can('sessao.cerimonialDelete')): ?>
                                <form method="post" action="<?= e(route_url('sessao/cerimonialDelete/' . $sessaoId . '/' . (int) $c['id'])) ?>" style="display:inline" onsubmit="return confirm('Excluir?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-soft btn--sm">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($sessaoBloqueada): ?>
                <div class="sessao-bloqueio-banner" style="margin-top:12px;"><?= e(sessao_bloqueio_motivo($sessao)) ?></div>
            <?php elseif (user_can('sessao.cerimonialSave')): ?>
                <form method="post" action="<?= e(route_url('sessao/cerimonialSave/' . $sessaoId)) ?>" class="panel-grid" style="margin-top:16px;grid-template-columns:repeat(2,minmax(0,1fr));">
                    <?= csrf_field() ?>
                    <div>
                        <label class="panel-label">Tipo</label>
                        <select class="field" name="tipo">
                            <?php foreach (\App\Models\SessaoCerimonial::TIPOS as $k => $lbl): ?>
                                <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="panel-label">Ordem</label>
                        <input class="field" type="number" name="ordem" value="<?= count($cerimonialItens) + 1 ?>" min="1">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="panel-label">Título</label>
                        <input class="field" name="titulo" required>
                    </div>
                    <div><label class="panel-label">Convidado</label><input class="field" name="nome_convidado"></div>
                    <div><label class="panel-label">Cargo</label><input class="field" name="cargo"></div>
                    <div style="grid-column:1/-1;"><label class="panel-label">Tema</label><textarea class="field" name="tema" rows="2"></textarea></div>
                    <div><label class="panel-label">Tempo (min)</label><input class="field" type="number" name="tempo_min" min="0"></div>
                    <div style="grid-column:1/-1;">
                        <button class="btn btn-primary" type="submit">Adicionar item cerimonial</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </details>
</article>
