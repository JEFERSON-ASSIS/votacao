CREATE TABLE IF NOT EXISTS sessao_votacao_rodadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    roteiro_passo_id INT NULL,
    item_id INT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ABERTA',
    resultado VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    encerrada_em TIMESTAMP NULL,
    CONSTRAINT fk_rodada_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_rodada_passo FOREIGN KEY (roteiro_passo_id) REFERENCES sessao_roteiro(id) ON DELETE SET NULL,
    CONSTRAINT fk_rodada_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL
);

ALTER TABLE sessao_votos ADD COLUMN IF NOT EXISTS rodada_id INT NULL AFTER item_id;
ALTER TABLE sessao_votos MODIFY COLUMN item_id INT NULL;
