<?php declare(strict_types=1); ?>
<?php
/** @var array{itens: list<array<string, mixed>>, total: int, paginas: int} $lista */
$itens = $lista['itens'] ?? [];
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent span-full sapl-topo">
        <div>
            <span class="sapl-prototipo">Protótipo — nada é gravado</span>
            <h1 class="sapl-titulo">Sessões cadastradas no SAPL</h1>
            <p class="panel-meta">Escolha uma sessão para ver a prévia do que seria importado: dados da sessão, matérias com autores e o roteiro do presidente.</p>
        </div>
        <a class="btn btn-soft" href="<?= e($saplUrl . '/sessao/pesquisar-sessao') ?>" target="_blank" rel="noopener">Abrir o SAPL</a>
    </article>

    <?php if ($erro !== ''): ?>
        <article class="panel-card span-full">
            <div class="auth-error" role="alert">Não foi possível consultar o SAPL agora. <?= e($erro) ?> Tente novamente em alguns instantes.</div>
        </article>
    <?php else: ?>
        <article class="panel-card span-full">
            <div class="table-wrap">
                <table class="leg-table">
                    <thead>
                        <tr>
                            <th>Sessão</th>
                            <th>Data</th>
                            <th>Situação no SAPL</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $s): ?>
                            <?php
                            $data = (string) ($s['data_inicio'] ?? '');
                            $dataFmt = $data !== '' ? date('d/m/Y', strtotime($data) ?: time()) : '—';
                            $situacao = !empty($s['finalizada']) ? 'Encerrada' : (!empty($s['iniciada']) ? 'Em andamento' : 'Agendada');
                            $chip = !empty($s['finalizada']) ? 'closed' : (!empty($s['iniciada']) ? 'vote' : 'created');
                            ?>
                            <tr>
                                <td><strong><?= e((string) ($s['__str__'] ?? '')) ?></strong></td>
                                <td><?= e($dataFmt) ?><?= !empty($s['hora_inicio']) ? ' às ' . e((string) $s['hora_inicio']) : '' ?></td>
                                <td><span class="status-chip <?= e($chip) ?>"><?= e($situacao) ?></span></td>
                                <td><a class="btn btn-primary btn--sm" href="<?= e(route_url('sapl/previa/' . (int) $s['id'])) ?>">Ver prévia</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($lista['paginas'] ?? 1) > 1): ?>
                <div class="action-row sapl-paginacao">
                    <?php if ($pagina > 1): ?>
                        <a class="btn btn-soft btn--sm" href="<?= e(route_url('sapl/sessoes', ['pagina' => $pagina - 1])) ?>">Mais recentes</a>
                    <?php endif; ?>
                    <span class="panel-meta">Página <?= (int) $pagina ?> de <?= (int) $lista['paginas'] ?></span>
                    <?php if ($pagina < (int) $lista['paginas']): ?>
                        <a class="btn btn-soft btn--sm" href="<?= e(route_url('sapl/sessoes', ['pagina' => $pagina + 1])) ?>">Mais antigas</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Sessões do SAPL';
$pageTitle = 'Importar sessão do SAPL';
require __DIR__ . '/../layouts/admin.php';
