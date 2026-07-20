<?php declare(strict_types=1); ?>
<?php
$sessaoId = (int) ($sessao['id'] ?? 0);
$estado = $status === 'EM_VOTACAO' ? 'vote' : (($status === 'ENCERRADA') ? 'closed' : 'open');
$usaRoteiro = !empty($usaRoteiro);
$roteiroStats = $roteiroStats ?? ['total' => 0, 'concluidos' => 0];
$sessaoBloqueada = !empty($sessao) && sessao_esta_bloqueada($sessao);
$podeConduzir = !empty($sessao) && sessao_pode_conduzir($sessao);
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;">
            <div>
                <div class="panel-label">Sessão em andamento</div>
                <div class="panel-value"><?= e($sessao['titulo'] ?? 'Sem sessão ativa') ?></div>
                <div class="panel-meta">
                    <?= e($sessao ? ('#' . $sessao['numero'] . '/' . $sessao['ano']) : 'Nenhuma sessão disponível') ?>
                    <?php if (!empty($status)): ?>
                        <span class="status-chip <?= e($estado) ?>" style="margin-left:10px;"><?= e(sessao_status_label($status)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="action-row">
                <?php if ($sessao && user_can('historico.sessao')): ?>
                    <a class="btn btn-soft" href="<?= e(route_url('historico/sessao/' . $sessaoId)) ?>">Histórico</a>
                <?php endif; ?>
                <?php if ($sessao && $usaRoteiro && $podeConduzir): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('?r=presidencia/conduzir/' . $sessaoId)) ?>">Conduzir sessão (votações)</a>
                <?php elseif ($sessao && $usaRoteiro && $sessaoBloqueada): ?>
                    <span class="status-chip closed">Sessão concluída</span>
                <?php elseif ($sessao && $status !== 'EM_VOTACAO' && !$sessaoBloqueada): ?>
                    <form method="post" action="<?= e(base_url('?r=votacao/abrir/' . $sessaoId)) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-soft" type="submit">Modo legado: abrir votação por matéria</button>
                    </form>
                <?php endif; ?>
                <?php if ($sessao && $status === 'EM_VOTACAO' && !$usaRoteiro): ?>
                    <form method="post" action="<?= e(base_url('?r=votacao/encerrar/' . $sessaoId)) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger" type="submit">Encerrar sessão</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($usaRoteiro): ?>
            <p class="panel-meta" style="margin-top:10px;">
                Esta sessão usa <strong>roteiro do presidente</strong>.
                <?php if ($podeConduzir): ?>
                    Abra cada votação em <a href="<?= e(base_url('?r=presidencia/conduzir/' . $sessaoId)) ?>">Conduzir sessão</a>
                    (<?= (int) $roteiroStats['concluidos'] ?>/<?= (int) $roteiroStats['total'] ?> passos).
                <?php else: ?>
                    Condução encerrada (<?= (int) $roteiroStats['concluidos'] ?>/<?= (int) $roteiroStats['total'] ?> passos concluídos).
                <?php endif; ?>
                Aqui ficam presença e histórico de rodadas.
            </p>
        <?php endif; ?>
    </article>

    <article class="panel-card">
        <div class="panel-label">Presentes</div>
        <div class="panel-value"><?= e(count($presencas)) ?></div>
        <div class="panel-meta">Registros confirmados</div>
    </article>

    <article class="panel-card">
        <div class="panel-label">Eventos</div>
        <div class="panel-value"><?= e(count($eventos)) ?></div>
        <div class="panel-meta">Linha do tempo da sessão</div>
    </article>

    <article class="panel-card">
        <div class="panel-label">Status operacional</div>
        <div class="panel-value"><?= e($status ? sessao_status_label($status) : 'Sem sessão') ?></div>
        <div class="panel-meta">Fluxo atual da plenária</div>
    </article>

    <?php if (!empty($rodadaAberta)): ?>
        <article class="panel-card accent" style="grid-column:1/-1;border-color:rgba(95,214,255,.4);">
            <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div>
                    <div class="panel-label">Votação em andamento</div>
                    <div class="panel-value" style="font-size:1rem;"><?= e((string) $rodadaAberta['titulo']) ?></div>
                </div>
                <a class="btn btn-primary" href="<?= e(base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . (int) $rodadaAberta['id'])) ?>">Ir ao painel</a>
            </div>
        </article>
    <?php endif; ?>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="panel-label">Controle de presença</div>
        <div class="table-wrap" style="margin-top:14px;">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Perfil</th>
                        <th>Controle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= e($u['nome']) ?></strong><div style="color:var(--muted);font-size:12px;"><?= e($u['email']) ?></div></td>
                            <td><?= e($u['role']) ?></td>
                            <td>
                                <div class="action-row">
                                    <form method="post" action="<?= e(base_url('?r=votacao/presenca/' . $sessaoId)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                        <button class="btn btn-soft" type="submit" name="presente" value="1">Presente</button>
                                        <button class="btn btn-soft" type="submit" name="presente" value="0">Ausente</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <?php if (!$usaRoteiro): ?>
    <?php foreach ($itens as $item): ?>
        <?php $resumo = \App\Models\SessaoVoto::resumoPorItem((int) $item['id']); ?>
        <?php $resumoPauta = pauta_resumo($item); ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="action-row" style="justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="panel-label">Matéria <?= e((string) $item['ordem']) ?></div>
                    <div class="panel-value" style="font-size:1.25rem;"><?= e((string) $item['titulo']) ?></div>
                    <?php if ($resumoPauta !== ''): ?>
                        <div class="panel-meta"><?= e($resumoPauta) ?></div>
                    <?php endif; ?>
                </div>
                <div class="action-row">
                    <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/apuracao/' . (int) $item['id'])) ?>">Ver apuração</a>
                </div>
            </div>

            <div class="panel-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:18px;">
                <div class="panel-card" style="padding:18px;"><div class="panel-label">Sim</div><div class="panel-value"><?= e($resumo['SIM']) ?></div></div>
                <div class="panel-card" style="padding:18px;"><div class="panel-label">Não</div><div class="panel-value"><?= e($resumo['NAO']) ?></div></div>
                <div class="panel-card" style="padding:18px;"><div class="panel-label">Abstenção</div><div class="panel-value"><?= e($resumo['ABSTENCAO']) ?></div></div>
                <div class="panel-card" style="padding:18px;"><div class="panel-label">Total</div><div class="panel-value"><?= e(\App\Models\SessaoVoto::totalPorItem((int) $item['id'])) ?></div></div>
            </div>

            <?php if ($status === 'EM_VOTACAO'): ?>
                <div class="table-wrap" style="margin-top:18px;">
                    <table class="leg-table">
                        <thead>
                            <tr>
                                <th>Vereador</th>
                                <th>Voto</th>
                                <th>Observação</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= e($u['nome']) ?></td>
                                    <td colspan="3">
                                        <form method="post" action="<?= e(base_url('?r=votacao/votar/' . $sessaoId . '/' . (int) $item['id'])) ?>" class="action-row">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                            <select name="voto" class="field" style="max-width:180px;">
                                                <option value="SIM">Sim</option>
                                                <option value="NAO">Não</option>
                                                <option value="ABSTENCAO">Abstenção</option>
                                                <option value="AUSENTE">Ausente</option>
                                            </select>
                                            <input name="observacao" class="field" placeholder="Observação opcional" style="max-width:320px;">
                                            <button class="btn btn-primary" type="submit">Registrar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($usaRoteiro && !empty($historicoRodadas)): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-label">Rodadas de votação (roteiro)</div>
            <ul class="conducao-ol" style="margin-top:10px;">
                <?php foreach ($historicoRodadas as $rod): ?>
                    <li>
                        <?= e((string) ($rod['titulo'] ?? 'Votação')) ?>
                        — <?= e((string) ($rod['status'] ?? '')) ?>
                        <?php if (!empty($rod['resultado'])): ?>
                            (<?= e((string) $rod['resultado']) ?>)
                        <?php endif; ?>
                        <a class="btn btn-soft" style="margin-left:8px;" href="<?= e(base_url('?r=votacao/apuracaoRodada/' . (int) $rod['id'])) ?>">Apuração</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Votação';
$pageTitle = 'Votação';
require __DIR__ . '/../layouts/admin.php';
?>
