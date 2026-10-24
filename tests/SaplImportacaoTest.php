<?php
declare(strict_types=1);

/**
 * Importação de sessão do SAPL: cria sessão, pauta (com autores) e roteiro;
 * não importa a mesma sessão duas vezes; "Atualizar do SAPL" traz o que mudou
 * sem apagar ajustes do roteiro.
 * Usa dados no formato da API do SAPL (sem acessar a internet).
 *
 * Uso: C:\xampp\php\php.exe tests/SaplImportacaoTest.php
 */

require __DIR__ . '/_db.php';

use App\Services\SaplImportacao;

$pdo = preparar_banco_teste();
$admin = criar_usuario_teste($pdo, 'Admin SAPL', 'ADMIN');
$saplId = 900000 + random_int(1, 99999);

$sessaoSapl = [
    'id' => $saplId,
    '__str__' => '27ª Ordinária da 2ª Sessão Legislativa da 11ª Legislatura',
    'numero' => 27,
    'data_inicio' => '2026-10-26',
    'hora_inicio' => '09:00',
    'tipo_descricao' => 'Ordinária',
];
$materia = static fn (int $id, string $tipo, int $numero, string $ementa, array $autores, string $parte = 'Ordem do Dia', int $ordem = 1, string $turno = 'U'): array => [
    'turno' => $turno,
    'parte' => $parte,
    'numero_ordem' => $ordem,
    'sapl_materia_id' => $id,
    'tipo' => $tipo,
    'numero' => (string) $numero,
    'ano' => '2026',
    'titulo' => $tipo . ' nº ' . $numero . ' de 2026',
    'ementa' => $ementa,
    'protocolo' => (string) (100 + $numero),
    'texto_original' => $numero === 48 ? 'https://sapl.exemplo/media/pl_48.pdf' : '',
    'tipo_votacao' => 1,
    'observacao' => '',
    'autores' => $autores,
];
$prefeito = [['nome' => 'Yago Pezarico Giacomelli', 'cargo' => 'Prefeito Municipal', 'primeiro' => true, 'parlamentar' => false]];
$vereador = [['nome' => 'Juca Moro', 'cargo' => '', 'primeiro' => true, 'parlamentar' => true]];
$mesa = [['nome' => 'Mesa Diretora', 'cargo' => '', 'primeiro' => true, 'parlamentar' => false]];

$materias = [
    $materia($saplId + 1, 'Projeto de Lei Ordinária', 48, 'Institui o Conselho Municipal de Política Cultural.', $prefeito, 'Ordem do Dia', 1),
    $materia($saplId + 2, 'Indicação', 9, 'Indica a construção de uma praça.', $vereador, 'Ordem do Dia', 2),
    $materia($saplId + 4, 'Projeto de Lei Ordinária', 54, 'Altera a lei do magistério.', $prefeito, 'Ordem do Dia', 3, 'D'),
    $materia($saplId + 5, 'Projeto de Resolução', 3, 'Altera o regimento.', $mesa, 'Ordem do Dia', 4, 'U'),
];

