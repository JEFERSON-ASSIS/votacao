<?php declare(strict_types=1); ?>
<?php
$sessaoId = (int) ($sessao['id'] ?? 0);
$rodada = $rodada ?? null;
$aberta = $rodada && ($rodada['status'] ?? '') === 'ABERTA';
$rodadaId = $rodada ? (int) $rodada['id'] : 0;
$resultado = $rodada ? (string) ($rodada['resultado'] ?? '') : '';
$empate = !empty($empate);
$podeEncerrar = (bool) ($podeEncerrar ?? true);
$encerrarMensagem = (string) ($encerrarMensagem ?? '');
$ehPresidente = !empty($ehPresidente);
$presidenteVoto = $presidenteVoto ?? null;
$presidenteId = (int) ($presidenteId ?? 0);
$resumoParlamentares = $resumoParlamentares ?? ['SIM' => 0, 'NAO' => 0, 'ABSTENCAO' => 0, 'AUSENTE' => 0];

$votosPorUser = [];
foreach ($votos ?? [] as $v) {
    $votosPorUser[(int) $v['user_id']] = $v;
}
?>
<?php ob_start(); ?>
<section
    class="panel-grid votacao-painel"
    id="votacao-painel"
    data-rodada-id="<?= $rodadaId ?>"
    data-estado-url="<?= $rodadaId > 0 ? e(route_url('votacao/estadoRodada/' . $rodadaId)) : '' ?>"
    data-stream-url="<?= $rodadaId > 0 ? e(route_url('votacao/streamRodada/' . $rodadaId)) : '' ?>"
    data-voto-url="<?= $rodadaId > 0 ? e(base_url('?r=votacao/votarRodada/' . $sessaoId . '/' . $rodadaId)) : '' ?>"
    data-eh-presidente="<?= $ehPresidente ? '1' : '0' ?>"
    data-situacao-url="<?= $rodadaId > 0 ? e(base_url('?r=votacao/situacaoEleitor/' . $sessaoId . '/' . $rodadaId)) : '' ?>"
