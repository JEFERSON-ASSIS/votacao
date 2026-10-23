<?php declare(strict_types=1); ?>
<?php
use App\Core\Auth;
use App\Helpers\UserPerfil;

$authUser = Auth::user();
// Item do menu correspondente à tela atual (aria-current="page").
[$navCtl, $navAcao] = array_pad(explode('/', trim((string) ($_GET['r'] ?? ''), '/')), 2, '');
$navAtivo = static function (string $rota) use ($navCtl, $navAcao): string {
    [$ctl, $acao] = array_pad(explode('/', $rota), 2, '');
    $ativo = $ctl === 'parlamentar'
        ? ($navCtl === 'parlamentar' && (($acao === 'meuPerfil') === ($navAcao === 'meuPerfil')))
        : $ctl === strtolower($navCtl);

    return $ativo ? ' aria-current="page"' : '';
};
$mostrarAlertaParlGlobal = user_can('menu.sessoes_parlamentar')
    && UserPerfil::ehParlamentar($authUser['role'] ?? '')
    && empty($parlAlertaGlobalDesligado);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? config('app_name', 'Plenario')) ?></title>
    <link rel="icon" href="<?= e(favicon_url()) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script src="<?= e(asset_url('assets/js/sessao-expirada.js')) ?>" data-login-url="<?= e(route_url('auth/login')) ?>"></script>
