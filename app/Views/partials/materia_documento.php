<?php declare(strict_types=1); ?>
<?php
/** @var string $urlRaw */
/** @var string|null $label */
/** @var string|null $uid */
/** @var bool|null $compactDoc */

$url = materia_extrair_url($urlRaw ?? '');
if ($url === null) {
    return;
}
$ext = materia_extensao_arquivo($url);
$tipo = materia_tipo_documento($ext);
$uid = $uid ?? substr(md5($url), 0, 8);
$label = $label ?? materia_nome_arquivo($url);
$podeVerAqui = in_array($tipo, ['pdf', 'image'], true);
$compactDoc = !empty($compactDoc);
$nomeArquivo = materia_nome_arquivo($url);
$tipoRotulo = materia_extensao_rotulo($url);
?>
<div class="materia-doc<?= $compactDoc ? ' materia-doc--compact' : '' ?>">
    <?php if (!$compactDoc): ?>
        <p class="materia-doc-label"><?= e($label) ?></p>
        <p class="materia-doc-url panel-meta"><?= e($url) ?></p>
    <?php endif; ?>
    <div class="action-row materia-doc-actions">
        <a class="btn btn-primary btn--sm" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Ver no navegador</a>
        <a class="btn btn-soft btn--sm" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" download="<?= e($nomeArquivo) ?>">Baixar</a>
        <?php if ($podeVerAqui): ?>
            <button type="button" class="btn btn-soft btn--sm" data-materia-doc-toggle="<?= e($uid) ?>" aria-expanded="false">
                Ver aqui
            </button>
        <?php endif; ?>
    </div>
    <?php if ($compactDoc): ?>
        <p class="materia-doc-meta">
            <span class="md-doc-pill"><?= e($tipoRotulo) ?></span>
            <span class="materia-doc-filename" title="<?= e($url) ?>"><?= e($nomeArquivo) ?></span>
        </p>
    <?php endif; ?>
    <?php if ($podeVerAqui): ?>
        <div class="materia-doc-preview is-hidden" id="materia-doc-<?= e($uid) ?>" data-materia-doc-panel>
            <?php if ($tipo === 'pdf'): ?>
                <iframe src="<?= e($url) ?>" title="<?= e($label) ?>" class="materia-doc-iframe"></iframe>
            <?php elseif ($tipo === 'image'): ?>
                <img src="<?= e($url) ?>" alt="<?= e($label) ?>" class="materia-doc-img" loading="lazy">
            <?php elseif ($tipo === 'office'): ?>
                <iframe src="<?= e(materia_office_embed_url($url)) ?>" title="<?= e($label) ?>" class="materia-doc-iframe"></iframe>
                <p class="panel-meta materia-doc-hint">Visualização online do Word/Excel. Se não abrir, use <strong>Ver no navegador</strong> ou <strong>Baixar</strong>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
