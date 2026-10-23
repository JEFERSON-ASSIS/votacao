<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\ParlamentarAcompanhar;
use App\Helpers\Permissao;
use App\Helpers\UserFotoUpload;
use App\Helpers\UserPerfil;
use App\Models\Sessao;
use App\Models\SessaoConducao;
use App\Models\SessaoCrud;
use App\Models\SessaoItem;
use App\Models\SessaoVotacaoRodada;
use App\Models\RodadaEleitor;
use App\Models\SessaoVoto;
use App\Models\User;

final class ParlamentarController
{
    public function meuPerfil(): void
    {
        Permissao::exigir('parlamentar.meuperfil');
        $user = User::find((int) Auth::user()['id']);
        if (!$user) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        View::render('parlamentar/meu_perfil', [
            'user' => $user,
            'error' => null,
            'salvo' => ($_GET['salvo'] ?? '') === '1',
        ]);
    }

    public function savePerfil(): void
    {
        Permissao::exigir('parlamentar.saveperfil');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $userId = (int) Auth::user()['id'];
        $existing = User::find($userId);
        if (!$existing) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        $senha = (string) ($_POST['senha'] ?? '');
        $senhaConfirm = (string) ($_POST['senha_confirm'] ?? '');
        $removerFoto = !empty($_POST['remover_foto']);

        $payload = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => (string) $existing['email'],
            'cpf' => (string) ($existing['cpf'] ?? ''),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'role' => (string) $existing['role'],
            'partido' => trim((string) ($_POST['partido'] ?? '')),
            'homepage' => trim((string) ($_POST['homepage'] ?? '')),
            'gabinete' => trim((string) ($_POST['gabinete'] ?? '')),
            'biografia' => trim((string) ($_POST['biografia'] ?? '')),
            'naturalidade' => trim((string) ($_POST['naturalidade'] ?? '')),
            'data_nascimento' => User::parseDataNascimento(trim((string) ($_POST['data_nascimento'] ?? ''))),
            'estado_civil' => trim((string) ($_POST['estado_civil'] ?? '')),
            'ocupacao' => trim((string) ($_POST['ocupacao'] ?? '')),
            'cor' => trim((string) ($_POST['cor'] ?? '')),
            'genero' => trim((string) ($_POST['genero'] ?? '')),
            'identidade_genero' => trim((string) ($_POST['identidade_genero'] ?? '')),
            'orientacao_sexual' => trim((string) ($_POST['orientacao_sexual'] ?? '')),
            'grau_instrucao' => trim((string) ($_POST['grau_instrucao'] ?? '')),
            'historico_politico' => trim((string) ($_POST['historico_politico'] ?? '')),
            'foto' => $existing['foto'] ?? null,
        ];

        $error = null;
        if ($payload['nome'] === '') {
            $error = 'Informe o nome.';
        } elseif ($senha !== '' && strlen($senha) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== '' && $senha !== $senhaConfirm) {
            $error = 'As senhas não conferem.';
        }

        if ($error !== null) {
            View::render('parlamentar/meu_perfil', ['user' => array_merge($existing, $payload), 'error' => $error, 'salvo' => false]);
            return;
        }

        if ($senha !== '') {
            $payload['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }

        $payload['foto'] = UserFotoUpload::handle($userId, (string) ($existing['foto'] ?? ''), $removerFoto);
        User::update($userId, $payload);

        $fresh = User::find($userId);
        if ($fresh) {
            Auth::login($fresh);
        }

        redirect_route('parlamentar/meuPerfil', ['salvo' => '1']);
    }

    public function sessoes(): void
    {
        Permissao::exigir('parlamentar.sessoes');
        View::render('parlamentar/sessoes', [
            'sessoes' => SessaoCrud::listParaParlamentar(),
        ]);
    }

    public function sessao(int $sessaoId): void
    {
        Permissao::exigir('parlamentar.sessao');
        $authId = (int) Auth::user()['id'];
        $ctx = ParlamentarAcompanhar::contexto($sessaoId, $authId);
        if (!$ctx) {
            http_response_code(404);
            echo 'Sessão não disponível.';
            return;
        }

        $sessaoEmAndamento = in_array($ctx['statusRaw'], ['ABERTA', 'EM_VOTACAO'], true);
        if ($sessaoEmAndamento && SessaoConducao::usuarioPodeConduzir($sessaoId, $authId)) {
            redirect_route('presidencia/conduzir/' . $sessaoId);
        }

        $conducaoEstado = SessaoConducao::estado($sessaoId);
        $ctx['conducaoEstado'] = $conducaoEstado;
        $ctx['souTitularForaDaConducao'] = $sessaoEmAndamento
            && $conducaoEstado !== null
            && (int) $conducaoEstado['titular_user_id'] === $authId
            && (int) $conducaoEstado['condutor_user_id'] !== $authId;
        $ctx['votoOk'] = ($_GET['voto'] ?? '') === '1';
        View::render('parlamentar/sessao', $ctx);
    }

    /** JSON: votação aberta em alguma sessão (alerta global na lista / layout). */
    public function votacaoAtiva(): void
    {
        Permissao::exigir('parlamentar.sessoes');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ParlamentarAcompanhar::votacaoAtivaResumo((int) Auth::user()['id']),
            JSON_UNESCAPED_UNICODE
        );
    }

