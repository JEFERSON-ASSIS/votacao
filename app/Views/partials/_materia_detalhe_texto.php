<?php declare(strict_types=1); ?>
<?php
/** @var string $titulo */
/** @var string $texto */
/** @var string|null $destaque */
if (!materia_campo_preenchido($texto ?? '')) {
    return;
}
$destaque = $destaque ?? '';
?>
<div class="md-texto<?= $destaque === 'ementa' ? ' md-texto--ementa' : '' ?>">
    <h4 class="md-texto-titulo"><?= e($titulo) ?></h4>
    <div class="md-texto-corpo"><?= nl2br(e((string) $texto)) ?></div>
</div>