>
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:flex-start;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Painel de votação</div>
                <div class="panel-value" style="font-size:1.1rem;"><?= e($sessao['titulo'] ?? '') ?></div>
                <?php if ($rodada): ?>
                    <div class="panel-meta" style="margin-top:6px;">
                        <strong><?= e((string) $rodada['titulo']) ?></strong>
                        <?php if (!empty($rodada['descricao'])): ?>
                            — <?= e(mb_strimwidth((string) $rodada['descricao'], 0, 120, '…')) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="panel-meta">Nenhuma votação aberta no momento.</div>
                <?php endif; ?>
            </div>
            <div class="action-row" style="align-items:center;gap:10px;">
                <?php if ($aberta): ?>
                    <span class="parl-live-badge" id="votacao-painel-live" title="Votos atualizando ao vivo">
                        <span class="parl-live-dot" aria-hidden="true"></span> Ao vivo
                    </span>
                <?php endif; ?>
                <?php if (sessao_pode_conduzir($sessao ?? null)): ?>
                    <a class="btn btn-soft" href="<?= e(base_url('?r=presidencia/conduzir/' . $sessaoId)) ?>">Conduzir sessão</a>
                <?php endif; ?>
                <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/index')) ?>">Visão geral</a>
            </div>
        </div>
    </article>

    <?php if (!empty($_GET['situacao_erro'])): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="auth-error" role="alert"><?= e(mb_substr((string) $_GET['situacao_erro'], 0, 160)) ?></div>
        </article>
    <?php endif; ?>

    <?php if (!$rodada): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <p class="panel-meta">
                <?php if (sessao_pode_conduzir($sessao ?? null)): ?>
                    Abra a votação a partir do passo do roteiro em <strong>Conduzir sessão</strong>.
                <?php else: ?>
                    A condução desta sessão já foi encerrada. Consulte o histórico de rodadas abaixo.
                <?php endif; ?>
            </p>
            <?php if (!empty($historicoRodadas)): ?>
                <div class="panel-label" style="margin-top:16px;">Últimas votações</div>
                <ul class="votacao-historico">
                    <?php foreach ($historicoRodadas as $h): ?>
                        <li>
                            <a href="<?= e(base_url('?r=votacao/apuracaoRodada/' . (int) $h['id'])) ?>">
                                <?= e((string) $h['titulo']) ?>
                            </a>
                            <span class="status-chip <?= ($h['status'] ?? '') === 'ABERTA' ? 'open' : 'closed' ?>">
                                <?php if (!empty($h['resultado'])): ?>
                                    <?= e(votacao_rotulo_resultado((string) $h['resultado'])) ?>
                                <?php else: ?>
                                    <?= e(votacao_rotulo_status((string) ($h['status'] ?? ''))) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <article class="panel-card">
            <div class="panel-label">Status</div>
            <div class="panel-value" id="votacao-painel-status">
                <span class="status-chip <?= $aberta ? 'open' : 'closed' ?>" data-painel-chip-status><?= $aberta ? 'Aberta' : 'Encerrada' ?></span>
                <?php if ($resultado !== ''): ?>
                    <span class="status-chip <?= $resultado === 'APROVADO' ? 'vote' : ($resultado === 'REJEITADO' ? 'closed' : 'warn') ?>" style="margin-left:8px;" data-painel-chip-resultado>
                        <?= e(votacao_rotulo_resultado($resultado)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </article>
        <article class="panel-card"><div class="panel-label">Sim</div><div class="panel-value voto-sim-text" data-resumo="SIM"><?= (int) ($resumo['SIM'] ?? 0) ?></div></article>
        <article class="panel-card"><div class="panel-label">Não</div><div class="panel-value voto-nao-text" data-resumo="NAO"><?= (int) ($resumo['NAO'] ?? 0) ?></div></article>
        <article class="panel-card"><div class="panel-label">Abstenção</div><div class="panel-value" data-resumo="ABSTENCAO"><?= (int) ($resumo['ABSTENCAO'] ?? 0) ?></div></article>

        <?php if ($aberta): ?>
            <article class="panel-card" style="grid-column:1/-1;" id="votacao-painel-empate-box" <?= !$empate ? 'hidden' : '' ?>>
                <div class="conducao-presidente-alert conducao-presidente-alert--inline" role="status">
                    <strong>Empate</strong> entre Sim e Não entre os vereadores.
                    <?php if ($ehPresidente): ?>
                        Registre abaixo o seu voto de desempate.
                    <?php else: ?>
                        Aguarde o voto do presidente para desempatar.
                    <?php endif; ?>
                </div>
            </article>

            <?php if (($_GET['votacao_erro'] ?? '') === 'empate_presidente'): ?>
                <div class="conducao-presidente-alert conducao-presidente-alert--inline" style="grid-column:1/-1;" role="alert">
                    Empate entre Sim e Não: registre o voto de desempate do presidente antes de encerrar a votação.
                </div>
            <?php endif; ?>
            <?php if (($_GET['votacao_erro'] ?? '') === 'votos_pendentes'): ?>
                <div class="conducao-presidente-alert" role="alert">
                    A votação não foi concluída porque ainda existem vereadores presentes sem voto registrado.
                </div>
            <?php endif; ?>
            <?php if (($_GET['votacao_erro'] ?? '') === 'sem_eleitores'): ?>
                <div class="conducao-presidente-alert" role="alert">
                    Não é possível abrir a votação sem parlamentares presentes registrados.
                </div>
            <?php endif; ?>

            <?php if ($ehPresidente): ?>
                <article class="panel-card votacao-presidente-desempate" style="grid-column:1/-1;" id="votacao-painel-presidente-card" <?= !$empate ? 'hidden' : '' ?>>
                    <div class="panel-label">Voto do presidente (desempate)</div>
                    <p class="panel-meta">Só em empate. Seu voto define o resultado desta votação.</p>
                    <form method="post" action="<?= e(base_url('?r=votacao/votarRodada/' . $sessaoId . '/' . $rodadaId)) ?>" class="votacao-presidente-form" id="votacao-presidente-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $presidenteId ?>">
                        <div class="parl-voto-botoes">
                            <button type="submit" name="voto" value="SIM" class="btn parl-voto-btn parl-voto-btn--sim">Sim</button>
                            <button type="submit" name="voto" value="NAO" class="btn parl-voto-btn parl-voto-btn--nao">Não</button>
                        </div>
                    </form>
                    <?php if ($presidenteVoto): ?>
                        <p class="panel-meta" id="votacao-presidente-voto-msg" style="margin-top:10px;">
                            Seu voto: <span class="voto-chip <?= e(votacao_classe_voto((string) $presidenteVoto)) ?>"><?= e(votacao_rotulo_voto((string) $presidenteVoto)) ?></span>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <article class="panel-card" style="grid-column:1/-1;">
                <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:14px;">
                    <div>
                        <div class="panel-label" style="margin:0;">Votos dos vereadores (tempo real)</div>
                        <p class="panel-meta" style="margin:4px 0 0;">Cada parlamentar registra o próprio voto em <strong>Acompanhar sessão</strong>.</p>
                    </div>
                    <div style="text-align:right;">
                        <p
                            class="panel-meta conducao-presidente-alert conducao-presidente-alert--inline"
                            id="votacao-encerrar-aviso"
                            style="margin:0 0 10px;<?= $podeEncerrar ? 'display:none;' : '' ?>"
                            role="status"
                        ><?= e($encerrarMensagem !== '' ? $encerrarMensagem : 'Aguardando voto do presidente em caso de empate.') ?></p>
                        <form
                            method="post"
                            id="votacao-encerrar-form"
                            action="<?= e(base_url('?r=votacao/encerrarRodada/' . $sessaoId . '/' . $rodadaId)) ?>"
                            <?php if ($podeEncerrar): ?>
                            data-confirm-title="Encerrar votação"
                            data-confirm="Esta ação encerra a votação em andamento e apura o resultado como Aprovado ou Reprovado. Os vereadores não poderão mais alterar votos."
                            data-confirm-ok="Encerrar e apurar"
                            data-confirm-variant="danger"
                            <?php endif; ?>
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="painel">
                            <button
                                class="btn btn-danger"
                                type="submit"
                                id="votacao-encerrar-btn"
                                <?= $podeEncerrar ? '' : 'disabled title="' . e($encerrarMensagem) . '"' ?>
                            >Concluir votação</button>
                        </form>
                    </div>
                </div>

                <ul class="votacao-vereador-grid votacao-vereador-grid--ao-vivo" id="votacao-painel-grid">
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $vReg = $votosPorUser[(int) $u['id']] ?? null;
                        $exib = votacao_exibicao_eleitor($u, $vReg ? (string) ($vReg['voto'] ?? '') : '');
                        $votoVal = (string) ($exib['voto'] ?? '');
                        $fotoUrl = user_foto_url($u);
                        ?>
                        <li
                            class="votacao-vereador-card votacao-vereador-card--ao-vivo<?= $votoVal !== '' ? ' votacao-vereador-card--votou' : ' votacao-vereador-card--aguardando' ?>"
                            data-user-id="<?= (int) $u['id'] ?>"
                        >
                            <div class="votacao-vereador-foto" aria-hidden="true">
                                <?php if ($fotoUrl): ?>
                                    <img src="<?= e($fotoUrl) ?>" alt="" width="56" height="56" loading="lazy">
                                <?php else: ?>
                                    <span class="votacao-vereador-iniciais"><?= e(user_iniciais((string) $u['nome'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="votacao-vereador-info">
                                <strong class="votacao-vereador-nome"><?= e($u['nome']) ?></strong>
                                <span class="votacao-vereador-partido"><?= e(trim((string) ($u['partido'] ?? '')) !== '' ? (string) $u['partido'] : '—') ?></span>
                            </div>
                            <span class="votacao-vereador-voto-slot">
                                <?php if ($votoVal !== ''): ?>
                                    <span class="voto-chip votacao-vereador-voto <?= e($exib['voto_classe']) ?>">
                                        <?= e($exib['voto_label']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="votacao-vereador-aguardando">Aguardando voto…</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php else: ?>
            <article class="panel-card" style="grid-column:1/-1;">
                <div class="panel-label">Votos registrados</div>
                <?php if ($votos === []): ?>
                    <p class="panel-meta" style="margin-top:8px;">Nenhum voto registrado.</p>
                <?php else: ?>
                    <ul class="votacao-vereador-grid votacao-vereador-grid--registrados" style="margin-top:12px;">
                        <?php foreach ($votos as $v): ?>
                            <?php
                            $uCard = [
                                'nome' => $v['nome'] ?? '',
                                'partido' => $v['partido'] ?? '',
                                'foto' => $v['foto'] ?? '',
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
                                <span class="voto-chip votacao-vereador-voto <?= e(votacao_classe_voto((string) $v['voto'])) ?>">
                                    <?= e(votacao_rotulo_voto((string) $v['voto'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/apuracaoRodada/' . $rodadaId)) ?>" style="margin-top:14px;">Ver apuração completa</a>
            </article>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php if ($rodadaId > 0 && $aberta): ?>
<script src="<?= e(asset_url('assets/js/votacao-painel.js')) ?>" defer></script>
<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php
$parlAlertaGlobalDesligado = true;
$title = 'Painel de votação';
$pageTitle = 'Painel de votação';
require __DIR__ . '/../layouts/admin.php';
