<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$estadoInicial = $estadoInicial ?? ['ok' => false];
$sessaoId = $sessao ? (int) $sessao['id'] : 0;
$statusRaw = strtoupper((string) ($sessao['status'] ?? ''));
$statusLabel = sessao_status_label($statusRaw);
$statusChip = sessao_status_chip_class($statusRaw);
?>
<?php ob_start(); ?>
<section
    class="publico-sessao publico-sessao--telao"
    id="publico-sessao"
    data-sessao-id="<?= $sessaoId ?>"
    data-estado-url="<?= $sessaoId ? e(route_url('publico/estado/' . $sessaoId)) : '' ?>"
    data-stream-url="<?= $sessaoId ? e(route_url('publico/stream/' . $sessaoId)) : '' ?>"
    data-carousel-seg="14"
    data-pausa-resultado-seg="10"
>
    <header class="public-telao-header public-hero">
        <div class="public-telao-header-left">
            <span class="public-hero-mark">CM</span>
            <div class="public-telao-header-titles">
                <span class="public-hero-kicker">Câmara Municipal</span>
                <?php if ($sessao): ?>
                    <h1 class="public-hero-title public-telao-title" id="publico-sessao-titulo"><?= e((string) $sessao['titulo']) ?></h1>
                <?php else: ?>
                    <h1 class="public-hero-title public-telao-title">Nenhuma sessão em andamento</h1>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($sessao): ?>
            <div class="public-telao-header-right">
                <span class="public-chip">#<?= (int) $sessao['numero'] ?>/<?= (int) $sessao['ano'] ?></span>
                <span class="public-chip public-chip--<?= e($statusChip) ?>" id="publico-status-chip"><?= e($statusLabel) ?></span>
                <span class="public-live-badge" id="publico-live-badge" title="Atualização automática">
                    <span class="public-live-dot" aria-hidden="true"></span> Ao vivo
                </span>
                <span class="public-telao-roteiro" id="publico-progresso-meta">
                    <?php if (!empty($estadoInicial['progresso_label'])): ?>
                        <?= e((string) $estadoInicial['progresso_label']) ?> passos
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$sessao): ?>
        <div class="public-telao-empty">
            <p>Aguardando abertura de sessão plenária.</p>
        </div>
    <?php else: ?>
        <div class="public-telao-carousel" id="publico-carousel" aria-live="polite">
            <div class="public-telao-carousel-track" id="publico-carousel-track"></div>
            <div class="public-telao-carousel-ui">
                <span class="public-telao-carousel-contador" id="publico-carousel-contador">Giro contínuo</span>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($sessaoId > 0): ?>
<script>
    window.PUBLICO_SESSAO_INICIAL = <?= json_encode($estadoInicial, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset_url('assets/js/publico-sessao.js')) ?>" defer></script>
<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php
$title = ($sessao['titulo'] ?? 'Sessão') . ' — Telão';
$bodyClass = 'public-page public-page--telao';
$hidePublicFooter = true;
require __DIR__ . '/../layouts/public.php';
