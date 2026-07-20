<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
<main class="auth-shell">
    <section class="auth-visual">
        <div class="auth-hero">
            <div class="auth-kicker">Sistema Legislativo</div>
            <h1 class="auth-title"><?= e($appName) ?></h1>
            <p class="auth-text">Base limpa para recriar o sistema com pauta, sessão, votação e tela pública no estilo SAPL.</p>
        </div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-kicker">Entradas</div>
            <h2>Portais principais</h2>
            <div class="auth-form">
                <a class="btn btn-primary" href="<?= e(route_url('auth/login')) ?>">Acesso interno</a>
                <a class="btn btn-soft" href="<?= e(route_url('publico/index')) ?>">Visão pública (ao vivo)</a>
            </div>
        </div>
    </section>
</main>
<?php $content = ob_get_clean(); ?>
<?php
$title = (string) $appName;
require __DIR__ . '/layouts/public.php';
?>
