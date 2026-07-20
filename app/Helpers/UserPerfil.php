<?php
declare(strict_types=1);

namespace App\Helpers;

/** Perfis de acesso — base para permissionamento futuro. */
final class UserPerfil
{
    public const PRESIDENTE = 'PRESIDENTE';
    public const ADMINISTRADOR = 'ADMINISTRADOR';
    public const PARLAMENTAR = 'PARLAMENTAR';
    public const USUARIO = 'USUARIO';

    /** @return array<string, string> valor => rótulo */
    public static function options(): array
    {
        return [
            self::PRESIDENTE => 'Presidente',
            self::ADMINISTRADOR => 'Administrador',
            self::PARLAMENTAR => 'Parlamentar',
            self::USUARIO => 'Usuário',
        ];
    }

    public static function normalize(?string $role): string
    {
        $key = strtoupper(trim((string) $role));

        return match ($key) {
            'PRESIDENTE' => self::PRESIDENTE,
            'ADMINISTRADOR', 'ADMIN', 'SECRETARIA' => self::ADMINISTRADOR,
            'PARLAMENTAR', 'VEREADOR' => self::PARLAMENTAR,
            'USUARIO', 'USER' => self::USUARIO,
            default => self::USUARIO,
        };
    }

    public static function label(?string $role): string
    {
        $norm = self::normalize($role);

        return self::options()[$norm] ?? $norm;
    }

    public static function chipClass(?string $role): string
    {
        return match (self::normalize($role)) {
            self::PRESIDENTE => 'perfil-presidente',
            self::ADMINISTRADOR => 'perfil-admin',
            self::PARLAMENTAR => 'perfil-parlamentar',
            self::USUARIO => 'perfil-usuario',
            default => 'open',
        };
    }

    /** Verifica se o perfil atual está entre os permitidos (com aliases legados). */
    public static function matches(?string $current, array|string $allowed): bool
    {
        $current = self::normalize($current);
        $allowed = is_array($allowed) ? $allowed : [$allowed];
        foreach ($allowed as $role) {
            if ($current === self::normalize((string) $role)) {
                return true;
            }
        }

        return false;
    }

    /** Quem pode gerenciar cadastro de usuários. */
    public static function podeGerenciarUsuarios(?string $role): bool
    {
        return self::matches($role, self::ADMINISTRADOR);
    }

    /** Quem acessa presidência / condução plenária. */
    public static function podeConduzirSessao(?string $role): bool
    {
        return self::matches($role, [self::PRESIDENTE, self::ADMINISTRADOR]);
    }

    /** Quem opera painel de votação e registro de votos. */
    public static function podeOperarVotacao(?string $role): bool
    {
        return self::matches($role, [self::PRESIDENTE, self::ADMINISTRADOR]);
    }

    /** Quem edita sessão, pauta e roteiro. */
    public static function podeEditarSessao(?string $role): bool
    {
        return self::matches($role, [self::ADMINISTRADOR, self::PRESIDENTE]);
    }

    /** Perfis listados no painel de votação (recebem voto). */
    public static function perfisVotacao(): array
    {
        return [self::PARLAMENTAR];
    }

    public static function ehParlamentar(?string $role): bool
    {
        return self::matches($role, self::PARLAMENTAR);
    }
}
