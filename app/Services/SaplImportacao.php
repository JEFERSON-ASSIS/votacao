<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Helpers\Auditoria;
use App\Models\SessaoEvento;
use App\Models\SessaoRoteiro;
use App\Models\SessaoRoteiroAnexo;
use PDO;

/**
 * Importa uma sessão do SAPL: cria a sessão, a pauta (matérias com autores)
 * e o roteiro do presidente. "Atualizar do SAPL" traz o que mudou lá sem
 * apagar os ajustes feitos no roteiro. Somente leitura do SAPL: os
 * resultados das votações ficam neste sistema.
 */
final class SaplImportacao
{
    /** Busca no SAPL e importa. @return int id da sessão criada */
    public static function importar(int $saplSessaoId, int $usuarioId): int
    {
        $sessao = self::sessaoComTipo($saplSessaoId);

        return self::criarSessao($sessao, SaplCliente::materiasDaSessao($saplSessaoId, (string) ($sessao['data_inicio'] ?? '')), $usuarioId);
    }

    /** Busca no SAPL e atualiza a sessão já importada. @return array{novas: int, atualizadas: int, retiradas: list<string>} */
    public static function atualizar(int $sessaoId, int $usuarioId): array
    {
        $saplId = (int) (DB::conn()->query('SELECT sapl_sessao_id FROM sessoes WHERE id = ' . $sessaoId)->fetchColumn() ?: 0);
        if ($saplId <= 0) {
            throw new \DomainException('Esta sessão não foi importada do SAPL.');
        }

        $sessao = self::sessaoComTipo($saplId);

        return self::atualizarSessao($sessaoId, $sessao, SaplCliente::materiasDaSessao($saplId, (string) ($sessao['data_inicio'] ?? '')), $usuarioId);
    }

