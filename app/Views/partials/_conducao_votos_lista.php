<?php declare(strict_types=1); ?>
<?php
/**
 * Placar e voto de cada vereador na caixa da votação (tela de condução).
 * O conducao-votacao.js redesenha este mesmo bloco ao vivo.
 *
 * @var list<array<string, mixed>> $listaVotos itens de VotacaoPainelEstado::jsonRodada
 * @var array<string, int> $placarVotos
 */
$listaVotos = $listaVotos ?? [];
$placarVotos = $placarVotos ?? ['SIM' => 0, 'NAO' => 0, 'ABSTENCAO' => 0];
?>
<div class="conducao-votos-resumo" id="conducao-votos-resumo">
    <div class="conducao-placar" role="group" aria-label="Placar da votação">
        <span class="conducao-placar-item conducao-placar-item--sim">Sim <strong data-placar="SIM"><?= (int) ($placarVotos['SIM'] ?? 0) ?></strong></span>
        <span class="conducao-placar-item conducao-placar-item--nao">Não <strong data-placar="NAO"><?= (int) ($placarVotos['NAO'] ?? 0) ?></strong></span>
        <span class="conducao-placar-item conducao-placar-item--abst">Abstenção <strong data-placar="ABSTENCAO"><?= (int) ($placarVotos['ABSTENCAO'] ?? 0) ?></strong></span>
    </div>
    <ul class="conducao-votos-lista" id="conducao-votos-lista">
        <?php foreach ($listaVotos as $p): ?>
            <li class="conducao-voto-item<?= !empty($p['aguardando']) ? ' conducao-voto-item--aguardando' : '' ?>">
                <span class="conducao-voto-nome"><?= e((string) ($p['nome'] ?? '')) ?></span>
                <?php if (!empty($p['aguardando'])): ?>
                    <span class="conducao-voto-aguardando">Aguardando</span>
                <?php else: ?>
                    <span class="voto-chip <?= e((string) ($p['voto_classe'] ?? '')) ?>"><?= e((string) ($p['voto_label'] ?? '')) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
