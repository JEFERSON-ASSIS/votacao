<?php declare(strict_types=1); ?>
<?php
$roteiro = $roteiro ?? [];
$roteiroStats = $roteiroStats ?? ['total' => 0, 'concluidos' => 0];
$sessao = $sessao ?? null;
$itens = $itens ?? [];
$conducaoConfigurada = $conducaoConfigurada ?? true;
$qtdMaterias = count($itens);
$confirmGerar = !empty($roteiro) || ($roteiroStats['total'] ?? 0) > 0;
$totalPassos = count($roteiro);
$passosAtivos = 0;
foreach ($roteiro as $p) {
    if (!isset($p['ativo']) || (int) $p['ativo'] === 1) {
        $passosAtivos++;
    }
}
$concluidos = (int) ($roteiroStats['concluidos'] ?? 0);
$totalConducao = (int) ($roteiroStats['total'] ?? 0);
$progressoPct = $totalConducao > 0 ? (int) round(($concluidos / $totalConducao) * 100) : 0;
$temRoteiro = !empty($roteiro);
$sessaoBloqueada = !empty($sessao) && sessao_esta_bloqueada($sessao);
$podePublicar = !empty($sessao) && sessao_pode_publicar($sessao, $roteiroStats);
?>
<article class="panel-card roteiro-panel" id="roteiro" style="grid-column:1/-1;">
    <header class="roteiro-panel-head">
        <div class="roteiro-panel-intro">
            <div class="panel-label">Condução dos trabalhos</div>
            <p class="roteiro-panel-lead">
                Roteiro do presidente: abertura, ata, matérias da pauta e encerramento.
            </p>
        </div>
        <?php if ($temRoteiro && $totalConducao > 0): ?>
            <div class="roteiro-panel-progress" title="Progresso na condução ao vivo">
                <span class="roteiro-panel-progress-label"><?= $concluidos ?>/<?= $totalConducao ?></span>
                <div class="roteiro-panel-progress-track" role="progressbar" aria-valuenow="<?= $progressoPct ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="roteiro-panel-progress-fill" style="width:<?= $progressoPct ?>%"></div>
                </div>
                <span class="roteiro-panel-progress-hint">concluidos</span>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($_GET['roteiro_ok'])): ?>
        <div class="sapl-ok roteiro-panel-alert">Roteiro gerado com <?= (int) $_GET['roteiro_ok'] ?> passo(s).
            <a href="<?= e(base_url('?r=roteiro/montar/' . (int) ($sessao['id'] ?? 0))) ?>">Montar roteiro</a>
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['roteiro_vazio'])): ?>
        <div class="auth-error roteiro-panel-alert">Nenhum passo criado. Confira a pauta e os modelos de condução.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['roteiro_erro'])): ?>
        <div class="auth-error roteiro-panel-alert"><?= e((string) $_GET['roteiro_erro']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_bloqueada'])): ?>
        <div class="auth-error roteiro-panel-alert"><?= e(sessao_bloqueio_motivo($sessao)) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['publicada_ok'])): ?>
        <div class="sapl-ok roteiro-panel-alert">Sessão publicada. Pauta e roteiro não podem mais ser alterados.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['publicar_erro'])): ?>
        <div class="auth-error roteiro-panel-alert">Não foi possível publicar. Conclua todos os passos na condução (<?= $concluidos ?>/<?= $totalConducao ?>) antes de publicar.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['conducao_encerrada'])): ?>
        <div class="sapl-ok roteiro-panel-alert">A condução desta sessão já foi encerrada. Não é possível reabrir a tela de condução.</div>
    <?php endif; ?>
    <?php if ($sessaoBloqueada): ?>
        <div class="sessao-bloqueio-banner roteiro-panel-alert"><?= e(sessao_bloqueio_motivo($sessao)) ?></div>
    <?php endif; ?>

    <?php if (!$sessao): ?>
        <p class="roteiro-panel-empty">Salve a sessão para gerar o roteiro.</p>
    <?php elseif (!$conducaoConfigurada): ?>
        <p class="roteiro-panel-empty">
            Modelos ausentes. Execute <code>php scripts/migrate_conducao.php</code>
        </p>
    <?php else: ?>
        <ol class="roteiro-fluxo" aria-label="Fluxo do roteiro">
            <li class="roteiro-fluxo-item<?= $temRoteiro ? ' is-done' : '' ?>">
                <span class="roteiro-fluxo-num">1</span>
                <span class="roteiro-fluxo-text">Gerar passos</span>
            </li>
            <li class="roteiro-fluxo-item<?= $temRoteiro ? ' is-current' : '' ?>">
                <span class="roteiro-fluxo-num">2</span>
                <span class="roteiro-fluxo-text">Montar roteiro</span>
            </li>
            <li class="roteiro-fluxo-item<?= $sessaoBloqueada ? ' is-done' : '' ?>">
                <span class="roteiro-fluxo-num">3</span>
                <span class="roteiro-fluxo-text">Conduzir sessão</span>
            </li>
        </ol>

        <div class="roteiro-panel-toolbar<?= $sessaoBloqueada ? ' is-locked' : '' ?>">
            <?php if ($sessaoBloqueada): ?>
                <p class="roteiro-toolbar-locked-msg">Sessão concluída — edição e condução encerradas.</p>
                <div class="roteiro-panel-actions">
                    <span class="btn btn-soft is-disabled" aria-disabled="true">Gerar condução</span>
                    <?php if ($temRoteiro): ?>
                        <span class="btn btn-primary is-disabled" aria-disabled="true">Montar roteiro</span>
                        <span class="btn btn-soft is-disabled" aria-disabled="true">Conduzir sessão</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form class="roteiro-gerar-form" method="post" action="<?= e(base_url('?r=roteiro/gerar/' . (int) $sessao['id'])) ?>"<?= $confirmGerar ? ' onsubmit="return confirm(\'Substituir o roteiro atual? Todos os passos serão recriados.\');"' : '' ?>>
                    <?= csrf_field() ?>
                    <label class="roteiro-check-tribuna">
                        <input type="checkbox" name="incluir_tribuna" value="1" checked>
                        Tribuna
                    </label>
                    <button class="btn btn-soft" type="submit">Gerar condução</button>
                </form>
                <div class="roteiro-panel-actions">
                    <?php if ($temRoteiro): ?>
                        <a class="btn btn-primary" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'])) ?>">Montar roteiro</a>
                    <?php endif; ?>
                    <a class="btn btn-soft" href="<?= e(base_url('?r=presidencia/conduzir/' . (int) $sessao['id'])) ?>">Conduzir sessão</a>
                    <?php if ($podePublicar): ?>
                        <form method="post" action="<?= e(base_url('?r=sessao/publicar/' . (int) $sessao['id'])) ?>" class="roteiro-publicar-form" onsubmit="return confirm('Publicar esta sessão? A pauta e o roteiro não poderão mais ser editados.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit">Publicar sessão</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($podePublicar && !$sessaoBloqueada): ?>
            <p class="roteiro-publicar-hint panel-meta">Todos os passos foram concluídos na condução. Use <strong>Publicar sessão</strong> para impedir novas alterações na pauta e no roteiro.</p>
        <?php endif; ?>

        <?php if (!$temRoteiro): ?>
            <p class="roteiro-panel-empty">
                <?php if ($qtdMaterias === 0): ?>
                    Adicione matérias na pauta acima.
                <?php else: ?>
                    <?= $qtdMaterias ?> matéria(s) na pauta — clique em <strong>Gerar condução</strong>.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="roteiro-panel-metrics">
                <div class="meta-box">
                    <div class="k">Passos</div>
                    <div class="v"><?= $totalPassos ?></div>
                </div>
                <div class="meta-box">
                    <div class="k">Na condução</div>
                    <div class="v"><?= $passosAtivos ?></div>
                </div>
                <?php if ($passosAtivos < $totalPassos): ?>
                    <div class="meta-box">
                        <div class="k">Fora (⊘)</div>
                        <div class="v"><?= $totalPassos - $passosAtivos ?></div>
                    </div>
                <?php endif; ?>
                <div class="meta-box meta-box--hint">
                    <div class="k">Ajustes</div>
                    <div class="v v-hint">Ordem, textos e ⊘ em Montar roteiro</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</article>