</head>
<body class="<?= e($bodyClass ?? 'admin-layout') ?>">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="brand">
                <div class="brand-mark">
                    <img src="<?= e(favicon_url()) ?>" alt="" width="22" height="22">
                </div>
                <div>
                    <div class="brand-title"><?= e(config('app_name', 'Plenario')) ?></div>
                    <div class="brand-subtitle">Painel administrativo</div>
                </div>
                <button class="btn btn-soft side-nav-toggle" type="button" aria-controls="side-nav" aria-expanded="false">Menu</button>
            </div>
            <nav class="side-nav" id="side-nav">
                <?php if (user_can('menu.dashboard')): ?>
                    <a href="<?= e(route_url('dashboard/index')) ?>"<?= $navAtivo('dashboard/index') ?>>Dashboard</a>
                <?php endif; ?>
                <?php if (user_can('menu.meu_perfil')): ?>
                    <a href="<?= e(route_url('parlamentar/meuPerfil')) ?>"<?= $navAtivo('parlamentar/meuPerfil') ?>>Meu perfil</a>
                <?php endif; ?>
                <?php if (user_can('menu.sessoes')): ?>
                    <a href="<?= e(route_url('sessao/index')) ?>"<?= $navAtivo('sessao/index') ?>>Sessões</a>
                <?php elseif (user_can('menu.sessoes_parlamentar')): ?>
                    <a href="<?= e(route_url('parlamentar/sessoes')) ?>"<?= $navAtivo('parlamentar/sessoes') ?>>Sessões</a>
                <?php endif; ?>
                <?php if (user_can('menu.votacao')): ?>
                    <a href="<?= e(route_url('votacao/index')) ?>"<?= $navAtivo('votacao/index') ?>>Votação</a>
                <?php endif; ?>
                <?php if (user_can('menu.presidencia') && !UserPerfil::matches($authUser['role'] ?? '', UserPerfil::PRESIDENTE)): ?>
                    <a href="<?= e(route_url('presidencia/index')) ?>"<?= $navAtivo('presidencia/index') ?>>Presidência</a>
                <?php endif; ?>
                <?php if (user_can('menu.usuarios')): ?>
                    <a href="<?= e(base_url('?r=usuario/index')) ?>"<?= $navAtivo('usuario/index') ?>>Usuários</a>
                <?php endif; ?>
                <?php if (user_can('menu.permissoes')): ?>
                    <a href="<?= e(route_url('permissoes/index')) ?>"<?= $navAtivo('permissoes/index') ?>>Permissões</a>
                <?php endif; ?>
                <?php if (user_can('menu.historico')): ?>
                    <a href="<?= e(route_url('historico/index')) ?>"<?= $navAtivo('historico/index') ?>>Histórico</a>
                <?php endif; ?>
                <?php if (user_can('menu.publico')): ?>
                    <a href="<?= e(route_url('publico/index')) ?>"<?= $navAtivo('publico/index') ?>>Visão pública</a>
                <?php endif; ?>
                <?php if (user_can('menu.auditoria')): ?>
                    <a href="<?= e(route_url('auditoria/index')) ?>"<?= $navAtivo('auditoria/index') ?>>Auditoria</a>
                <?php endif; ?>
                <a href="<?= e(base_url('?r=auth/logout')) ?>">Sair</a>
            </nav>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <div class="topbar-kicker">Câmara Municipal</div>
                    <div class="topbar-title"><?= e($pageTitle ?? 'Administração') ?></div>
                </div>
                <div class="topbar-actions">
                    <?php $topbarFoto = user_foto_url($authUser); ?>
                    <img class="topbar-user-avatar" src="<?= e($topbarFoto) ?>" alt="" width="36" height="36" title="<?= e((string) ($authUser['nome'] ?? '')) ?>">
                    <span class="status-pill"><?= e(user_perfil_label($authUser['role'] ?? '')) ?></span>
                </div>
            </header>
            <main class="admin-content">
                <?php if ($mostrarAlertaParlGlobal): ?>
                    <div
                        id="parl-alerta-global"
                        class="parl-alerta-votacao parl-alerta-global"
                        role="alert"
                        aria-live="assertive"
                        hidden
                    >
                        <div class="parl-alerta-votacao-inner">
                            <span class="parl-alerta-icone" aria-hidden="true">!</span>
                            <div class="parl-alerta-corpo">
                                <strong class="parl-alerta-titulo">Votação aberta — vote agora</strong>
                                <p class="parl-alerta-texto parl-alerta-subtitulo" id="parl-alerta-global-sub"></p>
                            </div>
                            <div class="parl-alerta-acoes">
                                <a class="btn btn-primary btn--sm" id="parl-alerta-global-ir" href="#">Ir para o voto</a>
                                <button type="button" class="btn btn-soft btn--sm" id="parl-alerta-global-fechar" aria-label="Fechar aviso">×</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <div class="modal-overlay is-hidden" id="app-confirm-modal" aria-hidden="true">
        <div class="modal-card modal-card-confirm" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title">
            <div class="app-confirm-head">
                <span class="app-confirm-icon app-confirm-icon--danger" id="app-confirm-icon" aria-hidden="true">⚖</span>
                <div class="modal-head" style="margin:0;flex:1;">
                    <div class="panel-label">Confirmação</div>
                    <div class="panel-value" id="app-confirm-title" style="font-size:1.2rem;">Confirmar ação</div>
                    <p class="panel-meta confirm-copy" id="app-confirm-copy"></p>
                </div>
            </div>
            <div class="action-row confirm-actions">
                <button class="btn btn-soft" type="button" id="app-confirm-cancel">Cancelar</button>
                <button class="btn btn-danger" type="button" id="app-confirm-ok">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="<?= e(asset_url('assets/js/app-confirm.js')) ?>" defer></script>
    <script>
    (function () {
        var botao = document.querySelector('.side-nav-toggle');
        var barra = document.querySelector('.admin-sidebar');
        if (!botao || !barra) return;
        botao.addEventListener('click', function () {
            var aberto = barra.classList.toggle('is-menu-aberto');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    })();
    </script>
    <?php if ($mostrarAlertaParlGlobal): ?>
        <script>
            window.PARL_GLOBAL_VOTO = {
                url: <?= json_encode(route_url('parlamentar/votacaoAtiva'), JSON_UNESCAPED_UNICODE) ?>
            };
        </script>
        <script src="<?= e(asset_url('assets/js/parlamentar-global-voto.js')) ?>" defer></script>
    <?php endif; ?>
</body>
</html>