$sessaoId = 0;
try {
    $sessaoId = SaplImportacao::criarSessao($sessaoSapl, $materias, $admin);
    $s = $pdo->query("SELECT * FROM sessoes WHERE id = $sessaoId")->fetch(PDO::FETCH_ASSOC);
    confere($s['titulo'] === $sessaoSapl['__str__'], 'sessao criada com o nome oficial do SAPL');
    confere((int) $s['numero'] === 27 && (int) $s['ano'] === 2026 && $s['tipo'] === 'ORDINARIA', 'numero, ano e tipo vindos do SAPL');
    confere($s['status'] === 'CRIADO', 'sessao importada fica como Criado (pronta para iniciar)');
    confere($s['data_sessao'] === '2026-10-26' && $s['hora_inicio'] === '09:00', 'data e hora da sessao');
    confere((int) $s['sapl_sessao_id'] === $saplId, 'guarda de qual sessao do SAPL veio');

    $itens = $pdo->query("SELECT * FROM pauta_itens WHERE sessao_id = $sessaoId ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
    confere(count($itens) === 4, 'pauta com as 4 materias');
    confere($itens[1]['sapl_autores_parlamentares'] === 'Juca Moro' && $itens[0]['sapl_autores_parlamentares'] === '', 'guarda os autores vereadores (tipo de autor do SAPL)');
    confere((int) $itens[0]['sapl_tipo_votacao'] === 1, 'guarda o tipo de votacao do SAPL');
    $textoMesa = implode(' ', $pdo->query("SELECT texto_fala FROM sessao_roteiro WHERE pauta_item_id = " . (int) $itens[3]['id'])->fetchAll(PDO::FETCH_COLUMN));
    confere(!str_contains($textoMesa, 'defesa') && str_contains($textoMesa, 'discussão do mérito') === false && str_contains($textoMesa, 'Coloco em discussão o mérito'), 'Mesa Diretora: sem defesa, com discussao');
    $textoInd = implode(' ', $pdo->query("SELECT texto_fala FROM sessao_roteiro WHERE pauta_item_id = " . (int) $itens[1]['id'])->fetchAll(PDO::FETCH_COLUMN));
    confere(str_contains($textoInd, 'Com a palavra o autor, Vereador Juca Moro, para a defesa.'), 'indicacao de vereador: defesa do autor');
    confere($itens[2]['sapl_turno'] === 'D', 'guarda o turno da materia (Deliberacao)');
    $passosD = $pdo->query("SELECT texto_fala, gera_votacao FROM sessao_roteiro WHERE pauta_item_id = " . (int) $itens[2]['id'])->fetchAll(PDO::FETCH_ASSOC);
    confere(count($passosD) === 1 && (int) $passosD[0]['gera_votacao'] === 0 && str_contains($passosD[0]['texto_fala'], 'Comissões Permanentes'), 'materia em deliberacao: so apresenta e encaminha, sem votacao');
    $votacao48 = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro WHERE gera_votacao = 1 AND pauta_item_id = " . (int) $itens[0]['id'])->fetchColumn();
    confere($votacao48 === 1, 'materia em turno unico: uma votacao (sem urgencia automatica)');
    confere(
        (int) $itens[0]['numero'] === 48 && $itens[0]['autor'] === 'Yago Pezarico Giacomelli (Prefeito Municipal)' && (int) $itens[0]['sapl_id'] === $saplId + 1,
        'materia com numero, autor (nome e cargo) e id do SAPL'
    );
    confere($itens[1]['autor'] === 'Juca Moro', 'autor vereador aparece so com o nome');

    $roteiro = $pdo->query("SELECT * FROM sessao_roteiro WHERE sessao_id = $sessaoId ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
    $textos = implode("\n", array_map(static fn (array $p): string => $p['texto_fala'] . ' ' . $p['texto_instrucao'], $roteiro));
    confere(count($roteiro) > 10, 'roteiro do presidente gerado (' . count($roteiro) . ' passos)');
    confere(str_contains($textos, 'nº 48/2026'), 'roteiro usa o numero da materia (48/2026), nao o protocolo');
    confere(!str_contains($textos, '{resultado_urgência}'), 'chave do pedido de urgencia preenchida');
    $ligados = array_filter($roteiro, static fn (array $p): bool => (int) $p['pauta_item_id'] === (int) $itens[0]['id']);
    confere($ligados !== [], 'passos da materia ficam ligados a ela (autor e resultado no roteiro)');

    // PDF do projeto anexado aos passos da matéria (vereador abre no tablet)
    $semAnexo = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro r LEFT JOIN sessao_roteiro_anexos a ON a.roteiro_passo_id = r.id WHERE r.pauta_item_id = " . (int) $itens[0]['id'] . " AND a.id IS NULL")->fetchColumn();
    $anexoUrl = $pdo->query("SELECT a.url FROM sessao_roteiro_anexos a INNER JOIN sessao_roteiro r ON r.id = a.roteiro_passo_id WHERE r.pauta_item_id = " . (int) $itens[0]['id'] . " LIMIT 1")->fetchColumn();
    confere($semAnexo === 0 && $anexoUrl === 'https://sapl.exemplo/media/pl_48.pdf', 'todos os passos da materia recebem o link do texto do projeto');
    $anexosIndicacao = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro_anexos a INNER JOIN sessao_roteiro r ON r.id = a.roteiro_passo_id WHERE r.pauta_item_id = " . (int) $itens[1]['id'])->fetchColumn();
    confere($anexosIndicacao === 0, 'materia sem texto no SAPL nao recebe anexo');

    // Não importa duas vezes
    $recusou = false;
    try {
        SaplImportacao::criarSessao($sessaoSapl, $materias, $admin);
    } catch (\DomainException $e) {
        $recusou = true;
    }
    confere($recusou, 'recusa importar a mesma sessao do SAPL duas vezes');
    confere(SaplImportacao::sessaoImportada($saplId) === $sessaoId, 'encontra a sessao ja importada');

    // Atualizar do SAPL: ementa mudou, uma matéria nova, uma retirada; ajuste manual preservado
    $passoEditado = (int) $roteiro[0]['id'];
    $pdo->exec("UPDATE sessao_roteiro SET texto_fala = 'TEXTO AJUSTADO PELO PRESIDENTE' WHERE id = $passoEditado");
    $materiasNovas = [
        $materia($saplId + 1, 'Projeto de Lei Ordinária', 48, 'Institui o Conselho Municipal de Política Cultural (ementa corrigida).', $prefeito, 'Ordem do Dia', 1),
        $materia($saplId + 3, 'Requerimento', 14, 'Requer informações sobre a obra da MT-438.', $vereador, 'Ordem do Dia', 2),
    ];
    $relatorio = SaplImportacao::atualizarSessao($sessaoId, $sessaoSapl, $materiasNovas, $admin);
    $passos48 = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro WHERE pauta_item_id = " . (int) $itens[0]['id'])->fetchColumn();
    $anexos48 = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro_anexos a INNER JOIN sessao_roteiro r ON r.id = a.roteiro_passo_id WHERE r.pauta_item_id = " . (int) $itens[0]['id'])->fetchColumn();
    confere($anexos48 === $passos48, 'atualizar nao duplica o anexo do texto do projeto');
    $ementa = $pdo->query("SELECT ementa FROM pauta_itens WHERE sessao_id = $sessaoId AND sapl_id = " . ($saplId + 1))->fetchColumn();
    confere(str_contains((string) $ementa, 'ementa corrigida'), 'atualizar traz a ementa corrigida');
    confere((int) $relatorio['novas'] === 1, 'atualizar inclui a materia nova');
    $passosNovos = (int) $pdo->query("SELECT COUNT(*) FROM sessao_roteiro r INNER JOIN pauta_itens p ON p.id = r.pauta_item_id WHERE r.sessao_id = $sessaoId AND p.sapl_id = " . ($saplId + 3))->fetchColumn();
    confere($passosNovos > 0, 'materia nova ganha os passos no roteiro');
    $ultimoBloco = $pdo->query("SELECT tipo_item FROM sessao_roteiro WHERE sessao_id = $sessaoId ORDER BY ordem DESC LIMIT 1")->fetchColumn();
    confere($ultimoBloco === 'ENCERRAMENTO', 'passos novos entram antes da tribuna e do encerramento');
    confere($pdo->query("SELECT texto_fala FROM sessao_roteiro WHERE id = $passoEditado")->fetchColumn() === 'TEXTO AJUSTADO PELO PRESIDENTE', 'ajuste manual do roteiro e preservado');
    confere(in_array('Indicação nº 9 de 2026', $relatorio['retiradas'], true), 'avisa a materia retirada da pauta no SAPL');
    confere((int) $pdo->query("SELECT COUNT(*) FROM pauta_itens WHERE sessao_id = $sessaoId AND sapl_id = " . ($saplId + 2))->fetchColumn() === 1, 'materia retirada nao e apagada (a decisao e do administrador)');

    // Sessão iniciada não é mais atualizada
    $pdo->exec("UPDATE sessoes SET status = 'ABERTA' WHERE id = $sessaoId");
    $recusou = false;
    try {
        SaplImportacao::atualizarSessao($sessaoId, $sessaoSapl, $materiasNovas, $admin);
    } catch (\DomainException $e) {
        $recusou = true;
    }
    confere($recusou, 'sessao ja iniciada nao e atualizada pelo SAPL');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    if ($sessaoId > 0) {
        foreach (['sessao_roteiro', 'pauta_itens', 'sessao_eventos'] as $t) {
            try {
                $pdo->exec("DELETE FROM $t WHERE sessao_id = $sessaoId");
            } catch (\Throwable $e) {
            }
        }
        $pdo->exec("DELETE FROM sessoes WHERE id = $sessaoId");
    }
    $pdo->exec("DELETE FROM users WHERE id = $admin");
}

finalizar_testes();
