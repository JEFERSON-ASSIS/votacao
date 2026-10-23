<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\Permissao;
use App\Helpers\PermissaoCatalog;

final class Router
{
    public static function dispatch(): void
    {
        $route = trim((string) ($_GET['r'] ?? ''), '/');
        if ($route === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $route = trim((string) ($_POST['r'] ?? ''), '/');
        }
        if ($route === '') {
            $route = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
        }
        if ($route === '') {
            $route = trim((string) ($_SERVER['REDIRECT_URL'] ?? ''), '/');
        }
        // As checagens de permissão por sessão leem a rota de $_GET['r']:
        // a rota vinda de PATH_INFO/REDIRECT_URL precisa estar lá também.
        $_GET['r'] = $route;
        if ($route === '') {
            $parts = Auth::check() ? ['dashboard', 'index'] : ['auth', 'login'];
        } else {
            $parts = explode('/', $route);
        }

        if (($parts[0] ?? '') === 'admin') {
            $parts = ['dashboard', 'index'];
        }

        $controller = ucfirst(strtolower($parts[0] ?? 'home'));
        $action = $parts[1] ?? 'index';

        $class = 'App\\Controllers\\' . $controller . 'Controller';
        if (!class_exists($class)) {
            responder_erro(404, 'Página não encontrada', 'O endereço acessado não existe ou foi alterado.');
            return;
        }

        $instance = new $class();
        if (!method_exists($instance, $action)) {
            responder_erro(404, 'Página não encontrada', 'O endereço acessado não existe ou foi alterado.');
            return;
        }

        $chaveRota = PermissaoCatalog::chaveRota($controller, $action);
        if (!in_array($chaveRota, PermissaoCatalog::rotasPublicas(), true)) {
            Auth::requireLogin();
            if (!Permissao::rotaPermitida($chaveRota)) {
                responder_erro(403, 'Acesso negado', 'Seu usuário não tem permissão para abrir esta página.');
                return;
            }
        }

        $params = array_map(static function (string $param): int|string {
            return ctype_digit($param) ? (int) $param : $param;
        }, array_slice($parts, 2));
        $instance->{$action}(...$params);
    }
}
