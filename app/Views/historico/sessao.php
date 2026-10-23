<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? null;
$timeline = $timeline ?? [];
$presentes = $presentes ?? [];
$votacoes = $votacoes ?? [];
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent" style="grid-column:1/-1;">
        <div class="action-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <div>
                <div class="panel-label">Histórico da sessão</div>
                <div class="panel-value"><?= e((string) ($sessao['titulo'] ?? '')) ?></div>
                <div class="panel-meta">#<?= (int) ($sessao['numero'] ?? 0) ?>/<?= (int) ($sessao['ano'] ?? 0) ?></div>
            </div>
            <div class="action-row">
                <?php if (user_can('sessao.exportarPauta')): ?>
                    <a class="btn btn-soft" href="<?= e(route_url('sessao/exportarPauta/' . (int) $sessao['id'])) ?>" target="_blank" rel="noopener">Exportar pauta</a>
                <?php endif; ?>
                <a class="btn btn-soft" href="<?= e(route_url('historico/index')) ?>">Voltar</a>
            </div>
        </div>
    </article>

    <?php if (!empty($sessao['sapl_sessao_id']) || !empty($sessao['data_sessao'])): ?>
        <article class="panel-card span-full relatorio-origem">
            <?php if (!empty($sessao['data_sessao'])): ?>
                <span>Data: <strong><?= e(date('d/m/Y', strtotime((string) $sessao['data_sessao']) ?: time())) ?><?= !empty($sessao['hora_inicio']) ? ' às ' . e((string) $sessao['hora_inicio']) : '' ?></strong></span>
            <?php endif; ?>
            <?php if (!empty($sessao['sapl_sessao_id'])): ?>
                <span class="status-chip open">Importada do SAPL</span>
                <a href="<?= e(\App\Services\SaplCliente::baseUrl() . '/sessao/' . (int) $sessao['sapl_sessao_id']) ?>" target="_blank" rel="noopener">Ver no SAPL</a>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <article class="panel-card span-full">
        <h2 class="relatorio-titulo">Presentes (<?= count($presentes) ?>)</h2>
        <?php if ($presentes === []): ?>
            <p class="panel-meta">Nenhuma presença registrada.</p>
        <?php else: ?>
            <ul class="relatorio-presentes">
                <?php foreach ($presentes as $p): ?>
                    <li><?= e((string) ($p['nome'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="panel-card span-full">
        <h2 class="relatorio-titulo">Votações (<?= count($votacoes) ?>)</h2>
        <?php if ($votacoes === []): ?>
            <p class="panel-meta">Nenhuma votação realizada nesta sessão.</p>
        <?php endif; ?>
        <?php foreach ($votacoes as $i => $v): ?>
            <?php $rodada = $v['rodada']; $resultado = (string) ($rodada['resultado'] ?? ''); ?>
            <section class="relatorio-votacao">
                <div class="relatorio-votacao-topo">
                    <strong><?= (int) $i + 1 ?>. <?= e((string) ($rodada['titulo'] ?? 'Votação')) ?></strong>
                    <?php if ($resultado !== ''): ?>
                        <span class="conducao-resultado conducao-resultado--<?= e(strtolower($resultado)) ?>"><?= e(votacao_rotulo_resultado($resultado)) ?></span>
                    <?php else: ?>
                        <span class="status-chip warn"><?= e(votacao_rotulo_status((string) ($rodada['status'] ?? ''))) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($v['descricao']): ?>
                    <p class="relatorio-frase"><?= e($v['descricao']['frase']) ?></p>
                    <p class="relatorio-explicacao"><?= e($v['descricao']['explicacao']) ?></p>
                <?php endif; ?>
                <?php
                $listaVotos = $v['parlamentares'];
                $placarVotos = $v['resumo'];
                require __DIR__ . '/../partials/_conducao_votos_lista.php';
                ?>
                <a class="btn btn-soft btn--sm" href="<?= e(base_url('?r=votacao/apuracaoRodada/' . (int) $rodada['id'])) ?>">Apuração completa</a>
            </section>
        <?php endforeach; ?>
    </article>

    <article class="panel-card" style="grid-column:1/-1;">
        <h2 class="relatorio-titulo">Linha do tempo</h2>
        <?php if ($timeline === []): ?>
            <p class="panel-meta">Nenhum evento registrado ainda nesta sessão.</p>
        <?php else: ?>
            <ul class="historico-timeline">
                <?php foreach ($timeline as $ev): ?>
                    <li class="historico-timeline-item historico-timeline-item--<?= e((string) ($ev['categoria'] ?? 'evento')) ?>">
                        <time class="historico-timeline-quando">
                            <?php
                            $q = (string) ($ev['quando'] ?? '');
                            echo $q !== '' ? e(date('d/m/Y H:i', strtotime($q) ?: time())) : '—';
                            ?>
                        </time>
                        <div class="historico-timeline-corpo">
                            <strong><?= e((string) ($ev['titulo'] ?? '')) ?></strong>
                            <?php if (!empty($ev['meta'])): ?>
                                <span class="panel-meta"><?= e((string) $ev['meta']) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Histórico — ' . ($sessao['titulo'] ?? '');
$pageTitle = 'Relatório da sessão';
require __DIR__ . '/../layouts/admin.php';