    public static function sessaoImportada(int $saplSessaoId): ?int
    {
        $stmt = DB::conn()->prepare('SELECT id FROM sessoes WHERE sapl_sessao_id = :sapl LIMIT 1');
        $stmt->execute(['sapl' => $saplSessaoId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $sessaoSapl sessão no formato da API (+ tipo_descricao)
     * @param list<array<string, mixed>> $materias formato de SaplCliente::materiasDaSessao
     */
    public static function criarSessao(array $sessaoSapl, array $materias, int $usuarioId): int
    {
        $saplId = (int) ($sessaoSapl['id'] ?? 0);
        $existente = self::sessaoImportada($saplId);
        if ($existente !== null) {
            throw new \DomainException('Esta sessão do SAPL já foi importada.');
        }

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            $data = (string) ($sessaoSapl['data_inicio'] ?? '');
            $pdo->prepare(
                "INSERT INTO sessoes (titulo, tipo, numero, ano, status, data_sessao, hora_inicio, sapl_sessao_id, sapl_atualizado_em)
                 VALUES (:titulo, :tipo, :numero, :ano, 'CRIADO', :data, :hora, :sapl, CURRENT_TIMESTAMP)"
            )->execute([
                'titulo' => mb_substr((string) ($sessaoSapl['__str__'] ?? 'Sessão importada do SAPL'), 0, 200),
                'tipo' => self::tipoSessao((string) ($sessaoSapl['tipo_descricao'] ?? '')),
                'numero' => (int) ($sessaoSapl['numero'] ?? 1),
                'ano' => $data !== '' ? (int) substr($data, 0, 4) : (int) date('Y'),
                'data' => $data !== '' ? $data : null,
                'hora' => ($sessaoSapl['hora_inicio'] ?? '') !== '' ? substr((string) $sessaoSapl['hora_inicio'], 0, 5) : null,
                'sapl' => $saplId,
            ]);
            $sessaoId = (int) $pdo->lastInsertId();

            $ordem = 1;
            $itensCriados = [];
            foreach ($materias as $m) {
                $itensCriados[self::inserirMateria($sessaoId, $m, $ordem++)] = $m;
            }

            RoteiroGerador::gerar($sessaoId, true);
            foreach ($itensCriados as $itemId => $m) {
                self::anexarTextoProjeto($sessaoId, $itemId, $m, $usuarioId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Auditoria::log('importar_sapl', 'sessao', $sessaoId, 'Sessão importada do SAPL (#' . $saplId . ') com ' . count($materias) . ' matéria(s)');
        SessaoEvento::registrar($sessaoId, 'sapl', 'Sessão importada do SAPL com ' . count($materias) . ' matéria(s)');

        return $sessaoId;
    }

    /**
     * @param array<string, mixed> $sessaoSapl
     * @param list<array<string, mixed>> $materias
     * @return array{novas: int, atualizadas: int, retiradas: list<string>}
     */
    public static function atualizarSessao(int $sessaoId, array $sessaoSapl, array $materias, int $usuarioId): array
    {
        $pdo = DB::conn();
        $status = strtoupper((string) $pdo->query('SELECT status FROM sessoes WHERE id = ' . $sessaoId)->fetchColumn());
        if (!in_array($status, ['RASCUNHO', 'CRIADO'], true)) {
            throw new \DomainException('A sessão já foi iniciada; não é mais atualizada pelo SAPL.');
        }

        $existentes = [];
        foreach ($pdo->query('SELECT id, sapl_id, titulo FROM pauta_itens WHERE sessao_id = ' . $sessaoId)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) $row['sapl_id'] > 0) {
                $existentes[(int) $row['sapl_id']] = $row;
            }
        }

        $relatorio = ['novas' => 0, 'atualizadas' => 0, 'retiradas' => []];
        $pdo->beginTransaction();
        try {
            $data = (string) ($sessaoSapl['data_inicio'] ?? '');
            $pdo->prepare('UPDATE sessoes SET titulo = :titulo, data_sessao = :data, hora_inicio = :hora, sapl_atualizado_em = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([
                    'titulo' => mb_substr((string) ($sessaoSapl['__str__'] ?? ''), 0, 200),
                    'data' => $data !== '' ? $data : null,
                    'hora' => ($sessaoSapl['hora_inicio'] ?? '') !== '' ? substr((string) $sessaoSapl['hora_inicio'], 0, 5) : null,
                    'id' => $sessaoId,
                ]);

            $ordemPauta = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) FROM pauta_itens WHERE sessao_id = ' . $sessaoId)->fetchColumn() + 1;
            $vistos = [];
            foreach ($materias as $m) {
                $saplMateria = (int) $m['sapl_materia_id'];
                $vistos[$saplMateria] = true;
                if (isset($existentes[$saplMateria])) {
                    $pdo->prepare('UPDATE pauta_itens SET titulo = :titulo, ementa = :ementa, autor = :autor, numero_protocolo = :protocolo, sapl_turno = :turno, sapl_tipo_votacao = :tipo_votacao, sapl_autores_parlamentares = :parlamentares WHERE id = :id')
                        ->execute([
                            'titulo' => mb_substr((string) $m['titulo'], 0, 200),
                            'ementa' => (string) $m['ementa'],
                            'autor' => mb_substr(SaplImportacaoPrevia::autorTexto($m['autores']), 0, 180),
                            'protocolo' => (string) $m['protocolo'],
                            'turno' => mb_substr(strtoupper((string) ($m['turno'] ?? '')), 0, 5),
                            'tipo_votacao' => (int) ($m['tipo_votacao'] ?? 0) ?: null,
                            'parlamentares' => mb_substr(SaplImportacaoPrevia::autoresParlamentares($m['autores']), 0, 255),
                            'id' => (int) $existentes[$saplMateria]['id'],
                        ]);
                    // Inclui o link do texto do projeto onde ainda não houver.
                    self::anexarTextoProjeto($sessaoId, (int) $existentes[$saplMateria]['id'], $m, $usuarioId);
                    $relatorio['atualizadas']++;
                    continue;
                }

                $itemId = self::inserirMateria($sessaoId, $m, $ordemPauta++);
                self::inserirPassosAntesDoFim($sessaoId, $itemId);
                self::anexarTextoProjeto($sessaoId, $itemId, $m, $usuarioId);
                $relatorio['novas']++;
            }

            foreach ($existentes as $saplMateria => $row) {
                if (!isset($vistos[$saplMateria])) {
                    $relatorio['retiradas'][] = (string) $row['titulo'];
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $resumo = $relatorio['novas'] . ' nova(s), ' . $relatorio['atualizadas'] . ' atualizada(s), '
            . count($relatorio['retiradas']) . ' retirada(s) no SAPL';
        Auditoria::log('atualizar_sapl', 'sessao', $sessaoId, 'Atualizada do SAPL: ' . $resumo);
        SessaoEvento::registrar($sessaoId, 'sapl', 'Pauta atualizada do SAPL: ' . $resumo);

        return $relatorio;
    }

    /** "Ordinária" -> ORDINARIA, "Extraordinária" -> EXTRAORDINARIA etc. */
    public static function tipoSessao(string $descricao): string
    {
        $t = strtoupper(strtr(trim($descricao), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'õ' => 'o', 'ç' => 'c', 'â' => 'a', 'ê' => 'e', 'ô' => 'o']));
        $t = preg_replace('/[^A-Z]+/', '_', $t) ?? '';

        return trim($t, '_') !== '' ? mb_substr(trim($t, '_'), 0, 50) : 'ORDINARIA';
    }

    /** @return array<string, mixed> */
    private static function sessaoComTipo(int $saplSessaoId): array
    {
        $sessao = SaplCliente::sessao($saplSessaoId);
        $tipos = [];
        foreach (SaplCliente::get('sessao/tiposessaoplenaria/', ['page_size' => 50])['results'] ?? [] as $t) {
            $tipos[(int) $t['id']] = (string) ($t['nome'] ?? $t['descricao'] ?? '');
        }
        $sessao['tipo_descricao'] = $tipos[(int) ($sessao['tipo'] ?? 0)] ?? 'Ordinária';

        return $sessao;
    }

    /** @param array<string, mixed> $m */
    private static function inserirMateria(int $sessaoId, array $m, int $ordem): int
    {
        $pdo = DB::conn();
        $pdo->prepare(
            'INSERT INTO pauta_itens (sessao_id, ordem, tipo_materia, titulo, ementa, numero, ano, sapl_id, autor, numero_protocolo, texto_original, observacao, sapl_turno, sapl_tipo_votacao, sapl_autores_parlamentares)
             VALUES (:sessao, :ordem, :tipo, :titulo, :ementa, :numero, :ano, :sapl, :autor, :protocolo, :texto, :obs, :turno, :tipo_votacao, :parlamentares)'
        )->execute([
            'sessao' => $sessaoId,
            'ordem' => $ordem,
            'tipo' => mb_substr((string) $m['tipo'], 0, 120),
            'titulo' => mb_substr((string) $m['titulo'], 0, 200),
            'ementa' => (string) $m['ementa'],
            'numero' => (int) $m['numero'] ?: null,
            'ano' => (int) $m['ano'] ?: null,
            'sapl' => (int) $m['sapl_materia_id'],
            'autor' => mb_substr(SaplImportacaoPrevia::autorTexto($m['autores']), 0, 180),
            'protocolo' => (string) $m['protocolo'],
            'texto' => mb_substr((string) $m['texto_original'], 0, 255),
            'obs' => trim(((string) ($m['parte'] ?? '')) . (($m['observacao'] ?? '') !== '' ? ' — ' . $m['observacao'] : '')),
            'turno' => mb_substr(strtoupper((string) ($m['turno'] ?? '')), 0, 5),
            'tipo_votacao' => (int) ($m['tipo_votacao'] ?? 0) ?: null,
            'parlamentares' => mb_substr(SaplImportacaoPrevia::autoresParlamentares($m['autores']), 0, 255),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Anexa o link do texto do projeto (PDF no SAPL) a todos os passos da
     * matéria, para os vereadores abrirem no tablet durante leitura,
     * discussão e votação.
     *
     * @param array<string, mixed> $m
     */
    private static function anexarTextoProjeto(int $sessaoId, int $itemId, array $m, int $usuarioId): void
    {
        $url = trim((string) ($m['texto_original'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return;
        }
        // Só passos da matéria que ainda não têm este link (pode rodar de novo sem duplicar).
        $passos = DB::conn()->prepare(
            'SELECT r.id FROM sessao_roteiro r
             WHERE r.sessao_id = :sessao AND r.pauta_item_id = :item
               AND NOT EXISTS (SELECT 1 FROM sessao_roteiro_anexos a WHERE a.roteiro_passo_id = r.id AND a.url = :url)'
        );
        $passos->execute(['sessao' => $sessaoId, 'item' => $itemId, 'url' => mb_substr($url, 0, 2048)]);
        $titulo = mb_substr('Texto do ' . (string) ($m['titulo'] ?? 'projeto') . ' (SAPL)', 0, 180);
        foreach ($passos->fetchAll(PDO::FETCH_COLUMN) as $passoId) {
            SessaoRoteiroAnexo::insert([
                'sessao_id' => $sessaoId,
                'roteiro_passo_id' => (int) $passoId,
                'tipo' => 'URL',
                'titulo' => $titulo,
                'url' => mb_substr($url, 0, 2048),
                'arquivo' => null,
                'nome_original' => null,
                'mime_type' => null,
                'tamanho_bytes' => null,
                'criado_por' => $usuarioId > 0 ? $usuarioId : null,
            ]);
        }
    }

    /** Coloca os passos de uma matéria nova antes da tribuna e do encerramento. */
    private static function inserirPassosAntesDoFim(int $sessaoId, int $itemId): void
    {
        $pdo = DB::conn();
        $item = $pdo->query('SELECT * FROM pauta_itens WHERE id = ' . $itemId)->fetch(PDO::FETCH_ASSOC);
        $inicioFim = $pdo->query(
            "SELECT MIN(ordem) FROM sessao_roteiro WHERE sessao_id = $sessaoId AND tipo_item IN ('TRIBUNA', 'ENCERRAMENTO')"
        )->fetchColumn();
        if ($inicioFim === null || $inicioFim === false) {
            $ordem = SessaoRoteiro::proximaOrdem($sessaoId);
            RoteiroGerador::inserirPassosMateria($sessaoId, $item, $ordem);

            return;
        }

        // Abre espaço: empurra tribuna e encerramento para depois dos passos novos.
        $deslocar = 1000;
        $pdo->exec("UPDATE sessao_roteiro SET ordem = ordem + $deslocar WHERE sessao_id = $sessaoId AND ordem >= " . (int) $inicioFim);
        $ordem = (int) $inicioFim;
        $inseridos = RoteiroGerador::inserirPassosMateria($sessaoId, $item, $ordem);
        $pdo->exec("UPDATE sessao_roteiro SET ordem = ordem - $deslocar + $inseridos WHERE sessao_id = $sessaoId AND ordem >= " . ((int) $inicioFim + $deslocar));
    }
}
