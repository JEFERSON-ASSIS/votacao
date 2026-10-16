<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$estadoInicial = $estadoInicial ?? ['ok' => false];
$aguardandoUrl = (string) ($aguardandoUrl ?? route_url('publico/atual'));
$sessaoId = $sessao ? (int) $sessao['id'] : 0;
$statusRaw = strtoupper((string) ($sessao['status'] ?? ''));
$statusLabel = sessao_status_label($statusRaw);
$statusChip = sessao_status_chip_class($statusRaw);
$momentoTitulo = (string) ($estadoInicial['momento_titulo'] ?? '');
$progressoLabel = (string) ($estadoInicial['progresso_label'] ?? '');
?>
<?php ob_start(); ?>
<section
    class="publico-sessao publico-sessao--telao<?= $sessao ? '' : ' publico-sessao--aguardando' ?>"
    id="publico-sessao"
    data-sessao-id="<?= $sessaoId ?>"
    data-estado-url="<?= $sessaoId ? e(route_url('publico/estado/' . $sessaoId)) : '' ?>"
    data-stream-url="<?= $sessaoId ? e(route_url('publico/stream/' . $sessaoId)) : '' ?>"
    data-atual-url="<?= e($aguardandoUrl) ?>"
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
                    <h1 class="public-hero-title public-telao-title" id="publico-sessao-titulo">Aguardando sessão</h1>
                <?php endif; ?>
            </div>
        </div>
        <div class="public-telao-header-right">
            <?php if ($sessao): ?>
                <span class="public-chip">#<?= (int) $sessao['numero'] ?>/<?= (int) $sessao['ano'] ?></span>
                <span class="public-chip public-chip--<?= e($statusChip) ?>" id="publico-status-chip"><?= e($statusLabel) ?></span>
                <button type="button" class="public-fullscreen-btn" id="publico-fullscreen" title="Tela cheia (F)" aria-label="Tela cheia">⛶</button>
                <span class="public-live-badge" id="publico-live-badge" title="Atualização automática">
                    <span class="public-live-dot" aria-hidden="true"></span> Ao vivo
                </span>
                <span class="public-telao-roteiro" id="publico-progresso-meta">
                    <?php if ($progressoLabel !== ''): ?>
                        <?= e($progressoLabel) ?> passos
                    <?php endif; ?>
                </span>
            <?php else: ?>
                <span class="public-chip public-chip--draft" id="publico-status-chip">Sem sessão ativa</span>
                <span class="public-live-badge public-live-badge--wait" id="publico-live-badge">
                    <span class="public-live-dot" aria-hidden="true"></span> Aguardando
                </span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$sessao): ?>
        <div class="public-telao-empty" id="publico-aguardando">
            <p class="public-telao-empty-title">Nenhuma sessão em andamento no telão</p>
            <p>O presidente precisa <strong>iniciar a sessão</strong> (status Em andamento). Esta tela atualiza sozinha.</p>
            <p class="public-telao-empty-hint" id="publico-aguardando-hint">Verificando a cada 3 segundos…</p>
        </div>
        <script>
        (function () {
            var url = <?= json_encode($aguardandoUrl, JSON_UNESCAPED_UNICODE) ?>;
            var hint = document.getElementById('publico-aguardando-hint');
            function checar() {
                fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.ok && d.sessao_id) {
                            if (hint) hint.textContent = 'Sessão encontrada: ' + (d.titulo || '') + ' — abrindo…';
                            window.location.href = d.url || (window.location.pathname + '?r=publico/sessao/' + d.sessao_id);
                            return;
                        }
                        if (hint) hint.textContent = 'Ainda sem sessão aberta. Verificando novamente…';
                    })
                    .catch(function () {
                        if (hint) hint.textContent = 'Falha ao consultar. Tentando de novo…';
                    });
            }
            checar();
            setInterval(checar, 3000);
        })();
        </script>
    <?php else: ?>
        <div class="public-telao-carousel" id="publico-carousel" aria-live="polite">
            <div class="public-telao-carousel-track" id="publico-carousel-track">
                <div class="public-carrossel-pane" data-slide-key="momento" data-set="0">
                    <article class="public-carrossel-slide public-carrossel-slide--momento">
                        <p class="public-carrossel-kicker">Em discussão na sessão</p>
                        <h2 class="public-carrossel-titulo"><?= e($momentoTitulo !== '' ? $momentoTitulo : 'Sessão plenária') ?></h2>
                        <p class="public-carrossel-texto public-carrossel-texto--suave">Acompanhe o andamento da sessão plenária.</p>
                    </article>
                </div>
            </div>
            <div class="public-telao-carousel-ui">
                <span class="public-telao-carousel-contador" id="publico-carousel-contador">Transmissão ao vivo</span>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    'use strict';
    // Navegadores exigem gesto do usuario para tela cheia: nao da para
    // entrar em fullscreen sozinho no load. Aqui damos botao, tecla F e
    // o primeiro clique/toque em qualquer lugar da pagina.
    var alvo = document.documentElement;

    function estaCheia() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function entrar() {
        if (estaCheia()) {
            return;
        }
        var req = alvo.requestFullscreen || alvo.webkitRequestFullscreen;
        if (req) {
            try {
                var r = req.call(alvo);
                if (r && r.catch) { r.catch(function () {}); }
            } catch (e) {}
        }
    }

    function alternar() {
        if (estaCheia()) {
            var sair = document.exitFullscreen || document.webkitExitFullscreen;
            if (sair) {
                try { sair.call(document); } catch (e) {}
            }
            return;
        }
        entrar();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('publico-fullscreen');
        if (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                alternar();
            });
        }
    });

    // Primeiro gesto em qualquer ponto da tela ja abre em tela cheia.
    function primeiroGesto() {
        document.removeEventListener('click', primeiroGesto);
        entrar();
    }

    // A tecla F continua alternando tela cheia durante toda a sessao.
    function atalho(ev) {
        if (ev.key === 'f' || ev.key === 'F') {
            ev.preventDefault();
            alternar();
        }
    }

    document.addEventListener('click', primeiroGesto);
    document.addEventListener('keydown', atalho);
})();
</script>

<?php if ($sessaoId > 0): ?>
<script>
    window.PUBLICO_SESSAO_INICIAL = <?= json_encode($estadoInicial, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset_url('assets/js/sessao-timer.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/publico-sessao.js')) ?>" defer></script>
<?php endif; ?>
<?php $content = ob_get_clean(); ?>
<?php
$title = ($sessao['titulo'] ?? 'Sessão') . ' — Telão';
$bodyClass = 'public-page public-page--telao';
$hidePublicFooter = true;
require __DIR__ . '/../layouts/public.php';
