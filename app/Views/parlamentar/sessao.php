<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? [];
$passoAtual = $passoAtual ?? null;
$anexosPasso = $anexosPasso ?? [];
$stats = $stats ?? ['total' => 0, 'concluidos' => 0];
$pauta = $pauta ?? [];
$rodadaAberta = $rodadaAberta ?? null;
$itemVotacao = $itemVotacao ?? null;
$itemRodadaId = (int) ($itemRodadaId ?? 0);
$exibirAta = !empty($exibirAta);
$ataDocumento = trim((string) ($ataDocumento ?? ''));
$ataSessaoRef = $ataSessaoRef ?? null;
$ataArquivoUrl = $ataArquivoUrl ?? null;
$ataArquivoPdf = !empty($ataArquivoPdf);
$textoLeitura = trim((string) ($textoLeitura ?? ''));
$tituloMomento = (string) ($tituloMomento ?? 'Sessão');
$podeVotar = (bool) ($podeVotar ?? false);
$meuVoto = $meuVoto ?? null;
$jaVotou = !empty($jaVotou) || ($meuVoto !== null && $meuVoto !== '');
$podeRegistrarVoto = (bool) ($podeRegistrarVoto ?? ($podeVotar && !$jaVotou));
$modo = (string) ($modo ?? 'aguardando');
$statusRaw = strtoupper((string) ($statusRaw ?? ($sessao['status'] ?? '')));
$votoOk = (bool) ($votoOk ?? false);
$sessaoId = (int) $sessao['id'];
$totalPassos = (int) ($stats['total'] ?? 0);
$concluidos = (int) ($stats['concluidos'] ?? 0);
$percentual = $totalPassos > 0 ? (int) round(($concluidos / $totalPassos) * 100) : 0;
?>
<?php ob_start(); ?>
<section
    class="panel-grid parl-acompanhar"
    id="parl-acompanhar"
    data-sessao-id="<?= $sessaoId ?>"
    data-stream-url="<?= e(route_url('parlamentar/stream/' . $sessaoId)) ?>"
    data-estado-url="<?= e(route_url('parlamentar/estado/' . $sessaoId)) ?>"
    data-voto-url="<?= e(route_url('parlamentar/votar')) ?>"
    data-rodada-inicial="<?= $rodadaAberta ? (int) $rodadaAberta['id'] : 0 ?>"
    data-pode-votar-inicial="<?= $podeRegistrarVoto ? '1' : '0' ?>"
    data-ja-votou-inicial="<?= $jaVotou ? '1' : '0' ?>"
    data-materia-base="<?= e(route_url('parlamentar/materia/' . $sessaoId . '/')) ?>"
