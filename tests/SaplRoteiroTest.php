<?php
declare(strict_types=1);

/**
 * Roteiro das matérias importadas do SAPL montado pelo turno de cada uma,
 * com os textos usados nas atas da Câmara:
 *  - Deliberação: só apresenta e encaminha às Comissões Permanentes (sem votação);
 *  - 1ª Votação / 2ª e 3ª Votações / Único: leitura, defesa só se o autor for
 *    vereador, discussão (projetos), votação e resultado com o turno.
 *
 * Uso: C:\xampp\php\php.exe tests/SaplRoteiroTest.php
 */

require __DIR__ . '/_db.php';

use App\Services\SaplRoteiro;

// 1) Turno da matéria na data da sessão (última tramitação até a data, ignorando "F")
$tramitacoes = [
    ['data_tramitacao' => '2026-10-09', 'turno' => 'F'],
    ['data_tramitacao' => '2026-09-28', 'turno' => 'F'],
    ['data_tramitacao' => '2026-09-28', 'turno' => 'C'],
    ['data_tramitacao' => '2026-09-25', 'turno' => 'C'],
    ['data_tramitacao' => '2026-09-21', 'turno' => 'B'],
];
confere(SaplRoteiro::turnoNaData($tramitacoes, '2026-09-28') === 'C', 'turno na data da sessao: 2a e 3a votacoes');
confere(SaplRoteiro::turnoNaData($tramitacoes, '2026-09-21') === 'B', 'turno em sessao anterior: 1a votacao');
confere(SaplRoteiro::turnoNaData([], '2026-09-21') === '', 'sem tramitacao: turno vazio');

$texto = static fn (array $passos): string => implode("\n", array_map(static fn (array $p): string => $p['texto'], $passos));
$votacoes = static fn (array $passos): int => count(array_filter($passos, static fn (array $p): bool => $p['gera_votacao']));

// 2) Deliberação: apresenta e encaminha, sem votação
$deliberacao = SaplRoteiro::passos([
    'tipo_materia' => 'Projeto de Lei Ordinária', 'numero' => 54, 'ano' => 2026,
    'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'D',
]);
confere(count($deliberacao) === 1, 'deliberacao: um passo so');
confere($votacoes($deliberacao) === 0, 'deliberacao: sem votacao');
confere(
    $deliberacao[0]['texto'] === 'Apresento o Projeto de Lei Ordinária nº 54/2026, de autoria de Yago Pezarico Giacomelli (Prefeito Municipal), e encaminho às Comissões Permanentes para análise e emissão de parecer.',
    'deliberacao: texto das atas (obtido: ' . $deliberacao[0]['texto'] . ')'
);

// 3) 1ª votação de projeto do Prefeito: sem defesa, com discussão do mérito
$primeira = SaplRoteiro::passos([
    'tipo_materia' => 'Projeto de Lei Ordinária', 'numero' => 45, 'ano' => 2026,
    'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'B',
]);
$t = $texto($primeira);
confere(str_contains($t, 'Peço a leitura resumida do Projeto de Lei Ordinária nº 45/2026.'), '1a votacao: leitura resumida');
confere(!str_contains($t, 'defesa'), 'projeto do Prefeito: sem defesa do autor');
confere(str_contains($t, 'Coloco em discussão o mérito do Projeto de Lei Ordinária nº 45/2026.'), 'projeto: discussao do merito');
confere(str_contains($t, 'Coloco em votação, em primeira votação, o Projeto de Lei Ordinária nº 45/2026.'), '1a votacao: texto da votacao');
confere(str_contains($t, 'Projeto de Lei Ordinária nº 45/2026 APROVADO / REPROVADO por unanimidade em sua primeira votação.'), '1a votacao: resultado com o turno');
confere($votacoes($primeira) === 1, '1a votacao: uma votacao');

// 4) 2ª e 3ª votações
$segunda = $texto(SaplRoteiro::passos([
    'tipo_materia' => 'Projeto de Lei Ordinária', 'numero' => 43, 'ano' => 2026,
    'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'C',
]));
confere(str_contains($segunda, 'em segunda e última votação, o Projeto'), '2a e 3a: texto da votacao');
confere(str_contains($segunda, 'em sua segunda e última votação.'), '2a e 3a: resultado com o turno');

