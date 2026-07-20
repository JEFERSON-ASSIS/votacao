<?php
declare(strict_types=1);

$roots = [__DIR__ . '/../app'];

$map = [
    'SessãoCrud' => 'SessaoCrud',
    'SessãoRoteiro' => 'SessaoRoteiro',
    'SessãoItem' => 'SessaoItem',
    'SessãoVoto' => 'SessaoVoto',
    'SessãoVotaçãoRodada' => 'SessaoVotacaoRodada',
    'SessãoEvento' => 'SessaoEvento',
    'SessãoPresenca' => 'SessaoPresenca',
    'SessãoAtaUpload' => 'SessaoAtaUpload',
    'Sessão::' => 'Sessao::',
    'SessãoController' => 'SessaoController',
    'VotaçãoController' => 'VotacaoController',
    'MatériaSaplFields' => 'MateriaSaplFields',
    'SaplMatériaImport' => 'SaplMateriaImport',
    'texto_instrução' => 'texto_instrucao',
    'textoInstrução' => 'textoInstrucao',
    'ataSessãoRef' => 'ataSessaoRef',
    'statusSessão' => 'statusSessao',
    'tituloVotação' => 'tituloVotacao',
    'tipo_matéria' => 'tipo_materia',
    'matéria_polemica' => 'materia_polemica',
    'novaMatériaForm' => 'novaMateriaForm',
    'bloco-instrução' => 'bloco-instrucao',
    'label-instrução' => 'label-instrucao',
    'texto_instrução' => 'texto_instrucao', // id/name duplicate ok
    'preview-instrução' => 'preview-instrucao',
    'conducao-instrução' => 'conducao-instrucao',
    "'votação/" => "'votacao/",
    '"votação/' => '"votacao/',
    "'sessões/" => "'sessoes/",
    'sessões/index' => 'sessoes/index',
    'concluídos' => 'concluidos',
    'Sessão anterior' => 'Sessão anterior', // keep accented UI - noop
];

uksort($map, static fn($a, $b) => strlen($b) <=> strlen($a));

foreach ($roots as $root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $new = $content;
        foreach ($map as $from => $to) {
            if ($from === $to) {
                continue;
            }
            $new = str_replace($from, $to, $new);
        }
        if ($new !== $content) {
            file_put_contents($path, $new);
            echo $path . "\n";
        }
    }
}

echo "Identificadores corrigidos.\n";
