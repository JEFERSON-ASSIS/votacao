<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Helpers\SessaoAtaUpload;
use PDO;

final class SessaoCrud
{
    public static function all(): array
    {
        return DB::conn()->query('SELECT * FROM sessoes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Sessões visíveis ao parlamentar (sem rascunho). */
    public static function listParaParlamentar(): array
    {
        return DB::conn()->query(
            "SELECT * FROM sessoes WHERE status IN ('ABERTA','EM_VOTACAO','ENCERRADA') ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array> */
    public static function listParaVinculo(?int $excludeId = null): array
    {
        $sql = 'SELECT id, titulo, numero, ano, status FROM sessoes';
        $params = [];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' WHERE id != :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' ORDER BY ano DESC, numero DESC, id DESC';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM sessoes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function existeDuplicada(string $tipo, int $numero, int $ano, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM sessoes WHERE tipo = :tipo AND numero = :numero AND ano = :ano';
        $params = ['tipo' => $tipo, 'numero' => $numero, 'ano' => $ano];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $stmt = DB::conn()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public static function proximoNumero(string $tipo, int $ano): int
    {
        $stmt = DB::conn()->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM sessoes WHERE tipo = :tipo AND ano = :ano'
        );
        $stmt->execute(['tipo' => $tipo, 'ano' => $ano]);

        return max(1, (int) $stmt->fetchColumn());
    }

    public static function findAnteriorSugerida(int $sessaoId): ?array
    {
        $atual = self::find($sessaoId);
        if (!$atual) {
            return null;
        }

        $ano = (int) ($atual['ano'] ?? 0);
        $numero = (int) ($atual['numero'] ?? 0);
        $stmt = DB::conn()->prepare(
            'SELECT * FROM sessoes
             WHERE id != :id
               AND tipo = :tipo
               AND (ano < :ano_antes OR (ano = :ano_igual AND numero < :numero))
             ORDER BY ano DESC, numero DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $sessaoId,
            'tipo' => (string) ($atual['tipo'] ?? 'ORDINARIA'),
            'ano_antes' => $ano,
            'ano_igual' => $ano,
            'numero' => $numero,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function resolveSessaoAnteriorId(array $sessao): int
    {
        $vinculo = (int) ($sessao['sessao_anterior_id'] ?? 0);
        if ($vinculo > 0) {
            return $vinculo;
        }
        $sug = self::findAnteriorSugerida((int) ($sessao['id'] ?? 0));
        return $sug ? (int) $sug['id'] : 0;
    }

    /** Texto da ata a exibir na conducao (override ou ata da sessao vinculada). */
    public static function textoAtaParaLeitura(array $sessao): string
    {
        $override = trim((string) ($sessao['ata_sessao_anterior'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $antId = self::resolveSessaoAnteriorId($sessao);
        if ($antId <= 0) {
            return '';
        }

        $anterior = self::find($antId);
        if (!$anterior) {
            return '';
        }

        return trim((string) ($anterior['ata_conteudo'] ?? ''));
    }

    public static function ataAnteriorArquivoPath(array $sessao): string
    {
        $path = trim((string) ($sessao['ata_sessao_anterior_arquivo'] ?? ''));
        if ($path !== '') {
            return $path;
        }

        $antId = self::resolveSessaoAnteriorId($sessao);
        if ($antId <= 0) {
            return '';
        }

        $anterior = self::find($antId);
        return trim((string) ($anterior['ata_conteudo_arquivo'] ?? ''));
    }

    public static function ataAnteriorArquivoUrl(array $sessao): ?string
    {
        return SessaoAtaUpload::publicUrl(self::ataAnteriorArquivoPath($sessao));
    }

    public static function temAtaParaConducao(array $sessao): bool
    {
        return self::textoAtaParaLeitura($sessao) !== '' || self::ataAnteriorArquivoPath($sessao) !== '';
    }

    /** @return array{id:int,titulo:string,numero:int,ano:int,label:string}|null */
    public static function metadadosSessaoAnterior(array $sessao): ?array
    {
        $antId = self::resolveSessaoAnteriorId($sessao);
        if ($antId <= 0) {
            return null;
        }
        $anterior = self::find($antId);
        if (!$anterior) {
            return null;
        }

        return self::formatarResumo($anterior);
    }

    /** @return array{id:int,titulo:string,numero:int,ano:int,label:string} */
    public static function formatarResumo(array $sessao): array
    {
        $numero = (int) ($sessao['numero'] ?? 0);
        $ano = (int) ($sessao['ano'] ?? 0);
        $titulo = trim((string) ($sessao['titulo'] ?? ''));
        $label = '#' . $numero . '/' . $ano;
        if ($titulo !== '') {
            $label .= ' — ' . $titulo;
        }

        return [
            'id' => (int) $sessao['id'],
            'titulo' => $titulo,
            'numero' => $numero,
            'ano' => $ano,
            'label' => $label,
        ];
    }

    public static function isPassoBlocoAta(array $passo): bool
    {
        if (($passo['tipo_item'] ?? '') !== 'SESSAO') {
            return false;
        }
        $titulo = mb_strtolower(trim((string) ($passo['titulo'] ?? '')));
        return $titulo !== '' && str_contains($titulo, 'ata');
    }

    public static function save(array $data, ?int $id = null): int
    {
        $pdo = DB::conn();
        $tipo = $data['tipo'] ?? 'ORDINARIA';
        $sessaoAnteriorId = isset($data['sessao_anterior_id']) && (int) $data['sessao_anterior_id'] > 0
            ? (int) $data['sessao_anterior_id']
            : null;
        $ataSessaoAnterior = trim((string) ($data['ata_sessao_anterior'] ?? ''));
        $ataConteudo = trim((string) ($data['ata_conteudo'] ?? ''));

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE sessoes SET
                    titulo = :titulo,
                    tipo = :tipo,
                    numero = :numero,
                    ano = :ano,
                    status = :status,
                    sessao_anterior_id = :sessao_anterior_id,
                    ata_sessao_anterior = :ata_sessao_anterior,
                    ata_conteudo = :ata_conteudo
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'titulo' => $data['titulo'],
                'tipo' => $tipo,
                'numero' => $data['numero'],
                'ano' => $data['ano'],
                'status' => $data['status'],
                'sessao_anterior_id' => $sessaoAnteriorId,
                'ata_sessao_anterior' => $ataSessaoAnterior !== '' ? $ataSessaoAnterior : null,
                'ata_conteudo' => $ataConteudo !== '' ? $ataConteudo : null,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sessoes
                (titulo, tipo, numero, ano, status, sessao_anterior_id, ata_sessao_anterior, ata_conteudo)
             VALUES
                (:titulo, :tipo, :numero, :ano, :status, :sessao_anterior_id, :ata_sessao_anterior, :ata_conteudo)'
        );
        $stmt->execute([
            'titulo' => $data['titulo'],
            'tipo' => $tipo,
            'numero' => $data['numero'],
            'ano' => $data['ano'],
            'status' => $data['status'],
            'sessao_anterior_id' => $sessaoAnteriorId,
            'ata_sessao_anterior' => $ataSessaoAnterior !== '' ? $ataSessaoAnterior : null,
            'ata_conteudo' => $ataConteudo !== '' ? $ataConteudo : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateAtaFields(
        int $id,
        ?int $sessaoAnteriorId,
        string $ataSessaoAnterior,
        string $ataConteudo,
        ?string $ataSessaoAnteriorArquivo = null,
        ?string $ataConteudoArquivo = null
    ): void {
        $stmt = DB::conn()->prepare(
            'UPDATE sessoes SET
                sessao_anterior_id = :sessao_anterior_id,
                ata_sessao_anterior = :ata_sessao_anterior,
                ata_sessao_anterior_arquivo = :ata_sessao_anterior_arquivo,
                ata_conteudo = :ata_conteudo,
                ata_conteudo_arquivo = :ata_conteudo_arquivo
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'sessao_anterior_id' => $sessaoAnteriorId !== null && $sessaoAnteriorId > 0 ? $sessaoAnteriorId : null,
            'ata_sessao_anterior' => trim($ataSessaoAnterior) !== '' ? trim($ataSessaoAnterior) : null,
            'ata_sessao_anterior_arquivo' => $ataSessaoAnteriorArquivo !== null && $ataSessaoAnteriorArquivo !== ''
                ? $ataSessaoAnteriorArquivo
                : null,
            'ata_conteudo' => trim($ataConteudo) !== '' ? trim($ataConteudo) : null,
            'ata_conteudo_arquivo' => $ataConteudoArquivo !== null && $ataConteudoArquivo !== ''
                ? $ataConteudoArquivo
                : null,
        ]);
    }

    public static function delete(int $id): void
    {
        $pdo = DB::conn();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Outras sessões podem apontar esta como "sessão anterior" da ata.
            $pdo->prepare(
                'UPDATE sessoes SET sessao_anterior_id = NULL WHERE sessao_anterior_id = :id'
            )->execute(['id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM sessoes WHERE id = :id');
            $stmt->execute(['id' => $id]);

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

    /**
     * Volta a sessão para "Criado" apagando só o que a condução gerou
     * (presenças, votações, votos, eventos, condução). Pauta, roteiro,
     * cerimonial e ata ficam como estão. Uso: ensaios/testes.
     */
    public static function resetarAndamento(int $id): void
    {
        $pdo = DB::conn();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Deletes explícitos (sem depender de ON DELETE CASCADE, que o SQLite pode não ter).
            $rodadas = 'SELECT id FROM sessao_votacao_rodadas WHERE sessao_id = :id';
            $sqls = [
                "DELETE FROM sessao_votacao_eleitores WHERE rodada_id IN ($rodadas)",
                'DELETE FROM sessao_votos_desempate WHERE sessao_id = :id',
                'DELETE FROM sessao_votos WHERE sessao_id = :id',
                'DELETE FROM sessao_votacao_rodadas WHERE sessao_id = :id',
                'DELETE FROM sessao_presentes WHERE sessao_id = :id',
                'DELETE FROM sessao_conducao_historico WHERE sessao_id = :id',
                'DELETE FROM sessao_conducao WHERE sessao_id = :id',
                'DELETE FROM sessao_eventos WHERE sessao_id = :id',
                "UPDATE sessao_roteiro SET status_execucao = 'PENDENTE' WHERE sessao_id = :id",
                "UPDATE pauta_itens SET status = 'PENDENTE' WHERE sessao_id = :id",
                "UPDATE sessoes SET status = 'CRIADO', publicada_em = NULL, publicada_por = NULL WHERE id = :id",
            ];
            foreach ($sqls as $sql) {
                $pdo->prepare($sql)->execute(['id' => $id]);
            }

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

    public static function marcarPublicada(int $id, ?int $userId = null): void
    {
        $stmt = DB::conn()->prepare(
            'UPDATE sessoes
             SET publicada_em = COALESCE(publicada_em, NOW()),
                 publicada_por = COALESCE(publicada_por, :uid)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'uid' => $userId !== null && $userId > 0 ? $userId : null,
        ]);
    }
}
