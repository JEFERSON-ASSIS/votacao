<?php declare(strict_types=1); ?>
<?php
use App\Helpers\MateriaSaplFields;

$item = isset($item) && is_array($item) ? MateriaSaplFields::mergeItemAndExtras($item) : null;
$ordemDefault = $ordemDefault ?? 1;
$sectionRows = $sectionRows ?? [
    'despachos' => [],
    'documentos' => [],
    'legislacoes' => [],
    'numeracoes' => [],
    'tramitacoes' => [],
    'relatorias' => [],
];
$formUid = !empty($item['id']) ? 'item-' . (int) $item['id'] : 'nova';

if (!function_exists('pauta_rows')) {
    function pauta_rows(array $rows, array $defaults = []): array
    {
        return !empty($rows) ? $rows : [$defaults];
    }
}

$numeroVal = MateriaSaplFields::val($item, 'numero');
$anoVal = MateriaSaplFields::val($item, 'ano');
if ($numeroVal === '' && !empty($sectionRows['numeracoes'][0]['numero'])) {
    $numeroVal = (string) $sectionRows['numeracoes'][0]['numero'];
}
if ($anoVal === '' && !empty($sectionRows['numeracoes'][0]['ano'])) {
    $anoVal = (string) $sectionRows['numeracoes'][0]['ano'];
}
$tipoSaplVal = (int) MateriaSaplFields::val($item, 'tipo_sapl');
$regimeIdVal = (int) MateriaSaplFields::val($item, 'regime_tramitacao_id');
?>
<div class="panel-meta" style="margin-bottom:10px;">Preencha os dados básicos da matéria. Campos de tramitação, documentos e integração SAPL ficam no modo avançado.</div>
<div class="modal-grid">
    <div class="wide-2">
        <label class="panel-label">Título</label>
        <input class="field" name="titulo" value="<?= e(MateriaSaplFields::val($item, 'titulo')) ?>" required>
    </div>
    <div>
        <label class="panel-label">Tipo de matéria</label>
        <select class="field" name="tipo_materia" id="field-tipo-materia-<?= e($formUid) ?>">
            <?php foreach (MateriaSaplFields::tipoMateriaOptions() as $opt): ?>
                <option value="<?= e($opt) ?>" <?= (MateriaSaplFields::val($item, 'tipo_materia', 'Projeto de Lei Ordinaria') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label class="panel-label">Número</label><input class="field" name="numero" type="number" value="<?= e($numeroVal) ?>"></div>
    <div><label class="panel-label">Ano</label><input class="field" name="ano" type="number" value="<?= e($anoVal) ?>"></div>
    <div class="wide-2"><label class="panel-label">Autor</label><input class="field" name="autor" value="<?= e(MateriaSaplFields::val($item, 'autor')) ?>"></div>
    <div><label class="panel-label">Ordem na pauta</label><input class="field" name="ordem" type="number" value="<?= e(MateriaSaplFields::val($item, 'ordem', (string) $ordemDefault)) ?>"></div>
    <div class="wide-3"><label class="panel-label">Ementa</label><textarea class="field" name="ementa" rows="4"><?= e(MateriaSaplFields::val($item, 'ementa')) ?></textarea></div>
</div>

<details class="pauta-advanced-toggle">
    <summary class="pauta-advanced-summary">
        <span class="pauta-advanced-icon" aria-hidden="true">▸</span>
        <span class="pauta-advanced-copy">
            <span class="pauta-advanced-title">Modo avançado</span>
            <span class="pauta-advanced-hint">Clique para mostrar SAPL, tramitação, documentos, prazos e outros campos</span>
        </span>
    </summary>
    <div style="margin-top:14px;">
    <div class="tabbar" data-tabbar>
        <button type="button" class="tab-btn is-active" data-target="sapl">Matéria legislativa</button>
        <button type="button" class="tab-btn" data-target="origem">Origem externa</button>
        <button type="button" class="tab-btn" data-target="prazo">Prazo</button>
        <button type="button" class="tab-btn" data-target="autoria">Autores</button>
        <button type="button" class="tab-btn" data-target="despacho">Despacho inicial</button>
        <button type="button" class="tab-btn" data-target="documentos">Documentos</button>
        <button type="button" class="tab-btn" data-target="legislacao">Legislação citada</button>
        <button type="button" class="tab-btn" data-target="numeracao">Numeração</button>
        <button type="button" class="tab-btn" data-target="tramitacao">Tramitação</button>
        <button type="button" class="tab-btn" data-target="relatoria">Relatoria</button>
        <button type="button" class="tab-btn" data-target="arquivos">Arquivos</button>
    </div>

    <div class="tab-panels">
    <div class="tab-panel is-active" data-panel="sapl">
        <div class="panel-meta" style="margin-bottom:10px;">Campos no padrão SAPL (matéria legislativa).</div>
        <div class="modal-grid">
            <div><label class="panel-label">ID SAPL</label><input class="field" name="sapl_id" type="number" value="<?= e(MateriaSaplFields::val($item, 'sapl_id')) ?>" placeholder="id"></div>
            <div>
                <label class="panel-label">Tipo (SAPL)</label>
                <select class="field" name="tipo_sapl" id="field-tipo-sapl-<?= e($formUid) ?>">
                    <option value="">—</option>
                    <?php foreach (MateriaSaplFields::TIPO_SAPL as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= $tipoSaplVal === (int) $id ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) $id ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="panel-label">Número protocolo</label><input class="field" name="numero_protocolo" value="<?= e(MateriaSaplFields::val($item, 'numero_protocolo')) ?>"></div>
            <div><label class="panel-label">Data de apresentação</label><input class="field" type="date" name="data_apresentacao" value="<?= e(MateriaSaplFields::val($item, 'data_apresentacao')) ?>"></div>
            <div>
                <label class="panel-label">Tipo de apresentação</label>
                <select class="field" name="tipo_apresentacao">
                    <option value="">—</option>
                    <?php foreach (MateriaSaplFields::APRESENTACAO_SAPL as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= MateriaSaplFields::val($item, 'tipo_apresentacao') === $code ? 'selected' : '' ?>><?= e($code) ?> — <?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="panel-label">Regime de tramitação</label>
                <select class="field" name="regime_tramitacao_id">
                    <option value="">—</option>
                    <?php foreach (MateriaSaplFields::REGIME_SAPL as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= $regimeIdVal === (int) $id ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) $id ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="panel-label">Data de publicação</label><input class="field" type="date" name="data_publicacao" value="<?= e(MateriaSaplFields::val($item, 'data_publicacao')) ?>"></div>
            <div><label class="panel-label">Apelido</label><input class="field" name="apelido" value="<?= e(MateriaSaplFields::val($item, 'apelido')) ?>"></div>
            <div class="wide-3"><label class="panel-label">Indexação</label><textarea class="field" name="indexacao" rows="2"><?= e(MateriaSaplFields::val($item, 'indexacao')) ?></textarea></div>
            <div class="wide-3"><label class="panel-label">Observação</label><textarea class="field" name="observacao" rows="2"><?= e(MateriaSaplFields::val($item, 'observacao')) ?></textarea></div>
            <div class="wide-3"><label class="panel-label">Objeto</label><textarea class="field" name="objeto" rows="2"><?= e(MateriaSaplFields::val($item, 'objeto')) ?></textarea></div>
            <div><label class="panel-label">Resultado</label><input class="field" name="resultado" value="<?= e(MateriaSaplFields::val($item, 'resultado')) ?>"></div>
            <div class="wide-3"><label class="panel-label">Texto original (URL)</label><input class="field" name="texto_original" value="<?= e(MateriaSaplFields::val($item, 'texto_original')) ?>"></div>
            <div class="wide-3"><label class="panel-label">Link SAPL</label><input class="field" name="link_detail_backend" value="<?= e(MateriaSaplFields::val($item, 'link_detail_backend')) ?>" placeholder="/matéria/465"></div>
            <div class="sapl-flags">
                <label><input type="checkbox" name="em_tramitacao" value="1" <?= MateriaSaplFields::checked($item, 'em_tramitacao') ? 'checked' : '' ?>> Em tramitação</label>
                <label><input type="checkbox" name="polemica" value="1" <?= MateriaSaplFields::checked($item, 'materia_polemica') ? 'checked' : '' ?>> Matéria polêmica</label>
                <label><input type="checkbox" name="complementar" value="1" <?= MateriaSaplFields::checked($item, 'eh_complementar') ? 'checked' : '' ?>> Complementar</label>
            </div>
        </div>
    </div>

    <div class="tab-panel" data-panel="origem">
        <div class="modal-grid">
            <div><label class="panel-label">Tipo de origem externa</label><input class="field" name="tipo_origem_externa" value="<?= e(MateriaSaplFields::val($item, 'tipo_origem_externa')) ?>"></div>
            <div><label class="panel-label">Número da origem externa</label><input class="field" name="numero_origem_externa" value="<?= e(MateriaSaplFields::val($item, 'numero_origem_externa')) ?>"></div>
            <div><label class="panel-label">Ano da origem externa</label><input class="field" name="ano_origem_externa" type="number" value="<?= e(MateriaSaplFields::val($item, 'ano_origem_externa')) ?>"></div>
            <div><label class="panel-label">Data da origem externa</label><input class="field" type="date" name="data_origem_externa" value="<?= e(MateriaSaplFields::val($item, 'data_origem_externa')) ?>"></div>
            <div class="wide-2"><label class="panel-label">Local da origem externa</label><input class="field" name="local_origem_externa" value="<?= e(MateriaSaplFields::val($item, 'local_origem_externa')) ?>"></div>
        </div>
    </div>

    <div class="tab-panel" data-panel="prazo">
        <div class="modal-grid">
            <div><label class="panel-label">Dias de prazo</label><input class="field" name="dias_prazo" type="number" value="<?= e(MateriaSaplFields::val($item, 'dias_prazo')) ?>"></div>
            <div><label class="panel-label">Data fim do prazo</label><input class="field" type="date" name="data_fim_prazo" value="<?= e(MateriaSaplFields::val($item, 'data_fim_prazo')) ?>"></div>
        </div>
    </div>

    <div class="tab-panel" data-panel="autoria">
        <div class="panel-meta" style="margin-bottom:10px;">O nome do autor principal fica nos dados básicos. Aqui você informa IDs SAPL e notas extras.</div>
        <div class="modal-grid">
            <div class="wide-2"><label class="panel-label">Autores (IDs SAPL)</label><input class="field" name="autores_ids" value="<?= e(MateriaSaplFields::val($item, 'autores_ids')) ?>" placeholder="39, 12"></div>
            <div class="wide-3"><label class="panel-label">Autoria / notas</label><input class="field" name="autoria" value="<?= e(MateriaSaplFields::val($item, 'autoria')) ?>"></div>
        </div>
    </div>

    <?php require __DIR__ . '/_repeat_panels.php'; ?>

    <div class="tab-panel" data-panel="arquivos">
        <div class="modal-grid">
            <div class="wide-3"><label class="panel-label">Upload texto original</label><input class="field" type="file" name="texto_original_arquivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp"></div>
            <div class="wide-3"><label class="panel-label">Documento acessório</label><input class="field" type="file" name="documento_acessorio_arquivo"></div>
        </div>
    </div>
    </div>
    </div>
</details>
