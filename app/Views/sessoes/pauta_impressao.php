<?php declare(strict_types=1); ?>
<?php
$sessao = $sessao ?? [];
$itens = $itens ?? [];
$roteiro = $roteiro ?? [];
$cerimonial = $cerimonial ?? [];
$appName = (string) config('app_name', 'Plenário');
$dataEmissao = date('d/m/Y \à\s H:i');

function pauta_impressao_item_label(array $item): string
{
    $tipo = trim((string) ($item['tipo_materia'] ?? ''));
    $titulo = trim((string) ($item['titulo'] ?? ''));
    if ($titulo === '') {
        return $tipo;
    }
    if ($tipo !== '' && mb_stripos($titulo, $tipo) === 0) {
        return $titulo;
    }

    return $tipo !== '' ? $tipo . ' — ' . $titulo : $titulo;
}

function pauta_impressao_roteiro_label(array $p): string
{
    $titulo = trim((string) ($p['titulo'] ?? ''));
    $materia = trim((string) ($p['pauta_titulo'] ?? ''));
    if ($materia !== '' && mb_strtolower($materia) !== mb_strtolower($titulo)) {
        return $titulo . ' — ' . $materia;
    }

    return $titulo;
}

function pauta_impressao_ementa_resumo(string $texto, int $max = 280): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    if ($texto === '') {
        return '—';
    }
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }

    return mb_strimwidth($texto, 0, $max, '…');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pauta — <?= e((string) ($sessao['titulo'] ?? 'Sessão')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/pauta-impressao.css')) ?>">
</head>
<body>
    <div class="pauta-toolbar">
        <button type="button" onclick="window.print()">Imprimir ou salvar PDF</button>
        <span>Use Ctrl+P → destino “Salvar como PDF” · papel A4</span>
    </div>

    <article class="pauta-doc">
        <header class="pauta-doc-header">
            <div class="pauta-doc-brasao" aria-hidden="true">CM</div>
            <p class="pauta-doc-org">Câmara Municipal · <?= e($appName) ?></p>
            <h1 class="pauta-doc-titulo-doc">Pauta da sessão plenária</h1>
            <p class="pauta-doc-subtitulo"><?= e((string) ($sessao['titulo'] ?? 'Sessão plenária')) ?></p>
        </header>

        <dl class="pauta-doc-meta">
            <div>
                <dt>Sessão</dt>
                <dd><?= (int) ($sessao['numero'] ?? 0) ?>/<?= (int) ($sessao['ano'] ?? 0) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= e(sessao_status_label((string) ($sessao['status'] ?? ''))) ?></dd>
            </div>
            <div>
                <dt>Tipo</dt>
                <dd><?= e((string) ($sessao['tipo'] ?? 'Ordinária')) ?></dd>
            </div>
            <div>
                <dt>Emissão do documento</dt>
                <dd><?= e($dataEmissao) ?></dd>
            </div>
        </dl>

        <?php if ($cerimonial !== []): ?>
            <section class="pauta-doc-sec">
                <h2 class="pauta-doc-sec-titulo"><span class="num">I</span> Cerimonial</h2>
                <table class="pauta-doc-tabela">
                    <thead>
                        <tr>
                            <th class="col-ordem">#</th>
                            <th>Item</th>
                            <th>Tipo</th>
                            <th>Convidado / observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cerimonial as $i => $c): ?>
                            <tr>
                                <td class="col-ordem"><?= $i + 1 ?></td>
                                <td class="col-titulo"><?= e((string) ($c['titulo'] ?? '')) ?></td>
                                <td><?= e(\App\Models\SessaoCerimonial::TIPOS[$c['tipo'] ?? ''] ?? (string) ($c['tipo'] ?? '')) ?></td>
                                <td class="col-ementa">
                                    <?php
                                    $conv = trim((string) ($c['nome_convidado'] ?? ''));
                                    $cargo = trim((string) ($c['cargo'] ?? ''));
                                    $obs = trim((string) ($c['observacao'] ?? ''));
                                    $partes = array_filter([$conv . ($cargo !== '' ? ' (' . $cargo . ')' : ''), $obs]);
                                    echo e($partes !== [] ? implode(' — ', $partes) : '—');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section class="pauta-doc-sec">
            <h2 class="pauta-doc-sec-titulo">
                <span class="num"><?= $cerimonial !== [] ? 'II' : 'I' ?></span>
                Matérias da pauta
            </h2>
            <?php if ($itens === []): ?>
                <p class="pauta-doc-vazio">Nenhuma matéria cadastrada.</p>
            <?php else: ?>
                <table class="pauta-doc-tabela">
                    <thead>
                        <tr>
                            <th class="col-ordem">#</th>
                            <th>Matéria</th>
                            <th>Ementa / resumo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $i => $item): ?>
                            <tr>
                                <td class="col-ordem"><?= (int) ($item['ordem'] ?? $i + 1) ?></td>
                                <td class="col-titulo"><?= e(pauta_impressao_item_label($item)) ?></td>
                                <td class="col-ementa"><?= e(pauta_impressao_ementa_resumo((string) ($item['ementa'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($roteiro !== []): ?>
            <section class="pauta-doc-sec">
                <h2 class="pauta-doc-sec-titulo">
                    <span class="num">
                        <?php
                        echo $cerimonial !== [] ? 'III' : 'II';
                        ?>
                    </span>
                    Ordem de condução (roteiro da presidência)
                </h2>
                <table class="pauta-doc-tabela">
                    <thead>
                        <tr>
                            <th class="col-ordem">#</th>
                            <th>Etapa</th>
                            <th>Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roteiro as $i => $p): ?>
                            <tr>
                                <td class="col-ordem"><?= $i + 1 ?></td>
                                <td class="col-titulo"><?= e(pauta_impressao_roteiro_label($p)) ?></td>
                                <td>
                                    <?php if (!empty($p['gera_votacao'])): ?>
                                        <span class="pauta-doc-badge">Votação nominal</span>
                                    <?php else: ?>
                                        <span class="col-ementa">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <footer class="pauta-doc-rodape">
            Documento gerado pelo sistema <?= e($appName) ?> — uso interno e publicação oficial conforme regimento interno.
        </footer>
    </article>
</body>
</html>
