<?php
// Usado por conducao-votos.html: simula o estado de uma votação aberta.
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'aberta' => true,
    'resumo' => ['SIM' => 2, 'NAO' => 1, 'ABSTENCAO' => 0, 'AUSENTE' => 0],
    'parlamentares' => [
        ['id' => 1, 'nome' => 'Ana Lima', 'voto' => 'SIM', 'voto_label' => 'Sim', 'voto_classe' => 'voto-sim', 'aguardando' => false, 'situacao' => 'PENDENTE'],
        ['id' => 2, 'nome' => 'Bruno Reis', 'voto' => 'NAO', 'voto_label' => 'Não', 'voto_classe' => 'voto-nao', 'aguardando' => false, 'situacao' => 'PENDENTE'],
        ['id' => 3, 'nome' => 'Carla Dias', 'voto' => 'SIM', 'voto_label' => 'Sim', 'voto_classe' => 'voto-sim', 'aguardando' => false, 'situacao' => 'PENDENTE'],
        ['id' => 4, 'nome' => 'Davi <i>Souza</i>', 'voto' => null, 'voto_label' => '', 'voto_classe' => '', 'aguardando' => true, 'situacao' => 'PENDENTE'],
    ],
    'pendentes' => [['user_id' => 4, 'nome' => 'Davi <i>Souza</i>']],
    'pode_encerrar' => false,
    'encerrar_mensagem' => 'Aguardando 1 voto: Davi Souza.',
], JSON_UNESCAPED_UNICODE);
