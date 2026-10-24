<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Permissao;
use App\Helpers\UserFotoUpload;
use App\Helpers\UserPerfil;
use App\Models\User;

final class UsuarioController
{
    public function index(): void
    {
        $this->requireAdmin();
        View::render('usuarios/index', [
            'users' => User::all(),
        ]);
    }

    public function form(?int $id = null): void
    {
        $this->requireAdmin();
        $user = $id ? User::find($id) : null;
        if ($id && !$user) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        View::render('usuarios/form', [
            'user' => $user,
            'error' => null,
        ]);
    }

    public function save(?int $id = null): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $existing = $id ? User::find($id) : null;
        if ($id && !$existing) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        $dados = User::dadosFromPost($_POST);
        $senha = (string) ($_POST['senha'] ?? '');
        $senhaConfirm = (string) ($_POST['senha_confirm'] ?? '');
        $removerFoto = !empty($_POST['remover_foto']);
        $dataNascInput = trim((string) ($_POST['data_nascimento'] ?? ''));

        $error = null;
        if ($dados['nome'] === '') {
            $error = 'Informe o nome.';
        } elseif ($dados['email'] === '' || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um e-mail válido.';
        } elseif (!User::cpfValido((string) $dados['cpf'])) {
            $error = 'CPF inválido.';
        } elseif (!User::dataNascimentoValida($dados['data_nascimento'], $dataNascInput)) {
            $error = 'Data de nascimento inválida. Use dd/mm/aaaa.';
        } elseif ($dados['homepage'] !== null && !filter_var($dados['homepage'], FILTER_VALIDATE_URL)) {
            $error = 'Homepage inválida. Informe uma URL completa (https://...).';
        } elseif (User::findByEmail($dados['email']) && (int) User::findByEmail($dados['email'])['id'] !== (int) ($existing['id'] ?? 0)) {
            $error = 'Este e-mail já está em uso.';
        } elseif (User::findByCpf((string) $dados['cpf'], $existing ? (int) $existing['id'] : null)) {
            $error = 'Este CPF já está cadastrado.';
        } elseif (!$existing && $senha === '') {
            $error = 'Defina uma senha para o novo usuário.';
        } elseif ($senha !== '' && strlen($senha) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== '' && $senha !== $senhaConfirm) {
            $error = 'As senhas não conferem.';
        } elseif (!array_key_exists($dados['role'], UserPerfil::options())) {
            $error = 'Perfil inválido.';
        }

        if ($error !== null) {
            View::render('usuarios/form', [
                'user' => $this->postAsUser($existing, $dados, $dataNascInput),
                'error' => $error,
            ]);
            return;
        }

        $payload = $dados;
        $payload['foto'] = $existing['foto'] ?? null;

        if ($senha !== '') {
            $payload['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }

        if ($existing) {
            $userId = (int) $existing['id'];
            $payload['foto'] = UserFotoUpload::handle(
                $userId,
                (string) ($existing['foto'] ?? ''),
                $removerFoto
            );
            User::update($userId, $payload);
        } else {
            $payload['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
            $payload['foto'] = null;
            $userId = User::create($payload);
            $fotoPath = UserFotoUpload::handle($userId, null, false);
            if ($fotoPath !== null) {
                $payload['foto'] = $fotoPath;
                User::update($userId, $payload);
            }
        }

        if (array_key_exists('nome_parlamentar', $_POST)) {
            $nomeParlamentar = mb_substr(trim((string) $_POST['nome_parlamentar']), 0, 120);
            \App\Core\DB::conn()->prepare('UPDATE users SET nome_parlamentar = :nome WHERE id = :id')
                ->execute(['nome' => $nomeParlamentar !== '' ? $nomeParlamentar : null, 'id' => $userId]);
        }

        $current = Auth::user();
        if ($current && (int) $current['id'] === $userId) {
            $fresh = User::find($userId);
            if ($fresh) {
                Auth::login($fresh);
            }
        }

        header('Location: ' . base_url('?r=usuario/index'));
        exit;
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Requisição inválida.';
            return;
        }

        $current = Auth::user();
        if ($current && (int) $current['id'] === $id) {
            header('Location: ' . base_url('?r=usuario/index&erro=auto'));
            exit;
        }

        $user = User::find($id);
        if ($user) {
            UserFotoUpload::deleteFile((string) ($user['foto'] ?? ''));
            User::delete($id);
        }

        header('Location: ' . base_url('?r=usuario/index'));
        exit;
    }

    private function requireAdmin(): void
    {
        Permissao::exigir('usuario.index');
    }

    /** @param array<string, mixed> $dados */
    private function postAsUser(?array $existing, array $dados, string $dataNascInput): array
    {
        $user = array_merge($existing ?? ['id' => 0], $dados, [
            'foto' => $existing['foto'] ?? null,
        ]);
        $user['cpf_formatado'] = User::formatarCpf((string) $dados['cpf']);
        $user['telefone_formatado'] = User::formatarTelefone((string) ($dados['telefone'] ?? ''));
        $user['data_nascimento_formatada'] = $dataNascInput !== ''
            ? $dataNascInput
            : User::formatarDataNascimento($dados['data_nascimento']);

        return $user;
    }
}
