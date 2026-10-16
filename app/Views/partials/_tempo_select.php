<?php declare(strict_types=1); ?>
<?php
$campoNome = $campoNome ?? 'tempo_seg';
$campoId = $campoId ?? 'tempo-seg';
$campoLabel = $campoLabel ?? 'Tempo';
$selecionado = sessao_tempo_normalizar($selecionado ?? 0);
?>
<label class="panel-label" for="<?= e($campoId) ?>"><?= e($campoLabel) ?></label>
<select class="field" id="<?= e($campoId) ?>" name="<?= e($campoNome) ?>">
    <?php foreach (sessao_tempo_opcoes() as $seg => $rotulo): ?>
        <option value="<?= (int) $seg ?>" <?= $selecionado === (int) $seg ? 'selected' : '' ?>><?= e($rotulo) ?></option>
    <?php endforeach; ?>
</select>
