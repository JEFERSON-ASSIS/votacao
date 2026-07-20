CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    cpf VARCHAR(14) NULL UNIQUE,
    telefone VARCHAR(20) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'USUARIO',
    partido VARCHAR(80) NULL,
    foto VARCHAR(255) NULL,
    homepage VARCHAR(255) NULL,
    gabinete VARCHAR(30) NULL,
    biografia TEXT NULL,
    naturalidade VARCHAR(120) NULL,
    data_nascimento DATE NULL,
    estado_civil VARCHAR(40) NULL,
    ocupacao VARCHAR(200) NULL,
    cor VARCHAR(40) NULL,
    genero VARCHAR(40) NULL,
    identidade_genero VARCHAR(60) NULL,
    orientacao_sexual VARCHAR(60) NULL,
    grau_instrucao VARCHAR(200) NULL,
    historico_politico TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_permissoes (
    user_id INT NOT NULL,
    permissao VARCHAR(80) NOT NULL,
    PRIMARY KEY (user_id, permissao),
    CONSTRAINT fk_user_permissoes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'ORDINARIA',
    numero INT NOT NULL DEFAULT 1,
    ano INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'RASCUNHO',
    sessao_anterior_id INT NULL,
    ata_sessao_anterior TEXT NULL,
    ata_sessao_anterior_arquivo VARCHAR(255) NULL,
    ata_conteudo TEXT NULL,
    ata_conteudo_arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pauta_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    tipo_materia VARCHAR(120) NOT NULL DEFAULT 'Materia',
    numero INT NULL,
    ano INT NULL,
    sapl_id INT NULL,
    apelido VARCHAR(120) NULL,
    indexacao TEXT NULL,
    observacao TEXT NULL,
    objeto TEXT NULL,
    resultado VARCHAR(120) NULL,
    dias_prazo INT NULL,
    data_fim_prazo DATE NULL,
    tipo_sapl INT NULL,
    regime_tramitacao_id INT NULL,
    numero_origem_externa VARCHAR(80) NULL,
    ano_origem_externa INT NULL,
    data_origem_externa DATE NULL,
    tipo_origem_externa VARCHAR(120) NULL,
    local_origem_externa VARCHAR(180) NULL,
    titulo VARCHAR(200) NOT NULL,
    ementa TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDENTE',
    texto_original VARCHAR(255) NULL,
    tipo_apresentacao VARCHAR(80) NULL,
    data_apresentacao DATE NULL,
    numero_protocolo VARCHAR(80) NULL,
    autor VARCHAR(180) NULL,
    autores_ids VARCHAR(255) NULL,
    regime_tramitacao VARCHAR(120) NULL,
    data_publicacao DATE NULL,
    materia_polemica VARCHAR(20) NOT NULL DEFAULT 'Nao',
    em_tramitacao VARCHAR(20) NOT NULL DEFAULT 'Nao',
    eh_complementar VARCHAR(20) NOT NULL DEFAULT 'Nao',
    dados_extras_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pauta_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_despachos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    comissao VARCHAR(180) NULL,
    situacao VARCHAR(180) NULL,
    observacao TEXT NULL,
    arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_despacho_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    nome VARCHAR(180) NULL,
    tipo VARCHAR(120) NULL,
    data_doc DATE NULL,
    autor VARCHAR(180) NULL,
    arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documento_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_legislacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    norma VARCHAR(255) NULL,
    disposicao VARCHAR(180) NULL,
    observacao TEXT NULL,
    arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_legislacao_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_numeracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    tipo_materia VARCHAR(180) NULL,
    numero VARCHAR(80) NULL,
    ano INT NULL,
    data_doc DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_numeracao_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_tramitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    data_tramitacao DATE NULL,
    unidade_local VARCHAR(180) NULL,
    unidade_destino VARCHAR(180) NULL,
    status VARCHAR(180) NULL,
    arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tramitacao_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pauta_relatorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    relator VARCHAR(180) NULL,
    data_relatoria DATE NULL,
    observacao TEXT NULL,
    arquivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_relatoria_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessao_presentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    user_id INT NOT NULL,
    presente TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_presenca_sessao_user (sessao_id, user_id),
    CONSTRAINT fk_presenca_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_presenca_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessao_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    item_id INT NULL,
    tipo VARCHAR(50) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_evento_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS sessao_votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    item_id INT NULL,
    rodada_id INT NULL,
    user_id INT NOT NULL,
    voto VARCHAR(20) NOT NULL,
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voto_item_user (item_id, user_id),
    UNIQUE KEY uq_voto_rodada_user (rodada_id, user_id),
    CONSTRAINT fk_voto_sessao FOREIGN KEY (sessao_id) REFERENCES sessoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_voto_item FOREIGN KEY (item_id) REFERENCES pauta_itens(id) ON DELETE CASCADE,
    CONSTRAINT fk_voto_rodada FOREIGN KEY (rodada_id) REFERENCES sessao_votacao_rodadas(id) ON DELETE CASCADE,
    CONSTRAINT fk_voto_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
