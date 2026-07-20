<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Models\UserPermissao;

final class Permissao
{
    public static function carregarSessao(int $userId, string $role): void
    {
        $personalizado = UserPermissao::usuarioTemPersonalizado($userId);
        $_SESSION['permissoes_personalizado'] = $personalizado;
        $_SESSION['permissoes'] = $personalizado
            ? UserPermissao::chavesDoUsuario($userId)
            : [];
    }

    public static function usuarioAtualPode(string $chave): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);

        if (!empty($_SESSION['permissoes_personalizado'])) {
            $perms = $_SESSION['permissoes'] ?? [];

            return in_array($chave, $perms, true);
        }

        return self::legadoPermite($role, $chave);
    }

    public static function rotaPermitida(string $chaveRota): bool
    {
        if (in_array($chaveRota, PermissaoCatalog::rotasPublicas(), true)) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        if (!in_array($chaveRota, PermissaoCatalog::todasChaves(), true)) {
            return false;
        }

        if (str_starts_with($chaveRota, 'parlamentar.') && self::usuarioAtualPode('parlamentar.sessao')) {
            return true;
        }

        if (in_array($chaveRota, ['votacao.estadoRodada', 'votacao.streamRodada'], true)
            && self::usuarioAtualPode('votacao.painel')) {
            return true;
        }

        return self::usuarioAtualPode($chaveRota);
    }

    public static function exigir(string $chave): void
    {
        Auth::requireLogin();
        if (!self::usuarioAtualPode($chave)) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }

    /** Perfil padrão quando o usuário não tem permissões personalizadas. */
    public static function legadoPermite(string $role, string $chave): bool
    {
        return in_array($chave, PermissaoCatalog::chavesPadraoPerfil($role), true);
    }

    /** Permissões padrão do perfil parlamentar (sem admin/presidência). */
    public static function legadoParlamentar(string $chave): bool
    {
        return in_array($chave, PermissaoCatalog::chavesParlamentar(), true);
    }
}
