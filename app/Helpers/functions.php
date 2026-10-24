<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Rótulo legível do status da sessão (ex.: EM_VOTACAO → Em Votação). */
function sessao_status_label(?string $status): string
{
    $key = strtoupper(trim((string) $status));
    $labels = [
        'RASCUNHO' => 'Rascunho',
        'CRIADO' => 'Criada',
        'ABERTA' => 'Em andamento',
        'EM_VOTACAO' => 'Em Votação',
        'ENCERRADA' => 'Encerrada',
    ];
    if (isset($labels[$key])) {
        return $labels[$key];
    }
    if ($key === '') {
        return '';
    }

    return ucwords(strtolower(str_replace('_', ' ', $key)));
}

/** Nome curto da sessão: "27ª Sessão Ordinária". */
function sessao_nome_curto(array $sessao): string
{
    $tipos = [
        'ORDINARIA' => 'Ordinária',
        'EXTRAORDINARIA' => 'Extraordinária',
        'SOLENE' => 'Solene',
        'ESPECIAL' => 'Especial',
    ];
    $tipo = strtoupper(trim((string) ($sessao['tipo'] ?? '')));
    $rotulo = $tipos[$tipo] ?? ($tipo !== '' ? ucfirst(mb_strtolower($tipo)) : '');
    $numero = (int) ($sessao['numero'] ?? 0);

    $nome = trim('Sessão ' . $rotulo);
    return $numero > 0 ? $numero . 'ª ' . $nome : $nome;
}

/** Data e hora da sessão para avisos: "Segunda, 26/10 às 09:00". */
function sessao_quando(array $sessao): string
{
    $data = (string) ($sessao['data_sessao'] ?? '');
    $ts = $data !== '' ? strtotime($data) : false;
    if ($ts === false) {
        return '';
    }

    $dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $texto = $dias[(int) date('w', $ts)] . ', ' . date('d/m', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        $texto = 'Hoje';
    }

    $hora = trim((string) ($sessao['hora_inicio'] ?? ''));
    return $hora !== '' ? $texto . ' às ' . $hora : $texto;
}

/** Classe CSS do chip conforme o status interno. */
function sessao_status_chip_class(?string $status): string
{
    return match (strtoupper(trim((string) $status))) {
        'EM_VOTACAO' => 'vote',
        'ENCERRADA' => 'closed',
        'ABERTA' => 'open',
        'CRIADO' => 'created',
        'RASCUNHO' => 'draft',
        default => 'open',
    };
}

function sessao_esta_bloqueada(?array $sessao): bool
{
    return \App\Helpers\SessaoBloqueio::isLocked($sessao);
}

function sessao_pode_publicar(?array $sessao, array $roteiroStats): bool
{
    return \App\Helpers\SessaoBloqueio::podePublicar($sessao, $roteiroStats);
}

function sessao_bloqueio_motivo(?array $sessao): string
{
    return \App\Helpers\SessaoBloqueio::motivo($sessao);
}

function sessao_pode_conduzir(?array $sessao): bool
{
    return $sessao !== null && $sessao !== [] && !sessao_esta_bloqueada($sessao);
}

function pauta_resumo(?array $item, int $maxLen = 160): string
{
    if (!$item) {
        return '';
    }
    $texto = trim((string) ($item['ementa'] ?? ''));
    if ($texto === '') {
        $texto = trim((string) ($item['tipo_materia'] ?? ''));
    }
    if ($texto === '') {
        return '';
    }

    return mb_strlen($texto) > $maxLen ? mb_strimwidth($texto, 0, $maxLen, '…') : $texto;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals((string) $_SESSION['_csrf'], $token);
}

function current_user_role(): string
{
    return strtoupper((string) ($_SESSION['user']['role'] ?? 'GUEST'));
}

function user_has_role(array|string $roles): bool
{
    return \App\Helpers\UserPerfil::matches($_SESSION['user']['role'] ?? '', $roles);
}

function user_can(string $chave): bool
{
    return \App\Helpers\Permissao::usuarioAtualPode($chave);
}

function user_perfil_label(?string $role): string
{
    return \App\Helpers\UserPerfil::label($role);
}

