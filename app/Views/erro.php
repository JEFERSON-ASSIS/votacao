<?php declare(strict_types=1); ?>
<?php
/** @var int $codigo */
/** @var string $titulo */
/** @var string $mensagem */
$logado = \App\Core\Auth::check();
$inicioUrl = $logado ? route_url('dashboard/index') : route_url('auth/login');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> — <?= e(config('app_name', 'Plenario')) ?></title>
    <link rel="icon" href="<?= e(favicon_url()) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="erro-page">
    <main class="erro-card" role="main">
        <img class="erro-brasao" src="<?= e(asset_url('assets/img/brasao-vera-mt.png')) ?>" alt="" width="72" height="72">
        <p class="erro-codigo">Erro <?= (int) $codigo ?></p>
        <h1 class="erro-titulo"><?= e($titulo) ?></h1>
        <p class="erro-mensagem"><?= e($mensagem) ?></p>
        <div class="erro-acoes">
            <a class="btn btn-primary" href="<?= e($inicioUrl) ?>">Ir para o início</a>
            <button class="btn btn-soft" type="button" onclick="history.back()">Voltar</button>
        </div>
    </main>
</body>
</html>
