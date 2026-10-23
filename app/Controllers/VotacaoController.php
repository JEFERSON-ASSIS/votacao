<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Auditoria;
use App\Helpers\ConducaoRichText;
use App\Helpers\Permissao;
use App\Helpers\UserPerfil;
use App\Helpers\VotacaoPainelEstado;
use App\Models\Sessao;
use App\Models\RodadaEleitor;
use App\Models\SessaoCrud;
use App\Models\SessaoEvento;
use App\Models\SessaoItem;
use App\Models\SessaoPresenca;
use App\Models\SessaoRoteiro;
use App\Models\SessaoVotacaoRodada;
use App\Models\SessaoVoto;
use App\Models\SessaoVotoDesempate;
use App\Models\SessaoConducao;
use App\Models\User;
use App\Services\VotacaoEncerramentoService;

final class VotacaoController
{
    public function index(): void
    {
        Permissao::exigir('votacao.index');
        $sessao = Sessao::atual() ?? SessaoCrud::all()[0] ?? null;
        $sessaoId = $sessao ? (int) $sessao['id'] : 0;

        $roteiroStats = $sessao ? SessaoRoteiro::contagem($sessaoId) : ['total' => 0, 'concluidos' => 0];
        $rodadaAberta = $sessao ? SessaoVotacaoRodada::findAberta($sessaoId) : null;
        $rodadaId = (int) ($rodadaAberta['id'] ?? 0);
        $usuarios = $rodadaId > 0 && RodadaEleitor::total($rodadaId) > 0
            ? RodadaEleitor::eleitores($rodadaId)
            : User::parlamentaresParaVotacao();

        View::render('votacao/index', [
            'sessao' => $sessao,
            'status' => $sessao ? strtoupper((string) $sessao['status']) : null,
            'itens' => $sessao ? Sessao::pauta($sessaoId) : [],
            'usuarios' => $usuarios,
            'presencas' => $sessao ? SessaoPresenca::allBySessao($sessaoId) : [],
            'eventos' => $sessao ? SessaoEvento::allBySessao($sessaoId) : [],
            'rodadaAberta' => $rodadaAberta,
            'historicoRodadas' => $sessao ? SessaoVotacaoRodada::historico($sessaoId, 10) : [],
            'roteiroStats' => $roteiroStats,
            'usaRoteiro' => ($roteiroStats['total'] ?? 0) > 0,
        ]);
    }

    public function painel(int $sessaoId): void
    {
        Permissao::exigir('votacao.painel');
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $rodadaId = (int) ($_GET['rodada'] ?? 0);
        $rodada = $rodadaId > 0
            ? SessaoVotacaoRodada::find($rodadaId)
            : SessaoVotacaoRodada::findAberta($sessaoId);

        if (!$rodada || (int) $rodada['sessao_id'] !== $sessaoId) {
            View::render('votacao/painel', [
                'sessao' => $sessao,
                'rodada' => null,
                'usuarios' => User::parlamentaresParaVotacao(),
                'votos' => [],
                'resumo' => ['SIM' => 0, 'NAO' => 0, 'ABSTENCAO' => 0, 'AUSENTE' => 0],
                'historicoRodadas' => SessaoVotacaoRodada::historico($sessaoId, 15),
            ]);
            return;
        }

        $rid = (int) $rodada['id'];
        $resumoParl = SessaoVoto::resumoParlamentaresPorRodada($rid);
        $encerramento = VotacaoPainelEstado::validarEncerramento($rid);
        $auth = Auth::user();
        $authId = (int) ($auth['id'] ?? 0);
        View::render('votacao/painel', [
            'sessao' => $sessao,
            'rodada' => $rodada,
            'usuarios' => User::parlamentaresParaVotacao(),
            'votos' => SessaoVoto::allByRodada($rid),
            'resumo' => SessaoVoto::resumoPorRodada($rid),
            'resumoParlamentares' => $resumoParl,
            'empate' => VotacaoPainelEstado::empateParlamentares($resumoParl),
            'podeEncerrar' => $encerramento['permitido'],
            'encerrarMensagem' => $encerramento['mensagem'],
            'presidenteVotouDesempate' => $encerramento['presidente_votou'],
            'ehPresidente' => SessaoConducao::usuarioPodeConduzir($sessaoId, $authId),
            'presidenteId' => $authId,
            'presidenteVoto' => SessaoVotoDesempate::voto($rid),
            'historicoRodadas' => SessaoVotacaoRodada::historico($sessaoId, 15),
        ]);
    }

    public function estadoRodada(int $rodadaId): void
    {
        Permissao::exigir('votacao.estadoRodada');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(VotacaoPainelEstado::jsonRodada($rodadaId), JSON_UNESCAPED_UNICODE);
    }

