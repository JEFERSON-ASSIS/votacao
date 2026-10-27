<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\LoginLimite;
use App\Models\Sessao;
use App\Models\User;

final class AuthController
{
    public function login(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');

            try {
                if ($email === '') {
                    $error = 'Informe o e-mail.';
                    app_log('login: e-mail vazio');
                } elseif (LoginLimite::bloqueado($email)) {
                    app_log('login: bloqueado por excesso de tentativas');
                    $error = 'Muitas tentativas com senha errada. Aguarde 15 minutos ou fale com a secretaria da Câmara.';
                } else {
                    $user = User::findByEmail($email);
                    // Confere a senha mesmo sem usuário, para o tempo de resposta não revelar quais e-mails existem.
                    $hash = (string) ($user['senha_hash'] ?? '$2y$10$fNWhbJ91Gclxpfb.PEbQq.fdpHdO2vYbGCevbDKW1cc8Eu4d223c2');
                    $senhaOk = password_verify($senha, $hash);

                    if (!$user || !$senhaOk) {
                        app_log('login: credenciais inválidas');
                        LoginLimite::registrarFalha($email);
                        $error = 'E-mail ou senha incorretos.';
                    } else {
                        app_log('login: ok user_id=' . (int) $user['id'] . ' role=' . (string) ($user['role'] ?? ''));
                        LoginLimite::limpar($email);
                        Auth::login($user);
                        redirect_route('dashboard/index');
                    }
                }
            } catch (\Throwable $e) {
                app_log(
                    'login: ERRO ' . $e::class
                    . ' — ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine()
                );
                $error = 'Não foi possível entrar agora. Tente novamente em instantes.';
            }
        }

        // Aviso de sessão na tela de login. Se o banco falhar, a tela abre sem o aviso.
        $sessaoAoVivo = null;
        $proximaSessao = null;
        try {
            $sessaoAoVivo = Sessao::atual();
            if (!$sessaoAoVivo) {
                $proximaSessao = Sessao::proxima();
            }
        } catch (\Throwable $e) {
            app_log('login: aviso de sessão indisponível — ' . $e->getMessage());
        }

        View::render('auth/login', [
            'error' => $error,
            'email' => (string) ($_POST['email'] ?? ''),
            'sessaoAoVivo' => $sessaoAoVivo,
            'proximaSessao' => $proximaSessao,
        ]);
    }

    public function logout(): void
    {
        app_log('logout: user_id=' . (int) (($_SESSION['user']['id'] ?? 0)));
        Auth::logout();
        redirect_route('auth/login');
    }
}
