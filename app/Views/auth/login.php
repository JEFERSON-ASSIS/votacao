<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso ao Sistema</title>
    <link rel="icon" href="<?= e(favicon_url()) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body>
<main class="auth-shell">
    <section class="auth-visual">
        <div class="auth-hero">
            <div class="auth-kicker">Câmara Municipal</div>
            <h1 class="auth-title">Plenário digital para gestão legislativa</h1>
            <p class="auth-text">
                Acompanhe sessões, pautas, presença, votação e apuração em um painel pensado para operação institucional.
            </p>

            <!-- <div class="auth-notes">
                <div class="auth-note">Fluxo de presidência com visão centralizada da sessão.</div>
                <div class="auth-note">Acesso administrativo organizado por perfis e responsabilidades.</div>
                <div class="auth-note">Base pronta para expansão de ata, histórico e trilha de auditoria.</div>
            </div> -->
        </div>       
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-kicker">Acesso interno</div>
            <h2>Entrar no sistema</h2>
            <p class="auth-help">Use suas credenciais para acessar o painel administrativo.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <div class="auth-field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" class="field" placeholder="nome@servidor.gov.br" required>
                </div>
                <div class="auth-field">
                    <label for="senha">Senha</label>
                    <input id="senha" name="senha" type="password" class="field" placeholder="Digite sua senha" required>
                </div>
                <div class="auth-actions">                    
                    <button class="btn btn-primary" type="submit">Entrar</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
