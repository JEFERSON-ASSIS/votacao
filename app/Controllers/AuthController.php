<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
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

            $user = $email !== '' ? User::findByEmail($email) : null;
            if (!$user || !password_verify($senha, (string) $user['senha_hash'])) {
                $error = 'Credenciais invalidas.';
            } else {
                Auth::login($user);
                redirect_route('dashboard/index');
            }
        }

        View::render('auth/login', ['error' => $error]);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect_route('auth/login');
    }
}

