<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Models\UserPermissao;
use App\Models\SessaoConducao;

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

        $chavesCondutor = [
            'presidencia.conduzir', 'presidencia.avancar', 'presidencia.voltar',
            'presidencia.encerrarSessao', 'presidencia.destacarParlamentar', 'presidencia.limparDestaque',
            'presidencia.transferirConducao', 'presidencia.devolverConducao',
            'votacao.painel', 'votacao.abrirRodada', 'votacao.encerrarRodada', 'votacao.votarRodada',
            'votacao.estadoRodada', 'votacao.streamRodada',
        ];
        if (in_array($chave, $chavesCondutor, true)) {
            $sessaoId = SessaoConducao::sessaoIdDaRota($chave);
            if ($sessaoId > 0 && SessaoConducao::estado($sessaoId) !== null) {
                // Titular fora da condução entra em "conduzir" e é redirecionado para a visão parlamentar.
                if ($chave === 'presidencia.conduzir' && SessaoConducao::titularId($sessaoId) === $userId) {
                    return true;
                }
                return SessaoConducao::usuarioPodeConduzir($sessaoId, $userId);
            }
        }

        // Retomar presidência: só o titular. Consultar "sou condutor?": qualquer usuário logado
        // (o ex-condutor precisa da resposta para voltar à visão parlamentar).
        if (in_array($chave, ['presidencia.retomarConducao', 'presidencia.estadoConducao'], true)) {
            $sessaoId = SessaoConducao::sessaoIdDaRota($chave);
            if ($sessaoId <= 0 || SessaoConducao::estado($sessaoId) === null) {
                return false;
            }
            if ($chave === 'presidencia.retomarConducao') {
                return SessaoConducao::titularId($sessaoId) === $userId;
            }
            return $userId > 0;
        }

        $chavesParlamentarSessao = [
            'parlamentar.sessao', 'parlamentar.estado', 'parlamentar.stream',
            'parlamentar.materia', 'parlamentar.votar',
        ];
        if (in_array($chave, $chavesParlamentarSessao, true)) {
            $sessaoId = $chave === 'parlamentar.votar'
                ? (int) ($_POST['sessao_id'] ?? 0)
                : SessaoConducao::sessaoIdDaRota($chave);
            if ($sessaoId > 0 && SessaoConducao::estado($sessaoId) !== null
                && SessaoConducao::usuarioPodeAcompanharComoParlamentar($sessaoId, $userId)) {
                return true;
            }
            // Condutor/titular precisam do estado ao vivo para trocar de tela quando a presidência muda.
            if ($sessaoId > 0 && in_array($chave, ['parlamentar.sessao', 'parlamentar.estado', 'parlamentar.stream'], true)
                && SessaoConducao::estado($sessaoId) !== null
                && (SessaoConducao::condutorId($sessaoId) === $userId || SessaoConducao::titularId($sessaoId) === $userId)) {
                return true;
            }
        }

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

        if ($chaveRota === 'roteiro.anexoSalvar' && self::usuarioAtualPode('roteiro.salvar')) {
            return true;
        }
        if ($chaveRota === 'roteiro.anexoExcluir' && self::usuarioAtualPode('roteiro.excluir')) {
            return true;
        }

        // Tela dedicada de ata herda a permissão de editar sessão.
        if (in_array($chaveRota, ['sessao.ata', 'sessao.salvarAta'], true)
            && self::usuarioAtualPode('sessao.form')) {
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
