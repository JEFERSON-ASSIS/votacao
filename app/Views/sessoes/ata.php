<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$sessoesVinculo = $sessoesVinculo ?? [];
$sessaoAnteriorSugerida = $sessaoAnteriorSugerida ?? null;
$statusSessao = strtoupper((string) ($sessao['status'] ?? ''));
$posEncerrar = $statusSessao === 'ENCERRADA'
    || (!empty($_GET['sessao_ok']) && (string) $_GET['sessao_ok'] === 'encerrada')
    || !empty($_GET['conducao_encerrada']);
// Nesta tela a ata sempre pode ser editada (publicar trava só pauta/roteiro).
$ataSomenteLeitura = false;
$ataTelaDedicada = true;
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Ata da sessão</div>
                <div class="panel-value"><?= e((string) ($sessao['titulo'] ?? 'Sessão')) ?></div>
                <div class="panel-meta">
                    Sessão #<?= e((string) ($sessao['numero'] ?? '')) ?>/<?= e((string) ($sessao['ano'] ?? '')) ?>
                    · <?= e(sessao_status_label((string) ($sessao['status'] ?? ''))) ?>
                </div>
            </div>
            <div class="action-row">
                <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/index')) ?>">Sessões</a>
            </div>
        </div>
    </article>

    <?php if ($posEncerrar): ?>
        <article class="panel-card" style="grid-column:1/-1;">
            <div class="panel-label">O que fazer agora</div>
            <div class="panel-value" style="font-size:1.05rem;margin-top:4px;">Registrar a ata desta sessão</div>
            <div class="panel-meta" style="margin-top:8px;max-width:52rem;">
                A sessão plenária já terminou. Aqui você guarda o texto (ou PDF) da <strong>ata desta sessão</strong>.
                Na próxima sessão, esse registro será carregado para leitura e aprovação.
            </div>
            <ol class="panel-meta" style="margin:12px 0 0;padding-left:1.25rem;max-width:52rem;">
                <li>Abra <strong>Ata desta sessão (para a próxima)</strong></li>
                <li>Cole o texto, anexe o documento, ou use <strong>Gerar minuta automática</strong></li>
                <li>Clique em <strong>Salvar dados da ata</strong></li>
            </ol>
        </article>
    <?php endif; ?>

    <?php require __DIR__ . '/_ata_sessao.php'; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Ata';
$pageTitle = 'Ata — ' . (string) ($sessao['titulo'] ?? 'Sessão');
require __DIR__ . '/../layouts/admin.php';
