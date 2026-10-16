<?php declare(strict_types=1); ?>
<?php
use App\Helpers\PermissaoCatalog;
use App\Helpers\UserPerfil;

$users = $users ?? [];
$usuario = $usuario ?? null;
$userId = (int) ($userId ?? 0);
$grupos = $grupos ?? [];
$chavesAtivas = $chavesAtivas ?? [];
$personalizado = (bool) ($personalizado ?? false);
$salvo = (bool) ($salvo ?? false);
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="panel-label">Permissões</div>
        <div class="panel-value">Liberar menu e ações por usuário</div>
        <div class="panel-meta">Marque o que cada pessoa pode acessar e clique em Salvar. Enquanto não salvar personalização, vale o perfil padrão do cargo.</div>
    </article>

    <article class="panel-card" style="grid-column:1/-1;max-width:420px;">
        <form method="get" action="<?= e(app_index_url()) ?>">
            <input type="hidden" name="r" value="permissoes/index">
            <label class="panel-label">Usuário</label>
            <div class="permissoes-user-picker">
                <select class="field" name="user_id" required onchange="this.form.submit()">
                    <option value="">— Selecione —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $userId === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['nome']) ?> (<?= e(user_perfil_label($u['role'] ?? '')) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </article>

    <?php if ($salvo && $usuario): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="sapl-ok">Permissões salvas para <?= e($usuario['nome']) ?>.</div>
        </article>
    <?php endif; ?>

    <?php if ($usuario): ?>
        <article class="panel-card permissoes-editor" style="grid-column:1/-1;">
            <div class="permissoes-editor-head">
                <div>
                    <div class="panel-value"><?= e($usuario['nome']) ?></div>
                    <div class="panel-meta">
                        Perfil: <?= e(user_perfil_label($usuario['role'] ?? '')) ?>
                        <?php if ($personalizado): ?>
                            · <strong>Permissões personalizadas</strong>
                        <?php else: ?>
                            · usando perfil padrão
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form method="post" action="<?= e(app_index_url()) ?>" class="permissoes-form">
                <input type="hidden" name="r" value="permissoes/save">
                <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                <?= csrf_field() ?>

                <p class="panel-meta permissoes-dica">
                    Marque ou desmarque as opções abaixo e salve.
                    (<?= e(user_perfil_label($usuario['role'] ?? '')) ?>),
                </p>

                <article class="panel-card" style="margin-bottom:16px;border-color:rgba(106,180,255,.28);">
                    <div class="panel-meta">
                        <strong>Pacotes por perfil:</strong> use um pacote abaixo para aplicar rapidamente uma visão padrão.
                        Depois, ajuste manualmente se precisar de exceções para este usuário.
                    </div>
                    <div class="action-row" style="margin-top:10px;">
                        <button type="button" class="btn btn-soft btn--sm" data-pacote-perfil="admin">Aplicar pacote Administrador</button>
                        <button type="button" class="btn btn-soft btn--sm" data-pacote-perfil="presidente">Aplicar pacote Presidente</button>
                        <button type="button" class="btn btn-soft btn--sm" data-pacote-perfil="parlamentar">Aplicar pacote Parlamentar</button>
                    </div>
                </article>

                <?php if (UserPerfil::ehParlamentar($usuario['role'] ?? '')): ?>
                    <article class="panel-card" style="margin-bottom:16px;border-color:rgba(126,226,140,.35);">
                        <div class="panel-meta">
                            <strong>Parlamentar — menu Sessões:</strong> marque
                            <code>menu.sessoes_parlamentar</code> em <strong>Menu principal</strong>
                            (não use <code>menu.sessoes</code>, que é só para admin).
                            No grupo <strong>Parlamentar (ações)</strong>, marque listar, ver pauta, ver matéria e votar.
                        </div>
                        <button type="button" class="btn btn-soft btn--sm" id="btn-pacote-parlamentar" style="margin-top:10px;">
                            Marcar pacote completo do parlamentar
                        </button>
                    </article>
                <?php endif; ?>

                <div id="permissoes-grid-wrap">
                    <?php foreach ($grupos as $grupoId => $grupo): ?>
                        <section class="permissoes-grupo" data-grupo="<?= e($grupoId) ?>">
                            <div class="permissoes-grupo-head">
                                <h3 class="permissoes-grupo-title"><?= e($grupo['label']) ?></h3>
                                <button type="button" class="btn btn-soft btn--sm permissoes-marca-grupo" data-acao="marcar">Marcar todos</button>
                                <button type="button" class="btn btn-soft btn--sm permissoes-marca-grupo" data-acao="desmarcar">Limpar</button>
                            </div>
                            <ul class="permissoes-lista">
                                <?php foreach ($grupo['items'] as $chave => $rotulo): ?>
                                    <li>
                                        <label class="permissoes-item">
                                            <input type="checkbox" name="permissoes[]" value="<?= e($chave) ?>"
                                                data-perm-chave="<?= e($chave) ?>"
                                                <?= isset($chavesAtivas[$chave]) ? 'checked' : '' ?>>
                                            <span><?= e($rotulo) ?></span>
                                            <code class="permissoes-chave"><?= e($chave) ?></code>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>

                <!-- <label class="permissoes-padrao-check permissoes-padrao-check--footer">
                    <input type="checkbox" name="usar_perfil_padrao" value="1" id="usar-perfil-padrao">
                    Ao salvar, remover personalização e usar só o perfil padrão
                </label> -->

                <div class="action-row" style="margin-top:20px;">
                    <button class="btn btn-primary" type="submit">Salvar permissões</button>
                    <a class="btn btn-soft" href="<?= e(route_url('usuario/index')) ?>">Voltar aos usuários</a>
                </div>
            </form>
        </article>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var padrao = document.getElementById('usar-perfil-padrao');
    var wrap = document.getElementById('permissoes-grid-wrap');
    if (padrao && wrap) {
        padrao.addEventListener('change', function () {
            wrap.classList.toggle('permissoes-grid-dim', padrao.checked);
        });
    }
    var pacotesPerfil = {
        admin: <?= json_encode(PermissaoCatalog::chavesAdministrador(), JSON_UNESCAPED_UNICODE) ?>,
        presidente: <?= json_encode(PermissaoCatalog::chavesPresidente(), JSON_UNESCAPED_UNICODE) ?>,
        parlamentar: <?= json_encode(PermissaoCatalog::chavesParlamentar(), JSON_UNESCAPED_UNICODE) ?>
    };
    function aplicarPacote(chaves) {
        var padrao = document.getElementById('usar-perfil-padrao');
        if (padrao) padrao.checked = false;
        document.querySelectorAll('input[data-perm-chave]').forEach(function (cb) {
            cb.checked = chaves.indexOf(cb.getAttribute('data-perm-chave')) >= 0;
        });
    }
    document.querySelectorAll('[data-pacote-perfil]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            aplicarPacote(pacotesPerfil[btn.getAttribute('data-pacote-perfil')] || []);
        });
    });
    var pacoteParl = pacotesPerfil.parlamentar;
    var btnPacote = document.getElementById('btn-pacote-parlamentar');
    if (btnPacote) {
        btnPacote.addEventListener('click', function () {
            aplicarPacote(pacoteParl);
        });
    }
    document.querySelectorAll('.permissoes-marca-grupo').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var grupo = btn.closest('.permissoes-grupo');
            if (!grupo) return;
            var marcar = btn.getAttribute('data-acao') === 'marcar';
            grupo.querySelectorAll('input[type=checkbox][name="permissoes[]"]').forEach(function (cb) {
                cb.checked = marcar;
            });
        });
    });
});
</script>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Permissões';
$pageTitle = 'Permissões';
require __DIR__ . '/../layouts/admin.php';
