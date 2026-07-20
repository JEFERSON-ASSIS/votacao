CREATE TABLE IF NOT EXISTS auditoria_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_nome VARCHAR(150) NULL,
    acao VARCHAR(80) NOT NULL,
    entidade VARCHAR(80) NOT NULL,
    entidade_id INT NULL,
    detalhe TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sessao_cerimonial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    tipo VARCHAR(40) NOT NULL DEFAULT 'TRIBUNA',
    titulo VARCHAR(200) NOT NULL,
    nome_convidado VARCHAR(150) NULL,
    cargo VARCHAR(120) NULL,
    tema TEXT NULL,
    tempo_min INT NULL,
    observacao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cerimonial_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE
);