/** Rótulo do voto no painel (SIM → Sim, NAO → Não, etc.). */
/** Normaliza entrada de voto (formulário/AJAX) para SIM, NAO ou ABSTENCAO. */
function votacao_normalizar_voto(string $voto): ?string
{
    $v = strtoupper(trim($voto));
    $v = str_replace([' ', '-'], '', $v);
    $v = strtr($v, [
        'Ã' => 'A',
        'Á' => 'A',
        'É' => 'E',
        'Ç' => 'C',
    ]);

    return match ($v) {
        'SIM' => 'SIM',
        'NAO', 'NO' => 'NAO',
        'ABSTENCAO', 'ABSTENÇÃO' => 'ABSTENCAO',
        default => null,
    };
}

function votacao_rotulo_voto(string $voto): string
{
    return match (strtoupper(trim($voto))) {
        'SIM' => 'Sim',
        'NAO' => 'Não',
        'ABSTENCAO' => 'Abstenção',
        'AUSENTE' => 'Ausente',
        'IMPEDIDO' => 'Impedido',
        default => $voto,
    };
}

function votacao_classe_voto(string $voto): string
{
    return match (strtoupper(trim($voto))) {
        'SIM' => 'voto-sim',
        'NAO' => 'voto-nao',
        'ABSTENCAO' => 'voto-abst',
        default => 'voto-outro',
    };
}

/**
 * Dados de exibição de um vereador da votação (painel e telão).
 * Impedido ou ausente aparece com a etiqueta da situação no lugar do voto.
 *
 * @param array<string, mixed> $eleitor linha de RodadaEleitor::eleitores (ou usuário)
 * @return array{voto: ?string, voto_label: string, voto_classe: string, aguardando: bool, situacao: string}
 */
function votacao_exibicao_eleitor(array $eleitor, string $voto): array
{
    $situacao = strtoupper(trim((string) ($eleitor['situacao'] ?? 'PENDENTE'))) ?: 'PENDENTE';
    if ($voto === '' && in_array($situacao, ['IMPEDIDO', 'AUSENTE'], true)) {
        return [
            'voto' => $situacao,
            'voto_label' => votacao_rotulo_voto($situacao),
            'voto_classe' => 'voto-situacao',
            'aguardando' => false,
            'situacao' => $situacao,
        ];
    }

    return [
        'voto' => $voto !== '' ? $voto : null,
        'voto_label' => $voto !== '' ? votacao_rotulo_voto($voto) : '',
        'voto_classe' => $voto !== '' ? votacao_classe_voto($voto) : '',
        'aguardando' => $voto === '',
        'situacao' => $situacao,
    ];
}

/**
 * Assinatura do estado enviado pelos canais ao vivo: só muda quando algo
 * muda de verdade. Ignora o horário (ts) e o tempo restante do cronômetro,
 * que mudam a cada segundo; o aparelho calcula o tempo pelo fim_ts.
 *
 * @param array<string, mixed> $estado
 * @param list<string> $ignorarTopo chaves extras ignoradas no primeiro nível
 */
function estado_hash(array $estado, array $ignorarTopo = []): string
{
    foreach (array_merge(['ts'], $ignorarTopo) as $chave) {
        unset($estado[$chave]);
    }
    $limpar = static function (array $dados) use (&$limpar): array {
        unset($dados['restante_seg'], $dados['esgotado']);
        foreach ($dados as $k => $v) {
            if (is_array($v)) {
                $dados[$k] = $limpar($v);
            }
        }

        return $dados;
    };

    return md5((string) json_encode($limpar($estado), JSON_UNESCAPED_UNICODE));
}