// 5) Indicação de vereador em turno único: defesa, sem discussão, feminino
$indicacao = SaplRoteiro::passos([
    'tipo_materia' => 'Indicação', 'numero' => 51, 'ano' => 2026,
    'autor' => 'Adailton Neguinho', 'sapl_turno' => 'U',
]);
$t = $texto($indicacao);
confere(str_contains($t, 'Peço a leitura da Indicação nº 51/2026.'), 'indicacao: leitura (sem "resumida")');
confere(str_contains($t, 'Com a palavra o autor, Vereador Adailton Neguinho, para a defesa.'), 'indicacao: defesa do autor vereador');
confere(!str_contains($t, 'discussão'), 'indicacao: sem discussao do merito');
confere(str_contains($t, 'Indicação nº 51/2026 APROVADA / REPROVADA por unanimidade em uma única votação.'), 'indicacao: resultado no feminino, unica votacao');

// 6) Sem turno conhecido: votação simples, sem frase de turno
$semTurno = $texto(SaplRoteiro::passos([
    'tipo_materia' => 'Requerimento', 'numero' => 14, 'ano' => 2026, 'autor' => 'Juca Moro', 'sapl_turno' => '',
]));
confere(str_contains($semTurno, 'Coloco em votação o Requerimento nº 14/2026.'), 'sem turno: votacao simples');