    /** SSE: votos dos parlamentares em tempo real no painel da presidência. */
    public function streamRodada(int $rodadaId): void
    {
        Permissao::exigir('votacao.streamRodada');
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

        echo ':' . str_repeat(' ', 2048) . "\n\n";
        flush();

        $ultimoHash = '';
        $inicio = time();
        $maxSeg = 300;
        $intervaloUs = 400000;

        while ((time() - $inicio) < $maxSeg) {
            if (connection_aborted()) {
                break;
            }

            $estado = VotacaoPainelEstado::jsonRodada($rodadaId);
            if (!($estado['ok'] ?? false)) {
                echo "event: error\ndata: {\"ok\":false}\n\n";
                flush();
                break;
            }

            $hash = VotacaoPainelEstado::hashEstado($estado);
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

    public function abrirRodada(int $sessaoId): void
    {
        Permissao::exigir('votacao.abrirRodada');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada.';
            return;
        }

        $statusSessao = strtoupper((string) ($sessao['status'] ?? ''));
        if (!in_array($statusSessao, ['ABERTA', 'EM_VOTACAO'], true)) {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&sessao_erro=nao_iniciada'));
            exit;
        }

        $roteiroPassoId = (int) ($_POST['roteiro_passo_id'] ?? 0) ?: null;
        $itemId = (int) ($_POST['item_id'] ?? 0) ?: null;
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        if ($roteiroPassoId) {
            $passo = SessaoRoteiro::find($roteiroPassoId);
            if ($passo && (int) $passo['sessao_id'] === $sessaoId) {
                if ($titulo === '') {
                    $titulo = trim((string) ($passo['titulo'] ?? ''));
                }
                if ($descricao === '') {
                    $descricao = ConducaoRichText::plain((string) ($passo['texto_fala'] ?: $passo['texto_instrucao'] ?? ''));
                }
                if (!$itemId && !empty($passo['pauta_item_id'])) {
                    $itemId = (int) $passo['pauta_item_id'];
                }
            }
        }

        if ($titulo === '') {
            $titulo = 'Votação';
        }

        $eleitoresIds = SessaoConducao::eleitoresPresentes($sessaoId);
        $tempoSeg = sessao_tempo_normalizar($_POST['tempo_seg'] ?? 0);
        try {
            $rodadaId = SessaoVotacaoRodada::abrir(
                $sessaoId,
                $titulo,
                $roteiroPassoId,
                $itemId,
                $descricao !== '' ? $descricao : null,
                $eleitoresIds,
                $tempoSeg
            );
        } catch (\Throwable $e) {
            app_log('[VotacaoController::abrirRodada] sessaoId=' . $sessaoId . ' msg=' . $e->getMessage());
            $return = trim((string) ($_POST['return_to'] ?? ''));
            $destino = $return === 'conduzir'
                ? '?r=presidencia/conduzir/' . $sessaoId . '&votacao_erro=sem_eleitores'
                : '?r=votacao/painel/' . $sessaoId . '&votacao_erro=sem_eleitores';
            header('Location: ' . base_url($destino));
            exit;
        }
        Auditoria::log('abrir', 'votacao_rodada', $rodadaId, $titulo);
        SessaoEvento::registrar(
            $sessaoId,
            'votacao',
            'Votação aberta: ' . $titulo . ' — fotografia com ' . count($eleitoresIds) . ' eleitor(es)',
            $itemId ?: null
        );

        $return = trim((string) ($_POST['return_to'] ?? ''));
        if ($return === 'conduzir') {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId));
        } else {
            header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId));
        }
        exit;
    }

    public function encerrarRodada(int $sessaoId, int $rodadaId): void
    {
        Permissao::exigir('votacao.encerrarRodada');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $rodada = SessaoVotacaoRodada::find($rodadaId);
        if (!$rodada || (int) $rodada['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Rodada não encontrada.';
            return;
        }

        $validacao = VotacaoPainelEstado::validarEncerramento($rodadaId);
        if (!$validacao['permitido']) {
            $return = trim((string) ($_POST['return_to'] ?? ''));
            $erro = !empty($validacao['pendentes']) ? 'votos_pendentes' : 'empate_presidente';
            if ($return === 'conduzir') {
                header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&votacao_erro=' . $erro));
            } else {
                header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId . '&votacao_erro=' . $erro));
            }
            exit;
        }

        $encerramento = VotacaoEncerramentoService::encerrar($sessaoId, $rodadaId);
        if (!$encerramento['permitido']) {
            $return = trim((string) ($_POST['return_to'] ?? ''));
            $erro = !empty($encerramento['pendentes']) ? 'votos_pendentes' : 'empate_presidente';
            $destino = $return === 'conduzir'
                ? '?r=presidencia/conduzir/' . $sessaoId . '&votacao_erro=' . $erro
                : '?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId . '&votacao_erro=' . $erro;
            header('Location: ' . base_url($destino));
            exit;
        }
        $apuracao = $encerramento['apuracao'] ?? [];
        Auditoria::log('encerrar', 'votacao_rodada', $rodadaId, (string) ($apuracao['resultado'] ?? ''));
        SessaoEvento::registrar(
            $sessaoId,
            'votacao',
            'Votação encerrada: ' . $rodada['titulo'] . ' — ' . $apuracao['resultado'],
            !empty($rodada['item_id']) ? (int) $rodada['item_id'] : null
        );

        $return = trim((string) ($_POST['return_to'] ?? ''));
        if ($return === 'conduzir') {
            header('Location: ' . base_url('?r=presidencia/conduzir/' . $sessaoId . '&votacao_ok=1'));
        } else {
            header('Location: ' . base_url('?r=votacao/apuracaoRodada/' . $rodadaId));
        }
        exit;
    }

    /** Marca um vereador da votação como impedido, ausente ou o devolve à votação. */
    public function situacaoEleitor(int $sessaoId, int $rodadaId): void
    {
        Permissao::exigir('votacao.situacaoEleitor');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $rodada = SessaoVotacaoRodada::find($rodadaId);
        if (!$rodada || (int) $rodada['sessao_id'] !== $sessaoId) {
            http_response_code(404);
            echo 'Rodada não encontrada.';
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $situacao = strtoupper(trim((string) ($_POST['situacao'] ?? '')));
        $voltar = '?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId;

        try {
            RodadaEleitor::alterarSituacao(
                $rodadaId,
                $userId,
                $situacao,
                (int) (Auth::user()['id'] ?? 0),
                trim((string) ($_POST['justificativa'] ?? '')) ?: null
            );
        } catch (\DomainException $e) {
            header('Location: ' . base_url($voltar . '&situacao_erro=' . rawurlencode($e->getMessage())));
            exit;
        }

        $nome = (string) (User::find($userId)['nome'] ?? ('#' . $userId));
        $descricao = match ($situacao) {
            'IMPEDIDO' => $nome . ' declarado impedido',
            'AUSENTE' => $nome . ' ausente na votação',
            default => $nome . ' devolvido à votação',
        };
        Auditoria::log('situacao_eleitor', 'votacao_rodada', $rodadaId, $descricao);
        SessaoEvento::registrar(
            $sessaoId,
            'votacao',
            $descricao . ' — ' . (string) $rodada['titulo'],
            !empty($rodada['item_id']) ? (int) $rodada['item_id'] : null
        );

        header('Location: ' . base_url($voltar));
        exit;
    }

    public function votarRodada(int $sessaoId, int $rodadaId): void
    {
        Permissao::exigir('votacao.votarRodada');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $rodada = SessaoVotacaoRodada::find($rodadaId);
        if (!$rodada || (int) $rodada['sessao_id'] !== $sessaoId || ($rodada['status'] ?? '') !== 'ABERTA') {
            http_response_code(400);
            echo 'Rodada de votação não está aberta.';
            return;
        }

        $auth = Auth::user();
        $authId = (int) ($auth['id'] ?? 0);
        $authRole = (string) ($auth['role'] ?? '');
        $userId = (int) ($_POST['user_id'] ?? 0);
        $voto = votacao_normalizar_voto(trim((string) ($_POST['voto'] ?? ''))) ?? '';
        $observacao = trim((string) ($_POST['observacao'] ?? ''));

        if (SessaoConducao::usuarioPodeConduzir($sessaoId, $authId)) {
            if ($userId !== $authId) {
                http_response_code(403);
                echo 'O condutor só registra o próprio voto de desempate.';
                return;
            }
            $resumoParl = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
            if (!VotacaoPainelEstado::empateParlamentares($resumoParl)) {
                http_response_code(400);
                echo 'O voto de desempate só é permitido quando houver empate entre Sim e Não.';
                return;
            }
            if (!in_array($voto, ['SIM', 'NAO'], true)) {
                http_response_code(400);
                echo 'Selecione Sim ou Não para desempatar.';
                return;
            }
            SessaoVotoDesempate::registrar($sessaoId, $rodadaId, $authId, $voto);
            Auditoria::log('desempatar', 'votacao_rodada', $rodadaId, 'Voto de desempate do condutor em exercício');
            header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId));
            exit;
        }

        $alvo = $userId > 0 ? User::find($userId) : null;
        if ($alvo && UserPerfil::ehParlamentar((string) ($alvo['role'] ?? ''))) {
            http_response_code(403);
            echo 'O voto do vereador é registrado pelo próprio parlamentar na tela Acompanhar sessão.';
            return;
        }

        if (UserPerfil::matches($authRole, UserPerfil::PRESIDENTE)) {
            if ($userId !== $authId) {
                http_response_code(403);
                echo 'O presidente só registra o próprio voto em caso de empate.';
                return;
            }
            $resumoParl = SessaoVoto::resumoParlamentaresPorRodada($rodadaId);
            if (!VotacaoPainelEstado::empateParlamentares($resumoParl)) {
                http_response_code(400);
                echo 'Voto do presidente só é permitido em empate entre Sim e Não.';
                return;
            }
        }

        if ($voto === '') {
            http_response_code(400);
            echo 'Selecione Sim ou Não.';
            return;
        }

        if ($userId > 0) {
            SessaoVoto::voteRodada(
                $sessaoId,
                $rodadaId,
                !empty($rodada['item_id']) ? (int) $rodada['item_id'] : null,
                $userId,
                $voto,
                $observacao !== '' ? $observacao : null
            );
        }

        header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . $rodadaId));
        exit;
    }

    public function apuracaoRodada(int $rodadaId): void
    {
        Permissao::exigir('votacao.apuracaoRodada');
        $rodada = SessaoVotacaoRodada::find($rodadaId);
        if (!$rodada) {
            http_response_code(404);
            echo 'Rodada não encontrada.';
            return;
        }

        $sessao = SessaoCrud::find((int) $rodada['sessao_id']);
        View::render('votacao/apuracao_rodada', [
            'sessao' => $sessao,
            'rodada' => $rodada,
            'resumo' => SessaoVoto::resumoPorRodada($rodadaId),
            'resultado' => (string) ($rodada['resultado'] ?? SessaoVoto::resultadoFromResumo(SessaoVoto::resumoPorRodada($rodadaId))),
            'total' => SessaoVoto::totalPorRodada($rodadaId),
            'votos' => SessaoVoto::allByRodada($rodadaId),
        ]);
    }

    public function presenca(int $sessaoId): void
    {
        Permissao::exigir('votacao.presenca');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $presente = (string) ($_POST['presente'] ?? '1') === '1';
        if ($userId > 0) {
            SessaoPresenca::set($sessaoId, $userId, $presente);
            SessaoEvento::registrar($sessaoId, 'presenca', $presente ? 'Presença registrada' : 'Presença removida');
        }

        header('Location: ' . base_url('?r=votacao/index'));
    }

    public function votar(int $sessaoId, int $itemId): void
    {
        Permissao::exigir('votacao.votar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $voto = trim((string) ($_POST['voto'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        if ($userId > 0 && $voto !== '') {
            SessaoVoto::vote($sessaoId, $itemId, $userId, $voto, $observacao !== '' ? $observacao : null);
            SessaoEvento::registrar($sessaoId, 'votacao', 'Voto registrado', $itemId);
        }

        header('Location: ' . base_url('?r=votacao/index'));
    }

    public function apuracao(int $itemId): void
    {
        Permissao::exigir('votacao.apuracao');
        $item = SessaoItem::find($itemId);
        if (!$item) {
            http_response_code(404);
            echo 'Matéria não encontrada.';
            return;
        }

        View::render('votacao/apuracao', [
            'item' => $item,
            'resumo' => SessaoVoto::resumoPorItem($itemId),
            'resultado' => SessaoVoto::resultado($itemId),
            'total' => SessaoVoto::totalPorItem($itemId),
            'votos' => SessaoVoto::allByItem($itemId),
        ]);
    }

    public function abrir(int $sessaoId): void
    {
        Permissao::exigir('votacao.abrir');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        Sessao::updateStatus($sessaoId, 'EM_VOTACAO');
        SessaoEvento::registrar($sessaoId, 'status', 'Sessão aberta para votação');
        header('Location: ' . base_url('?r=votacao/index'));
    }

    public function encerrar(int $sessaoId): void
    {
        Permissao::exigir('votacao.encerrar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $aberta = SessaoVotacaoRodada::findAberta($sessaoId);
        if ($aberta) {
            $validacao = VotacaoPainelEstado::validarEncerramento((int) $aberta['id']);
            if (!$validacao['permitido']) {
                $erro = !empty($validacao['pendentes']) ? 'votos_pendentes' : 'empate_presidente';
                header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . (int) $aberta['id'] . '&votacao_erro=' . $erro));
                exit;
            }
            $encerramento = VotacaoEncerramentoService::encerrar($sessaoId, (int) $aberta['id']);
            if (!$encerramento['permitido']) {
                $erro = !empty($encerramento['pendentes']) ? 'votos_pendentes' : 'empate_presidente';
                header('Location: ' . base_url('?r=votacao/painel/' . $sessaoId . '&rodada=' . (int) $aberta['id'] . '&votacao_erro=' . $erro));
                exit;
            }
        }

        Sessao::updateStatus($sessaoId, 'ENCERRADA');
        SessaoEvento::registrar($sessaoId, 'status', 'Sessão encerrada');
        header('Location: ' . base_url('?r=votacao/index'));
    }
}
