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

finalizar_testes();
