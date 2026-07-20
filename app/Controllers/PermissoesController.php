<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Permissao;
use App\Helpers\PermissaoCatalog;
use App\Models\User;
use App\Models\UserPermissao;

final class PermissoesController
{
    public function index(): void
    {
        Permissao::exigir('permissoes.index');

        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $usuario = $userId > 0 ? User::find($userId) : null;

        $chavesAtivas = [];
        $personalizado = false;
        if ($usuario) {
            $personalizado = UserPermissao::usuarioTemPersonalizado($userId);
            $chavesAtivas = $personalizado
                ? UserPermissao::chavesDoUsuario($userId)
                : $this->chavesLegado((string) ($usuario['role'] ?? ''));
        }

        View::render('permissoes/index', [
            'users' => User::all(),
            'usuario' => $usuario,
            'userId' => $userId,
            'grupos' => PermissaoCatalog::grupos(),
            'chavesAtivas' => array_flip($chavesAtivas),
            'personalizado' => $personalizado,
            'salvo' => ($_GET['salvo'] ?? '') === '1',
        ]);
    }

    public function save(?int $userId = null): void
    {
        Permissao::exigir('permissoes.save');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect_route('permissoes/index');
        }

        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Sessão expirada ou token inválido. Volte à tela de permissões e tente novamente.';
            return;
        }

        $userId = $userId > 0 ? $userId : (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(400);
            echo 'Usuário inválido.';
            return;
        }

        $usuario = User::find($userId);
        if (!$usuario) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        $usarPadrao = !empty($_POST['usar_perfil_padrao']);
        if ($usarPadrao) {
            UserPermissao::limpar($userId);
        } else {
            $chaves = isset($_POST['permissoes']) && is_array($_POST['permissoes'])
                ? array_map('strval', $_POST['permissoes'])
                : [];
            UserPermissao::salvar($userId, $chaves);
        }

        $current = Auth::user();
        if ($current && (int) $current['id'] === $userId) {
            Permissao::carregarSessao($userId, (string) ($usuario['role'] ?? ''));
        }

        if (Permissao::usuarioAtualPode('dashboard.index')) {
            redirect_route('dashboard/index', ['permissoes_salvo' => '1']);
        }

        redirect_route('permissoes/index', ['user_id' => $userId, 'salvo' => '1']);
    }

    /** @return list<string> */
    private function chavesLegado(string $role): array
    {
        $ativas = [];
        foreach (PermissaoCatalog::todasChaves() as $chave) {
            if (Permissao::legadoPermite($role, $chave)) {
                $ativas[] = $chave;
            }
        }

        return $ativas;
    }
}
