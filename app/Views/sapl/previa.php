<?php declare(strict_types=1); ?>
<?php
/** @var array|null $previa */
$sessao = $previa['sessao'] ?? [];
$materias = $previa['materias'] ?? [];
$roteiro = $previa['roteiro'] ?? [];
$avisos = $previa['avisos'] ?? [];
$data = (string) ($sessao['data_inicio'] ?? '');
$dataFmt = $data !== '' ? date('d/m/Y', strtotime($data) ?: time()) : '—';
?>
<?php ob_start(); ?>
<section class="panel-grid">
    <article class="panel-card accent span-full sapl-topo">
        <div>
            <span class="sapl-prototipo">Confira antes de importar</span>
            <h1 class="sapl-titulo"><?= e((string) ($sessao['__str__'] ?? 'Sessão do SAPL')) ?></h1>
            <p class="panel-meta"><?= e($dataFmt) ?><?= !empty($sessao['hora_inicio']) ? ' às ' . e((string) $sessao['hora_inicio']) : '' ?></p>
        </div>
        <div class="action-row">
            <a class="btn btn-soft" href="<?= e(route_url('sapl/sessoes')) ?>">Voltar à lista</a>
            <a class="btn btn-soft" href="<?= e($saplUrl . '/sessao/pauta-sessao/' . (int) $saplId . '/') ?>" target="_blank" rel="noopener">Ver pauta no SAPL</a>
        </div>
    </article>

    <?php if (!empty($_GET['sapl_erro'])): ?>
        <article class="panel-card span-full">
            <div class="auth-error" role="alert"><?= e(mb_substr((string) $_GET['sapl_erro'], 0, 200)) ?></div>
        </article>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <article class="panel-card span-full">
            <div class="auth-error" role="alert">Não foi possível consultar o SAPL agora. <?= e($erro) ?></div>
        </article>
    <?php else: ?>
        <article class="panel-card span-full sapl-acao">
            <?php if (!empty($sessaoImportadaId)): ?>
                <div>
                    <strong>Esta sessão já foi importada.</strong>
                    <p class="panel-meta">Para trazer mudanças feitas no SAPL depois da importação, use "Atualizar do SAPL" na lista de sessões.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(route_url('roteiro/montar/' . (int) $sessaoImportadaId)) ?>">Abrir a sessão importada</a>
            <?php elseif (user_can('sapl.importar')): ?>
                <div>
                    <strong>Tudo certo?</strong>
                    <p class="panel-meta">A importação cria a sessão, a pauta com <?= count($materias) ?> matéria(s) e o roteiro do presidente. Você pode ajustar o roteiro depois.</p>
                </div>
                <form method="post" action="<?= e(route_url('sapl/importar/' . (int) $saplId)) ?>"
                      data-confirm-title="Importar sessão do SAPL"
                      data-confirm="Criar a sessão, a pauta e o roteiro a partir do SAPL?"
                      data-confirm-ok="Importar"
                      data-confirm-variant="primary">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit">Importar esta sessão</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="panel-card span-full">
            <h2 class="sapl-subtitulo">1. Dados da sessão</h2>
            <dl class="sapl-dados">
                <div><dt>Nome</dt><dd><?= e((string) ($sessao['__str__'] ?? '')) ?></dd></div>
                <div><dt>Número</dt><dd><?= (int) ($sessao['numero'] ?? 0) ?>ª</dd></div>
                <div><dt>Data e hora</dt><dd><?= e($dataFmt) ?> <?= e((string) ($sessao['hora_inicio'] ?? '')) ?></dd></div>
                <div><dt>Pauta em PDF</dt><dd><?php if (!empty($sessao['upload_pauta'])): ?><a href="<?= e((string) $sessao['upload_pauta']) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>—<?php endif; ?></dd></div>
            </dl>
        </article>

        <article class="panel-card span-full">
            <h2 class="sapl-subtitulo">2. Matérias que entrariam na pauta (<?= count($materias) ?>)</h2>
            <?php if ($materias === []): ?>
                <p class="panel-meta">Esta sessão ainda não tem matérias no expediente nem na ordem do dia do SAPL.</p>
            <?php else: ?>
                <ol class="sapl-materias">
                    <?php foreach ($materias as $m): ?>
                        <li class="sapl-materia">
                            <div class="sapl-materia-topo">
                                <strong><?= e((string) $m['titulo']) ?></strong>
                                <span class="status-chip open"><?= e((string) $m['parte']) ?></span>
                            </div>
                            <p class="sapl-materia-autor">Autor: <strong><?= e((string) ($m['autor_texto'] !== '' ? $m['autor_texto'] : 'não informado no SAPL')) ?></strong></p>
                            <?php if ($m['ementa'] !== ''): ?>
                                <p class="sapl-materia-ementa"><?= e(mb_strimwidth((string) $m['ementa'], 0, 320, '…')) ?></p>
                            <?php endif; ?>
                            <p class="panel-meta">
                                Nesta sessão: <strong><?= e((string) $m['turno_texto']) ?></strong>
                                <?php if ($m['protocolo'] !== ''): ?> — protocolo <?= e((string) $m['protocolo']) ?><?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <?php if ($avisos !== []): ?>
            <article class="panel-card span-full">
                <h2 class="sapl-subtitulo">Pontos para conferir</h2>
                <ul class="sapl-avisos">
                    <?php foreach ($avisos as $aviso): ?>
                        <li><?= e($aviso) ?></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endif; ?>

        <article class="panel-card span-full">
            <h2 class="sapl-subtitulo">3. Roteiro do presidente que seria gerado (<?= count($roteiro) ?> passos)</h2>
            <ol class="sapl-roteiro">
                <?php $blocoAnterior = null; ?>
                <?php foreach ($roteiro as $passo): ?>
                    <?php if ($passo['bloco'] !== $blocoAnterior): ?>
                        <?php $blocoAnterior = $passo['bloco']; ?>
                        <li class="sapl-roteiro-bloco"><?= e((string) $passo['bloco']) ?></li>
                    <?php endif; ?>
                    <li class="sapl-roteiro-passo<?= $passo['tipo_passo'] === 'INSTRUCAO' ? ' sapl-roteiro-passo--instrucao' : '' ?>">
                        <?= e(\App\Helpers\ConducaoRichText::plain((string) $passo['texto'])) ?>
                        <?php if ($passo['votacao']): ?><span class="status-chip vote">Votação</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$title = 'Prévia da importação';
$pageTitle = 'Importar sessão do SAPL';
require __DIR__ . '/../layouts/admin.php';
