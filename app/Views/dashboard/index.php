<?php declare(strict_types=1); ?>
<?php
use App\Helpers\UserPerfil;

$user = $user ?? null;
$ehParlamentar = UserPerfil::ehParlamentar($user['role'] ?? '');
$permissoesSalvo = ($_GET['permissoes_salvo'] ?? '') === '1';
?>
<?php ob_start(); ?>
<?php if ($permissoesSalvo): ?>
    <div class="panel-card" style="grid-column:1/-1;margin-bottom:0;">
        <div class="panel-meta" style="color:var(--accent-2);">Permissões salvas com sucesso.</div>
    </div>
<?php endif; ?>
<section class="panel-grid">
    <article class="panel-card accent">
        <div class="panel-label">Usuário</div>
        <div class="panel-value"><?= e($user['nome'] ?? '') ?></div>
        <div class="panel-meta"><?= e(user_perfil_label($user['role'] ?? '')) ?></div>
        <?php if ($ehParlamentar && user_can('menu.meu_perfil')): ?>
            <div class="action-row" style="margin-top:12px;">
                <a class="btn btn-soft btn--sm" href="<?= e(route_url('parlamentar/meuPerfil')) ?>">Meu perfil</a>
            </div>
        <?php endif; ?>
    </article>
    <article class="panel-card">
        <div class="panel-label">Sessão atual</div>
        <div class="panel-value"><?= e($sessaoAtual['titulo'] ?? 'Sem sessão ativa') ?></div>
        <div class="panel-meta"><?= e(isset($sessaoAtual['status']) ? sessao_status_label((string) $sessaoAtual['status']) : 'Inativa') ?></div>
        <?php if ($ehParlamentar && $sessaoAtual && user_can('parlamentar.sessao')): ?>
            <div class="action-row" style="margin-top:12px;flex-wrap:wrap;">
                <a class="btn btn-primary btn--sm" href="<?= e(route_url('parlamentar/sessao/' . (int) $sessaoAtual['id'])) ?>">
                    <?= !empty($votacaoAberta) ? 'Acompanhar e votar' : 'Acompanhar sessão' ?>
                </a>
                <?php if (!empty($materiaEmVotacao) && user_can('parlamentar.materia')): ?>
                    <a class="btn btn-soft btn--sm" href="<?= e(route_url('parlamentar/materia/' . (int) $sessaoAtual['id'] . '/' . (int) $materiaEmVotacao['id'])) ?>">Ver matéria em votação</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($votacaoAberta)): ?>
                <p class="panel-meta" style="margin-top:10px;color:var(--accent-2);">
                    <strong>Votação aberta:</strong> <?= e((string) ($rodadaTitulo ?? 'Votação')) ?> — entre em <strong>Acompanhar sessão</strong> para registrar seu voto.
                </p>
            <?php else: ?>
                <p class="panel-meta" style="margin-top:10px;">Acompanhe leitura da ata, pauta e votações em tempo real nesta tela.</p>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <article class="panel-card">
        <div class="panel-label">Acesso rápido</div>
        <div class="quick-links">
            <?php if ($ehParlamentar): ?>
                <?php if (user_can('menu.sessoes_parlamentar')): ?>
                    <a href="<?= e(route_url('parlamentar/sessoes')) ?>">Ver sessões</a>
                <?php endif; ?>
                <?php if (user_can('menu.meu_perfil')): ?>
                    <a href="<?= e(route_url('parlamentar/meuPerfil')) ?>">Meu perfil</a>
                <?php endif; ?>
            <?php else: ?>
                <?php if (user_can('menu.sessoes')): ?>
                    <a href="<?= e(route_url('sessao/index')) ?>">Sessões</a>
                <?php endif; ?>
                <?php if (user_can('menu.presidencia') && !UserPerfil::matches($user['role'] ?? '', UserPerfil::PRESIDENTE)): ?>
                    <a href="<?= e(route_url('presidencia/index')) ?>">Presidência</a>
                <?php endif; ?>
                <?php if (user_can('menu.votacao')): ?>
                    <a href="<?= e(route_url('votacao/index')) ?>">Votação</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (user_can('menu.usuarios')): ?>
                <a href="<?= e(route_url('usuario/index')) ?>">Usuários</a>
            <?php endif; ?>
            <?php if (user_can('menu.permissoes')): ?>
                <a href="<?= e(route_url('permissoes/index')) ?>">Permissões</a>
            <?php endif; ?>
            <?php if (user_can('menu.publico')): ?>
                <a href="<?= e(route_url('publico/index')) ?>">Visão pública</a>
            <?php endif; ?>
        </div>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Dashboard';
$pageTitle = 'Dashboard';
require __DIR__ . '/../layouts/admin.php';
