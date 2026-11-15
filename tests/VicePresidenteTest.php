<?php
declare(strict_types=1);

/**
 * Vice-presidente: um parlamentar marcado no cadastro abre e conduz a sessão
 * quando o presidente não está. Com o presidente presente (sessão aberta por
 * ele), o vice é parlamentar comum. Se o presidente chega a uma sessão aberta
 * pelo vice, assume a presidência e o vice volta a votar.
 *
 * Uso: php tests/VicePresidenteTest.php
 */

require __DIR__ . '/_db.php';

use App\Helpers\Permissao;
use App\Models\SessaoConducao;
use App\Models\SessaoPresenca;
use App\Models\User;

$pdo = preparar_banco_teste();
$pdo->exec('UPDATE users SET vice_presidente = 0');
$presidente = criar_usuario_teste($pdo, 'Presidente Vice Teste', 'PRESIDENTE');
$vice = criar_usuario_teste($pdo, 'Vice Teste');
$comum = criar_usuario_teste($pdo, 'Vereador Comum Teste');

$novaSessao = static function (string $status) use ($pdo): int {
    $pdo->prepare('INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 88, ?)')
        ->execute(['Sessao vice ' . bin2hex(random_bytes(3)), $status]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO sessao_roteiro (sessao_id, ordem, tipo_item, texto_fala, status_execucao) VALUES (?, 1, 'CONDUCAO', 'Abertura', 'PENDENTE')")
        ->execute([$id]);
    return $id;
};
$sessaoVice = $novaSessao('CRIADO');      // presidente ausente: o vice abre
$sessaoPres = $novaSessao('CRIADO');      // presidente presente: ele abre
$sessaoHttp = $novaSessao('CRIADO');      // abertura pelo vice passando pelo sistema inteiro

$como = static function (int $id, string $role, string $rota): void {
    $u = User::find($id);
    $_SESSION['user'] = ['id' => $id, 'role' => $role, 'nome' => (string) $u['nome'], 'vice_presidente' => User::ehVicePresidente($u)];
    $_SESSION['permissoes_personalizado'] = false;
    $_GET['r'] = $rota;
    $_POST = [];
};

try {
    // Cadastro: só um vice, só parlamentar.
    User::definirVicePresidente($comum, true);
    User::definirVicePresidente($vice, true);
    confere(User::ehVicePresidente(User::find($vice)), 'Vice marcado no cadastro');
    confere(!User::ehVicePresidente(User::find($comum)), 'Marcar outro vice desmarca o anterior');
    User::definirVicePresidente($presidente, true);
    confere(!User::ehVicePresidente(User::find($presidente)), 'Presidente nao pode ser marcado como vice');
    confere(User::ehVicePresidente(User::find($vice)), 'Vice continua marcado');

    // Sessão criada, presidente não abriu: o vice pode abrir; vereador comum não.
    $como($vice, 'PARLAMENTAR', 'presidencia/iniciar/' . $sessaoVice);
    confere(Permissao::usuarioAtualPode('presidencia.iniciar'), 'Vice pode iniciar a sessao que o presidente nao abriu');
    $_GET['r'] = 'presidencia/conduzir/' . $sessaoVice;
    confere(Permissao::usuarioAtualPode('presidencia.conduzir'), 'Vice abre a tela de conducao da sessao criada');
    $como($comum, 'PARLAMENTAR', 'presidencia/iniciar/' . $sessaoVice);
    confere(!Permissao::usuarioAtualPode('presidencia.iniciar'), 'Vereador comum nao inicia sessao');
    $_GET['r'] = 'presidencia/conduzir/' . $sessaoVice;
    confere(!Permissao::usuarioAtualPode('presidencia.conduzir'), 'Vereador comum nao abre a conducao');

    // Presidente abriu a outra sessão: o vice é parlamentar comum nela.
    SessaoPresenca::set($sessaoPres, $presidente, true);
    SessaoPresenca::set($sessaoPres, $vice, true);
    SessaoConducao::iniciar($sessaoPres, $presidente);
    $pdo->exec("UPDATE sessoes SET status = 'ABERTA' WHERE id = $sessaoPres");
    $como($vice, 'PARLAMENTAR', 'presidencia/iniciar/' . $sessaoPres);
    confere(!Permissao::usuarioAtualPode('presidencia.iniciar'), 'Presidente presente: vice nao inicia');
    $_GET['r'] = 'presidencia/avancar/' . $sessaoPres;
    confere(!Permissao::usuarioAtualPode('presidencia.avancar'), 'Presidente presente: vice nao conduz');
    confere(in_array($vice, SessaoConducao::eleitoresPresentes($sessaoPres), true), 'Presidente presente: vice vota como parlamentar');
    confere(!SessaoConducao::presidentePodeAssumir($sessaoPres, $presidente), 'Sessao do proprio presidente: nada a assumir');

    // Vice abre a sessão na ausência do presidente.
    SessaoPresenca::set($sessaoVice, $comum, true);
    SessaoPresenca::set($sessaoVice, $vice, true);
    SessaoConducao::iniciar($sessaoVice, $vice);
    $pdo->exec("UPDATE sessoes SET status = 'ABERTA' WHERE id = $sessaoVice");
    $como($vice, 'PARLAMENTAR', 'presidencia/avancar/' . $sessaoVice);
    foreach (['presidencia.avancar', 'presidencia.encerrarSessao', 'presidencia.concederPalavra', 'presidencia.retirarPauta'] as $chave) {
        $_GET['r'] = str_replace('.', '/', $chave) . '/' . $sessaoVice;
        confere(Permissao::usuarioAtualPode($chave), 'Vice em exercicio pode: ' . $chave);
    }
    $_GET['r'] = 'votacao/abrirRodada/' . $sessaoVice;
    confere(Permissao::usuarioAtualPode('votacao.abrirRodada'), 'Vice em exercicio abre votacao');
    confere(!in_array($vice, SessaoConducao::eleitoresPresentes($sessaoVice), true), 'Vice presidindo so desempata (fora dos eleitores)');

    // O presidente chega: vê a sessão e pode assumir.
    confere(SessaoConducao::presidentePodeAssumir($sessaoVice, $presidente), 'Presidente pode assumir a sessao aberta pelo vice');
    confere(!SessaoConducao::presidentePodeAssumir($sessaoVice, $comum), 'Vereador comum nao pode assumir');
    $como($presidente, 'PRESIDENTE', 'presidencia/conduzir/' . $sessaoVice);
    confere(Permissao::usuarioAtualPode('presidencia.conduzir'), 'Presidente entra na sessao aberta pelo vice');
    $_GET['r'] = 'parlamentar/sessao/' . $sessaoVice;
    confere(Permissao::usuarioAtualPode('parlamentar.sessao'), 'Presidente acompanha a sessao antes de assumir');
    $_GET['r'] = 'presidencia/avancar/' . $sessaoVice;
    confere(!Permissao::usuarioAtualPode('presidencia.avancar'), 'Antes de assumir o presidente nao conduz');
    $_GET['r'] = 'presidencia/retomarConducao/' . $sessaoVice;
    confere(Permissao::usuarioAtualPode('presidencia.retomarConducao'), 'Presidente pode acionar "Assumir a presidencia"');
    $como($comum, 'PARLAMENTAR', 'presidencia/retomarConducao/' . $sessaoVice);
    confere(!Permissao::usuarioAtualPode('presidencia.retomarConducao'), 'Vereador comum nao aciona "Assumir a presidencia"');

    try {
        SessaoConducao::assumir($sessaoVice, $comum);
        confere(false, 'Vereador comum nao deveria assumir');
    } catch (\RuntimeException $e) {
        confere(true, 'Assumir recusa quem nao e presidente');
    }

    SessaoConducao::assumir($sessaoVice, $presidente);
    confere(SessaoConducao::usuarioPodeConduzir($sessaoVice, $presidente), 'Presidente assumiu: conduz a sessao');
    confere(SessaoConducao::titularId($sessaoVice) === $presidente, 'Presidente assumiu: vira o titular');
    confere(in_array($presidente, SessaoPresenca::idsPresentes($sessaoVice), true), 'Presidente assumiu: fica presente');
    confere(in_array($vice, SessaoConducao::eleitoresPresentes($sessaoVice), true), 'Vice volta a votar como parlamentar');
    $como($vice, 'PARLAMENTAR', 'presidencia/avancar/' . $sessaoVice);
    confere(!Permissao::usuarioAtualPode('presidencia.avancar'), 'Vice deixa de conduzir');
    $_GET['r'] = 'presidencia/retomarConducao/' . $sessaoVice;
    confere(!Permissao::usuarioAtualPode('presidencia.retomarConducao'), 'Vice nao toma a presidencia de volta');
    $tipo = $pdo->query("SELECT tipo FROM sessao_conducao_historico WHERE sessao_id = $sessaoVice ORDER BY id DESC LIMIT 1")->fetchColumn();
    confere($tipo === 'ASSUNCAO', 'Historico registra que o presidente assumiu');

    // Pelo sistema inteiro: o vice abre a sessão; o presidente não entra como presente.
    $userVice = ['id' => $vice, 'role' => 'PARLAMENTAR', 'nome' => 'Vice Teste'];
    $r = rodar_cenario([
        'user' => $userVice,
        'metodo' => 'POST',
        'get_r' => 'presidencia/iniciar/' . $sessaoHttp,
        'post' => ['presentes' => [$comum, $vice, $presidente]],
    ]);
    $status = (string) $pdo->query("SELECT status FROM sessoes WHERE id = $sessaoHttp")->fetchColumn();
    confere($status === 'ABERTA', 'Vice abre a sessao pelo sistema (status ' . $status . ', http ' . $r['http'] . ')');
    confere(SessaoConducao::titularId($sessaoHttp) === $vice && SessaoConducao::condutorId($sessaoHttp) === $vice, 'Vice fica na presidencia em exercicio');
    confere(!in_array($presidente, SessaoPresenca::idsPresentes($sessaoHttp), true), 'Presidente ausente nao entra como presente');
    $evento = (string) $pdo->query("SELECT descricao FROM sessao_eventos WHERE sessao_id = $sessaoHttp ORDER BY id DESC LIMIT 1")->fetchColumn();
    confere(str_contains($evento, 'vice-presidente'), 'Registro da sessao diz que foi aberta pelo vice-presidente');

    $r = rodar_cenario(['user' => $userVice, 'metodo' => 'GET', 'get_r' => 'presidencia/conduzir/' . $sessaoHttp]);
    confere($r['http'] === 200 && str_contains($r['saida'], 'presidente em exercício'), 'Tela de conducao avisa que o vice e presidente em exercicio (http ' . $r['http'] . ')');

    // Vereador comum tentando abrir pelo sistema: recusado.
    $outra = $novaSessao('CRIADO');
    $r = rodar_cenario([
        'user' => ['id' => $comum, 'role' => 'PARLAMENTAR', 'nome' => 'Comum'],
        'metodo' => 'POST',
        'get_r' => 'presidencia/iniciar/' . $outra,
        'post' => ['presentes' => [$comum]],
    ]);
    $status = (string) $pdo->query("SELECT status FROM sessoes WHERE id = $outra")->fetchColumn();
    confere($status === 'CRIADO' && $r['http'] === 403, 'Vereador comum nao abre sessao pelo sistema (http ' . $r['http'] . ')');

    // O presidente chega e assume pelo sistema.
    $r = rodar_cenario([
        'user' => ['id' => $presidente, 'role' => 'PRESIDENTE', 'nome' => 'Presidente Vice Teste'],
        'metodo' => 'POST',
        'get_r' => 'presidencia/retomarConducao/' . $sessaoHttp,
    ]);
    confere(SessaoConducao::condutorId($sessaoHttp) === $presidente && SessaoConducao::titularId($sessaoHttp) === $presidente, 'Presidente assume pelo sistema (http ' . $r['http'] . ')');

    // Lista de sessões do vice mostra a sessão pronta para abrir; a do vereador comum não.
    $r = rodar_cenario(['user' => $userVice, 'metodo' => 'GET', 'get_r' => 'parlamentar/sessoes']);
    confere(str_contains($r['saida'], 'data-vice-abrir="' . $outra . '"'), 'Vice ve a sessao pronta para abrir');
    $r = rodar_cenario(['user' => ['id' => $comum, 'role' => 'PARLAMENTAR', 'nome' => 'Comum'], 'metodo' => 'GET', 'get_r' => 'parlamentar/sessoes']);
    confere(!str_contains($r['saida'], 'data-vice-abrir'), 'Vereador comum nao ve o botao de abrir sessao');

    // Cadastro pela tela de usuários: a secretaria marca outro vereador como vice.
    $admin = criar_usuario_teste($pdo, 'Admin Vice Teste', 'ADMINISTRADOR');
    $dadosComum = User::find($comum);
    $r = rodar_cenario([
        'user' => ['id' => $admin, 'role' => 'ADMINISTRADOR', 'nome' => 'Admin Vice Teste'],
        'metodo' => 'POST',
        'get_r' => 'usuario/save/' . $comum,
        'post' => [
            'nome' => (string) $dadosComum['nome'],
            'email' => (string) $dadosComum['email'],
            'cpf' => '390.533.447-05',
            'role' => 'PARLAMENTAR',
            'vice_presidente' => '1',
        ],
    ]);
    confere(User::ehVicePresidente(User::find($comum)), 'Secretaria marca o vice pela tela de usuarios (http ' . $r['http'] . ')');
    confere(!User::ehVicePresidente(User::find($vice)), 'O vice anterior e desmarcado pela tela');
    $r = rodar_cenario(['user' => ['id' => $admin, 'role' => 'ADMINISTRADOR', 'nome' => 'Admin'], 'metodo' => 'GET', 'get_r' => 'usuario/form/' . $comum]);
    confere($r['http'] === 200 && (bool) preg_match('/name="vice_presidente"[^>]*checked/', $r['saida']), 'Formulario mostra o vice marcado');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    $ids = implode(',', array_filter([$sessaoVice, $sessaoPres, $sessaoHttp, $outra ?? 0]));
    foreach (['sessao_conducao_historico', 'sessao_conducao', 'sessao_presentes', 'sessao_eventos', 'sessao_roteiro'] as $t) {
        try { $pdo->exec("DELETE FROM $t WHERE sessao_id IN ($ids)"); } catch (\Throwable $e) {}
    }
    $pdo->exec("DELETE FROM sessoes WHERE id IN ($ids)");
    try { $pdo->exec("DELETE FROM auditoria_log WHERE user_id IN ($presidente, $vice, $comum" . (isset($admin) ? ", $admin" : '') . ')'); } catch (\Throwable $e) {}
    $pdo->exec("DELETE FROM users WHERE id IN ($presidente, $vice, $comum" . (isset($admin) ? ", $admin" : '') . ')');
}

finalizar_testes();
