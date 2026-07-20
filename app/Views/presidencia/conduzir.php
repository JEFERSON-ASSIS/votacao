<?php declare(strict_types=1); ?>
<?php
use App\Helpers\ConducaoTexto;
use App\Helpers\VotacaoPainelEstado;

$passo = $passoAtual ?? null;
$roteiroAtivos = $roteiroAtivos ?? array_filter($roteiro ?? [], static function (array $p): bool {
    return !isset($p['ativo']) || (int) $p['ativo'] === 1;
});
$totalConducao = count($roteiroAtivos);
$numeroPasso = $passo && $totalConducao > 0 ? (int) ($indiceAtual + 1) : 0;
$percentual = $totalConducao > 0 && $numeroPasso > 0
    ? (int) round(($numeroPasso / $totalConducao) * 100)
    : 0;

$exibirAta = !empty($exibirAta);
$ataDocumento = trim((string) ($ataDocumento ?? ''));
$ataSessaoRef = $ataSessaoRef ?? null;
$ataArquivoUrl = $ataArquivoUrl ?? null;
$ataArquivoPdf = !empty($ataArquivoPdf);

$textoPrincipal = '';
$textoInstrucao = '';
if ($passo) {
    if (($passo['tipo_passo'] ?? 'FALA') === 'INSTRUCAO') {
        $textoInstrucao = ConducaoTexto::acentuarTextoPresidente(
            (string) ($passo['texto_instrucao'] ?? $passo['texto_fala'])
        );
    } else {
        $textoPrincipal = ConducaoTexto::acentuarTextoPresidente((string) ($passo['texto_fala'] ?? ''));
        $textoInstrucao = ConducaoTexto::acentuarTextoPresidente((string) ($passo['texto_instrucao'] ?? ''));
    }
}

function conducao_resumo_passo(array $p): string
{
    $raw = trim((string) ($p['texto_fala'] ?: $p['texto_instrucao'] ?? ''));
    return ConducaoTexto::acentuarTextoPresidente($raw);
}

