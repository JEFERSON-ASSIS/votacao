<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\User;

final class AuthController
{
    public function login(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');

            app_log('login: POST recebido email=' . ($email !== '' ? $email : '(vazio)'));

            try {
                if ($email === '') {
                    $error = 'Informe o e-mail.';
                    app_log('login: e-mail vazio');
                } else {
                    app_log('login: buscando usuário no banco...');
                    $user = User::findByEmail($email);

                    if (!$user) {
                        app_log('login: usuário não encontrado para email=' . $email);
                        $error = 'Credenciais inválidas.';
                    } elseif (!password_verify($senha, (string) ($user['senha_hash'] ?? ''))) {
                        app_log('login: senha inválida para user_id=' . (int) ($user['id'] ?? 0));
                        $error = 'Credenciais inválidas.';
                    } else {
                        app_log('login: ok user_id=' . (int) $user['id'] . ' role=' . (string) ($user['role'] ?? ''));
                        Auth::login($user);
                        app_log('login: sessão criada, redirecionando dashboard');
                        redirect_route('dashboard/index');
                    }
                }
            } catch (\Throwable $e) {
                app_log(
                    'login: ERRO ' . $e::class
                    . ' — ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine()
                );
                $error = 'Erro ao entrar: ' . $e->getMessage();
            }
        }

        View::render('auth/login', ['error' => $error]);
    }

    public function logout(): void
    {
        app_log('logout: user_id=' . (int) (($_SESSION['user']['id'] ?? 0)));
        Auth::logout();
        redirect_route('auth/login');
    }
}