/** Requisição feita em segundo plano pelo JavaScript (fetch JSON ou canal ao vivo). */
function requisicao_ao_vivo(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return str_contains($accept, 'application/json')
        || str_contains($accept, 'text/event-stream')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

/** Responde um erro: página do sistema para o navegador, JSON para chamadas em segundo plano. */
function responder_erro(int $codigo, string $titulo, string $mensagem): void
{
    http_response_code($codigo);
    if (requisicao_ao_vivo()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => $titulo, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
        return;
    }
    \App\Core\View::render('erro', ['codigo' => $codigo, 'titulo' => $titulo, 'mensagem' => $mensagem]);
}

/** Resultado da votação para exibição (REJEITADO → Reprovado). */
function votacao_rotulo_resultado(?string $resultado): string
{
    return match (strtoupper(trim((string) $resultado))) {
        'APROVADO' => 'Aprovado',
        'REJEITADO' => 'Reprovado',
        'EMPATADO' => 'Empatado',
        default => $resultado !== '' && $resultado !== null ? (string) $resultado : '—',
    };
}

/** Status da rodada/votação para exibição. */
function votacao_rotulo_status(?string $status): string
{
    return match (strtoupper(trim((string) $status))) {
        'ABERTA' => 'Aberta',
        'ENCERRADA' => 'Encerrada',
        'CANCELADA' => 'Cancelada',
        default => $status !== '' && $status !== null ? (string) $status : '—',
    };
}

function user_iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    if ($partes === []) {
        return '?';
    }
    if (count($partes) === 1) {
        return mb_strtoupper(mb_substr($partes[0], 0, 2));
    }

    return mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[count($partes) - 1], 0, 1));
}

function user_foto_url(?array $user): ?string
{
    $foto = trim((string) ($user['foto'] ?? ''));
    if ($foto === '') {
        return asset_url('assets/img/default-user.svg');
    }
    if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://')) {
        return $foto;
    }
    $relativo = str_starts_with($foto, 'uploads/') ? $foto : 'uploads/vereadores/' . ltrim($foto, '/');
    // Arquivo apagado/não copiado para o servidor: avatar padrão em vez de imagem quebrada.
    if (!is_file(dirname(__DIR__, 2) . '/public/' . $relativo)) {
        return asset_url('assets/img/default-user.svg');
    }

    return asset_url($relativo);
}

/** Extrai URL http(s) de texto ou link. */
function materia_extrair_url(?string $texto): ?string
{
    $t = trim((string) $texto);
    if ($t === '') {
        return null;
    }
    if (filter_var($t, FILTER_VALIDATE_URL)) {
        return $t;
    }
    if (preg_match('#\bhttps?://[^\s<>"\']+#i', $t, $m)) {
        return $m[0];
    }

    return null;
}

function materia_extensao_arquivo(?string $url): string
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);

    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

/** pdf | image | office | link */
function materia_tipo_documento(string $ext): string
{
    return match ($ext) {
        'pdf' => 'pdf',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp' => 'image',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods' => 'office',
        default => 'link',
    };
}

function materia_office_embed_url(string $url): string
{
    return 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($url);
}

function materia_nome_arquivo(?string $url): string
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $base = basename($path);

    return $base !== '' ? $base : 'documento';
}

function materia_campo_preenchido(mixed $valor): bool
{
    if ($valor === null) {
        return false;
    }
    if (is_bool($valor)) {
        return true;
    }

    return trim((string) $valor) !== '';
}

/** Linhas de seção repetível com pelo menos um campo útil preenchido. */
function materia_secao_tem_dados(array $rows): bool
{
    return materia_filtrar_secao($rows) !== [];
}

/** Remove linhas vazias das tabelas de seção da pauta. */
function materia_filtrar_secao(array $rows): array
{
    $ignorar = ['id', 'item_id', 'ordem'];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($row as $chave => $valor) {
            if (in_array($chave, $ignorar, true)) {
                continue;
            }
            if (materia_campo_preenchido($valor)) {
                $out[] = $row;
                break;
            }
        }
    }

    return $out;
}

function materia_rotulo_tipo_sapl(array $item): string
{
    $id = (int) (\App\Helpers\MateriaSaplFields::val($item, 'tipo_sapl') ?: 0);
    if ($id > 0 && isset(\App\Helpers\MateriaSaplFields::TIPO_SAPL[$id])) {
        return \App\Helpers\MateriaSaplFields::TIPO_SAPL[$id] . ' (' . $id . ')';
    }

    return \App\Helpers\MateriaSaplFields::val($item, 'tipo_materia');
}