$statusSessao = strtoupper((string) ($sessao['status'] ?? ''));
$rodadaAberta = $rodadaAberta ?? null;
$podeEncerrarSessao = !in_array($statusSessao, ['ENCERRADA', 'RASCUNHO'], true);
$sessaoBloqueada = sessao_esta_bloqueada($sessao);
$ehPresidente = user_has_role('PRESIDENTE');
$podeEditarRoteiro = user_can('roteiro.montar') && !$ehPresidente;
$podeEditarSessao = user_can('sessao.form') && !$ehPresidente;
$podeResetarRoteiro = user_can('roteiro.resetarExecucao');
$urlVoltarConducao = user_can('sessao.index') ? base_url('?r=sessao/index') : base_url('?r=dashboard/index');
?>
<?php ob_start(); ?>
<section class="conducao-presidente" aria-label="Condução da sessão plenária">
    <header class="conducao-presidente-head">
        <div class="conducao-presidente-intro">
            <p class="conducao-presidente-eyebrow">Condução da sessão</p>
            <h1 class="conducao-presidente-title"><?= e($sessao['titulo'] ?? 'Sessão') ?></h1>
            <p class="conducao-presidente-ref">
                Sessão <?= e((string) ($sessao['numero'] ?? '')) ?>/<?= e((string) ($sessao['ano'] ?? '')) ?>
                <?php if ($passo && $totalConducao > 0): ?>
                    <span class="conducao-presidente-dot" aria-hidden="true">·</span>
                    <span class="conducao-presidente-step-label">Passo <?= e((string) $numeroPasso) ?> de <?= e((string) $totalConducao) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($passo && $totalConducao > 0): ?>
            <div class="conducao-presidente-progress" role="progressbar" aria-valuenow="<?= $numeroPasso ?>" aria-valuemin="1" aria-valuemax="<?= $totalConducao ?>" aria-label="Progresso da sessão">
                <div class="conducao-presidente-progress-ring" style="--pct: <?= $percentual ?>">
                    <span class="conducao-presidente-progress-value"><?= $percentual ?>%</span>
                </div>
                <div class="conducao-presidente-progress-bar">
                    <div class="conducao-presidente-progress-fill" style="width:<?= e((string) $percentual) ?>%"></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="conducao-presidente-tools">
            <a class="btn btn-soft btn-has-icon" href="<?= e($urlVoltarConducao) ?>" title="Voltar">
                <span class="icon-glyph" aria-hidden="true">&#8592;</span>
                <span>Voltar</span>
            </a>
            <?php if (!$sessaoBloqueada && $podeEditarRoteiro): ?>
            <a class="btn btn-soft btn-has-icon" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'] . ($passo ? '&passo=' . (int) $passo['id'] : ''))) ?>" title="Editar roteiro">
                <span class="icon-glyph" aria-hidden="true">&#9998;</span>
                <span>Editar</span>
            </a>
            <?php endif; ?>
            <?php if (!$sessaoBloqueada && $podeResetarRoteiro): ?>
            <form method="post" action="<?= e(base_url('?r=roteiro/resetarExecucao/' . (int) $sessao['id'])) ?>" onsubmit="return confirm('Reiniciar a condução do início?');">
                <?= csrf_field() ?>
                <button class="btn btn-soft btn-has-icon" type="submit" title="Reiniciar condução">
                    <span class="icon-glyph" aria-hidden="true">&#8634;</span>
                    <span>Reiniciar</span>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($sessaoBloqueada && $podeEditarRoteiro): ?>
            <a class="btn btn-soft btn-has-icon" href="<?= e(base_url('?r=roteiro/montar/' . (int) $sessao['id'] . ($passo ? '&passo=' . (int) $passo['id'] : ''))) ?>" title="Ver roteiro (somente leitura)">
                <span class="icon-glyph" aria-hidden="true">&#128065;</span>
                <span>Ver roteiro</span>
            </a>
            <?php endif; ?>
            <?php if ($podeEncerrarSessao): ?>
                <form
                    method="post"
                    action="<?= e(base_url('?r=presidencia/encerrarSessao/' . (int) $sessao['id'])) ?>"
                    data-confirm-title="Concluir sessão"
                    data-confirm="Encerrar esta sessão plenária?"
                    data-confirm-ok="Encerrar sessão"
                >
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-has-icon" type="submit" title="Encerrar sessão plenária">
                        <span class="icon-glyph" aria-hidden="true">&#9632;</span>
                        <span>Encerrar sessão</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!in_array($statusSessao, ['ABERTA', 'EM_VOTACAO'], true)): ?>
        <div class="conducao-presidente-alert" role="status">
            Sessão com status <strong><?= e(sessao_status_label($statusSessao)) ?></strong>.
            <?php if ($podeEditarSessao): ?>
                Para operação oficial, altere para <strong>Aberta</strong> em
                <a href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'])) ?>">editar sessão</a>.
            <?php else: ?>
                Aguarde a preparação administrativa antes da operação oficial.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['sessao_ok']) && $_GET['sessao_ok'] === 'encerrada'): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--ok" role="status">
            Sessão <strong>encerrada</strong> com sucesso.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'rodada_aberta'): ?>
        <div class="conducao-presidente-alert" role="alert">
            Encerre a <strong>votação em andamento</strong> antes de concluir a sessão.
        </div>
    <?php endif; ?>

    <?php if (!$passo): ?>
        <article class="conducao-stage conducao-stage--empty">
            <div class="conducao-stage-empty-icon" aria-hidden="true">✓</div>
            <?php if ($totalConducao > 0): ?>
                <h2 class="conducao-stage-empty-title">Roteiro concluído</h2>
                <p class="conducao-stage-empty-copy">Todos os passos foram executados. Você pode encerrar a sessão plenária.</p>
                <div class="action-row" style="justify-content:center;flex-wrap:wrap;margin-top:16px;">
                    <?php if ($podeEncerrarSessao): ?>
                        <form
                            method="post"
                            action="<?= e(base_url('?r=presidencia/encerrarSessao/' . (int) $sessao['id'])) ?>"
                            data-confirm-title="Concluir sessão"
                            data-confirm="Encerrar esta sessão plenária? O status passará para Encerrada."
                            data-confirm-ok="Encerrar sessão"
                        >
                            <?= csrf_field() ?>
                            <button class="btn btn-danger" type="submit" <?= $rodadaAberta ? 'disabled title="Encerre a votação aberta primeiro"' : '' ?>>
                                Encerrar sessão
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($podeEditarSessao): ?>
                        <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#ata')) ?>">Ata desta sessão</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h2 class="conducao-stage-empty-title">Sem roteiro</h2>
                <p class="conducao-stage-empty-copy"><?= $podeEditarSessao ? 'Gere e monte o roteiro desta sessão para iniciar a condução.' : 'Esta sessão ainda não possui roteiro pronto para condução.' ?></p>
                <?php if ($podeEditarSessao): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#roteiro')) ?>">Montar roteiro</a>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <div class="conducao-presidente-grid">
            <div class="conducao-presidente-main">
                <article class="conducao-stage" aria-live="polite">
                    <?php if (!empty($passo['titulo']) || !empty($passo['pauta_titulo'])): ?>
                        <header class="conducao-stage-header">
                            <?php if (!empty($passo['titulo'])): ?>
                                <span class="conducao-stage-tag"><?= e(ConducaoTexto::acentuarTextoPresidente((string) $passo['titulo'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($passo['pauta_titulo'])): ?>
                                <p class="conducao-stage-materia"><?= e((string) $passo['pauta_titulo']) ?></p>
                            <?php endif; ?>
                        </header>
                    <?php endif; ?>

                    <?php if ($exibirAta): ?>
                        <aside class="conducao-ata-panel">
                            <div class="conducao-ata-panel-head">
                                <span class="conducao-ata-panel-title">Documento da ata</span>
                                <?php if ($ataSessaoRef): ?>
                                    <span class="conducao-ata-panel-ref"><?= e($ataSessaoRef['label']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($ataDocumento !== ''): ?>
                                <div class="conducao-ata-corpo conducao-ata-corpo--scroll"><?= nl2br(e($ataDocumento)) ?></div>
                            <?php endif; ?>
                            <?php if ($ataArquivoUrl): ?>
                                <div class="conducao-ata-arquivo">
                                    <a class="btn btn-soft btn-has-icon" href="<?= e($ataArquivoUrl) ?>" target="_blank" rel="noopener">
                                        <span class="icon-glyph" aria-hidden="true">&#128196;</span>
                                        <span>Abrir documento</span>
                                    </a>
                                    <?php if ($ataArquivoPdf): ?>
                                        <iframe class="conducao-ata-pdf" src="<?= e($ataArquivoUrl) ?>" title="Documento da ata"></iframe>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($ataDocumento === '' && !$ataArquivoUrl): ?>
                                <p class="conducao-ata-vazio">
                                    Nenhum texto ou documento cadastrado.
                                    <?php if ($podeEditarSessao): ?>
                                        <a href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'] . '#ata')) ?>">Cadastrar na sessão</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>

                    <div class="conducao-teleprompter">
                        <?php if ($textoPrincipal !== ''): ?>
                            <p class="conducao-fala conducao-fala--stage"><?= nl2br(e($textoPrincipal)) ?></p>
                        <?php elseif ($textoInstrucao !== '' && ($passo['tipo_passo'] ?? '') === 'INSTRUCAO'): ?>
                            <p class="conducao-fala conducao-fala--stage conducao-fala--instrucao"><?= nl2br(e($textoInstrucao)) ?></p>
                        <?php else: ?>
                            <p class="conducao-fala conducao-fala--stage conducao-fala--muted">Sem texto cadastrado para este passo.</p>
                        <?php endif; ?>
                        <?php if ($textoInstrucao !== '' && ($passo['tipo_passo'] ?? '') !== 'INSTRUCAO' && $textoPrincipal !== ''): ?>
                            <p class="conducao-instrucao conducao-instrucao--stage"><?= nl2br(e($textoInstrucao)) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($passo['gera_votacao'])): ?>
                        <?php
                        $rodada = $rodadaPasso ?? null;
                        $rodadaAbertaGlobal = $rodadaAberta ?? null;
                        $tituloVotacao = trim((string) ($passo['titulo'] ?? ''));
                        if ($tituloVotacao === '' && !empty($passo['pauta_titulo'])) {
                            $tituloVotacao = (string) $passo['pauta_titulo'];
                        }
                        if ($tituloVotacao === '') {
                            $tituloVotacao = 'Votação';
                        }
                        ?>
                        <div class="conducao-votacao-box conducao-votacao-box--stage">
                            <div class="conducao-votacao-head">
                                <span class="conducao-votacao-icon" aria-hidden="true">⚖</span>
                                <div>
                                    <div class="conducao-votacao-title">Votação deste passo</div>
                                    <div class="conducao-votacao-sub"><?= e($tituloVotacao) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($_GET['votacao_ok'])): ?>
                                <div class="sapl-ok conducao-votacao-msg">Votação encerrada. Confira o resultado abaixo ou na apuração.</div>
                            <?php endif; ?>
                            <?php if (($_GET['votacao_erro'] ?? '') === 'empate_presidente'): ?>
                                <div class="conducao-presidente-alert conducao-presidente-alert--inline" role="alert">
                                    Empate entre Sim e Não: o presidente precisa votar o desempate antes de encerrar.
                                </div>
                            <?php endif; ?>
                            <?php if ($rodada && ($rodada['status'] ?? '') === 'ABERTA'): ?>
                                <?php
                                $encerramentoRodada = VotacaoPainelEstado::validarEncerramento((int) $rodada['id']);
                                $podeEncerrarRodada = $encerramentoRodada['permitido'];
                                $msgEncerrarRodada = $encerramentoRodada['mensagem'];
                                ?>
                                <p class="conducao-votacao-msg">Votação <strong>aberta</strong> — acompanhe os votos dos vereadores em tempo real no painel e conclua quando terminar.</p>
                                <div class="conducao-votacao-actions">
                                    <a class="btn btn-primary btn-has-icon" href="<?= e(base_url('?r=votacao/painel/' . (int) $sessao['id'] . '&rodada=' . (int) $rodada['id'])) ?>">
                                        <span class="icon-glyph" aria-hidden="true">&#9632;</span>
                                        <span>Painel de votação</span>
                                    </a>
                                    <?php if (!$podeEncerrarRodada): ?>
                                        <p class="panel-meta conducao-presidente-alert conducao-presidente-alert--inline" style="margin:0 0 8px;width:100%;">
                                            <?= e($msgEncerrarRodada) ?>
                                        </p>
                                    <?php endif; ?>
                                    <form
                                        method="post"
                                        action="<?= e(base_url('?r=votacao/encerrarRodada/' . (int) $sessao['id'] . '/' . (int) $rodada['id'])) ?>"
                                        <?php if ($podeEncerrarRodada): ?>
                                        data-confirm-title="Encerrar votação"
                                        data-confirm="Encerrar a votação em andamento e apurar o resultado (Aprovado ou Reprovado)?"
                                        data-confirm-ok="Encerrar e apurar"
                                        data-confirm-variant="danger"
                                        <?php endif; ?>
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="conduzir">
                                        <button
                                            class="btn btn-danger"
                                            type="submit"
                                            <?= $podeEncerrarRodada ? '' : 'disabled title="' . e($msgEncerrarRodada) . '"' ?>
                                        >Concluir votação</button>
                                    </form>
                                </div>
                            <?php elseif ($rodada && ($rodada['status'] ?? '') === 'ENCERRADA'): ?>
                                <p class="conducao-votacao-msg">
                                    Resultado: <strong class="conducao-resultado"><?= e(votacao_rotulo_resultado((string) ($rodada['resultado'] ?? ''))) ?></strong>
                                </p>
                                <a class="btn btn-soft" href="<?= e(base_url('?r=votacao/apuracaoRodada/' . (int) $rodada['id'])) ?>">Ver quem votou</a>
                            <?php else: ?>
                                <?php if ($rodadaAbertaGlobal && (int) ($rodadaAbertaGlobal['roteiro_passo_id'] ?? 0) !== (int) $passo['id']): ?>
                                    <div class="conducao-presidente-alert conducao-presidente-alert--inline">
                                        Há outra votação aberta: <?= e((string) $rodadaAbertaGlobal['titulo']) ?>. Conclua-a antes de abrir esta.
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="<?= e(base_url('?r=votacao/abrirRodada/' . (int) $sessao['id'])) ?>" class="conducao-votacao-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="roteiro_passo_id" value="<?= (int) $passo['id'] ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) ($passo['pauta_item_id'] ?? 0) ?>">
                                    <input type="hidden" name="titulo" value="<?= e($tituloVotacao) ?>">
                                    <input type="hidden" name="return_to" value="conduzir">
                                    <button class="btn btn-primary btn-has-icon" type="submit" <?= ($rodadaAbertaGlobal && (int) ($rodadaAbertaGlobal['roteiro_passo_id'] ?? 0) !== (int) $passo['id']) ? 'disabled' : '' ?>>
                                        <span class="icon-glyph" aria-hidden="true">&#9654;</span>
                                        <span>Abrir votação</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <nav class="conducao-nav conducao-nav--stage" aria-label="Navegação do roteiro">
                    <form method="post" action="<?= e(base_url('?r=presidencia/voltar/' . (int) $sessao['id'])) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-soft btn-has-icon conducao-nav-btn" type="submit" <?= ($indiceAtual ?? 0) <= 0 ? 'disabled' : '' ?>>
                            <span class="icon-glyph" aria-hidden="true">&#8592;</span>
                            <span>Anterior</span>
                        </button>
                    </form>
                    <form method="post" action="<?= e(base_url('?r=presidencia/avancar/' . (int) $sessao['id'])) ?>" class="conducao-nav-next-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-has-icon conducao-nav-btn conducao-nav-btn--next" type="submit">
                            <span>Próximo passo</span>
                            <span class="icon-glyph" aria-hidden="true">&#8594;</span>
                        </button>
                    </form>
                </nav>
            </div>

            <aside class="conducao-timeline-panel" aria-label="Roteiro completo">
                <div class="conducao-timeline-head">
                    <span class="conducao-timeline-title">Roteiro</span>
                    <span class="conducao-timeline-count"><?= e((string) $totalConducao) ?> passos</span>
                </div>
                <ol class="conducao-timeline" id="conducao-timeline-list">
                    <?php foreach ($roteiroAtivos as $i => $p): ?>
                        <?php
                        $isCurrent = $passo && (int) $p['id'] === (int) $passo['id'];
                        $statusExec = (string) ($p['status_execucao'] ?? '');
                        $resumo = conducao_resumo_passo($p);
                        $tituloItem = trim((string) ($p['titulo'] ?? ''));
                        if ($tituloItem === '' && !empty($p['pauta_titulo'])) {
                            $tituloItem = (string) $p['pauta_titulo'];
                        }
                        ?>
                        <li
                            class="conducao-timeline-item<?= $isCurrent ? ' is-current' : '' ?><?= $statusExec !== '' ? ' is-' . strtolower($statusExec) : '' ?>"
                            <?= $isCurrent ? 'aria-current="step"' : '' ?>
                        >
                            <span class="conducao-timeline-num"><?= $i + 1 ?></span>
                            <span class="conducao-timeline-body">
                                <?php if ($tituloItem !== ''): ?>
                                    <span class="conducao-timeline-label"><?= e(ConducaoTexto::acentuarTextoPresidente($tituloItem)) ?></span>
                                <?php endif; ?>
                                <span class="conducao-timeline-preview"><?= e(mb_strimwidth($resumo, 0, 88, '…')) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </aside>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var current = document.querySelector('.conducao-timeline-item.is-current');
            var list = document.getElementById('conducao-timeline-list');
            if (current && list) {
                var top = current.offsetTop - list.clientHeight / 2 + current.clientHeight / 2;
                list.scrollTop = Math.max(0, top);
            }
        });
        </script>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$parlAlertaGlobalDesligado = true;
$title = 'Conduzir sessão';
$pageTitle = 'Conduzir sessão';
$bodyClass = 'admin-layout admin-layout--conducao';
require __DIR__ . '/../layouts/admin.php';