    public function estado(int $sessaoId): void
    {
        Permissao::exigir('parlamentar.sessao');
        header('Content-Type: application/json; charset=utf-8');
        $comHtml = ($_GET['html_voto'] ?? '') === '1';
        echo json_encode(
            ParlamentarAcompanhar::estadoJson($sessaoId, (int) Auth::user()['id'], $comHtml),
            JSON_UNESCAPED_UNICODE
        );
    }

    /** Server-Sent Events: push de estado sem recarregar a página. */
    public function stream(int $sessaoId): void
    {
        Permissao::exigir('parlamentar.sessao');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Quebra buffer do Apache/mod_proxy para o cliente receber eventos na hora.
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        flush();

        $userId = (int) Auth::user()['id'];
        $ultimoHash = '';
        $inicio = time();
        $maxSeg = 300;
        $intervaloUs = 400000;

        while ((time() - $inicio) < $maxSeg) {
            if (connection_aborted()) {
                break;
            }

            $estado = ParlamentarAcompanhar::estadoJson($sessaoId, $userId, true);
            if (!($estado['ok'] ?? false)) {
                echo "event: error\ndata: {\"ok\":false}\n\n";
                flush();
                break;
            }

            $hash = ParlamentarAcompanhar::hashEstado($estado);
            if ($hash !== $ultimoHash) {
                echo "event: estado\n";
                echo 'data: ' . json_encode($estado, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                $ultimoHash = $hash;
            } else {
                echo ": ping\n\n";
                flush();
            }

            usleep($intervaloUs);
        }

        echo "event: reconnect\n";
        echo "data: {}\n\n";
        flush();
    }

    public function materia(int $sessaoId, int $itemId): void
    {
        Permissao::exigir('parlamentar.materia');
        $sessao = SessaoCrud::find($sessaoId);
        $item = SessaoItem::find($itemId);
        if (!$sessao || !$item || (int) $item['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Matéria não encontrada.';
            return;
        }

        $rodadaAberta = SessaoVotacaoRodada::findAberta($sessaoId);
        $podeVotar = false;
        $meuVoto = null;
        $rodadaItemOk = false;

        if ($rodadaAberta) {
            $rid = (int) $rodadaAberta['id'];
            $itemRodada = !empty($rodadaAberta['item_id']) ? (int) $rodadaAberta['item_id'] : 0;
            $rodadaItemOk = $itemRodada === 0 || $itemRodada === $itemId;
            $podeVotar = $rodadaItemOk
                && in_array(strtoupper((string) ($sessao['status'] ?? '')), ['ABERTA', 'EM_VOTACAO'], true)
                && RodadaEleitor::usuarioApto($rid, (int) Auth::user()['id']);
            $meuVoto = SessaoVoto::votoUsuarioRodada($rid, (int) Auth::user()['id']);
        }

        View::render('parlamentar/materia', [
            'sessao' => $sessao,
            'item' => \App\Helpers\MateriaSaplFields::mergeItemAndExtras($item),
            'sections' => SessaoItem::loadSectionRows($itemId),
            'rodadaAberta' => $rodadaAberta,
            'podeVotar' => $podeVotar,
            'meuVoto' => $meuVoto,
            'votoOk' => ($_GET['voto'] ?? '') === '1',
        ]);
    }

    public function votar(): void
    {
        Permissao::exigir('parlamentar.votar');
        $querJson = self::requisicaoAjax();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            self::responderVoto($querJson, false, 'Requisição inválida.', null, 0, 0);
            return;
        }

        $sessaoId = (int) ($_POST['sessao_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $rodadaId = (int) ($_POST['rodada_id'] ?? 0);
        $votoRaw = trim((string) ($_POST['voto'] ?? ''));
        $voto = votacao_normalizar_voto($votoRaw) ?? '';
        $userId = (int) Auth::user()['id'];

        $sessao = SessaoCrud::find($sessaoId);
        $rodada = SessaoVotacaoRodada::find($rodadaId);

        if (!$sessao || !$rodada
            || (int) $rodada['sessao_id'] !== $sessaoId
            || ($rodada['status'] ?? '') !== 'ABERTA'
            || !in_array(strtoupper((string) ($sessao['status'] ?? '')), ['ABERTA', 'EM_VOTACAO'], true)) {
            self::responderVoto($querJson, false, 'Votação indisponível.', null, $sessaoId, 0);
            return;
        }

        if (!self::usuarioPodeRegistrarVotoParlamentar($userId, (string) (Auth::user()['role'] ?? ''))) {
            self::responderVoto($querJson, false, 'Acesso negado.', null, $sessaoId, 0);
            return;
        }

        if (!RodadaEleitor::usuarioApto($rodadaId, $userId)) {
            self::responderVoto($querJson, false, 'Seu nome não consta na lista de presentes desta votação.', null, $sessaoId, 0);
            return;
        }

        $itemRodada = !empty($rodada['item_id']) ? (int) $rodada['item_id'] : 0;
        if ($itemRodada > 0) {
            $item = SessaoItem::find($itemId > 0 ? $itemId : $itemRodada);
            if (!$item || (int) $item['sessao_id'] !== $sessaoId || $itemRodada !== (int) $item['id']) {
                self::responderVoto($querJson, false, 'Esta matéria não está em votação no momento.', null, $sessaoId, $itemRodada);
                return;
            }
        }

        if ($voto === '') {
            self::responderVoto($querJson, false, 'Selecione Sim, Não ou Abstenção.', null, $sessaoId, $itemRodada);
            return;
        }

        if (SessaoVoto::votoUsuarioRodada($rodadaId, $userId) !== null) {
            self::responderVoto($querJson, false, 'Você já registrou seu voto nesta votação.', null, $sessaoId, $itemRodada);
            return;
        }

        try {
            SessaoVoto::voteRodada(
                $sessaoId,
                $rodadaId,
                $itemRodada > 0 ? $itemRodada : null,
                $userId,
                $voto
            );
        } catch (\Throwable $e) {
            self::responderVoto(
                $querJson,
                false,
                'Não foi possível registrar o voto. Tente novamente.',
                null,
                $sessaoId,
                $itemRodada
            );
            return;
        }

        self::responderVoto($querJson, true, 'Voto registrado.', $voto, $sessaoId, $itemRodada);
    }

    private static function requisicaoAjax(): bool
    {
        if (($_POST['ajax'] ?? '') === '1') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json');
    }

    private static function responderVoto(
        bool $json,
        bool $ok,
        string $mensagem,
        ?string $voto,
        int $sessaoId,
        int $itemRodada
    ): void {
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($ok ? 200 : 400);
            $payload = [
                'ok' => $ok,
                'mensagem' => $mensagem,
                'voto' => $voto,
                'voto_label' => $voto ? votacao_rotulo_voto($voto) : '',
                'voto_classe' => $voto ? votacao_classe_voto($voto) : '',
            ];
            if ($ok && $sessaoId > 0) {
                $payload['estado'] = ParlamentarAcompanhar::estadoJson(
                    $sessaoId,
                    (int) Auth::user()['id'],
                    true
                );
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!$ok) {
            http_response_code(400);
            echo $mensagem;

            return;
        }

        if ($itemRodada > 0) {
            redirect_route('parlamentar/materia/' . $sessaoId . '/' . $itemRodada, ['voto' => '1']);
            return;
        }

        redirect_route('parlamentar/sessao/' . $sessaoId, ['voto' => '1']);
    }

    private static function usuarioPodeRegistrarVotoParlamentar(int $userId, string $role): bool
    {
        return $userId > 0;
    }
}
