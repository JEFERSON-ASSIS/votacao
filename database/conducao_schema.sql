CREATE TABLE IF NOT EXISTS conducao_modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    nome VARCHAR(180) NOT NULL,
    categoria VARCHAR(30) NOT NULL DEFAULT 'MATERIA',
    tipo_materia_vinculo VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conducao_modelo_passos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    tipo_passo VARCHAR(20) NOT NULL DEFAULT 'FALA',
    texto_template TEXT NOT NULL,
    gera_votacao TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_passo_modelo FOREIGN KEY (modelo_id) REFERENCES conducao_modelos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessao_roteiro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    tipo_item VARCHAR(40) NOT NULL DEFAULT 'CONDUCAO',
    pauta_item_id INT NULL,
    modelo_id INT NULL,
    titulo VARCHAR(200) NULL,
    texto_fala TEXT NOT NULL,
    texto_instrucao TEXT NULL,
    tipo_passo VARCHAR(20) NOT NULL DEFAULT 'FALA',
    gera_votacao TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    status_execucao VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    dados_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_roteiro_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_roteiro_pauta FOREIGN KEY (pauta_item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL,
    CONSTRAINT fk_roteiro_modelo FOREIGN KEY (modelo_id) REFERENCES conducao_modelos(id) ON DELETE SET NULL
);
