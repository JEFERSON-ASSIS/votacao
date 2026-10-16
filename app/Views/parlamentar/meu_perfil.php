<?php declare(strict_types=1); ?>
<?php
use App\Models\User;

$user = $user ?? null;
$error = $error ?? null;
$salvo = (bool) ($salvo ?? false);
$fotoUrl = $user ? user_foto_url($user) : null;
$dataNasc = (string) ($user['data_nascimento_formatada'] ?? User::formatarDataNascimento((string) ($user['data_nascimento'] ?? '')));
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Parlamentar</div>
        <div class="panel-value">Meu perfil</div>
        <div class="panel-meta">Atualize seus dados exibidos no painel e na votação.</div>
    </article>
    <?php if ($salvo): ?>
        <article class="panel-card" style="grid-column:1/-1;"><div class="sapl-ok">Perfil salvo.</div></article>
    <?php endif; ?>
    <article class="panel-card usuario-form-card" style="grid-column:1/-1;">
        <?php if ($error): ?><div class="auth-error" style="margin-bottom:14px;"><?= e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(app_index_url()) ?>">
            <input type="hidden" name="r" value="parlamentar/savePerfil">
            <?= csrf_field() ?>
            <div class="usuario-form-foto-row">
                <div class="usuario-form-foto-preview">
                    <?php if ($fotoUrl): ?><img src="<?= e($fotoUrl) ?>" alt="" width="96" height="96"><?php else: ?>
                        <span class="usuario-form-foto-iniciais"><?= e(user_iniciais((string) ($user['nome'] ?? '?'))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="usuario-form-foto-fields">
                    <label class="panel-label">Foto</label>
                    <input class="field" type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if ($fotoUrl): ?>
                        <label class="usuario-check-remover"><input type="checkbox" name="remover_foto" value="1"> Remover foto</label>
                    <?php endif; ?>
                </div>
            </div>
            <div class="usuario-form-grid">
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Nome completo</label>
                    <input class="field" name="nome" value="<?= e((string) ($user['nome'] ?? '')) ?>" required>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">E-mail</label>
                    <input class="field" value="<?= e((string) ($user['email'] ?? '')) ?>" disabled>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Partido</label>
                    <input class="field" name="partido" value="<?= e((string) ($user['partido'] ?? '')) ?>">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Telefone</label>
                    <input class="field" name="telefone" value="<?= e($user['telefone_formatado'] ?? User::formatarTelefone((string) ($user['telefone'] ?? ''))) ?>">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Gabinete</label>
                    <input class="field" name="gabinete" value="<?= e((string) ($user['gabinete'] ?? '')) ?>">
                </div>
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Biografia</label>
                    <textarea class="field usuario-form-textarea" name="biografia" rows="4"><?= e((string) ($user['biografia'] ?? '')) ?></textarea>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Nova senha (opcional)</label>
                    <input class="field" type="password" name="senha" autocomplete="new-password">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Confirmar senha</label>
                    <input class="field" type="password" name="senha_confirm" autocomplete="new-password">
                </div>
            </div>
            <div class="action-row" style="margin-top:18px;">
                <button class="btn btn-primary" type="submit">Salvar perfil</button>
                <a class="btn btn-soft" href="<?= e(route_url('dashboard/index')) ?>">Voltar</a>
            </div>
        </form>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php $title = 'Meu perfil'; $pageTitle = 'Meu perfil'; require __DIR__ . '/../layouts/admin.php'; ?>
