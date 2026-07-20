<?php declare(strict_types=1); ?>
<?php
use App\Helpers\UserPerfil;
use App\Models\User;

$users = $users ?? [];
$erro = $_GET['erro'] ?? '';
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Usuários</div>
                <div class="panel-value">Cadastro e perfis de acesso</div>
                <div class="panel-meta">Perfis e permissões granulares em Permissões.</div>
            </div>
            <div class="action-row">
                <?php if (user_can('usuario.form')): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('?r=usuario/form')) ?>">Novo usuário</a>
                <?php endif; ?>
                <?php if (user_can('menu.permissoes')): ?>
                    <a class="btn btn-soft" href="<?= e(route_url('permissoes/index')) ?>">Permissões</a>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <?php if ($erro === 'auto'): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="auth-error">Você não pode excluir o próprio usuário.</div>
        </article>
    <?php endif; ?>

    <article class="panel-card" style="grid-column:1/-1;">
        <div class="table-wrap">
            <table class="leg-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Partido</th>
                        <th>Perfil</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr><td colspan="8" class="panel-meta">Nenhum usuário cadastrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <?php $fotoUrl = user_foto_url($u); ?>
                        <tr>
                            <td>
                                <div class="usuario-lista-foto">
                                    <?php if ($fotoUrl): ?>
                                        <img src="<?= e($fotoUrl) ?>" alt="" width="40" height="40">
                                    <?php else: ?>
                                        <span class="usuario-lista-iniciais"><?= e(user_iniciais((string) $u['nome'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?= e($u['nome']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e($u['cpf_formatado'] ?? User::formatarCpf((string) ($u['cpf'] ?? ''))) ?></td>
                            <td><?= e($u['telefone_formatado'] ?? ($u['telefone'] ?? '—')) ?></td>
                            <td><?= e($u['partido'] ?? '—') ?></td>
                            <td>
                                <span class="status-chip <?= e(UserPerfil::chipClass($u['role'] ?? '')) ?>">
                                    <?= e(UserPerfil::label($u['role'] ?? '')) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-row">
                                    <?php if (user_can('usuario.form')): ?>
                                        <a class="btn btn-soft btn--sm" href="<?= e(base_url('?r=usuario/form/' . (int) $u['id'])) ?>">Editar</a>
                                    <?php endif; ?>
                                    <?php if (user_can('menu.permissoes')): ?>
                                        <a class="btn btn-soft btn--sm" href="<?= e(route_url('permissoes/index', ['user_id' => (int) $u['id']])) ?>">Permissões</a>
                                    <?php endif; ?>
                                    <?php if (user_can('usuario.delete')): ?>
                                        <form method="post" action="<?= e(base_url('?r=usuario/delete/' . (int) $u['id'])) ?>" onsubmit="return confirm('Excluir este usuário?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-danger btn--sm" type="submit">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Usuários';
$pageTitle = 'Usuários';
require __DIR__ . '/../layouts/admin.php';
