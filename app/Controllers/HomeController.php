<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home', [
            'appName' => config('app_name', 'Plenario'),
        ]);
    }
}

