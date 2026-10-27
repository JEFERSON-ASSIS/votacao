<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\DB;
use PDO;

/**
 * Limite de tentativas de login erradas.
 * Por e-mail (impede adivinhar a senha de um vereador) e, bem folgado, por
 * endereço de internet: na Câmara todos os notebooks saem pelo mesmo Wi-Fi.
 */
final class LoginLimite
{
    public const JANELA_SEG = 900;       // 15 minutos
    private const MAX_POR_EMAIL = 8;
    private const MAX_POR_IP = 40;

    public static function bloqueado(string $email): bool
    {
        try {
            return self::contar('email:' . self::chaveEmail($email)) >= self::MAX_POR_EMAIL
                || self::contar('ip:' . self::ip()) >= self::MAX_POR_IP;
        } catch (\Throwable) {
            return false; // sem a tabela, o login segue funcionando
        }
    }

    public static function registrarFalha(string $email): void
    {
        try {
            $stmt = DB::conn()->prepare('INSERT INTO login_falhas (chave, criado_em) VALUES (:chave, :agora)');
            $agora = date('Y-m-d H:i:s');
            $stmt->execute(['chave' => 'email:' . self::chaveEmail($email), 'agora' => $agora]);
            $stmt->execute(['chave' => 'ip:' . self::ip(), 'agora' => $agora]);
            // Limpeza ocasional do que já venceu.
            if (random_int(1, 50) === 1) {
                DB::conn()->prepare('DELETE FROM login_falhas WHERE criado_em < :limite')
                    ->execute(['limite' => date('Y-m-d H:i:s', time() - self::JANELA_SEG)]);
            }
        } catch (\Throwable $e) {
            app_log('login: não registrou falha — ' . $e->getMessage());
        }
    }

    public static function limpar(string $email): void
    {
        try {
            DB::conn()->prepare('DELETE FROM login_falhas WHERE chave = :chave')
                ->execute(['chave' => 'email:' . self::chaveEmail($email)]);
        } catch (\Throwable) {
            // sem efeito no login
        }
    }

    private static function contar(string $chave): int
    {
        $stmt = DB::conn()->prepare('SELECT COUNT(*) FROM login_falhas WHERE chave = :chave AND criado_em >= :desde');
        $stmt->execute(['chave' => $chave, 'desde' => date('Y-m-d H:i:s', time() - self::JANELA_SEG)]);

        return (int) $stmt->fetch(PDO::FETCH_COLUMN);
    }

    private static function chaveEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** IP do visitante. Atrás do Traefik, o REMOTE_ADDR é o do proxy; o X-Real-Ip vem dele. */
    private static function ip(): string
    {
        $real = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        $ip = filter_var($real, FILTER_VALIDATE_IP) ? $real : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' ? $ip : 'desconhecido';
    }
}
