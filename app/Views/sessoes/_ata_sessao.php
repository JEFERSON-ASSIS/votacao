<?php declare(strict_types=1); ?>
<?php
use App\Helpers\SessaoAtaUpload;

$sessao = $sessao ?? null;
$sessoesVinculo = $sessoesVinculo ?? [];
$sessaoAnteriorSugerida = $sessaoAnteriorSugerida ?? null;
if (!$sessao || empty($sessao['id'])) {
    return;
}
$sessaoId = (int) $sessao['id'];
$sessaoBloqueada = sessao_esta_bloqueada($sessao);
$vinculoAtual = (int) ($sessao['sessao_anterior_id'] ?? 0);
$sugestaoId = $sessaoAnteriorSugerida ? (int) $sessaoAnteriorSugerida['id'] : 0;
$urlArqAnterior = SessaoAtaUpload::publicUrl((string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''));
$urlArqConteudo = SessaoAtaUpload::publicUrl((string) ($sessao['ata_conteudo_arquivo'] ?? ''));
$extPermitidas = implode(', ', SessaoAtaUpload::allowedExtensions());
?>
<article class="panel-card" id="ata" style="grid-column:1/-1;">
    <div class="panel-label">Ata da sessão anterior</div>
    <div class="panel-meta">
        Cadastre o texto ou documento que o presidente consulta na condução (leitura e votação da ata).
        A <strong>ata desta sessão</strong> (abaixo) sera usada na próxima sessão, se vinculada.
    </div>

    <?php if (!empty($_GET['ata_ok'])): ?>
        <div class="sapl-ok" style="margin-top:10px;">Ata carregada da sessão vinculada (texto e/ou arquivo).</div>
    <?php endif; ?>
    <?php if (!empty($_GET['ata_vazia'])): ?>
        <div class="auth-error" style="margin-top:10px;">A sessão vinculada ainda não tem &ldquo;Ata desta sessão&rdquo; preenchida.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['ata_vinculo'])): ?>
        <div class="sapl-ok" style="margin-top:10px;">Sessão anterior vinculada automaticamente.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['minuta_ok'])): ?>
        <div class="sapl-ok" style="margin-top:10px;">Minuta gerada e acrescentada em &ldquo;Ata desta sessão&rdquo;. Revise o texto antes de publicar.</div>
    <?php endif; ?>

    <?php if ($sessaoBloqueada): ?>
        <div class="sessao-bloqueio-banner" style="margin-top:12px;">Ata em modo somente leitura — <?= e(sessao_bloqueio_motivo($sessao)) ?></div>
    <?php endif; ?>

    <?php if (!$sessaoBloqueada): ?>
    <div class="action-row ata-sessao-acoes" style="margin-top:12px;">
        <?php if ($sugestaoId > 0 && $vinculoAtual !== $sugestaoId): ?>
            <form method="post" action="<?= e(base_url('?r=sessao/vincularAnteriorSugerida/' . $sessaoId)) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-soft" type="submit">Vincular sugestão (#<?= e((string) $sessaoAnteriorSugerida['numero']) ?>/<?= e((string) $sessaoAnteriorSugerida['ano']) ?>)</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('?r=sessao/carregarAtaAnterior/' . $sessaoId)) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-soft" type="submit">Carregar ata da sessão vinculada</button>
        </form>
    </div>
    <p class="panel-meta" style="margin-top:6px;">Salve o vínculo antes de carregar, ou use &ldquo;Vincular sugestão&rdquo;.</p>

    <form method="post" enctype="multipart/form-data" class="ata-sessao-form" style="margin-top:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="return_hash" value="#ata">
        <input type="hidden" name="titulo" value="<?= e((string) ($sessao['titulo'] ?? '')) ?>">
        <input type="hidden" name="numero" value="<?= e((string) ($sessao['numero'] ?? '1')) ?>">
        <input type="hidden" name="ano" value="<?= e((string) ($sessao['ano'] ?? date('Y'))) ?>">
        <input type="hidden" name="status" value="<?= e((string) ($sessao['status'] ?? 'RASCUNHO')) ?>">

        <label class="panel-label">Sessão anterior (vínculo)</label>
        <select class="field" name="sessao_anterior_id" style="margin-bottom:12px;">
            <option value="">— Nenhuma / sugestão automática na condução —</option>
            <?php foreach ($sessoesVinculo as $opt): ?>
                <?php $oid = (int) $opt['id']; ?>
                <option value="<?= $oid ?>" <?= $vinculoAtual === $oid ? 'selected' : '' ?>>
                    #<?= e((string) $opt['numero']) ?>/<?= e((string) $opt['ano']) ?> — <?= e((string) $opt['titulo']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <details class="ata-accordion">
            <summary class="ata-accordion-summary">Texto e documento — ata a ler / aprovar nesta sessão</summary>
            <div class="ata-accordion-body">
                <label class="panel-label">Texto da ata</label>
                <textarea class="field ata-textarea" name="ata_sessao_anterior" rows="12" placeholder="Cole aqui a ata da última sessão ou use Carregar acima…"><?= e((string) ($sessao['ata_sessao_anterior'] ?? '')) ?></textarea>

                <label class="panel-label" style="margin-top:12px;">Documento (opcional)</label>
                <p class="panel-meta">Formatos: <?= e($extPermitidas) ?>. Substitui o arquivo anterior ao enviar um novo.</p>
                <?php if ($urlArqAnterior): ?>
                    <p class="ata-arquivo-atual">
                        Arquivo atual:
                        <a href="<?= e($urlArqAnterior) ?>" target="_blank" rel="noopener">abrir documento</a>
                    </p>
                    <label class="ata-remover-arquivo">
                        <input type="checkbox" name="remover_ata_anterior_arquivo" value="1">
                        Remover arquivo atual
                    </label>
                <?php endif; ?>
                <input class="field ata-file-input" type="file" name="ata_anterior_arquivo" accept=".pdf,.doc,.docx,.txt,.odt,.rtf">
            </div>
        </details>

        <details class="ata-accordion">
            <summary class="ata-accordion-summary">Ata desta sessão (para a proxima)</summary>
            <div class="ata-accordion-body">
                <p class="panel-meta">Preencha ao encerrar. Na sessão seguinte, vincule esta sessão e clique em Carregar.</p>
                <label class="panel-label">Texto</label>
                <textarea class="field ata-textarea" name="ata_conteudo" rows="10" placeholder="Resumo / texto integral da ata desta sessão…"><?= e((string) ($sessao['ata_conteudo'] ?? '')) ?></textarea>

                <label class="panel-label" style="margin-top:12px;">Documento (opcional)</label>
                <?php if ($urlArqConteudo): ?>
                    <p class="ata-arquivo-atual">
                        Arquivo atual:
                        <a href="<?= e($urlArqConteudo) ?>" target="_blank" rel="noopener">abrir documento</a>
                    </p>
                    <label class="ata-remover-arquivo">
                        <input type="checkbox" name="remover_ata_conteudo_arquivo" value="1">
                        Remover arquivo atual
                    </label>
                <?php endif; ?>
                <input class="field ata-file-input" type="file" name="ata_conteudo_arquivo" accept=".pdf,.doc,.docx,.txt,.odt,.rtf">
            </div>
        </details>

        <div class="action-row" style="margin-top:12px;flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">Salvar dados da ata</button>
            <?php if (user_can('sessao.gerarMinutaAta')): ?>
                <button
                    class="btn btn-soft"
                    type="submit"
                    formmethod="post"
                    formaction="<?= e(route_url('sessao/gerarMinutaAta/' . $sessaoId)) ?>"
                >Gerar minuta automática</button>
            <?php endif; ?>
            <?php if (sessao_pode_conduzir($sessao)): ?>
                <a class="btn btn-soft" href="<?= e(base_url('?r=presidencia/conduzir/' . $sessaoId)) ?>">Ir para condução</a>
            <?php endif; ?>
        </div>
    </form>
    <?php else: ?>
        <?php if ($urlArqAnterior || trim((string) ($sessao['ata_sessao_anterior'] ?? '')) !== ''): ?>
            <div class="panel-meta" style="margin-top:12px;"><strong>Leitura na condução:</strong> texto/arquivo cadastrados (somente leitura).</div>
        <?php endif; ?>
        <?php if (trim((string) ($sessao['ata_conteudo'] ?? '')) !== '' || $urlArqConteudo): ?>
            <div class="panel-meta" style="margin-top:8px;"><strong>Ata desta sessão:</strong> registrada.</div>
        <?php endif; ?>
    <?php endif; ?>
</article>