>
    <?php
    $conducaoOk = (string) ($_GET['conducao_ok'] ?? '');
    $conducaoMsgs = [
        'transferida' => 'Presidência transferida. Você está acompanhando a sessão como parlamentar.',
        'devolvida' => 'Presidência devolvida ao presidente titular. Você voltou à visão de parlamentar.',
        'retomada_titular' => 'O presidente titular retomou a presidência. Você voltou à visão de parlamentar.',
    ];
    ?>
    <?php if (isset($conducaoMsgs[$conducaoOk])): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--ok" role="status" style="grid-column:1/-1;">
            <?= e($conducaoMsgs[$conducaoOk]) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['conducao_erro'])): ?>
        <div class="conducao-presidente-alert" role="alert" style="grid-column:1/-1;"><?= e((string) $_GET['conducao_erro']) ?></div>
    <?php endif; ?>
    <?php if (!empty($souTitularForaDaConducao)): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--warn" role="status" style="grid-column:1/-1;display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;">
            <span>
                Presidência em exercício com <strong><?= e((string) ($conducaoEstado['condutor_nome'] ?? '')) ?></strong>.
                Você é o presidente titular.
            </span>
            <form
                method="post"
                action="<?= e(route_url('presidencia/retomarConducao/' . $sessaoId)) ?>"
                data-confirm-title="Retomar presidência"
                data-confirm="Reassumir a condução desta sessão agora?"
                data-confirm-ok="Retomar presidência"
            >
                <?= csrf_field() ?>
                <button class="btn btn-primary btn--sm" type="submit">Retomar presidência</button>
            </form>
        </div>
    <?php endif; ?>
    <?php if ($podeRegistrarVoto && $rodadaAberta): ?>
    <div
        class="parl-alerta-votacao is-visible"
        id="parl-alerta-votacao"
        role="alert"
        aria-live="assertive"
        style="grid-column:1/-1;"
    >
        <div class="parl-alerta-votacao-inner">
            <span class="parl-alerta-icone" aria-hidden="true">!</span>
            <div class="parl-alerta-corpo">
                <strong class="parl-alerta-titulo">Votação aberta — vote agora</strong>
                <p class="parl-alerta-texto parl-alerta-subtitulo"><?= e((string) ($rodadaAberta['titulo'] ?? 'Votação nominal')) ?></p>
            </div>
            <div class="parl-alerta-acoes">
                <button type="button" class="btn btn-primary btn--sm" id="parl-alerta-ir-voto">Ir para o voto</button>
                <button type="button" class="btn btn-soft btn--sm" id="parl-alerta-fechar" aria-label="Fechar aviso">×</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <article class="panel-card accent parl-acompanhar-hero" style="grid-column:1/-1;">
        <div class="parl-materia-hero-top">
            <div>
                <div class="panel-label">Acompanhar sessão</div>
                <div class="panel-value parl-materia-hero-title"><?= e((string) $sessao['titulo']) ?></div>
                <div class="parl-materia-hero-badges">
                    <span class="md-badge">#<?= (int) $sessao['numero'] ?>/<?= (int) $sessao['ano'] ?></span>
                    <span class="status-chip <?= e(sessao_status_chip_class($statusRaw)) ?>" id="parl-status-chip"><?= e(sessao_status_label($statusRaw)) ?></span>
                    <span class="parl-live-badge" id="parl-live-badge" title="Conexão ao vivo — atualização instantânea">
                        <span class="parl-live-dot" aria-hidden="true"></span> Ao vivo
                    </span>
                    <?php if ($rodadaAberta && $podeRegistrarVoto): ?>
                        <span class="status-chip vote" id="parl-rodada-chip">Votação aberta</span>
                    <?php endif; ?>
                    <?php if ($meuVoto): ?>
                        <span class="status-chip open" id="parl-voto-registrado-chip">Voto registrado</span>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn-soft" href="<?= e(route_url('parlamentar/sessoes')) ?>">← Sessões</a>
        </div>
        <?php if ($totalPassos > 0): ?>
            <div class="parl-acompanhar-progress">
                <span class="panel-meta">Roteiro: <?= $concluidos ?>/<?= $totalPassos ?> passos</span>
                <div class="parl-acompanhar-progress-bar"><div style="width:<?= $percentual ?>%"></div></div>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel-card parl-momento-card" style="grid-column:1/-1;" id="parl-momento-card" data-modo="<?= e($modo) ?>">
        <?php if ($modo === 'votacao' || $modo === 'voto_registrado'): ?>
            <span class="parl-momento-tag parl-momento-tag--vote">Votação</span>
        <?php elseif ($modo === 'leitura'): ?>
            <span class="parl-momento-tag parl-momento-tag--leitura">Leitura</span>
        <?php else: ?>
            <span class="parl-momento-tag">Aguardando</span>
        <?php endif; ?>
        <h2 class="parl-momento-titulo" id="parl-momento-titulo"><?= e($tituloMomento) ?></h2>
        <p class="panel-meta parl-momento-hint" id="parl-momento-hint">
            <?php if ($modo === 'votacao'): ?>
                O presidente abriu a votação. Use o box abaixo para votar.
            <?php elseif ($modo === 'voto_registrado' && $meuVoto): ?>
                Seu voto: <span class="voto-chip <?= e(votacao_classe_voto((string) $meuVoto)) ?>"><?= e(votacao_rotulo_voto((string) $meuVoto)) ?></span>
                — registrado. Aguarde o encerramento da votação pelo presidente.
            <?php elseif ($modo === 'voto_registrado'): ?>
                Seu voto foi registrado. Aguarde o encerramento da votação.
            <?php elseif ($modo === 'leitura'): ?>
                Modo leitura — acompanhe o texto exibido pelo presidente neste momento.
            <?php else: ?>
                Aguardando o próximo passo da sessão. A tela atualiza ao vivo — você verá um aviso quando abrir votação.
            <?php endif; ?>
        </p>
    </article>

    <div id="parl-voto-slot" style="grid-column:1/-1;">
        <?php require __DIR__ . '/../partials/_parlamentar_voto_box.php'; ?>
        <?php if (!empty($situacaoVotacaoTexto) && $meuVoto === null): ?>
            <article class="panel-card parl-voto-situacao" role="status">
                <div class="panel-label">Votação em andamento</div>
                <p class="parl-voto-situacao-texto"><?= e((string) $situacaoVotacaoTexto) ?></p>
            </article>
        <?php endif; ?>
    </div>

    <?php if ($exibirAta): ?>
        <article class="panel-card parl-ata-card" style="grid-column:1/-1;" id="parl-ata-panel">
            <div class="parl-ata-head">
                <h3 class="parl-ata-title">Ata da sessão anterior</h3>
                <?php if ($ataSessaoRef): ?>
                    <span class="panel-meta"><?= e($ataSessaoRef['label']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($ataDocumento !== ''): ?>
                <div class="parl-ata-corpo"><?= nl2br(e($ataDocumento)) ?></div>
            <?php endif; ?>
            <?php if ($ataArquivoUrl): ?>
                <div class="parl-ata-arquivo">
                    <a class="btn btn-soft btn--sm" href="<?= e($ataArquivoUrl) ?>" target="_blank" rel="noopener">Abrir documento da ata</a>
                    <?php if ($ataArquivoPdf): ?>
                        <iframe class="parl-ata-pdf" src="<?= e($ataArquivoUrl) ?>" title="Ata em PDF"></iframe>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($ataDocumento === '' && !$ataArquivoUrl): ?>
                <p class="panel-meta">Nenhum texto da ata cadastrado para esta sessão.</p>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <?php if ($textoLeitura !== '' && !in_array($modo, ['votacao', 'voto_registrado'], true)): ?>
        <article class="panel-card parl-leitura-card" style="grid-column:1/-1;" id="parl-leitura-panel">
            <h3 class="parl-leitura-label">Texto do momento</h3>
            <div class="parl-leitura-corpo" id="parl-leitura-corpo"><?= nl2br(e($textoLeitura)) ?></div>
        </article>
    <?php endif; ?>

    <article class="panel-card parl-anexos-card" style="grid-column:1/-1;<?= $anexosPasso === [] ? 'display:none;' : '' ?>" id="parl-anexos-panel">
        <h3 class="parl-leitura-label">Conteúdo e documentos deste passo</h3>
        <p class="panel-meta">Abra os links, documentos e imagens disponibilizados para o momento atual.</p>
        <div class="parl-anexos-grid" id="parl-anexos-grid">
            <?php foreach ($anexosPasso as $anexo): ?>
                <a class="parl-anexo" href="<?= e((string) $anexo['href']) ?>" target="_blank" rel="noopener">
                    <?php if (!empty($anexo['imagem'])): ?><img src="<?= e((string) $anexo['href']) ?>" alt=""><?php endif; ?>
                    <span class="parl-anexo-texto">
                        <strong><?= e((string) ($anexo['titulo'] ?: ($anexo['nome'] ?: 'Link externo'))) ?></strong>
                        <span class="parl-anexo-tipo"><?= e($anexo['tipo'] === 'URL' ? 'URL externa' : (!empty($anexo['imagem']) ? 'Imagem' : 'Documento')) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <?php if ($pauta !== []): ?>
    <article class="panel-card" style="grid-column:1/-1;">
        <details class="parl-pauta-details"<?= ($rodadaAberta && $itemRodadaId > 0) ? ' open' : '' ?>>
            <summary class="parl-pauta-summary">
                <span class="panel-label" style="margin:0;">Pauta da sessão</span>
                <span class="panel-meta"><?= count($pauta) ?> matéria(s)<?= ($rodadaAberta && $itemRodadaId > 0) ? ' — há matéria em votação' : '' ?></span>
            </summary>
            <p class="panel-meta" style="margin-top:12px;">Abra uma matéria para ver o conteúdo completo. Quando uma matéria específica estiver em votação, ela aparecerá destacada.</p>
            <ol class="parl-pauta-list">
                <?php foreach ($pauta as $item): ?>
                        <?php
                        $itemId = (int) $item['id'];
                        $emVotacao = $rodadaAberta
                            && $podeVotar
                            && $itemRodadaId > 0
                            && $itemRodadaId === $itemId;
                        ?>
                        <li class="parl-pauta-item <?= $emVotacao ? 'parl-pauta-item--vote' : '' ?>" style="background:transparent !important;color:var(--text) !important;">
                            <div class="parl-pauta-item-top">
                                <span class="public-pauta-ordem">Item <?= (int) $item['ordem'] ?></span>
                                <?php if ($emVotacao): ?><span class="status-chip vote">Em votação</span><?php endif; ?>
                                <?php if (strtoupper((string) ($item['status'] ?? '')) === 'RETIRADA'): ?><span class="status-chip closed">Retirada de pauta</span><?php endif; ?>
                            </div>
                            <h3><?= e((string) $item['titulo']) ?></h3>
                            <?php $resumo = pauta_resumo($item, 160); ?>
                            <?php if ($resumo !== ''): ?><p class="panel-meta"><?= e($resumo) ?></p><?php endif; ?>
                            <a class="btn <?= $emVotacao ? 'btn-primary' : 'btn-soft' ?> btn--sm" href="<?= e(route_url('parlamentar/materia/' . $sessaoId . '/' . $itemId)) ?>">
                                <?= $emVotacao ? 'Ver e votar' : 'Ver matéria' ?>
                            </a>
                        </li>
                <?php endforeach; ?>
            </ol>
        </details>
    </article>
    <?php endif; ?>
</section>
<script src="<?= e(asset_url('assets/js/parlamentar-voto-confirmar.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/parlamentar-acompanhar.js')) ?>" defer></script>
<?php $content = ob_get_clean(); ?>
<?php
$parlAlertaGlobalDesligado = true;
$title = (string) $sessao['titulo'];
$pageTitle = 'Acompanhar sessão';
$bodyClass = 'admin-layout admin-layout--parl-acompanhar';
require __DIR__ . '/../layouts/admin.php';
