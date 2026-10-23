<?php
declare(strict_types=1);

/**
 * Na tela de condução, a caixa da votação mostra o placar e o voto de cada
 * vereador (sem precisar abrir o painel de votação).
 *
 * Uso: C:\xampp\php\php.exe tests/ConducaoVotosListaTest.php
 */

require __DIR__ . '/_db.php';

$listaVotos = [
    ['id' => 1, 'nome' => 'Ana Lima', 'partido' => 'P1', 'voto' => 'SIM', 'voto_label' => 'Sim', 'voto_classe' => 'voto-sim', 'aguardando' => false, 'situacao' => 'PENDENTE'],
    ['id' => 2, 'nome' => 'Bruno <b>Reis</b>', 'partido' => '', 'voto' => null, 'voto_label' => '', 'voto_classe' => '', 'aguardando' => true, 'situacao' => 'PENDENTE'],
    ['id' => 3, 'nome' => 'Carla Dias', 'partido' => 'P2', 'voto' => 'IMPEDIDO', 'voto_label' => 'Impedido', 'voto_classe' => 'voto-situacao', 'aguardando' => false, 'situacao' => 'IMPEDIDO'],
];
$placarVotos = ['SIM' => 1, 'NAO' => 0, 'ABSTENCAO' => 0, 'AUSENTE' => 0];

ob_start();
require __DIR__ . '/../app/Views/partials/_conducao_votos_lista.php';
$html = (string) ob_get_clean();

confere(str_contains($html, 'id="conducao-votos-lista"'), 'lista tem id para a atualizacao ao vivo');
confere(str_contains($html, 'Ana Lima') && str_contains($html, 'voto-chip voto-sim'), 'mostra o voto Sim de Ana com a cor do voto');
confere(str_contains($html, 'Aguardando'), 'quem ainda nao votou aparece como Aguardando');
confere(str_contains($html, 'Impedido'), 'impedido aparece com a etiqueta');
confere(str_contains($html, 'Bruno &lt;b&gt;Reis&lt;/b&gt;'), 'nomes sao escapados');
confere((bool) preg_match('#data-placar="SIM"[^>]*>\s*1\s*<#', $html), 'placar mostra Sim = 1');
confere((bool) preg_match('#data-placar="NAO"[^>]*>\s*0\s*<#', $html), 'placar mostra Nao = 0');

finalizar_testes();
