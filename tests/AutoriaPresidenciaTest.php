<?php
declare(strict_types=1);

/**
 * Quando quem preside é autor da matéria em andamento, a condução sugere
 * passar a presidência; quando a matéria termina, sugere devolver.
 * Os vereadores são ligados ao nome parlamentar do SAPL pelo nome completo.
 *
 * Uso: C:\xampp\php\php.exe tests/AutoriaPresidenciaTest.php
 */

require __DIR__ . '/_db.php';

use App\Models\SessaoRoteiro;
use App\Services\AutoriaPresidencia;
use App\Services\SaplParlamentares;

// 1) Vínculo pelo nome completo (sem acento, maiúsculas, "da/de" e ordem das palavras)
$usuarios = [
    ['id' => 3, 'nome' => 'JADER PAULO IZIDORIO'],
    ['id' => 2, 'nome' => 'ROBSON SILVA DE FREITAS'],
    ['id' => 99, 'nome' => 'FULANO SEM SAPL'],
];
$parlamentares = [
    ['id' => 12, 'nome_parlamentar' => 'Jadão do Dogão', 'nome_completo' => 'Jader Paulo Izidório'],
    ['id' => 25, 'nome_parlamentar' => 'Robson Freitas', 'nome_completo' => 'Robson da Silva Freitas'],
];
$vinculos = SaplParlamentares::vincular($usuarios, $parlamentares);
confere(($vinculos[3]['nome_parlamentar'] ?? '') === 'Jadão do Dogão', 'liga Jader ao nome parlamentar Jadão do Dogão');
confere(($vinculos[2]['nome_parlamentar'] ?? '') === 'Robson Freitas', 'liga Robson mesmo com "da/de" e ordem diferentes');
confere(!isset($vinculos[99]), 'usuario sem correspondencia fica sem vinculo');

// 2) Autoria
$presidente = ['id' => 3, 'nome' => 'JADER PAULO IZIDORIO', 'nome_parlamentar' => 'Jadão do Dogão'];
confere(AutoriaPresidencia::ehAutor(['sapl_autores_parlamentares' => 'Jadão do Dogão', 'autor' => 'Jadão do Dogão'], $presidente), 'presidente e autor (nome parlamentar)');
confere(AutoriaPresidencia::ehAutor(['sapl_autores_parlamentares' => 'Ana, Jadão do Dogão', 'autor' => 'Ana, Jadão do Dogão'], $presidente), 'presidente e um dos autores');
confere(!AutoriaPresidencia::ehAutor(['sapl_autores_parlamentares' => 'Tonhão', 'autor' => 'Tonhão'], $presidente), 'presidente nao e autor');
confere(AutoriaPresidencia::ehAutor(['autor' => 'Jader Paulo Izidório'], ['id' => 3, 'nome' => 'JADER PAULO IZIDORIO', 'nome_parlamentar' => '']), 'matéria manual: compara com o nome do cadastro');
confere(!AutoriaPresidencia::ehAutor(['autor' => 'Yago Pezarico Giacomelli (Prefeito Municipal)', 'sapl_autores_parlamentares' => ''], $presidente), 'materia do Prefeito: presidente nao e autor');

// 3) Sugestão na condução
$pdo = preparar_banco_teste();
$titular = criar_usuario_teste($pdo, 'Jader Teste', 'PRESIDENTE');
$vice = criar_usuario_teste($pdo, 'Robson Teste');
$pdo->prepare('UPDATE users SET nome_parlamentar = ? WHERE id = ?')->execute(['Jadão Teste', $titular]);
$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 9, 'ABERTA')")->execute(['Sessao autoria ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO sessao_conducao (sessao_id, titular_user_id, condutor_user_id, atualizado_por) VALUES (?, ?, ?, ?)')->execute([$sessaoId, $titular, $titular, $titular]);
$item = static function (string $titulo, string $autores) use ($pdo, $sessaoId): int {
    $pdo->prepare('INSERT INTO pauta_itens (sessao_id, titulo, autor, sapl_autores_parlamentares) VALUES (?, ?, ?, ?)')->execute([$sessaoId, $titulo, $autores, $autores]);

    return (int) $pdo->lastInsertId();
};
$passo = static function (int $ordem, ?int $itemId, string $status) use ($pdo, $sessaoId): int {
    $pdo->prepare("INSERT INTO sessao_roteiro (sessao_id, ordem, tipo_item, pauta_item_id, texto_fala, status_execucao) VALUES (?, ?, 'CONDUCAO', ?, 'Texto', ?)")->execute([$sessaoId, $ordem, $itemId, $status]);

    return (int) $pdo->lastInsertId();
};

try {
    $doPresidente = $item('Projeto de Lei Legislativo nº 12/2026', 'Jadão Teste');
    $outra = $item('Indicação nº 50/2026', 'Marcelo Teste');
    $p1 = $passo(1, $doPresidente, 'EM_ANDAMENTO');
    $p2 = $passo(2, null, 'PENDENTE');
    $p3 = $passo(3, $outra, 'PENDENTE');

    confere(AutoriaPresidencia::sugestao($sessaoId, SessaoRoteiro::find($p1)) === 'passar', 'titular conduzindo a propria materia: sugere passar a presidencia');

    $pdo->exec("UPDATE sessao_conducao SET condutor_user_id = $vice WHERE sessao_id = $sessaoId");
    confere(AutoriaPresidencia::sugestao($sessaoId, SessaoRoteiro::find($p2)) === null, 'vice durante a materia do titular: sem sugestao');

    $pdo->exec("UPDATE sessao_roteiro SET status_execucao = 'CONCLUIDO' WHERE id IN ($p1, $p2)");
    confere(AutoriaPresidencia::sugestao($sessaoId, SessaoRoteiro::find($p3)) === 'devolver', 'materia do titular terminou: sugere devolver a presidencia');

    $pdo->exec("UPDATE sessao_conducao SET condutor_user_id = $titular WHERE sessao_id = $sessaoId");
    confere(AutoriaPresidencia::sugestao($sessaoId, SessaoRoteiro::find($p3)) === null, 'titular de volta, outra materia: sem sugestao');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach (['sessao_roteiro', 'pauta_itens'] as $t) {
        $pdo->exec("DELETE FROM $t WHERE sessao_id = $sessaoId");
    }
    $pdo->exec("DELETE FROM sessoes WHERE id = $sessaoId");
    $pdo->exec("DELETE FROM users WHERE id IN ($titular, $vice)");
}

finalizar_testes();