// 7) Urgência (turno A): apresentação, votação do pedido de urgência, dispensa, leitura, discussão, votação única
$urg = SaplRoteiro::passos(['tipo_materia' => 'Projeto de Lei Ordinária', 'numero' => 46, 'ano' => 2026, 'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'A', 'sapl_autores_parlamentares' => '']);
$t = $texto($urg);
confere(str_contains($t, 'Apresento o Projeto de Lei Ordinária nº 46/2026, de autoria de Yago Pezarico Giacomelli (Prefeito Municipal), e encaminho às Comissões Permanentes'), 'urgencia: apresenta e encaminha');
confere(str_contains($t, 'Coloco em votação o pedido de urgência (redução do interstício regimental) do Projeto de Lei Ordinária nº 46/2026.'), 'urgencia: vota o pedido de urgencia');
confere(str_contains($t, 'Pedido de urgência APROVADO / REPROVADO por unanimidade.'), 'urgencia: resultado do pedido (preenchido na sessao)');
confere(str_contains($t, 'Dispenso a reunião das Comissões.'), 'urgencia: dispensa a reuniao das comissoes');
confere(str_contains($t, 'em única votação, o Projeto de Lei Ordinária nº 46/2026.') && str_contains($t, 'em uma única votação.'), 'urgencia: votacao unica');
confere($votacoes($urg) === 2, 'urgencia: duas votacoes (pedido e materia)');

// 8) Turnos juntos (G = 1ª e 2ª, E = 1ª, 2ª e 3ª): ata registra "em uma única votação"
foreach (['G', 'E'] as $tj) {
    $t = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Lei Ordinária', 'numero' => 38, 'ano' => 2026, 'autor' => 'Prefeito (Prefeito Municipal)', 'sapl_turno' => $tj, 'sapl_autores_parlamentares' => '']));
    confere(str_contains($t, 'em uma única votação.'), "turno $tj: resultado em uma unica votacao");
}
$t = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Emenda à Lei Orgânica', 'numero' => 2, 'ano' => 2026, 'autor' => 'Prefeito (Prefeito Municipal)', 'sapl_turno' => 'P', 'sapl_tipo_votacao' => 2, 'sapl_autores_parlamentares' => '']));
confere(str_contains($t, 'Coloco em votação nominal, em primeira votação, o Projeto de Emenda à Lei Orgânica nº 2/2026.'), 'turno P = primeira votacao; votacao nominal quando o SAPL indica');

// 9) Autor que não é vereador (Mesa Diretora, Comissão): sem defesa
$mesa = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Resolução', 'numero' => 3, 'ano' => 2026, 'autor' => 'Mesa Diretora', 'sapl_turno' => 'U', 'sapl_autores_parlamentares' => '']));
confere(!str_contains($mesa, 'defesa'), 'Mesa Diretora: sem defesa do autor');
confere(str_contains($mesa, 'Coloco em discussão o mérito do Projeto de Resolução nº 3/2026.'), 'Mesa Diretora: discussao do merito');
$comissao = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Decreto Legislativo', 'numero' => 5, 'ano' => 2025, 'autor' => 'CCJ - Comissão de Constituição, Justiça e Redação', 'sapl_turno' => 'U', 'sapl_tipo_votacao' => 2, 'sapl_autores_parlamentares' => '']));
confere(!str_contains($comissao, 'defesa') && str_contains($comissao, 'votação nominal, em única votação'), 'Comissao: sem defesa, votacao nominal');

// 10) Projeto de vereador: leitura (sem "resumida"), defesa, sem discussão
$pll = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Lei Legislativo', 'numero' => 12, 'ano' => 2026, 'autor' => 'Juca Moro', 'sapl_turno' => 'U', 'sapl_autores_parlamentares' => 'Juca Moro']));
confere(str_contains($pll, 'Peço a leitura do Projeto de Lei Legislativo nº 12/2026.'), 'projeto de vereador: leitura sem resumida');
confere(str_contains($pll, 'Com a palavra o autor, Vereador Juca Moro, para a defesa.'), 'projeto de vereador: defesa');
confere(!str_contains($pll, 'discussão'), 'projeto de vereador: a defesa substitui a discussao (como nas atas)');

// 11) Veto: leitura, discussão das razões do veto, votação
$veto = $texto(SaplRoteiro::passos(['tipo_materia' => 'Veto', 'numero' => 1, 'ano' => 2026, 'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'U', 'sapl_autores_parlamentares' => '']));
confere(str_contains($veto, 'Peço a leitura do Veto nº 1/2026.'), 'veto: leitura');
confere(str_contains($veto, 'Coloco em discussão as razões do veto apresentadas pelo Poder Executivo.'), 'veto: discussao das razoes do veto');
confere(str_contains($veto, 'Veto nº 1/2026 APROVADO / REPROVADO por unanimidade em uma única votação.'), 'veto: resultado');

// 12) Emenda: votada uma vez só, mesmo com turno C; feminino
$emenda = $texto(SaplRoteiro::passos(['tipo_materia' => 'Emenda Supressiva', 'numero' => 1, 'ano' => 2026, 'autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_turno' => 'C', 'sapl_autores_parlamentares' => '']));
confere(str_contains($emenda, 'Coloco em discussão a Emenda Supressiva nº 1/2026.'), 'emenda: discussao');
confere(str_contains($emenda, 'Emenda Supressiva nº 1/2026 APROVADA / REPROVADA por unanimidade em uma única votação.'), 'emenda: uma unica votacao, feminino');

// 13) Muitos autores (ex.: todos os vereadores): sem listar todos os nomes
$pdl = $texto(SaplRoteiro::passos(['tipo_materia' => 'Projeto de Decreto Legislativo', 'numero' => 1, 'ano' => 2026, 'autor' => 'A, B, C, D', 'sapl_turno' => 'U', 'sapl_autores_parlamentares' => 'Ana, Bia, Caio, Davi']));
confere(str_contains($pdl, 'Com a palavra os autores, para a defesa.'), 'mais de 3 autores: texto curto');
$dois = $texto(SaplRoteiro::passos(['tipo_materia' => 'Indicação', 'numero' => 2, 'ano' => 2026, 'autor' => 'Ana, Bia', 'sapl_turno' => 'U', 'sapl_autores_parlamentares' => 'Ana, Bia']));
confere(str_contains($dois, 'Com a palavra os autores, Vereadores Ana e Bia, para a defesa.'), 'dois autores: nomes ligados por e');

finalizar_testes();
