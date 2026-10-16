<?php declare(strict_types=1); ?>
<?php
if (!function_exists('pauta_rows')) {
    function pauta_rows(array $rows, array $defaults = []): array
    {
        return !empty($rows) ? $rows : [$defaults];
    }
}
$sectionRows = $sectionRows ?? [
    'despachos' => [],
    'documentos' => [],
    'legislacoes' => [],
    'numeracoes' => [],
    'tramitacoes' => [],
    'relatorias' => [],
];
?>
<div class="tab-panel" data-panel="despacho">
    <div class="repeat-list" data-repeat-list="despachos">
        <?php foreach (pauta_rows($sectionRows['despachos'] ?? [], ['comissao' => '', 'situacao' => '', 'observacao' => '', 'arquivo' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div class="wide-3"><label class="panel-label">Comissão</label><input class="field" name="despachos[<?= $idx ?>][comissao]" value="<?= e((string) ($row['comissao'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Situação</label><input class="field" name="despachos[<?= $idx ?>][situacao]" value="<?= e((string) ($row['situacao'] ?? '')) ?>"></div>
                    <div class="wide-3"><label class="panel-label">Observação</label><textarea class="field" name="despachos[<?= $idx ?>][observacao]" rows="3"><?= e((string) ($row['observacao'] ?? '')) ?></textarea></div>
                    <div class="wide-3"><label class="panel-label">Arquivo</label><input class="field" type="file" name="despachos[<?= $idx ?>][arquivo]"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="despachos" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="tab-panel" data-panel="documentos">
    <div class="repeat-list" data-repeat-list="documentos">
        <?php foreach (pauta_rows($sectionRows['documentos'] ?? [], ['nome' => '', 'tipo' => '', 'data_doc' => '', 'autor' => '', 'arquivo' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div><label class="panel-label">Nome</label><input class="field" name="documentos[<?= $idx ?>][nome]" value="<?= e((string) ($row['nome'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Tipo</label><input class="field" name="documentos[<?= $idx ?>][tipo]" value="<?= e((string) ($row['tipo'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Data</label><input class="field" type="date" name="documentos[<?= $idx ?>][data_doc]" value="<?= e((string) ($row['data_doc'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Autor</label><input class="field" name="documentos[<?= $idx ?>][autor]" value="<?= e((string) ($row['autor'] ?? '')) ?>"></div>
                    <div class="wide-3"><label class="panel-label">Arquivo</label><input class="field" type="file" name="documentos[<?= $idx ?>][arquivo]"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="documentos" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="tab-panel" data-panel="legislacao">
    <div class="repeat-list" data-repeat-list="legislacoes">
        <?php foreach (pauta_rows($sectionRows['legislacoes'] ?? [], ['norma' => '', 'disposicao' => '', 'observacao' => '', 'arquivo' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div class="wide-2"><label class="panel-label">Norma</label><input class="field" name="legislacoes[<?= $idx ?>][norma]" value="<?= e((string) ($row['norma'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Disposição</label><input class="field" name="legislacoes[<?= $idx ?>][disposicao]" value="<?= e((string) ($row['disposicao'] ?? '')) ?>"></div>
                    <div class="wide-3"><label class="panel-label">Observação</label><textarea class="field" name="legislacoes[<?= $idx ?>][observacao]" rows="3"><?= e((string) ($row['observacao'] ?? '')) ?></textarea></div>
                    <div class="wide-3"><label class="panel-label">Arquivo</label><input class="field" type="file" name="legislacoes[<?= $idx ?>][arquivo]"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="legislacoes" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="tab-panel" data-panel="numeracao">
    <div class="repeat-list" data-repeat-list="numeracao">
        <?php foreach (pauta_rows($sectionRows['numeracoes'] ?? [], ['tipo_materia' => '', 'numero' => '', 'ano' => '', 'data_doc' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div><label class="panel-label">Tipo de matéria</label><input class="field" name="numeracoes[<?= $idx ?>][tipo_materia]" value="<?= e((string) ($row['tipo_materia'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Número</label><input class="field" name="numeracoes[<?= $idx ?>][numero]" value="<?= e((string) ($row['numero'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Ano</label><input class="field" name="numeracoes[<?= $idx ?>][ano]" value="<?= e((string) ($row['ano'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Data</label><input class="field" type="date" name="numeracoes[<?= $idx ?>][data_doc]" value="<?= e((string) ($row['data_doc'] ?? '')) ?>"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="numeracao" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="tab-panel" data-panel="tramitacao">
    <div class="repeat-list" data-repeat-list="tramitacoes">
        <?php foreach (pauta_rows($sectionRows['tramitacoes'] ?? [], ['data_tramitacao' => '', 'unidade_local' => '', 'unidade_destino' => '', 'status' => '', 'arquivo' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div><label class="panel-label">Data</label><input class="field" type="date" name="tramitacoes[<?= $idx ?>][data_tramitacao]" value="<?= e((string) ($row['data_tramitacao'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Unidade local</label><input class="field" name="tramitacoes[<?= $idx ?>][unidade_local]" value="<?= e((string) ($row['unidade_local'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Unidade destino</label><input class="field" name="tramitacoes[<?= $idx ?>][unidade_destino]" value="<?= e((string) ($row['unidade_destino'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Status</label><input class="field" name="tramitacoes[<?= $idx ?>][status]" value="<?= e((string) ($row['status'] ?? '')) ?>"></div>
                    <div class="wide-3"><label class="panel-label">Arquivo</label><input class="field" type="file" name="tramitacoes[<?= $idx ?>][arquivo]"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="tramitacoes" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="tab-panel" data-panel="relatoria">
    <div class="repeat-list" data-repeat-list="relatorias">
        <?php foreach (pauta_rows($sectionRows['relatorias'] ?? [], ['relator' => '', 'data_relatoria' => '', 'observacao' => '', 'arquivo' => '']) as $idx => $row): ?>
            <div class="repeat-item">
                <div class="modal-grid">
                    <div><label class="panel-label">Relator</label><input class="field" name="relatorias[<?= $idx ?>][relator]" value="<?= e((string) ($row['relator'] ?? '')) ?>"></div>
                    <div><label class="panel-label">Data</label><input class="field" type="date" name="relatorias[<?= $idx ?>][data_relatoria]" value="<?= e((string) ($row['data_relatoria'] ?? '')) ?>"></div>
                    <div class="wide-3"><label class="panel-label">Observação</label><textarea class="field" name="relatorias[<?= $idx ?>][observacao]" rows="3"><?= e((string) ($row['observacao'] ?? '')) ?></textarea></div>
                    <div class="wide-3"><label class="panel-label">Arquivo</label><input class="field" type="file" name="relatorias[<?= $idx ?>][arquivo]"></div>
                </div>
                <div class="repeat-actions">
                    <button class="btn btn-soft" type="button" data-remove-row aria-label="Remover linha">−</button>
                    <button class="btn btn-soft" type="button" data-add-row="relatorias" aria-label="Adicionar linha">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
