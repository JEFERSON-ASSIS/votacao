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
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $instance = new $class();
        if (!method_exists($instance, $action)) {
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $chaveRota = PermissaoCatalog::chaveRota($controller, $action);
        if (!in_array($chaveRota, PermissaoCatalog::rotasPublicas(), true)) {
            Auth::requireLogin();
            if (!Permissao::rotaPermitida($chaveRota)) {
                http_response_code(403);
                echo 'Acesso negado.';
                return;
            }
        }

        $params = array_map(static function (string $param): int|string {
            return ctype_digit($param) ? (int) $param : $param;
        }, array_slice($parts, 2));
        $instance->{$action}(...$params);
    }
}
