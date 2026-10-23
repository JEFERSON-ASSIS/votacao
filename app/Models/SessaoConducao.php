<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\UserPerfil;
use PDO;

final class SessaoConducao
{
    public static function estado(int $sessaoId): ?array
    {
        $stmt = DB::conn()->prepare(
            'SELECT sc.*, titular.nome AS titular_nome, condutor.nome AS condutor_nome
             FROM sessao_conducao sc
             INNER JOIN users titular ON titular.id = sc.titular_user_id
             INNER JOIN users condutor ON condutor.id = sc.condutor_user_id
             WHERE sc.sessao_id = :sessao_id LIMIT 1'
        );
        $stmt->execute(['sessao_id' => $sessaoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function iniciar(int $sessaoId, int $titularUserId): void
    {
        $pdo = DB::conn();
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO sessao_conducao (sessao_id, titular_user_id, condutor_user_id, atualizado_por)
               VALUES (:sessao_id, :titular, :condutor, :autor)
               ON CONFLICT(sessao_id) DO UPDATE SET titular_user_id = excluded.titular_user_id,
                   condutor_user_id = excluded.condutor_user_id, atualizado_por = excluded.atualizado_por,
                   updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO sessao_conducao (sessao_id, titular_user_id, condutor_user_id, atualizado_por)
               VALUES (:sessao_id, :titular, :condutor, :autor)
               ON DUPLICATE KEY UPDATE titular_user_id = VALUES(titular_user_id),
                   condutor_user_id = VALUES(condutor_user_id), atualizado_por = VALUES(atualizado_por),
                   updated_at = CURRENT_TIMESTAMP';
        $pdo->prepare($sql)->execute([
            'sessao_id' => $sessaoId,
            'titular' => $titularUserId,
            'condutor' => $titularUserId,
            'autor' => $titularUserId,
        ]);
    }

    public static function transferir(int $sessaoId, int $novoCondutorId, int $autorId): void
    {
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao || !in_array(strtoupper((string) ($sessao['status'] ?? '')), ['ABERTA', 'EM_VOTACAO'], true)) {
            throw new \RuntimeException('A sessão precisa estar em andamento para transferir a condução.');
        }
        if (SessaoVotacaoRodada::findAberta($sessaoId)) {
            throw new \RuntimeException('Encerre a votação aberta antes de transferir a condução.');
        }
        $estado = self::estado($sessaoId);
        if (!$estado || (int) $estado['condutor_user_id'] !== $autorId) {
            throw new \RuntimeException('Somente o condutor atual pode passar a condução.');
        }
        $alvo = User::find($novoCondutorId);
        if (!$alvo || !UserPerfil::ehParlamentar((string) ($alvo['role'] ?? ''))) {
            throw new \RuntimeException('Escolha um parlamentar válido.');
        }
        if (!in_array($novoCondutorId, SessaoPresenca::idsPresentes($sessaoId), true)) {
            throw new \RuntimeException('A condução só pode ser passada para um parlamentar presente.');
        }
        self::alterar($sessaoId, $novoCondutorId, $autorId, 'TRANSFERENCIA');
    }

    public static function devolver(int $sessaoId, int $autorId): void
    {
        if (SessaoVotacaoRodada::findAberta($sessaoId)) {
            throw new \RuntimeException('Encerre a votação aberta antes de devolver a condução.');
        }
        $estado = self::estado($sessaoId);
        if (!$estado || (int) $estado['condutor_user_id'] !== $autorId) {
            throw new \RuntimeException('Somente o condutor atual pode devolver a condução.');
        }
        self::alterar($sessaoId, (int) $estado['titular_user_id'], $autorId, 'DEVOLUCAO');
    }

    /** Presidente titular reassume a condução quando volta ao plenário. */
    public static function retomar(int $sessaoId, int $autorId): void
    {
        if (SessaoVotacaoRodada::findAberta($sessaoId)) {
            throw new \RuntimeException('Aguarde o encerramento da votação aberta para retomar a presidência.');
        }
        $estado = self::estado($sessaoId);
        if (!$estado || (int) $estado['titular_user_id'] !== $autorId) {
            throw new \RuntimeException('Somente o presidente titular pode retomar a presidência.');
        }
        self::alterar($sessaoId, $autorId, $autorId, 'RETOMADA');
    }

    private static function alterar(int $sessaoId, int $novoCondutorId, int $autorId, string $tipo): void
    {
        $pdo = DB::conn();
        $estado = self::estado($sessaoId);
        if (!$estado) {
            throw new \RuntimeException('A presidência desta sessão ainda não foi definida.');
        }
        $anterior = (int) $estado['condutor_user_id'];
        if ($anterior === $novoCondutorId) {
            return;
        }
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $update = $pdo->prepare(
                'UPDATE sessao_conducao SET condutor_user_id = :condutor, atualizado_por = :autor,
                 updated_at = CURRENT_TIMESTAMP WHERE sessao_id = :sessao_id AND condutor_user_id = :anterior'
            );
            $update->execute(['condutor' => $novoCondutorId, 'autor' => $autorId, 'sessao_id' => $sessaoId, 'anterior' => $anterior]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('A condução foi alterada por outra operação. Atualize a página e tente novamente.');
            }
            $pdo->prepare(
                'INSERT INTO sessao_conducao_historico
                 (sessao_id, tipo, condutor_anterior_id, condutor_novo_id, realizado_por)
                 VALUES (:sessao_id, :tipo, :anterior, :novo, :autor)'
            )->execute(['sessao_id' => $sessaoId, 'tipo' => $tipo, 'anterior' => $anterior, 'novo' => $novoCondutorId, 'autor' => $autorId]);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function setDestaque(int $sessaoId, int $userId, int $tempoSeg = 0): void
    {
        $sessao = SessaoCrud::find($sessaoId);
        if (!$sessao || !in_array(strtoupper((string) ($sessao['status'] ?? '')), ['ABERTA', 'EM_VOTACAO'], true)) {
            throw new \RuntimeException('A sessão precisa estar em andamento para destacar no telão.');
        }
        $estado = self::estado($sessaoId);
        if ($estado === null) {
            throw new \RuntimeException('A presidência desta sessão ainda não foi definida.');
        }
        if (!in_array($userId, SessaoPresenca::idsPresentes($sessaoId), true)) {
            // Marca presença automaticamente ao destacar (ex.: só o presidente estava na lista).
            SessaoPresenca::set($sessaoId, $userId, true);
        }
        $alvo = User::find($userId);
        if (!$alvo || !UserPerfil::ehParlamentar((string) ($alvo['role'] ?? ''))) {
            throw new \RuntimeException('Escolha um parlamentar válido.');
        }

        $tempoSeg = function_exists('sessao_tempo_normalizar') ? sessao_tempo_normalizar($tempoSeg) : 0;
        $agora = date('Y-m-d H:i:s');

        DB::conn()->prepare(
            'UPDATE sessao_conducao
             SET destaque_user_id = :user_id,
                 destaque_em = :destaque_em,
                 destaque_tempo_seg = :tempo_seg,
                 updated_at = CURRENT_TIMESTAMP
             WHERE sessao_id = :sessao_id'
        )->execute([
            'user_id' => $userId,
            'destaque_em' => $agora,
            'tempo_seg' => $tempoSeg > 0 ? $tempoSeg : null,
            'sessao_id' => $sessaoId,
        ]);
    }

    public static function clearDestaque(int $sessaoId): void
    {
        DB::conn()->prepare(
            'UPDATE sessao_conducao
             SET destaque_user_id = NULL,
                 destaque_em = NULL,
                 destaque_tempo_seg = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE sessao_id = :sessao_id'
        )->execute(['sessao_id' => $sessaoId]);
    }

    public static function destaqueUserId(int $sessaoId): int
    {
        return (int) (self::estado($sessaoId)['destaque_user_id'] ?? 0);
    }

    public static function usuarioPodeConduzir(int $sessaoId, int $userId): bool
    {
        $estado = self::estado($sessaoId);
        return $estado !== null && (int) $estado['condutor_user_id'] === $userId;
    }

    public static function condutorId(int $sessaoId): int
    {
        return (int) (self::estado($sessaoId)['condutor_user_id'] ?? 0);
    }

    public static function titularId(int $sessaoId): int
    {
        return (int) (self::estado($sessaoId)['titular_user_id'] ?? 0);
    }

    public static function usuarioPodeAcompanharComoParlamentar(int $sessaoId, int $userId): bool
    {
        return $userId > 0
            && $userId !== self::condutorId($sessaoId)
            && in_array($userId, SessaoPresenca::idsPresentes($sessaoId), true);
    }

    /** @return list<int> */
    public static function eleitoresPresentes(int $sessaoId): array
    {
        $condutorId = self::condutorId($sessaoId);
        return array_values(array_filter(
            SessaoPresenca::idsPresentes($sessaoId),
            static fn (int $id): bool => $id !== $condutorId
        ));
    }

    public static function sessaoIdDaRota(string $chave): int
    {
        $partes = explode('/', trim((string) ($_GET['r'] ?? $_POST['r'] ?? ''), '/'));
        $id = (int) ($partes[2] ?? 0);
        if ($id <= 0) {
            return 0;
        }
        $acaoRota = (string) ($partes[1] ?? '');
        if (in_array($acaoRota, ['estadoRodada', 'streamRodada', 'apuracaoRodada'], true)
            || in_array($chave, ['votacao.estadoRodada', 'votacao.streamRodada', 'votacao.apuracaoRodada'], true)) {
            return (int) (SessaoVotacaoRodada::find($id)['sessao_id'] ?? 0);
        }
        return $id;
    }
}
