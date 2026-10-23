<?php declare(strict_types=1); ?>
<?php
use App\Helpers\ConducaoTexto;
use App\Helpers\ConducaoRichText;
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
    return ConducaoRichText::plain($raw);
}

$statusSessao = strtoupper((string) ($sessao['status'] ?? ''));
$rodadaAberta = $rodadaAberta ?? null;
$sessaoIniciada = in_array($statusSessao, ['ABERTA', 'EM_VOTACAO'], true);
$podeIniciarSessao = user_can('presidencia.iniciar') && $statusSessao === 'CRIADO';
$podeEncerrarSessao = !in_array($statusSessao, ['ENCERRADA', 'RASCUNHO', 'CRIADO'], true);
$sessaoBloqueada = sessao_esta_bloqueada($sessao);
$ehPresidente = user_has_role('PRESIDENTE');
$podeEditarRoteiro = user_can('roteiro.montar') && !$ehPresidente;
$podeEditarSessao = user_can('sessao.form') && !$ehPresidente;
$podeResetarRoteiro = user_can('roteiro.resetarExecucao');
$parlamentares = $parlamentares ?? [];
$presentesIds = array_map('intval', $presentesIds ?? []);
$urlVoltarConducao = user_can('sessao.index') ? base_url('?r=sessao/index') : base_url('?r=dashboard/index');
$conducaoEstado = $conducaoEstado ?? null;
$authIdConducao = (int) (\App\Core\Auth::user()['id'] ?? 0);
$titularIdConducao = (int) ($conducaoEstado['titular_user_id'] ?? 0);
$condutorTemporario = $conducaoEstado && $authIdConducao !== $titularIdConducao;
$presentesMap = array_fill_keys(array_map('intval', $presentesIds ?? []), true);
$parlamentaresTransferencia = array_values(array_filter(
    $parlamentares ?? [],
    static fn (array $u): bool => isset($presentesMap[(int) ($u['id'] ?? 0)])
));
?>
<?php ob_start(); ?>
<section class="conducao-presidente" aria-label="Condução da sessão plenária" data-server-ts="<?= time() ?>">
    <header class="conducao-presidente-head">
        <div class="conducao-presidente-intro">
            <p class="conducao-presidente-eyebrow">Condução da sessão</p>
            <h1 class="conducao-presidente-title"><?= e($sessao['titulo'] ?? 'Sessão') ?></h1>
            <p class="conducao-presidente-ref">
                Sessão <?= e((string) ($sessao['numero'] ?? '')) ?>/<?= e((string) ($sessao['ano'] ?? '')) ?>
                <?php if ($sessaoIniciada): ?>
                    <span class="conducao-presidente-dot" aria-hidden="true">·</span>
                    <span><?= count($presentesIds) ?> parlamentar(es) presente(s)</span>
                <?php endif; ?>
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
            <?php if (!$sessaoBloqueada && $podeEditarSessao && !$sessaoIniciada): ?>
            <a class="btn btn-soft btn-has-icon" href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'])) ?>" title="Editar dados e pauta">
                <span class="icon-glyph" aria-hidden="true">&#9776;</span>
                <span>Dados e pauta</span>
            </a>
            <?php endif; ?>
            <?php if ($sessaoIniciada && $conducaoEstado): ?>
                <?php if ($condutorTemporario): ?>
                    <form
                        method="post"
                        action="<?= e(base_url('?r=presidencia/devolverConducao/' . (int) $sessao['id'])) ?>"
                        data-confirm-title="Devolver presidência"
                        data-confirm="Devolver a presidência a <?= e((string) ($conducaoEstado['titular_nome'] ?? 'presidente titular')) ?>? Você voltará à visão de parlamentar."
                        data-confirm-ok="Devolver presidência"
                    >
                        <?= csrf_field() ?>
                        <button class="btn btn-soft btn-has-icon" type="submit" title="<?= $rodadaAberta ? 'Encerre a votação aberta antes de devolver' : 'Devolver a presidência ao titular' ?>"<?= $rodadaAberta ? ' disabled' : '' ?>>
                            <span class="icon-glyph" aria-hidden="true">&#8617;</span>
                            <span>Devolver presidência</span>
                        </button>
                    </form>
                <?php elseif ($parlamentaresTransferencia !== []): ?>
                    <button class="btn btn-soft btn-has-icon" type="button" id="abrir-transferencia-conducao" title="<?= $rodadaAberta ? 'Encerre a votação aberta antes de transferir' : 'Passar a presidência a um parlamentar presente' ?>"<?= $rodadaAberta ? ' disabled' : '' ?>>
                        <span class="icon-glyph" aria-hidden="true">&#8644;</span>
                        <span>Passar presidência</span>
                    </button>
                <?php endif; ?>
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

    <?php if ($condutorTemporario): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--warn" role="status">
            Você está presidindo esta sessão em exercício. Presidente titular: <strong><?= e((string) ($conducaoEstado['titular_nome'] ?? '')) ?></strong>.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['conducao_erro'])): ?>
        <div class="conducao-presidente-alert" role="alert"><?= e((string) $_GET['conducao_erro']) ?></div>
    <?php endif; ?>
    <?php if (in_array($_GET['conducao_ok'] ?? '', ['retomada', 'assumida'], true) && !$condutorTemporario): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--ok" role="status">Você está novamente na presidência desta sessão.</div>
    <?php endif; ?>

    <?php if (!$condutorTemporario && $parlamentaresTransferencia !== []): ?>
        <div class="modal-overlay is-hidden" id="transferencia-conducao-modal" aria-hidden="true">
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="transferencia-conducao-titulo">
                <div class="panel-label">Presidência temporária</div>
                <div class="panel-value" id="transferencia-conducao-titulo">Passar presidência</div>
                <div class="panel-meta" style="margin-bottom:16px;">O parlamentar escolhido assumirá a condução desta sessão. Você passará para a visão parlamentar e poderá retomar a presidência quando voltar.</div>
                <form method="post" action="<?= e(base_url('?r=presidencia/transferirConducao/' . (int) $sessao['id'])) ?>">
                    <?= csrf_field() ?>
                    <label class="panel-label" for="parlamentar-condutor">Parlamentar presente</label>
                    <select class="field" id="parlamentar-condutor" name="parlamentar_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($parlamentaresTransferencia as $parlamentar): ?>
                            <option value="<?= (int) $parlamentar['id'] ?>"><?= e((string) $parlamentar['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="action-row" style="margin-top:16px;">
                        <button class="btn btn-primary" type="submit">Confirmar transferência</button>
                        <button class="btn btn-soft" type="button" id="fechar-transferencia-conducao">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($podeIniciarSessao): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--warn" role="status">
            Sessão <strong>criada</strong> e pronta. Clique em <strong>Iniciar sessão</strong> para liberar a condução, o telão e o voto dos parlamentares.
            <button class="btn btn-primary" type="button" id="abrir-presenca-modal" style="margin-top:10px;">Iniciar sessão</button>
        </div>
    <?php elseif (!$sessaoIniciada && $statusSessao !== 'ENCERRADA'): ?>
        <div class="conducao-presidente-alert" role="status">
            Sessão com status <strong><?= e(sessao_status_label($statusSessao)) ?></strong>.
            <?php if ($podeEditarSessao): ?>
                Quando a preparação terminar, defina o status como <strong>Criada</strong> em
                <a href="<?= e(base_url('?r=sessao/form/' . (int) $sessao['id'])) ?>">editar sessão</a>.
                Depois o presidente inicia a sessão.
            <?php else: ?>
                Aguarde o administrador marcar a sessão como <strong>Criada</strong>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['sessao_ok']) && $_GET['sessao_ok'] === 'iniciada'): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--ok" role="status">
            Sessão <strong>iniciada</strong>. A condução e o voto dos parlamentares estão liberados.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'sem_presentes'): ?>
        <div class="conducao-presidente-alert" role="alert">
            Selecione ao menos um vereador presente para iniciar a sessão.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_ok']) && $_GET['sessao_ok'] === 'criada'): ?>
        <div class="conducao-presidente-alert conducao-presidente-alert--ok" role="status">
            Sessão criada e roteiro inicial gerado. Revise esta tela e clique em <strong>Iniciar sessão</strong> quando estiver pronto.
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
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'nao_criado'): ?>
        <div class="conducao-presidente-alert" role="alert">
            Só é possível iniciar sessões com status <strong>Criada</strong>.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'sem_roteiro'): ?>
        <div class="conducao-presidente-alert" role="alert">
            Gere o roteiro da sessão antes de iniciar.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'nao_iniciada'): ?>
        <div class="conducao-presidente-alert" role="alert">
            Inicie a sessão antes de abrir uma votação.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['sessao_erro']) && $_GET['sessao_erro'] === 'roteiro_inicial'): ?>
        <div class="conducao-presidente-alert" role="alert">
            A sessão foi criada, mas o roteiro inicial não pôde ser gerado. Use <strong>Dados e pauta</strong> para revisar a configuração.
        </div>
    <?php endif; ?>

    <?php if ($podeIniciarSessao): ?>
        <div class="modal-overlay is-hidden" id="presenca-inicial-modal" aria-hidden="true">
            <div class="modal-card presenca-inicial-modal-card" role="dialog" aria-modal="true" aria-labelledby="presenca-inicial-title">
                <div class="modal-head">
                    <div>
                        <div class="panel-label">Chamada nominal</div>
                        <h2 id="presenca-inicial-title">Vereadores presentes</h2>
                        <p class="panel-meta">Esta lista será usada para definir os eleitores de cada votação.</p>
                    </div>
                    <button class="modal-close" type="button" id="fechar-presenca-modal" aria-label="Fechar">&times;</button>
                </div>

                <form method="post" action="<?= e(base_url('?r=presidencia/iniciar/' . (int) $sessao['id'])) ?>" id="presenca-inicial-form">
                    <?= csrf_field() ?>
                    <div class="presenca-inicial-toolbar">
                        <strong><span id="presenca-selecionados">0</span> presente(s)</strong>
                        <div class="action-row">
                            <button class="btn btn-soft btn--sm" type="button" id="marcar-todos-presentes">Marcar todos</button>
                            <button class="btn btn-soft btn--sm" type="button" id="limpar-presentes">Limpar</button>
                        </div>
                    </div>

                    <div class="presenca-inicial-lista">
                        <?php foreach ($parlamentares as $vereador): ?>
                            <?php $vereadorId = (int) ($vereador['id'] ?? 0); ?>
                            <label class="presenca-inicial-item">
                                <input
                                    type="checkbox"
                                    name="presentes[]"
                                    value="<?= $vereadorId ?>"
                                    <?= in_array($vereadorId, $presentesIds, true) ? 'checked' : '' ?>
                                >
                                <span class="presenca-inicial-avatar"><?= e(user_iniciais((string) ($vereador['nome'] ?? ''))) ?></span>
                                <span>
                                    <strong><?= e((string) ($vereador['nome'] ?? '')) ?></strong>
                                    <small><?= e(trim((string) ($vereador['partido'] ?? '')) ?: 'Sem partido informado') ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="action-row presenca-inicial-actions">
                        <button class="btn btn-soft" type="button" id="cancelar-presenca-modal">Cancelar</button>
                        <button class="btn btn-primary" type="submit" id="confirmar-inicio-sessao" disabled>Confirmar presentes e iniciar</button>
                    </div>
                </form>
            </div>
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
                        <a class="btn btn-soft" href="<?= e(base_url('?r=sessao/ata/' . (int) $sessao['id'])) ?>">Ata desta sessão</a>
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
                                        <a href="<?= e(base_url('?r=sessao/ata/' . (int) $sessao['id'])) ?>">Cadastrar na sessão</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>

                    <div class="conducao-teleprompter">
                        <?php if ($textoPrincipal !== ''): ?>
                            <div class="conducao-fala conducao-fala--stage"><?= ConducaoRichText::render($textoPrincipal) ?></div>
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
                            <?php if (($_GET['votacao_erro'] ?? '') === 'votos_pendentes'): ?>
                                <div class="conducao-presidente-alert conducao-presidente-alert--inline" role="alert">
                                    A votação não pode ser concluída enquanto houver vereador presente sem votar.
                                </div>
                            <?php endif; ?>
                            <?php if (($_GET['votacao_erro'] ?? '') === 'sem_eleitores'): ?>
                                <div class="conducao-presidente-alert conducao-presidente-alert--inline" role="alert">
                                    Nenhum parlamentar presente foi registrado para esta sessão.
                                </div>
                            <?php endif; ?>
                            <?php if ($rodada && ($rodada['status'] ?? '') === 'ABERTA'): ?>
                                <?php
                                $encerramentoRodada = VotacaoPainelEstado::validarEncerramento((int) $rodada['id']);
                                $podeEncerrarRodada = $encerramentoRodada['permitido'];
                                $msgEncerrarRodada = $encerramentoRodada['mensagem'];
                                $tempoRodada = sessao_tempo_payload($rodada['tempo_limite_seg'] ?? 0, $rodada['created_at'] ?? null);
                                ?>
                                <p class="conducao-votacao-msg">Votação <strong>aberta</strong> — acompanhe os votos dos vereadores em tempo real no painel e conclua quando terminar.</p>
                                <?php if ($tempoRodada): ?>
                                    <div
                                        class="sessao-timer sessao-timer--conducao<?= !empty($tempoRodada['esgotado']) ? ' sessao-timer--esgotado' : '' ?>"
                                        data-sessao-timer
                                        data-fim-ts="<?= (int) $tempoRodada['fim_ts'] ?>"
                                        role="timer"
                                        aria-live="polite"
                                    >
                                        <span class="sessao-timer-label">Tempo da votação</span>
                                        <strong class="sessao-timer-valor" data-sessao-timer-valor><?= e(sessao_tempo_formatar((int) $tempoRodada['restante_seg'])) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div
                                    class="conducao-votacao-actions"
                                    id="conducao-votacao-live"
                                    data-estado-url="<?= e(route_url('votacao/estadoRodada/' . (int) $rodada['id'])) ?>"
                                    data-stream-url="<?= e(route_url('votacao/streamRodada/' . (int) $rodada['id'])) ?>"
                                >
                                    <a class="btn btn-primary btn-has-icon" href="<?= e(base_url('?r=votacao/painel/' . (int) $sessao['id'] . '&rodada=' . (int) $rodada['id'])) ?>">
                                        <span class="icon-glyph" aria-hidden="true">&#9632;</span>
                                        <span>Painel de votação</span>
                                    </a>
                                    <?php $votosPendentes = $encerramentoRodada['pendentes'] ?? []; ?>
                                    <div
                                        class="conducao-presidente-alert conducao-presidente-alert--inline conducao-votos-pendentes"
                                        id="conducao-votos-pendentes"
                                        <?= $podeEncerrarRodada ? 'hidden' : '' ?>
                                    >
                                        <?php if ($votosPendentes !== []): ?>
                                                <strong>Aguardando <?= count($votosPendentes) ?> <?= count($votosPendentes) === 1 ? 'voto' : 'votos' ?>:</strong>
                                                <ul>
                                                    <?php foreach ($votosPendentes as $votoPendente): ?>
                                                        <li><?= e((string) ($votoPendente['nome'] ?? 'Parlamentar')) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                        <?php else: ?>
                                            <?= e($msgEncerrarRodada) ?>
                                        <?php endif; ?>
                                    </div>
                                    <form
                                        method="post"
                                        action="<?= e(base_url('?r=votacao/encerrarRodada/' . (int) $sessao['id'] . '/' . (int) $rodada['id'])) ?>"
                                        data-confirm-title="Encerrar votação"
                                        data-confirm="Encerrar a votação em andamento e apurar o resultado (Aprovado ou Reprovado)?"
                                        data-confirm-ok="Encerrar e apurar"
                                        data-confirm-variant="danger"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="conduzir">
                                        <button
                                            class="btn btn-danger"
                                            id="conducao-concluir-votacao"
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
                                    <div class="conducao-tempo-field">
                                        <?php
                                        $campoId = 'tempo-votacao-' . (int) $passo['id'];
                                        $campoLabel = 'Tempo da votação';
                                        $selecionado = 300;
                                        require __DIR__ . '/../partials/_tempo_select.php';
                                        ?>
                                    </div>
                                    <button class="btn btn-primary btn-has-icon" type="submit" <?= (!$sessaoIniciada || ($rodadaAbertaGlobal && (int) ($rodadaAbertaGlobal['roteiro_passo_id'] ?? 0) !== (int) $passo['id'])) ? 'disabled' : '' ?>>
                                        <span class="icon-glyph" aria-hidden="true">&#9654;</span>
                                        <span>Abrir votação</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <?php if ($sessaoIniciada): ?>
                    <?php
                    $destaqueAtual = $destaqueAtual ?? null;
                    $destaqueIdAtual = (int) ($destaqueAtual['id'] ?? 0);
                    $listaDestaque = $parlamentaresTransferencia !== []
                        ? $parlamentaresTransferencia
                        : ($parlamentares ?? []);
                    ?>
                    <aside class="conducao-destaque-box conducao-destaque-box--fora" aria-label="Destacar parlamentar no telão">
                        <div class="conducao-destaque-head">
                            <span class="conducao-destaque-icon" aria-hidden="true">▣</span>
                            <div>
                                <div class="conducao-destaque-title">Exibir no telão</div>
                                <div class="conducao-destaque-sub">Selecione o parlamentar que pediu a indicação (ou a palavra)</div>
                            </div>
                        </div>
                        <?php if (!empty($_GET['destaque_ok'])): ?>
                            <div class="sapl-ok conducao-destaque-msg">Parlamentar em destaque no telão.</div>
                        <?php endif; ?>
                        <?php if (!empty($_GET['destaque_limpo'])): ?>
                            <div class="sapl-ok conducao-destaque-msg">Destaque do telão removido.</div>
                        <?php endif; ?>
                        <?php if (!empty($_GET['destaque_erro'])): ?>
                            <div class="conducao-presidente-alert conducao-presidente-alert--inline" role="alert"><?= e((string) $_GET['destaque_erro']) ?></div>
                        <?php endif; ?>
                        <?php if ($destaqueAtual): ?>
                            <p class="conducao-destaque-ativo" role="status">
                                No telão agora:
                                <strong><?= e((string) $destaqueAtual['nome']) ?></strong>
                                <?php if (!empty($destaqueAtual['partido'])): ?>
                                    <span>(<?= e((string) $destaqueAtual['partido']) ?>)</span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($destaqueAtual['tempo'])): ?>
                                <?php $tempoDestaque = $destaqueAtual['tempo']; ?>
                                <div
                                    class="sessao-timer sessao-timer--conducao<?= !empty($tempoDestaque['esgotado']) ? ' sessao-timer--esgotado' : '' ?>"
                                    data-sessao-timer
                                    data-fim-ts="<?= (int) $tempoDestaque['fim_ts'] ?>"
                                    role="timer"
                                    aria-live="polite"
                                >
                                    <span class="sessao-timer-label">Tempo de fala</span>
                                    <strong class="sessao-timer-valor" data-sessao-timer-valor><?= e(sessao_tempo_formatar((int) $tempoDestaque['restante_seg'])) ?></strong>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($parlamentaresTransferencia === [] && $listaDestaque !== []): ?>
                            <p class="conducao-destaque-aviso">Nenhum vereador marcado como presente. Ajuste a presença ou selecione abaixo (precisa estar presente para aparecer no telão).</p>
                        <?php endif; ?>
                        <?php if ($listaDestaque === []): ?>
                            <p class="conducao-destaque-aviso">Cadastre parlamentares e marque a presença para destacar no telão.</p>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('?r=presidencia/destacarParlamentar/' . (int) $sessao['id'])) ?>" class="conducao-destaque-form">
                                <?= csrf_field() ?>
                                <label class="panel-label" for="destaque-parlamentar">Parlamentar</label>
                                <select class="field" id="destaque-parlamentar" name="parlamentar_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($listaDestaque as $parlamentar): ?>
                                        <?php
                                        $pid = (int) ($parlamentar['id'] ?? 0);
                                        $estaPresente = isset($presentesMap[$pid]);
                                        ?>
                                        <option value="<?= $pid ?>" <?= $destaqueIdAtual === $pid ? 'selected' : '' ?>>
                                            <?= e((string) ($parlamentar['nome'] ?? '')) ?>
                                            <?php if (!empty($parlamentar['partido'])): ?>
                                                — <?= e((string) $parlamentar['partido']) ?>
                                            <?php endif; ?>
                                            <?= $estaPresente ? '' : ' (ausente)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="conducao-tempo-field">
                                    <?php
                                    $campoId = 'tempo-destaque';
                                    $campoLabel = 'Tempo de fala';
                                    $selecionado = 300;
                                    require __DIR__ . '/../partials/_tempo_select.php';
                                    ?>
                                </div>
                                <div class="action-row conducao-destaque-actions">
                                    <button class="btn btn-primary" type="submit">Mostrar no telão</button>
                                    <?php if ($destaqueAtual): ?>
                                        <button class="btn btn-soft" type="submit" formaction="<?= e(base_url('?r=presidencia/limparDestaque/' . (int) $sessao['id'])) ?>" formnovalidate>Limpar</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        <?php endif; ?>
                    </aside>
                <?php endif; ?>

                <nav class="conducao-nav conducao-nav--stage" aria-label="Navegação do roteiro">
                    <form method="post" action="<?= e(base_url('?r=presidencia/voltar/' . (int) $sessao['id'])) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-soft btn-has-icon conducao-nav-btn" type="submit" <?= (!$sessaoIniciada || ($indiceAtual ?? 0) <= 0) ? 'disabled' : '' ?>>
                            <span class="icon-glyph" aria-hidden="true">&#8592;</span>
                            <span>Anterior</span>
                        </button>
                    </form>
                    <form method="post" action="<?= e(base_url('?r=presidencia/avancar/' . (int) $sessao['id'])) ?>" class="conducao-nav-next-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-has-icon conducao-nav-btn conducao-nav-btn--next" type="submit" <?= !$sessaoIniciada ? 'disabled' : '' ?>>
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
    <?php if ($podeIniciarSessao): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('presenca-inicial-modal');
        var abrir = document.getElementById('abrir-presenca-modal');
        var fechar = document.getElementById('fechar-presenca-modal');
        var cancelar = document.getElementById('cancelar-presenca-modal');
        var marcarTodos = document.getElementById('marcar-todos-presentes');
        var limpar = document.getElementById('limpar-presentes');
        var contador = document.getElementById('presenca-selecionados');
        var confirmar = document.getElementById('confirmar-inicio-sessao');
        var checkboxes = modal ? Array.from(modal.querySelectorAll('input[name="presentes[]"]')) : [];

        function atualizarContador() {
            var total = checkboxes.filter(function (item) { return item.checked; }).length;
            if (contador) contador.textContent = String(total);
            if (confirmar) confirmar.disabled = total === 0;
        }

        function abrirModal() {
            if (!modal) return;
            modal.classList.remove('is-hidden');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            atualizarContador();
        }

        function fecharModal() {
            if (!modal) return;
            modal.classList.add('is-hidden');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        if (abrir) abrir.addEventListener('click', abrirModal);
        if (fechar) fechar.addEventListener('click', fecharModal);
        if (cancelar) cancelar.addEventListener('click', fecharModal);
        if (modal) modal.addEventListener('click', function (event) { if (event.target === modal) fecharModal(); });
        checkboxes.forEach(function (item) { item.addEventListener('change', atualizarContador); });
        if (marcarTodos) marcarTodos.addEventListener('click', function () {
            checkboxes.forEach(function (item) { item.checked = true; });
            atualizarContador();
        });
        if (limpar) limpar.addEventListener('click', function () {
            checkboxes.forEach(function (item) { item.checked = false; });
            atualizarContador();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) fecharModal();
        });
        <?php if (($_GET['sessao_erro'] ?? '') === 'sem_presentes'): ?>abrirModal();<?php endif; ?>
    });
    </script>
    <?php endif; ?>
    <script>
    (function () {
        var modal = document.getElementById('transferencia-conducao-modal');
        var abrir = document.getElementById('abrir-transferencia-conducao');
        var fechar = document.getElementById('fechar-transferencia-conducao');
        function alternar(visivel) {
            if (!modal) return;
            modal.classList.toggle('is-hidden', !visivel);
            modal.classList.toggle('is-open', visivel);
            modal.setAttribute('aria-hidden', visivel ? 'false' : 'true');
        }
        if (abrir) abrir.addEventListener('click', function () { alternar(true); });
        if (fechar) fechar.addEventListener('click', function () { alternar(false); });
        if (modal) modal.addEventListener('click', function (event) {
            if (event.target === modal) alternar(false);
        });
    })();
    </script>
    <?php if ($sessaoIniciada && $conducaoEstado): ?>
    <script>
    (function () {
        // Se a presidência sair deste usuário (ex.: titular retomou), volta para a visão parlamentar.
        var url = <?= json_encode(base_url('?r=presidencia/estadoConducao/' . (int) $sessao['id'])) ?>;
        function verificar() {
            fetch(url + '&_=' + Date.now(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && d.ok && !d.sou_condutor && d.url_parlamentar) {
                        window.location.href = d.url_parlamentar;
                    }
                })
                .catch(function () {});
        }
        setInterval(verificar, 3000);
    })();
    </script>
    <?php endif; ?>
    <script src="<?= e(asset_url('assets/js/sessao-timer.js')) ?>" defer></script>
    <script src="<?= e(asset_url('assets/js/conducao-votacao.js')) ?>" defer></script>
</section>
<?php $content = ob_get_clean(); ?>
<?php
$parlAlertaGlobalDesligado = true;
$title = 'Conduzir sessão';
$pageTitle = 'Conduzir sessão';
$bodyClass = 'admin-layout admin-layout--conducao';
require __DIR__ . '/../layouts/admin.php';
