<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Helpers\ParlamentarAcompanhar;
use App\Models\SessaoVotacaoRodada;
use App\Models\User;

$sessaoId = (int) ($argv[1] ?? 11);
$user = User::findByEmail('admin@local.test') ?? User::findByEmail('parlamentar@local.test');
if (!$user) {
    $rows = \App\Core\DB::conn()->query("SELECT * FROM users WHERE role = 'PARLAMENTAR' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $user = $rows ?: null;
}
if (!$user) {
    echo "Nenhum usuário parlamentar encontrado.\n";
    exit(1);
}

$rodada = SessaoVotacaoRodada::findAberta($sessaoId);
$estado = ParlamentarAcompanhar::estadoJson($sessaoId, (int) $user['id'], true);

echo "User: {$user['email']} ({$user['role']})\n";
echo "Rodada aberta: " . ($rodada ? json_encode($rodada, JSON_UNESCAPED_UNICODE) : 'null') . "\n";
echo "Estado JSON:\n" . json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