function materia_rotulo_regime(array $item): string
{
    $label = trim((string) ($item['regime_tramitacao'] ?? ''));
    if ($label !== '') {
        return $label;
    }
    $id = (int) (\App\Helpers\MateriaSaplFields::val($item, 'regime_tramitacao_id') ?: 0);
    if ($id > 0 && isset(\App\Helpers\MateriaSaplFields::REGIME_SAPL[$id])) {
        return \App\Helpers\MateriaSaplFields::REGIME_SAPL[$id] . ' (' . $id . ')';
    }

    return '';
}

function materia_rotulo_apresentacao(array $item): string
{
    $code = \App\Helpers\MateriaSaplFields::val($item, 'tipo_apresentacao');
    if ($code === '') {
        return '';
    }
    $map = \App\Helpers\MateriaSaplFields::APRESENTACAO_SAPL;

    return isset($map[$code]) ? $code . ' — ' . $map[$code] : $code;
}

function materia_rotulo_sim_nao(array $item, string $campo): string
{
    return \App\Helpers\MateriaSaplFields::checked($item, $campo) ? 'Sim' : 'Não';
}

function materia_formatar_data(string $data): string
{
    $data = trim($data);
    if ($data === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    return $data;
}

/** Monta URL absoluta a partir de caminho SAPL (/materia/417) usando host de referência. */
function materia_sapl_url_absoluta(string $path, ?string $referenciaUrl = null): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    if ($referenciaUrl !== null && preg_match('#^(https?://[^/]+)#i', $referenciaUrl, $m)) {
        return $m[1] . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    return $path;
}

function materia_extensao_rotulo(string $url): string
{
    $ext = materia_extensao_arquivo($url);

    return match ($ext) {
        'pdf' => 'PDF',
        'doc', 'docx' => 'Word',
        'xls', 'xlsx' => 'Excel',
        'png', 'jpg', 'jpeg', 'gif', 'webp' => 'Imagem',
        default => strtoupper($ext !== '' ? $ext : 'Arquivo'),
    };
}

/** Opções de tempo para fala / votação (segundos => rótulo). */
function sessao_tempo_opcoes(): array
{
    return [
        0 => 'Sem limite',
        120 => '2 min',
        300 => '5 min',
        600 => '10 min',
        900 => '15 min',
    ];
}

function sessao_tempo_normalizar(mixed $segundos): int
{
    $seg = (int) $segundos;

    return array_key_exists($seg, sessao_tempo_opcoes()) ? $seg : 0;
}

/**
 * Payload de temporizador a partir do início e da duração.
 *
 * @return array{segundos:int,fim_ts:int,restante_seg:int,esgotado:bool,label:string}|null
 */
function sessao_tempo_payload(mixed $segundos, mixed $inicioEm): ?array
{
    $seg = sessao_tempo_normalizar($segundos);
    if ($seg <= 0) {
        return null;
    }
    $inicioRaw = trim((string) $inicioEm);
    if ($inicioRaw === '') {
        return null;
    }
    $inicioTs = ctype_digit($inicioRaw)
        ? (int) $inicioRaw
        : strtotime($inicioRaw);
    if ($inicioTs === false || $inicioTs <= 0) {
        return null;
    }
    $agora = time();
    // MySQL TIMESTAMP vs timezone do PHP: início "no futuro" = relógio dessincronizado.
    if ($inicioTs > $agora + 15) {
        $inicioTs = $agora;
    }
    $fimTs = $inicioTs + $seg;
    $restante = max(0, $fimTs - $agora);

    return [
        'segundos' => $seg,
        'fim_ts' => $fimTs,
        'restante_seg' => $restante,
        'esgotado' => $restante <= 0,
        'label' => (string) (sessao_tempo_opcoes()[$seg] ?? ($seg . 's')),
    ];
}

function sessao_tempo_formatar(int $segundosRestantes): string
{
    $segundosRestantes = max(0, $segundosRestantes);
    $m = intdiv($segundosRestantes, 60);
    $s = $segundosRestantes % 60;

    return sprintf('%d:%02d', $m, $s);
}
