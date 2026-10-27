<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? config('app_name', 'Plenario')) ?></title>
    <link rel="icon" href="<?= e(favicon_url()) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Public+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? 'public-page') ?>">
<?php if (str_contains((string) ($bodyClass ?? ''), 'telao')): ?>
<script>document.documentElement.classList.add('public-page--telao');</script>
<?php endif; ?>
    <div class="public-shell">
        <?= $content ?? '' ?>
    </div>
    <?php if (empty($hidePublicFooter)): ?>
    <footer class="public-footer">
        <span><?= e(config('app_name', 'Plenario')) ?></span>
        <span class="public-footer-sep">·</span>
        <span>Transparência — sessão e votações em tempo real</span>
    </footer>
    <?php endif; ?>
</body>
</html>
