<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\User;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

$user = User::findByEmail('parlamentar@local.test');
if (!$user) {
    $row = \App\Core\DB::conn()->query("SELECT * FROM users WHERE UPPER(role) IN ('PARLAMENTAR','VEREADOR') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $user = $row ?: User::findByEmail('admin@local.test');
}
if (!$user) {
    echo "Sem usuário de teste\n";
    exit(1);
}

Auth::login($user);
$_SESSION['_csrf'] = 'test';
$_POST = [
    'csrf_token' => 'test',
    'ajax' => '1',
    'r' => 'parlamentar/votar',
    'sessao_id' => '11',
    'item_id' => '0',
    'rodada_id' => '13',
    'item_id' => '11',
    'voto' => 'SIM',
];

ob_start();
try {
    \App\Core\Router::dispatch();
} catch (Throwable $e) {
    ob_end_clean();
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
$out = ob_get_clean();
echo $out . "\n";
