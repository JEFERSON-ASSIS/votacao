<?php declare(strict_types=1); ?>
<?php
/** @var array<string, mixed> $sessao */
/** @var array<string, mixed>|null $rodadaAberta */
/** @var bool $podeVotar */
/** @var string|null $meuVoto */
/** @var array<string, mixed>|null $itemVotacao */
/** @var int $itemRodadaId */

$rodadaAberta = $rodadaAberta ?? null;
$podeVotar = (bool) ($podeVotar ?? false);
$itemRodadaId = (int) ($itemRodadaId ?? 0);
$meuVoto = $meuVoto ?? null;

if (!$podeVotar || !$rodadaAberta || $meuVoto !== null) {
    return;
}
?>
<article class="panel-card parl-voto-card parl-voto-card--destaque" id="parl-voto-box" style="grid-column:1/-1;">
    <div class="parl-voto-card-head">
        <span class="parl-live-dot" aria-hidden="true"></span>
        <div>
            <div class="panel-label">Votação aberta</div>
            <div class="panel-value"><?= e((string) ($rodadaAberta['titulo'] ?? 'Votação nominal')) ?></div>
        </div>
    </div>
    <?php if ($itemVotacao): ?>
        <p class="panel-meta">
            Matéria: <strong><?= e((string) $itemVotacao['titulo']) ?></strong>
            <a class="btn btn-soft btn--sm" style="margin-left:8px;" href="<?= e(route_url('parlamentar/materia/' . (int) $sessao['id'] . '/' . (int) $itemVotacao['id'])) ?>">Ver matéria</a>
        </p>
    <?php endif; ?>
    <p class="panel-meta">O presidente abriu a votação. Registre seu voto abaixo.</p>
    <form method="post" action="<?= e(route_url('parlamentar/votar')) ?>" class="parl-voto-form" id="parl-voto-form">
        <input type="hidden" name="sessao_id" value="<?= (int) $sessao['id'] ?>">
        <input type="hidden" name="item_id" value="<?= $itemRodadaId > 0 ? $itemRodadaId : 0 ?>">
        <input type="hidden" name="rodada_id" value="<?= (int) $rodadaAberta['id'] ?>">
        <?= csrf_field() ?>
        <div class="parl-voto-botoes">
            <button type="submit" name="voto" value="SIM" class="btn parl-voto-btn parl-voto-btn--sim">Sim</button>
            <button type="submit" name="voto" value="NAO" class="btn parl-voto-btn parl-voto-btn--nao">Não</button>
            <button type="submit" name="voto" value="ABSTENCAO" class="btn parl-voto-btn parl-voto-btn--abst">Abstenção</button>
        </div>
    </form>
</article>
