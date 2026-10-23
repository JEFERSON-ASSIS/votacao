<?php
// Usado por sessao-expirada.html: simula o servidor com o login expirado.
http_response_code(401);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'sessao_expirada' => true]);
