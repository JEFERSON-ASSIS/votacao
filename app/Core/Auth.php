<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\Permissao;
use App\Helpers\UserPerfil;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /** Requisição feita em segundo plano pelo JavaScript (fetch JSON ou canal ao vivo). */
    private static function requisicaoAoVivo(): bool
    {
        return requisicao_ao_vivo();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (self::requisicaoAoVivo()) {
                // Chamadas em segundo plano (JSON/SSE) não podem receber a página de login:
                // o aparelho mostraria "Reconectando…" para sempre.
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'sessao_expirada' => true, 'mensagem' => 'Sua sessão expirou. Entre novamente.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            redirect_route('auth/login');
            exit;
        }
    }

    public static function requireRole(array|string $roles): void
    {
        self::requireLogin();
        if (!UserPerfil::matches($_SESSION['user']['role'] ?? '', $roles)) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $role = UserPerfil::normalize((string) $user['role']);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nome' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'role' => $role,
        ];
        Permissao::carregarSessao((int) $user['id'], $role);
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['permissoes'], $_SESSION['permissoes_personalizado']);
        session_regenerate_id(true);
    }
}
