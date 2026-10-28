<?php
declare(strict_types=1);

/**
 * Quando o presidente titular passa a presidência, ele volta a ser
 * parlamentar comum na sessão: vota e pede a palavra. Quem assumiu conduz
 * e sai da lista de eleitores (só desempata).
 *
 * Uso: C:\xampp\php\php.exe tests/PresidenciaTransferidaTest.php
 */

require __DIR__ . '/_db.php';

use App\Helpers\Permissao;
use App\Models\SessaoConducao;
use App\Models\SessaoPresenca;
use App\Helpers\ConducaoRichText;
use App\Models\User;

$pdo = preparar_banco_teste();
$jader = criar_usuario_teste($pdo, 'Jader Transf', 'PRESIDENTE');
$robson = criar_usuario_teste($pdo, 'Robson Transf');
$pdo->prepare("INSERT INTO sessoes (titulo, ano, numero, status) VALUES (?, 2026, 77, 'ABERTA')")->execute(['Sessao transf ' . bin2hex(random_bytes(3))]);
$sessaoId = (int) $pdo->lastInsertId();

$como = static function (int $id, string $role): void {
    $_SESSION['user'] = ['id' => $id, 'role' => $role, 'nome' => 'Teste'];
    $_SESSION['permissoes_personalizado'] = false;
    $_GET['r'] = 'parlamentar/sessao/' . $GLOBALS['sessaoId'];
};

try {
    SessaoPresenca::set($sessaoId, $jader, true);
    SessaoPresenca::set($sessaoId, $robson, true);
    SessaoConducao::iniciar($sessaoId, $jader);

    confere(!in_array($jader, SessaoConducao::eleitoresPresentes($sessaoId), true), 'Jader conduzindo: fora dos eleitores');

    SessaoConducao::transferir($sessaoId, $robson, $jader);

    confere(SessaoConducao::usuarioPodeConduzir($sessaoId, $robson), 'Robson passa a conduzir');
    confere(!SessaoConducao::usuarioPodeConduzir($sessaoId, $jader), 'Jader deixa de conduzir');
    confere(in_array($jader, SessaoConducao::eleitoresPresentes($sessaoId), true), 'Jader entra na lista de eleitores');
    confere(!in_array($robson, SessaoConducao::eleitoresPresentes($sessaoId), true), 'Robson sai da lista de eleitores');
    confere(SessaoConducao::usuarioPodeAcompanharComoParlamentar($sessaoId, $jader), 'Jader acompanha como parlamentar');

    $como($jader, 'PRESIDENTE');
    confere(Permissao::usuarioAtualPode('parlamentar.pedirPalavra'), 'Jader pode pedir a palavra');
    confere(Permissao::usuarioAtualPode('parlamentar.sessao'), 'Jader abre a tela do parlamentar');
    $_POST['sessao_id'] = $sessaoId;
    confere(Permissao::usuarioAtualPode('parlamentar.votar'), 'Jader pode votar');
    $_GET['r'] = 'presidencia/avancar/' . $sessaoId;
    confere(!Permissao::usuarioAtualPode('presidencia.avancar'), 'Jader nao conduz o roteiro');

    $como($robson, 'PARLAMENTAR');
    $_GET['r'] = 'presidencia/avancar/' . $sessaoId;
    confere(Permissao::usuarioAtualPode('presidencia.avancar'), 'Robson conduz o roteiro');
    confere(!SessaoConducao::usuarioPodeAcompanharComoParlamentar($sessaoId, $robson), 'Robson presidindo nao vota nem pede a palavra');

    // O presidente titular é vereador: aparece na lista de presença/votação e
    // pode ir para o telão quando pede a palavra e o condutor concede.
    $idsLista = array_map(static fn (array $u): int => (int) $u['id'], User::parlamentaresParaVotacao());
    confere(in_array($jader, $idsLista, true), 'Presidente titular aparece na lista de vereadores');
    SessaoConducao::setDestaque($sessaoId, $jader, 0);
    confere(SessaoConducao::destaqueUserId($sessaoId) === $jader, 'Presidente titular vai para o telao com a palavra');
    SessaoConducao::clearDestaque($sessaoId);

    SessaoConducao::devolver($sessaoId, $robson);
    confere(SessaoConducao::usuarioPodeConduzir($sessaoId, $jader), 'presidencia devolvida: Jader conduz de novo');
    confere(!in_array($jader, SessaoConducao::eleitoresPresentes($sessaoId), true), 'Jader de volta: sai dos eleitores');
    confere(in_array($robson, SessaoConducao::eleitoresPresentes($sessaoId), true), 'Robson volta a votar');
    // Texto sem formatação vindo do editor não mostra a entidade escrita.
    $html = ConducaoRichText::render('Peço a leitura do Edital&nbsp;');
    confere(!str_contains($html, '&amp;nbsp;') && !str_contains($html, '&nbsp;'), 'Previa nao mostra "&nbsp;" escrito');
    confere(str_contains(ConducaoRichText::render('a < b'), '&lt;'), 'Previa continua escapando HTML');
} catch (\Throwable $e) {
    confere(false, 'erro inesperado: ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach (['sessao_conducao', 'sessao_presencas'] as $t) {
        try { $pdo->exec("DELETE FROM $t WHERE sessao_id = $sessaoId"); } catch (\Throwable $e) {}
    }
    $pdo->exec("DELETE FROM sessoes WHERE id = $sessaoId");
    $pdo->exec("DELETE FROM users WHERE id IN ($jader, $robson)");
}

finalizar_testes();
