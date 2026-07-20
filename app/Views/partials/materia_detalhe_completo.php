<?php declare(strict_types=1); ?>
<?php
use App\Helpers\MateriaSaplFields;

$item = isset($item) && is_array($item) ? MateriaSaplFields::mergeItemAndExtras($item) : [];
$sections = $sections ?? [
    'despachos' => [],
    'documentos' => [],
    'legislacoes' => [],
    'numeracoes' => [],
    'tramitacoes' => [],
    'relatorias' => [],
];

$despachos = materia_filtrar_secao($sections['despachos'] ?? []);
$documentos = materia_filtrar_secao($sections['documentos'] ?? []);
$legislacoes = materia_filtrar_secao($sections['legislacoes'] ?? []);
$numeracoes = materia_filtrar_secao($sections['numeracoes'] ?? []);
$tramitacoes = materia_filtrar_secao($sections['tramitacoes'] ?? []);
$relatorias = materia_filtrar_secao($sections['relatorias'] ?? []);

$numeroVal = MateriaSaplFields::val($item, 'numero');
$anoVal = MateriaSaplFields::val($item, 'ano');
if ($numeroVal === '' && !empty($numeracoes[0]['numero'])) {
    $numeroVal = (string) $numeracoes[0]['numero'];
}
if ($anoVal === '' && !empty($numeracoes[0]['ano'])) {
    $anoVal = (string) $numeracoes[0]['ano'];
}

$tipoMateria = MateriaSaplFields::val($item, 'tipo_materia') ?: materia_rotulo_tipo_sapl($item);
$regime = materia_rotulo_regime($item);
$referenciaUrl = materia_extrair_url(MateriaSaplFields::val($item, 'texto_original')) ?? '';

$camposIdent = [
    ['Ordem na pauta', (string) ($item['ordem'] ?? ''), 'text'],
    ['ID SAPL', MateriaSaplFields::val($item, 'sapl_id'), 'text'],
    ['Tipo SAPL', materia_rotulo_tipo_sapl($item), 'text'],
    ['Tipo de matéria', MateriaSaplFields::val($item, 'tipo_materia'), 'text'],
    ['Número', $numeroVal, 'text'],
    ['Ano', $anoVal, 'text'],
    ['Protocolo', MateriaSaplFields::val($item, 'numero_protocolo'), 'text'],
];

$camposDatas = [
    ['Apresentação', MateriaSaplFields::val($item, 'data_apresentacao'), 'data'],
    ['Tipo apresentação', materia_rotulo_apresentacao($item), 'text'],
    ['Regime', $regime, 'regime'],
    ['Publicação', MateriaSaplFields::val($item, 'data_publicacao'), 'data'],
    ['Apelido', MateriaSaplFields::val($item, 'apelido'), 'text'],
    ['Resultado', MateriaSaplFields::val($item, 'resultado'), 'text'],
];

$flags = [
    ['Em tramitação', materia_rotulo_sim_nao($item, 'em_tramitacao'), 'flag'],
    ['Polêmica', materia_rotulo_sim_nao($item, 'materia_polemica'), 'flag'],
    ['Complementar', materia_rotulo_sim_nao($item, 'eh_complementar'), 'flag'],
];

$camposOrigem = [
    ['Tipo', MateriaSaplFields::val($item, 'tipo_origem_externa'), 'text'],
    ['Número', MateriaSaplFields::val($item, 'numero_origem_externa'), 'text'],
    ['Ano', MateriaSaplFields::val($item, 'ano_origem_externa'), 'text'],
    ['Data', MateriaSaplFields::val($item, 'data_origem_externa'), 'data'],
    ['Local', MateriaSaplFields::val($item, 'local_origem_externa'), 'text'],
];

$camposPrazo = [
    ['Dias de prazo', MateriaSaplFields::val($item, 'dias_prazo'), 'text'],
    ['Fim do prazo', MateriaSaplFields::val($item, 'data_fim_prazo'), 'data'],
];

$camposAutoria = [
    ['Autor', MateriaSaplFields::val($item, 'autor'), 'text'],
    ['IDs SAPL', MateriaSaplFields::val($item, 'autores_ids'), 'text'],
];

$ementa = MateriaSaplFields::val($item, 'ementa');
$textoOriginal = MateriaSaplFields::val($item, 'texto_original');
$linkSapl = MateriaSaplFields::val($item, 'link_detail_backend');
$linkSaplHref = materia_sapl_url_absoluta($linkSapl, $referenciaUrl);

