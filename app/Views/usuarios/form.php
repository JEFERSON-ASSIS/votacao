<?php declare(strict_types=1); ?>
<?php
use App\Helpers\UserPerfil;
use App\Models\User;

$user = $user ?? null;
$error = $error ?? null;
$isEdit = $user && !empty($user['id']);
$fotoUrl = $user ? user_foto_url($user) : null;
$roleAtual = UserPerfil::normalize((string) ($user['role'] ?? UserPerfil::USUARIO));

$v = static function (string $key) use ($user): string {
    return e((string) ($user[$key] ?? ''));
};
$dataNasc = (string) ($user['data_nascimento_formatada'] ?? User::formatarDataNascimento((string) ($user['data_nascimento'] ?? '')));
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Usuários</div>
        <div class="panel-value"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></div>
        <div class="panel-meta">Cadastro completo: acesso ao sistema, gabinete, dados pessoais e histórico político.</div>
    </article>

    <article class="panel-card usuario-form-card" style="grid-column:1/-1;">
        <?php if ($error): ?>
            <div class="auth-error" style="margin-bottom:14px;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="<?= e(base_url('?r=usuario/save' . ($isEdit ? '/' . (int) $user['id'] : ''))) ?>">
            <?= csrf_field() ?>

            <div class="usuario-form-foto-row">
                <div class="usuario-form-foto-preview">
                    <?php if ($fotoUrl): ?>
                        <img src="<?= e($fotoUrl) ?>" alt="" width="96" height="96" id="usuario-foto-preview">
                    <?php else: ?>
                        <span class="usuario-form-foto-iniciais" id="usuario-foto-preview"><?= e(user_iniciais((string) ($user['nome'] ?? '?'))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="usuario-form-foto-fields">
                    <label class="panel-label">Foto</label>
                    <input class="field usuario-file-input" type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if ($isEdit && $fotoUrl): ?>
                        <label class="usuario-check-remover">
                            <input type="checkbox" name="remover_foto" value="1"> Remover foto atual
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="usuario-form-section-title">Acesso ao sistema</h3>
            <div class="usuario-form-grid">
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Nome completo</label>
                    <input class="field" name="nome" value="<?= $v('nome') ?>" required>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">E-mail (login)</label>
                    <input class="field" type="email" name="email" value="<?= $v('email') ?>" required autocomplete="off">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">CPF</label>
                    <input class="field" name="cpf" value="<?= e($user['cpf_formatado'] ?? User::formatarCpf((string) ($user['cpf'] ?? ''))) ?>" required placeholder="000.000.000-00" maxlength="14">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Perfil</label>
                    <select class="field" name="role" required>
                        <?php foreach (UserPerfil::options() as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>" <?= $roleAtual === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label"><?= $isEdit ? 'Nova senha (opcional)' : 'Senha' ?></label>
                    <input class="field" type="password" name="senha" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Confirmar senha</label>
                    <input class="field" type="password" name="senha_confirm" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                </div>
            </div>

            <h3 class="usuario-form-section-title">Gabinete e contato</h3>
            <div class="usuario-form-grid">
                <div class="usuario-form-field">
                    <label class="panel-label" for="nome_parlamentar">Nome parlamentar (como no SAPL)</label>
                    <input class="field" id="nome_parlamentar" name="nome_parlamentar" value="<?= $v('nome_parlamentar') ?>" placeholder="Ex.: Jadão do Dogão" maxlength="120">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Partido</label>
                    <input class="field" name="partido" value="<?= $v('partido') ?>" placeholder="Ex.: UNIÃO" maxlength="80">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Telefone</label>
                    <input class="field" name="telefone" value="<?= e($user['telefone_formatado'] ?? User::formatarTelefone((string) ($user['telefone'] ?? ''))) ?>" placeholder="(66) 9 9976-9193" maxlength="20">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Número do gabinete</label>
                    <input class="field" name="gabinete" value="<?= $v('gabinete') ?>" maxlength="30">
                </div>
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Homepage</label>
                    <input class="field" type="url" name="homepage" value="<?= $v('homepage') ?>" placeholder="https://...">
                </div>
            </div>

            <h3 class="usuario-form-section-title">Dados pessoais</h3>
            <div class="usuario-form-grid">
                <div class="usuario-form-field">
                    <label class="panel-label">Naturalidade</label>
                    <input class="field" name="naturalidade" value="<?= $v('naturalidade') ?>" placeholder="Cidade/UF">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Data de nascimento</label>
                    <input class="field" name="data_nascimento" value="<?= e($dataNasc) ?>" placeholder="dd/mm/aaaa" maxlength="10">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Estado civil</label>
                    <input class="field" name="estado_civil" value="<?= $v('estado_civil') ?>" placeholder="Solteiro, Casado...">
                </div>
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Ocupação</label>
                    <input class="field" name="ocupacao" value="<?= $v('ocupacao') ?>" maxlength="200">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Cor</label>
                    <input class="field" name="cor" value="<?= $v('cor') ?>" maxlength="40">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Gênero</label>
                    <input class="field" name="genero" value="<?= $v('genero') ?>" maxlength="40">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Identidade de gênero</label>
                    <input class="field" name="identidade_genero" value="<?= $v('identidade_genero') ?>" maxlength="60">
                </div>
                <div class="usuario-form-field">
                    <label class="panel-label">Orientação sexual</label>
                    <input class="field" name="orientacao_sexual" value="<?= $v('orientacao_sexual') ?>" maxlength="60">
                </div>
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Grau de instrução</label>
                    <input class="field" name="grau_instrucao" value="<?= $v('grau_instrucao') ?>" maxlength="200">
                </div>
            </div>

            <h3 class="usuario-form-section-title">Textos do perfil público</h3>
            <div class="usuario-form-grid usuario-form-grid--stack">
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Biografia</label>
                    <textarea class="field usuario-form-textarea" name="biografia" rows="5"><?= $v('biografia') ?></textarea>
                </div>
                <div class="usuario-form-field usuario-form-field--wide">
                    <label class="panel-label">Histórico cronológico na política</label>
                    <textarea class="field usuario-form-textarea" name="historico_politico" rows="10" placeholder="Mandatos, comissões, votação na urna..."><?= $v('historico_politico') ?></textarea>
                </div>
            </div>

            <div class="action-row" style="margin-top:18px;">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-soft" href="<?= e(base_url('?r=usuario/index')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector('.usuario-file-input');
    var preview = document.getElementById('usuario-foto-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'usuario-foto-preview';
                img.width = 96;
                img.height = 96;
                img.alt = '';
                img.src = e.target.result;
                preview.replaceWith(img);
            }
        };
        reader.readAsDataURL(file);
    });
});
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = $isEdit ? 'Editar usuário' : 'Novo usuário';
$pageTitle = $title;
require __DIR__ . '/../layouts/admin.php';
