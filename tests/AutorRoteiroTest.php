<?php
declare(strict_types=1);

/**
 * O autor cadastrado na matéria da pauta aparece no roteiro no lugar de
 * "Vereador ????" (ou "Vereador ___" / {autor}). O passo é ligado à matéria
 * na montagem do roteiro; os passos seguintes herdam essa matéria.
 *
 * Uso: C:\xampp\php\php.exe tests/AutorRoteiroTest.php
 */

require __DIR__ . '/_db.php';

use App\Services\TextoRoteiro;

$pdo = preparar_banco_teste();
$admin = criar_usuario_teste($pdo, 'Admin Roteiro', 'ADMIN');
$userAdmin = ['id' => $admin, 'nome' => 'Admin Roteiro', 'email' => 'a@test', 'role' => 'ADMIN'];

$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 3, 'CRIADO')")
    ->execute(['Sessao autor ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 4, 'CRIADO')")
    ->execute(['Outra sessao ' . bin2hex(random_bytes(3))]);
$outraSessao = (int) $pdo->lastInsertId();

$item = static function (int $sessao, string $titulo, ?string $autor) use ($pdo): int {
    $pdo->prepare('INSERT INTO pauta_itens (sessao_id, titulo, autor) VALUES (?, ?, ?)')->execute([$sessao, $titulo, $autor]);

    return (int) $pdo->lastInsertId();
};
$passo = static function (int $ordem, string $texto, ?int $itemId) use ($pdo, $sessaoId): array {
    $pdo->prepare('INSERT INTO sessao_roteiro (sessao_id, ordem, texto_fala, pauta_item_id) VALUES (?, ?, ?, ?)')
        ->execute([$sessaoId, $ordem, $texto, $itemId]);

    return ['id' => (int) $pdo->lastInsertId(), 'sessao_id' => $sessaoId, 'ordem' => $ordem, 'texto_fala' => $texto, 'pauta_item_id' => $itemId];
};

try {
    $indicacao = $item($sessaoId, 'Indicação nº 009/2026', 'João da Silva');
    $projeto = $item($sessaoId, 'Projeto de Lei Complementar 001/2026', '');
    $deOutraSessao = $item($outraSessao, 'Matéria de outra sessão', 'Fulano');

    $antes = $passo(1, 'Peço a defesa do autor - Vereador ????.', null);
    $leitura = $passo(2, 'Peço a leitura da INDICAÇÃO nº 009/2026.', $indicacao);
    $defesa = $passo(3, '•	(Peço a defesa do autor - Vereador ????).', null);
    $chaves = $passo(4, 'Com a palavra o autor, {autor}.', null);
    $sublinhado = $passo(5, 'Peço a defesa do autor - Vereadora ___.', null);
    $outroItem = $passo(6, 'Peço a leitura do PLC.', $projeto);
    $semAutor = $passo(7, 'Peço a defesa do autor - Vereador ????.', null);

    confere(
        TextoRoteiro::exibir($defesa['texto_fala'], $defesa) === '•	(Peço a defesa do autor - Vereador João da Silva).',
        'passo seguinte herda a materia e mostra o autor (obtido: ' . TextoRoteiro::exibir($defesa['texto_fala'], $defesa) . ')'
    );
    confere(TextoRoteiro::exibir($chaves['texto_fala'], $chaves) === 'Com a palavra o autor, João da Silva.', '{autor} vira o nome do autor');
    confere(
        TextoRoteiro::exibir($sublinhado['texto_fala'], $sublinhado) === 'Peço a defesa do autor - Vereadora João da Silva.',
        '"Vereadora ___" tambem e preenchido'
    );
    confere(TextoRoteiro::exibir($antes['texto_fala'], $antes) === $antes['texto_fala'], 'passo antes de qualquer materia fica como esta');
    confere(TextoRoteiro::exibir($semAutor['texto_fala'], $semAutor) === $semAutor['texto_fala'], 'materia sem autor cadastrado: texto fica como esta');

    // Ligar o passo à matéria pela montagem do roteiro
    rodar_cenario([
        'user' => $userAdmin,
        'metodo' => 'POST',
        'get_r' => "roteiro/salvar/$sessaoId/" . $antes['id'],
        'post' => ['titulo' => 'Defesa', 'texto_fala' => $antes['texto_fala'], 'tipo_passo' => 'FALA', 'pauta_item_id' => (string) $indicacao],
    ]);
    $ligado = $pdo->query('SELECT pauta_item_id FROM sessao_roteiro WHERE id = ' . $antes['id'])->fetchColumn();
    confere((int) $ligado === $indicacao, 'montagem do roteiro liga o passo a materia da pauta');

    rodar_cenario([
        'user' => $userAdmin,
        'metodo' => 'POST',
        'get_r' => "roteiro/salvar/$sessaoId/" . $antes['id'],
        'post' => ['titulo' => 'Defesa', 'texto_fala' => $antes['texto_fala'], 'tipo_passo' => 'FALA', 'pauta_item_id' => (string) $deOutraSessao],
    ]);
    $ligado = $pdo->query('SELECT pauta_item_id FROM sessao_roteiro WHERE id = ' . $antes['id'])->fetchColumn();
    confere((int) $ligado === $indicacao, 'recusa ligar a materia de outra sessao');

    $res = rodar_cenario(['user' => $userAdmin, 'metodo' => 'GET', 'get_r' => "roteiro/montar/$sessaoId", 'post' => []]);
    confere(str_contains($res['saida'], 'name="pauta_item_id"'), 'tela de montar tem o campo Materia da pauta');
    confere(str_contains($res['saida'], 'Indicação nº 009/2026') && str_contains($res['saida'], 'João da Silva'), 'campo lista as materias com o autor');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    foreach ([$sessaoId, $outraSessao] as $sid) {
        $pdo->prepare('DELETE FROM sessao_roteiro WHERE sessao_id = ?')->execute([$sid]);
        $pdo->prepare('DELETE FROM pauta_itens WHERE sessao_id = ?')->execute([$sid]);
        try {
            $pdo->prepare('DELETE FROM sessao_eventos WHERE sessao_id = ?')->execute([$sid]);
        } catch (\Throwable $e) {
        }
        $pdo->prepare('DELETE FROM sessoes WHERE id = ?')->execute([$sid]);
    }
    $pdo->exec("DELETE FROM users WHERE id = $admin");
}

finalizar_testes();
