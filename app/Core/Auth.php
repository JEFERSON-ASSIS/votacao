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

    public static function requireLogin(): void
    {
        if (!self::check()) {
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
