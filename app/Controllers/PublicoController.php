<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Helpers\PublicoSessaoEstado;
use App\Models\Sessao;
use App\Models\SessaoCrud;

final class PublicoController
{
    public function index(): void
    {
        $sessao = self::resolverSessao(null);
        $estado = $sessao ? PublicoSessaoEstado::estadoJson((int) $sessao['id']) : ['ok' => false];

        View::render('publico/sessao', [
            'sessao' => $sessao,
            'estadoInicial' => $estado,
            'aguardandoUrl' => route_url('publico/atual'),
        ]);
    }

    /** JSON: sessão em andamento (para o telão aguardando atualizar sozinho). */
    public function atual(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $sessao = Sessao::atual();
        if (!$sessao) {
            echo json_encode(['ok' => false, 'sessao_id' => 0], JSON_UNESCAPED_UNICODE);
            return;
        }

        $status = strtoupper((string) ($sessao['status'] ?? ''));
        echo json_encode([
            'ok' => true,
            'sessao_id' => (int) $sessao['id'],
            'titulo' => (string) ($sessao['titulo'] ?? ''),
            'numero' => (int) ($sessao['numero'] ?? 0),
            'ano' => (int) ($sessao['ano'] ?? 0),
            'status' => $status,
            'status_label' => sessao_status_label($status),
            'url' => route_url('publico/sessao/' . (int) $sessao['id']),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function sessao(int $sessaoId): void
    {
        $sessao = self::resolverSessao($sessaoId);
        if (!$sessao) {
            http_response_code(404);
            echo 'Sessão não encontrada ou indisponível para visualização pública.';
            return;
        }

        $estado = PublicoSessaoEstado::estadoJson((int) $sessao['id']);
        View::render('publico/sessao', [
            'sessao' => $sessao,
            'estadoInicial' => $estado,
            'aguardandoUrl' => route_url('publico/atual'),
        ]);
    }

    public function estado(int $sessaoId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(PublicoSessaoEstado::estadoJson($sessaoId), JSON_UNESCAPED_UNICODE);
    }

    /** SSE — atualização ao vivo da visão pública (sem login). */
    public function stream(int $sessaoId): void
    {
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

            $estado = PublicoSessaoEstado::estadoJson($sessaoId);
            if (!($estado['ok'] ?? false)) {
                echo "event: error\ndata: {\"ok\":false}\n\n";
                flush();
                break;
            }

            $hash = PublicoSessaoEstado::hashEstado($estado);
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

    /** @return array<string, mixed>|null */
    private static function resolverSessao(?int $sessaoId): ?array
    {
        if ($sessaoId !== null && $sessaoId > 0) {
            $sessao = SessaoCrud::find($sessaoId);
        } else {
            $q = (int) ($_GET['sessao'] ?? 0);
            $sessao = $q > 0 ? SessaoCrud::find($q) : Sessao::atual();
        }

        if (!$sessao) {
            return null;
        }

        $status = strtoupper((string) ($sessao['status'] ?? ''));
        if (in_array($status, ['RASCUNHO', 'CRIADO'], true)) {
            return null;
        }

        return $sessao;
    }
}
