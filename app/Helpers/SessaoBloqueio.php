<?php
declare(strict_types=1);

namespace App\Helpers;

final class SessaoBloqueio
{
    public static function isLocked(?array $sessao): bool
    {
        if (!$sessao) {
            return false;
        }

        if (strtoupper((string) ($sessao['status'] ?? '')) === 'ENCERRADA') {
            return true;
        }

        return trim((string) ($sessao['publicada_em'] ?? '')) !== '';
    }

    public static function motivo(?array $sessao): string
    {
        if (!$sessao) {
            return '';
        }

        if (strtoupper((string) ($sessao['status'] ?? '')) === 'ENCERRADA') {
            return 'Sessão encerrada — não é possível alterar pauta, roteiro ou dados da sessão.';
        }

        if (trim((string) ($sessao['publicada_em'] ?? '')) !== '') {
            return 'Sessão publicada — pauta e roteiro estão bloqueados para edição.';
        }

        return '';
    }

    /** Roteiro gerado e todos os passos concluídos na condução. */
    public static function podePublicar(?array $sessao, array $roteiroStats): bool
    {
        if (!$sessao || self::isLocked($sessao)) {
            return false;
        }

        $total = (int) ($roteiroStats['total'] ?? 0);
        $concluidos = (int) ($roteiroStats['concluidos'] ?? 0);

        return $total > 0 && $concluidos >= $total;
    }

    public static function abortSeBloqueada(?array $sessao, string $redirectUrl): void
    {
        if (!self::isLocked($sessao)) {
            return;
        }

        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if (!str_contains($redirectUrl, 'sessao_bloqueada')) {
            $redirectUrl .= $sep . 'sessao_bloqueada=1';
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    /** Sessão publicada (pauta/roteiro/ata fechados). Encerrada ainda pode editar a ata. */
    public static function isPublicada(?array $sessao): bool
    {
        if (!$sessao) {
            return false;
        }

        return trim((string) ($sessao['publicada_em'] ?? '')) !== '';
    }

    public static function abortSePublicada(?array $sessao, string $redirectUrl): void
    {
        if (!self::isPublicada($sessao)) {
            return;
        }

        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if (!str_contains($redirectUrl, 'sessao_bloqueada')) {
            $redirectUrl .= $sep . 'sessao_bloqueada=1';
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}
