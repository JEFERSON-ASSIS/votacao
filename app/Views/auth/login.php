<?php declare(strict_types=1);

$appName = (string) config('app_name', 'Plenário Vera');
// "Plenário Vera" -> a última palavra fica em destaque (dourado).
$partesNome = preg_split('/\s+/u', trim($appName)) ?: [$appName];
$destaqueNome = count($partesNome) > 1 ? array_pop($partesNome) : '';
$inicioNome = implode(' ', $partesNome);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> · Entrar</title>
    <link rel="icon" href="<?= e(favicon_url()) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Public+Sans:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="login-page">
<main class="login">
    <section class="login-marca inst">
        <img class="inst-marca-dagua" src="<?= e(asset_url('assets/img/brasao-vera-mt.png')) ?>" alt="" aria-hidden="true">

        <header class="inst-casa">
            <img class="inst-brasao" src="<?= e(asset_url('assets/img/brasao-vera-mt.png')) ?>" alt="Brasão do Município de Vera - MT">
            <div>
                <strong>Câmara Municipal de Vera</strong>
                <span>Estado de Mato Grosso</span>
            </div>
        </header>

        <div class="inst-centro">
            <h1 class="inst-titulo">
                <span><?= e($inicioNome) ?></span>
                <?php if ($destaqueNome !== ''): ?><span class="inst-titulo-b"><?= e($destaqueNome) ?></span><?php endif; ?>
            </h1>
            <p class="inst-lema">Sistema de votação eletrônica do Poder Legislativo Municipal.</p>
        </div>

        <footer class="inst-rodape">
            <?php if (!empty($sessaoAoVivo)): ?>
                <span class="inst-ao-vivo"><span aria-hidden="true"></span>Sessão em andamento: <?= e(sessao_nome_curto($sessaoAoVivo)) ?></span>
            <?php else: ?>
                <span>Sessões, pautas, presença e votação nominal.</span>
            <?php endif; ?>
        </footer>
    </section>

    <section class="login-acesso">
        <div class="login-conteudo">
            <header class="login-cabecalho">
                <h2>Acesse sua conta</h2>
                <p>Entre com o e-mail e a senha cadastrados pela secretaria da Câmara.</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="login-erro" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <?= csrf_field() ?>
                <div class="login-campo">
                    <label for="email">E-mail</label>
                    <div class="login-entrada">
                        <svg class="login-entrada-icone" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg>
                        <input id="email" name="email" type="email" placeholder="nome@exemplo.com"
                               autocomplete="username" value="<?= e($email ?? '') ?>" required>
                    </div>
                </div>
                <div class="login-campo">
                    <label for="senha">Senha</label>
                    <div class="login-senha login-entrada">
                        <svg class="login-entrada-icone" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
                        <input id="senha" name="senha" type="password" placeholder="Digite sua senha"
                               autocomplete="current-password" required>
                        <button type="button" class="login-ver-senha" aria-controls="senha"
                                aria-pressed="false" aria-label="Mostrar senha" hidden>
                            <svg class="login-icone-ver" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="login-icone-ocultar" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.7 10.7 0 0 1 12 5c6 0 9.5 7 9.5 7a17 17 0 0 1-3.2 4.1"/><path d="M6.6 6.6C3.9 8.4 2.5 12 2.5 12s3.5 7 9.5 7a9.9 9.9 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                </div>
                <button class="login-entrar" type="submit">Entrar</button>
                <p class="login-ajuda">Esqueceu a senha? <strong>Fale com a secretaria da Câmara.</strong></p>
            </form>

            <hr class="login-divisor">

            <?php if (!empty($sessaoAoVivo)): ?>
                <a class="login-sessao login-sessao--ao-vivo" href="<?= e(route_url('publico/index')) ?>">
                    <span class="login-sessao-ponto" aria-hidden="true"></span>
                    <span class="login-sessao-texto">
                        <span class="login-sessao-rotulo">Ao vivo agora</span>
                        <span class="login-sessao-nome"><?= e(sessao_nome_curto($sessaoAoVivo)) ?></span>
                    </span>
                    <span class="login-sessao-acao">Acompanhar</span>
                </a>
            <?php elseif (!empty($proximaSessao)): ?>
                <a class="login-sessao" href="<?= e(route_url('publico/index')) ?>">
                    <span class="login-sessao-ponto" aria-hidden="true"></span>
                    <span class="login-sessao-texto">
                        <span class="login-sessao-rotulo">Próxima sessão</span>
                        <span class="login-sessao-nome"><?= e(sessao_quando($proximaSessao) ?: sessao_nome_curto($proximaSessao)) ?></span>
                    </span>
                    <span class="login-sessao-acao">Visão pública</span>
                </a>
            <?php else: ?>
                <a class="login-sessao" href="<?= e(route_url('publico/index')) ?>">
                    <span class="login-sessao-ponto" aria-hidden="true"></span>
                    <span class="login-sessao-texto">
                        <span class="login-sessao-rotulo">Visão pública</span>
                        <span class="login-sessao-nome">Acompanhe as sessões ao vivo</span>
                    </span>
                    <span class="login-sessao-acao">Abrir</span>
                </a>
            <?php endif; ?>
        </div>

        <p class="login-copy">© Câmara Municipal de Vera - MT</p>
    </section>
</main>
<script>
(function () {
    var botao = document.querySelector('.login-ver-senha');
    var campo = document.getElementById('senha');
    if (!botao || !campo) { return; }
    botao.hidden = false;
    botao.addEventListener('click', function () {
        var mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        botao.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
        botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
        campo.focus();
    });
})();
</script>
</body>
</html>
