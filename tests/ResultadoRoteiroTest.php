<?php
declare(strict_types=1);

/**
 * O texto do roteiro "APROVADA / REPROVADA por unanimidade" é preenchido com o
 * resultado real da votação do passo anterior.
 *
 * Uso: C:\xampp\php\php.exe tests/ResultadoRoteiroTest.php
 */

require __DIR__ . '/_db.php';

use App\Models\RodadaEleitor;
use App\Models\SessaoVoto;
use App\Models\SessaoVotoDesempate;
use App\Services\ResultadoRoteiro;

$res = ['SIM' => 0, 'NAO' => 0, 'ABSTENCAO' => 0];

// 1) Frases do resultado
confere(
    ResultadoRoteiro::frase(['SIM' => 7, 'NAO' => 0, 'ABSTENCAO' => 0], 'APROVADO', false, 'A') === 'APROVADA por unanimidade',
    'todos Sim: APROVADA por unanimidade'
);
confere(
    ResultadoRoteiro::frase(['SIM' => 0, 'NAO' => 6, 'ABSTENCAO' => 0], 'REJEITADO', false, 'O') === 'REPROVADO por unanimidade',
    'todos Nao: REPROVADO por unanimidade (genero do texto)'
);
confere(
    ResultadoRoteiro::frase(['SIM' => 5, 'NAO' => 3, 'ABSTENCAO' => 1], 'APROVADO', false, 'A')
        === 'APROVADA por maioria, com 5 votos favoráveis, 3 contrários e 1 abstenção',
    'votos divergentes: APROVADA por maioria com o placar'
);
confere(
    ResultadoRoteiro::frase(['SIM' => 2, 'NAO' => 6, 'ABSTENCAO' => 0], 'REJEITADO', false, 'A')
        === 'REPROVADA por maioria, com 6 votos contrários e 2 favoráveis',
    'reprovada por maioria com o placar'
);
confere(
    ResultadoRoteiro::frase(['SIM' => 5, 'NAO' => 0, 'ABSTENCAO' => 2], 'APROVADO', false, 'A')
        === 'APROVADA, com 5 votos favoráveis e 2 abstenções',
    'sem votos contrarios, com abstencoes'
);
confere(
    ResultadoRoteiro::frase(['SIM' => 1, 'NAO' => 1, 'ABSTENCAO' => 0], 'APROVADO', true, 'A')
        === 'APROVADA com voto de desempate da Presidência, após empate de 1 a 1',
    'empate decidido pela Presidencia'
);

// 2) Troca no texto do roteiro, usando a votação do passo anterior
$pdo = preparar_banco_teste();
$v1 = criar_usuario_teste($pdo, 'Vereador R1');
$v2 = criar_usuario_teste($pdo, 'Vereador R2');
$pres = criar_usuario_teste($pdo, 'Presidente R', 'PRESIDENTE');
$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 7, 'ABERTA')")
    ->execute(['Sessao resultado ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();

$passo = static function (int $ordem, string $texto, int $geraVotacao) use ($pdo, $sessaoId): array {
    $pdo->prepare('INSERT INTO sessao_roteiro (sessao_id, ordem, texto_fala, gera_votacao) VALUES (?, ?, ?, ?)')
        ->execute([$sessaoId, $ordem, $texto, $geraVotacao]);

    return ['id' => (int) $pdo->lastInsertId(), 'sessao_id' => $sessaoId, 'ordem' => $ordem, 'texto_fala' => $texto, 'gera_votacao' => $geraVotacao];
};

try {
    $votacao = $passo(1, 'Coloco em discussão e faço a votação.', 1);
    $texto = 'Ata da sessão anterior APROVADA / REPROVADA por “unanimidade”';
    $anuncio = $passo(2, $texto, 0);

    confere(ResultadoRoteiro::aplicar($texto, $anuncio) === $texto, 'sem votacao encerrada o texto fica como esta');

    $pdo->prepare("INSERT INTO sessao_votacao_rodadas (sessao_id, roteiro_passo_id, titulo, status) VALUES (?, ?, 'Ata', 'ABERTA')")
        ->execute([$sessaoId, $votacao['id']]);
    $rodadaId = (int) $pdo->lastInsertId();
    RodadaEleitor::criarFotografia($rodadaId, [$v1, $v2]);
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $v1, 'SIM');
    SessaoVoto::voteRodada($sessaoId, $rodadaId, null, $v2, 'SIM');
    confere(ResultadoRoteiro::aplicar($texto, $anuncio) === $texto, 'com a votacao ainda aberta o texto fica como esta');

    $pdo->prepare("UPDATE sessao_votacao_rodadas SET status = 'ENCERRADA', resultado = 'APROVADO' WHERE id = ?")->execute([$rodadaId]);
    confere(
        ResultadoRoteiro::aplicar($texto, $anuncio) === 'Ata da sessão anterior APROVADA por unanimidade',
        'depois de encerrada: APROVADA por unanimidade (obtido: ' . ResultadoRoteiro::aplicar($texto, $anuncio) . ')'
    );

    $html = '<p>Ata da sessão anterior <strong>APROVADA</strong> / <strong>REPROVADA</strong> por &ldquo;unanimidade&rdquo;.</p>';
    confere(
        str_contains(ResultadoRoteiro::aplicar($html, $anuncio), 'APROVADA por unanimidade'),
        'funciona com texto formatado (negrito e aspas)'
    );

    $semResultado = 'Peço a leitura do projeto.';
    confere(ResultadoRoteiro::aplicar($semResultado, $anuncio) === $semResultado, 'texto sem o trecho nao muda');

    // Empate decidido pela Presidência
    $pdo->prepare("UPDATE sessao_votacao_rodadas SET status = 'ABERTA', resultado = NULL WHERE id = ?")->execute([$rodadaId]);
    $pdo->prepare('UPDATE sessao_votos SET voto = ? WHERE rodada_id = ? AND user_id = ?')->execute(['NAO', $rodadaId, $v2]);
    SessaoVotoDesempate::registrar($sessaoId, $rodadaId, $pres, 'SIM');
    $pdo->prepare("UPDATE sessao_votacao_rodadas SET status = 'ENCERRADA', resultado = 'APROVADO' WHERE id = ?")->execute([$rodadaId]);
    confere(
        str_contains(ResultadoRoteiro::aplicar($texto, $anuncio), 'APROVADA com voto de desempate da Presidência, após empate de 1 a 1'),
        'empate decidido pela Presidencia aparece no texto'
    );
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    $pdo->prepare('DELETE FROM sessao_votacao_rodadas WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessao_roteiro WHERE sessao_id = ?')->execute([$sessaoId]);
    $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sessaoId]);
    $pdo->exec("DELETE FROM users WHERE id IN ($v1, $v2, $pres)");
}

finalizar_testes();
