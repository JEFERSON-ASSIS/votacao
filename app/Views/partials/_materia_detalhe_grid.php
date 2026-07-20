<?php declare(strict_types=1); ?>
<?php
/** @var array<int, array{0: string, 1: string, 2?: string}> $campos */
$campos = $campos ?? [];
$linhas = array_values(array_filter($campos, static fn (array $c): bool => materia_campo_preenchido($c[1] ?? '')));
if ($linhas === []) {
    return;
}
?>
<div class="md-grid">
    <?php foreach ($linhas as $campo): ?>
        <?php
        $rotulo = $campo[0];
        $valor = (string) ($campo[1] ?? '');
        $tipo = $campo[2] ?? 'text';
        $valorExibir = $valor;
        if ($tipo === 'data') {
            $valorExibir = materia_formatar_data((string) $valor);
        }
        $chipClass = '';
        if ($tipo === 'flag') {
            $chipClass = strtolower((string) $valor) === 'sim' ? 'md-chip md-chip--sim' : 'md-chip md-chip--nao';
        }
        if ($tipo === 'regime' && stripos((string) $valor, 'urg') !== false) {
            $chipClass = 'md-chip md-chip--urgente';
        }
        ?>
        <div class="md-field<?= $tipo === 'wide' ? ' md-field--wide' : '' ?>">
            <span class="md-field-label"><?= e($rotulo) ?></span>
            <?php if ($tipo === 'link'): ?>
                <?php $href = materia_sapl_url_absoluta((string) $valor, $referenciaUrl ?? null); ?>
                <a class="md-field-link" href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e((string) $valor) ?></a>
            <?php elseif ($chipClass !== ''): ?>
                <span class="<?= e($chipClass) ?>"><?= e((string) $valorExibir) ?></span>
            <?php else: ?>
                <span class="md-field-value"><?= e((string) $valorExibir) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