?>
<article class="panel-card materia-detalhe-card" style="grid-column:1/-1;">
    <header class="md-header">
        <div class="md-header-text">
            <p class="md-header-kicker">Detalhamento</p>
            <h3 class="md-header-title">Matéria completa</h3>
            <p class="md-header-desc">Dados cadastrados na pauta (padrão SAPL)</p>
        </div>
        <div class="md-header-badges">
            <?php if (materia_campo_preenchido($tipoMateria)): ?>
                <span class="md-badge md-badge--tipo"><?= e($tipoMateria) ?></span>
            <?php endif; ?>
            <?php if (materia_campo_preenchido($numeroVal) && materia_campo_preenchido($anoVal)): ?>
                <span class="md-badge md-badge--numero">nº <?= e($numeroVal) ?>/<?= e($anoVal) ?></span>
            <?php endif; ?>
            <?php if (materia_campo_preenchido($regime)): ?>
                <span class="md-badge<?= stripos($regime, 'urg') !== false ? ' md-badge--urgente' : '' ?>"><?= e($regime) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (materia_campo_preenchido($ementa)): ?>
        <div class="md-ementa-strip">
            <?php
            $titulo = 'Ementa';
            $texto = $ementa;
            $destaque = 'ementa';
            require __DIR__ . '/_materia_detalhe_texto.php';
            ?>
        </div>
    <?php endif; ?>

    <div class="tabbar md-tabbar" data-tabbar>
        <button type="button" class="tab-btn is-active" data-target="sapl">Legislativa</button>
        <button type="button" class="tab-btn" data-target="origem">Origem</button>
        <button type="button" class="tab-btn" data-target="prazo">Prazo</button>
        <button type="button" class="tab-btn" data-target="autoria">Autores</button>
        <?php if ($despachos !== []): ?><button type="button" class="tab-btn" data-target="despacho">Despachos <span class="md-tab-count"><?= count($despachos) ?></span></button><?php endif; ?>
        <?php if ($documentos !== [] || materia_campo_preenchido($textoOriginal)): ?><button type="button" class="tab-btn" data-target="documentos">Documentos</button><?php endif; ?>
        <?php if ($legislacoes !== []): ?><button type="button" class="tab-btn" data-target="legislacao">Legislação</button><?php endif; ?>
        <?php if ($numeracoes !== []): ?><button type="button" class="tab-btn" data-target="numeracao">Numeração</button><?php endif; ?>
        <?php if ($tramitacoes !== []): ?><button type="button" class="tab-btn" data-target="tramitacao">Tramitação</button><?php endif; ?>
        <?php if ($relatorias !== []): ?><button type="button" class="tab-btn" data-target="relatoria">Relatoria</button><?php endif; ?>
    </div>

    <div class="tab-panels md-panels">
        <div class="tab-panel is-active" data-panel="sapl">
            <section class="md-section">
                <h4 class="md-section-title">Identificação</h4>
                <?php $campos = $camposIdent; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
            </section>
            <section class="md-section">
                <h4 class="md-section-title">Datas e tramitação</h4>
                <?php $campos = $camposDatas; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
            </section>
            <?php if (array_filter($flags, fn ($f) => materia_campo_preenchido($f[1])) !== []): ?>
                <section class="md-section md-section--flags">
                    <h4 class="md-section-title">Situação</h4>
                    <?php $campos = $flags; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
                </section>
            <?php endif; ?>
            <?php
            foreach (
                [
                    ['Indexação', MateriaSaplFields::val($item, 'indexacao'), ''],
                    ['Observação', MateriaSaplFields::val($item, 'observacao'), ''],
                    ['Objeto', MateriaSaplFields::val($item, 'objeto'), ''],
                ] as [$t, $txt, $dest]
            ):
                if (!materia_campo_preenchido($txt)) {
                    continue;
                }
                $titulo = $t;
                $texto = $txt;
                require __DIR__ . '/_materia_detalhe_texto.php';
            endforeach;
            ?>
            <?php if (materia_campo_preenchido($linkSapl)): ?>
                <div class="md-actions">
                    <a class="btn btn-soft" href="<?= e($linkSaplHref) ?>" target="_blank" rel="noopener">Abrir ficha no SAPL</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-panel" data-panel="origem">
            <?php if (array_filter($camposOrigem, fn ($c) => materia_campo_preenchido($c[1])) === []): ?>
                <p class="md-empty">Sem dados de origem externa.</p>
            <?php else: ?>
                <section class="md-section">
                    <?php $campos = $camposOrigem; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
                </section>
            <?php endif; ?>
        </div>

        <div class="tab-panel" data-panel="prazo">
            <?php if (array_filter($camposPrazo, fn ($c) => materia_campo_preenchido($c[1])) === []): ?>
                <p class="md-empty">Sem prazos cadastrados.</p>
            <?php else: ?>
                <section class="md-section">
                    <?php $campos = $camposPrazo; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
                </section>
            <?php endif; ?>
        </div>

        <div class="tab-panel" data-panel="autoria">
            <?php
            $autoriaNotas = MateriaSaplFields::val($item, 'autoria');
            $temAutoria = array_filter($camposAutoria, fn ($c) => materia_campo_preenchido($c[1])) !== []
                || materia_campo_preenchido($autoriaNotas);
            ?>
            <?php if (!$temAutoria): ?>
                <p class="md-empty">Sem autoria cadastrada.</p>
            <?php else: ?>
                <section class="md-section">
                    <?php $campos = $camposAutoria; require __DIR__ . '/_materia_detalhe_grid.php'; ?>
                </section>
                <?php if (materia_campo_preenchido($autoriaNotas)): ?>
                    <?php $titulo = 'Autoria / notas'; $texto = $autoriaNotas; require __DIR__ . '/_materia_detalhe_texto.php'; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($despachos !== []): ?>
        <div class="tab-panel" data-panel="despacho">
            <?php foreach ($despachos as $idx => $row): ?>
                <article class="md-card-item">
                    <header class="md-card-item-head">
                        <span class="md-card-item-num"><?= (int) $idx + 1 ?></span>
                        <strong>Despacho inicial</strong>
                    </header>
                    <?php
                    $campos = array_filter([
                        ['Comissão', (string) ($row['comissao'] ?? ''), 'text'],
                        ['Situação', (string) ($row['situacao'] ?? ''), 'text'],
                    ], fn ($c) => materia_campo_preenchido($c[1]));
                    if ($campos !== []) {
                        require __DIR__ . '/_materia_detalhe_grid.php';
                    }
                    ?>
                    <?php if (materia_campo_preenchido($row['observacao'] ?? '')): ?>
                        <?php $titulo = 'Observação'; $texto = (string) $row['observacao']; require __DIR__ . '/_materia_detalhe_texto.php'; ?>
                    <?php endif; ?>
                    <?php if (materia_campo_preenchido($row['arquivo'] ?? '')): ?>
                        <?php $urlRaw = (string) $row['arquivo']; $label = 'Arquivo do despacho'; $uid = 'desp' . $idx; $compactDoc = true; require __DIR__ . '/materia_documento.php'; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="tab-panel" data-panel="documentos">
            <?php if ($documentos === [] && !materia_campo_preenchido($textoOriginal)): ?>
                <p class="md-empty">Nenhum documento anexo.</p>
            <?php else: ?>
                <?php if (materia_campo_preenchido($textoOriginal)): ?>
                    <article class="md-card-item md-card-item--doc">
                        <header class="md-card-item-head">
                            <span class="md-doc-icon"><?= e(materia_extensao_rotulo(materia_extrair_url($textoOriginal) ?? '')) ?></span>
                            <div>
                                <strong>Texto original</strong>
                                <p class="md-doc-sub"><?= e(materia_nome_arquivo(materia_extrair_url($textoOriginal) ?? '')) ?></p>
                            </div>
                        </header>
                        <?php
                        $urlTexto = materia_extrair_url($textoOriginal);
                        if ($urlTexto):
                            $urlRaw = $textoOriginal;
                            $label = 'Texto original';
                            $uid = 'texto-original';
                            $compactDoc = true;
                            require __DIR__ . '/materia_documento.php';
                        else:
                        ?>
                            <p class="md-texto-corpo"><?= nl2br(e($textoOriginal)) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>
                <?php foreach ($documentos as $docIdx => $doc): ?>
                    <article class="md-card-item md-card-item--doc">
                        <header class="md-card-item-head">
                            <span class="md-doc-icon"><?= e(materia_extensao_rotulo((string) ($doc['arquivo'] ?? ''))) ?></span>
                            <div>
                                <strong><?= e(trim((string) ($doc['nome'] ?? '')) !== '' ? (string) $doc['nome'] : 'Documento ' . ((int) $docIdx + 1)) ?></strong>
                                <?php if (materia_campo_preenchido($doc['tipo'] ?? '')): ?>
                                    <p class="md-doc-sub"><?= e((string) $doc['tipo']) ?></p>
                                <?php endif; ?>
                            </div>
                        </header>
                        <?php
                        $campos = array_filter([
                            ['Data', (string) ($doc['data_doc'] ?? ''), 'data'],
                            ['Autor', (string) ($doc['autor'] ?? ''), 'text'],
                        ], fn ($c) => materia_campo_preenchido($c[1]));
                        if ($campos !== []) {
                            require __DIR__ . '/_materia_detalhe_grid.php';
                        }
                        ?>
                        <?php if (materia_campo_preenchido($doc['arquivo'] ?? '')): ?>
                            <?php $urlRaw = (string) $doc['arquivo']; $label = materia_nome_arquivo($urlRaw); $uid = 'doc' . $docIdx; $compactDoc = true; require __DIR__ . '/materia_documento.php'; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($legislacoes !== []): ?>
        <div class="tab-panel" data-panel="legislacao">
            <?php foreach ($legislacoes as $idx => $row): ?>
                <article class="md-card-item">
                    <header class="md-card-item-head">
                        <span class="md-card-item-num"><?= (int) $idx + 1 ?></span>
                        <strong>Legislação citada</strong>
                    </header>
                    <?php
                    $campos = array_filter([
                        ['Norma', (string) ($row['norma'] ?? ''), 'text'],
                        ['Disposição', (string) ($row['disposicao'] ?? ''), 'text'],
                    ], fn ($c) => materia_campo_preenchido($c[1]));
                    require __DIR__ . '/_materia_detalhe_grid.php';
                    ?>
                    <?php if (materia_campo_preenchido($row['observacao'] ?? '')): ?>
                        <?php $titulo = 'Observação'; $texto = (string) $row['observacao']; require __DIR__ . '/_materia_detalhe_texto.php'; ?>
                    <?php endif; ?>
                    <?php if (materia_campo_preenchido($row['arquivo'] ?? '')): ?>
                        <?php $urlRaw = (string) $row['arquivo']; $label = 'Arquivo'; $uid = 'leg' . $idx; $compactDoc = true; require __DIR__ . '/materia_documento.php'; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($numeracoes !== []): ?>
        <div class="tab-panel" data-panel="numeracao">
            <?php foreach ($numeracoes as $idx => $row): ?>
                <article class="md-card-item">
                    <header class="md-card-item-head">
                        <span class="md-card-item-num"><?= (int) $idx + 1 ?></span>
                        <strong>Numeração</strong>
                    </header>
                    <?php
                    $campos = array_filter([
                        ['Tipo', (string) ($row['tipo_materia'] ?? ''), 'text'],
                        ['Número', (string) ($row['numero'] ?? ''), 'text'],
                        ['Ano', (string) ($row['ano'] ?? ''), 'text'],
                        ['Data', (string) ($row['data_doc'] ?? ''), 'data'],
                    ], fn ($c) => materia_campo_preenchido($c[1]));
                    require __DIR__ . '/_materia_detalhe_grid.php';
                    ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($tramitacoes !== []): ?>
        <div class="tab-panel" data-panel="tramitacao">
            <?php foreach ($tramitacoes as $idx => $row): ?>
                <article class="md-card-item">
                    <header class="md-card-item-head">
                        <span class="md-card-item-num"><?= (int) $idx + 1 ?></span>
                        <strong>Tramitação</strong>
                    </header>
                    <?php
                    $campos = array_filter([
                        ['Data', (string) ($row['data_tramitacao'] ?? ''), 'data'],
                        ['Unidade local', (string) ($row['unidade_local'] ?? ''), 'text'],
                        ['Destino', (string) ($row['unidade_destino'] ?? ''), 'text'],
                        ['Status', (string) ($row['status'] ?? ''), 'text'],
                    ], fn ($c) => materia_campo_preenchido($c[1]));
                    require __DIR__ . '/_materia_detalhe_grid.php';
                    ?>
                    <?php if (materia_campo_preenchido($row['arquivo'] ?? '')): ?>
                        <?php $urlRaw = (string) $row['arquivo']; $label = 'Arquivo'; $uid = 'tram' . $idx; $compactDoc = true; require __DIR__ . '/materia_documento.php'; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($relatorias !== []): ?>
        <div class="tab-panel" data-panel="relatoria">
            <?php foreach ($relatorias as $idx => $row): ?>
                <article class="md-card-item">
                    <header class="md-card-item-head">
                        <span class="md-card-item-num"><?= (int) $idx + 1 ?></span>
                        <strong>Relatoria</strong>
                    </header>
                    <?php
                    $campos = array_filter([
                        ['Relator', (string) ($row['relator'] ?? ''), 'text'],
                        ['Data', (string) ($row['data_relatoria'] ?? ''), 'data'],
                    ], fn ($c) => materia_campo_preenchido($c[1]));
                    require __DIR__ . '/_materia_detalhe_grid.php';
                    ?>
                    <?php if (materia_campo_preenchido($row['observacao'] ?? '')): ?>
                        <?php $titulo = 'Observação'; $texto = (string) $row['observacao']; require __DIR__ . '/_materia_detalhe_texto.php'; ?>
                    <?php endif; ?>
                    <?php if (materia_campo_preenchido($row['arquivo'] ?? '')): ?>
                        <?php $urlRaw = (string) $row['arquivo']; $label = 'Arquivo'; $uid = 'rel' . $idx; $compactDoc = true; require __DIR__ . '/materia_documento.php'; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</article>
