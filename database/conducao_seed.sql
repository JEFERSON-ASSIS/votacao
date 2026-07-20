INSERT IGNORE INTO conducao_modelos (slug, nome, categoria, tipo_materia_vinculo, ativo) VALUES
('abertura', 'Abertura da sessão', 'SESSAO', NULL, 1),
('ata', 'Ata da sessão anterior', 'SESSAO', NULL, 1),
('pauta_dia', 'Leitura da pauta do dia', 'SESSAO', NULL, 1),
('tribuna', 'Tribuna livre', 'SESSAO', NULL, 1),
('encerramento', 'Encerramento da sessão', 'SESSAO', NULL, 1),
('pl_completo', 'Projeto de Lei — fluxo completo', 'MATERIA', 'Projeto de Lei Ordinaria', 1),
('pl_complementar', 'Projeto de Lei Complementar', 'MATERIA', 'Projeto de Lei Complementar', 1),
('indicacao', 'Indicação — votação única', 'MATERIA', 'Indicacao', 1),
('resolucao', 'Projeto de Resolução', 'MATERIA', 'Projeto de Resolucao', 1),
('requerimento', 'Requerimento', 'MATERIA', 'Requerimento', 1),
('emenda', 'Emenda', 'MATERIA', 'Emenda', 1),
('mocao', 'Moção', 'MATERIA', 'Mocao', 1),
('materia_generica', 'Matéria genérica', 'MATERIA', NULL, 1);

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA',
'Gostaria de dar boas-vindas e agradecer a presença de todos.

Invocando a proteção divina sobre os nossos trabalhos, declaro aberta a presente sessão.',
0 FROM conducao_modelos m WHERE m.slug = 'abertura';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura da ata da sessão anterior.', 0 FROM conducao_modelos m WHERE m.slug = 'ata';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'INSTRUCAO', 'Coloco em discussão e faço a votação.', 1 FROM conducao_modelos m WHERE m.slug = 'ata';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Ata da sessão anterior aprovada.', 0 FROM conducao_modelos m WHERE m.slug = 'ata';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura da pauta do dia.', 0 FROM conducao_modelos m WHERE m.slug = 'pauta_dia';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Concedo palavra aos Vereadores inscritos na tribuna.', 0 FROM conducao_modelos m WHERE m.slug = 'tribuna';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Não havendo mais nada a tratar, agradeço a presença de todos e declaro encerrada a sessão.', 0 FROM conducao_modelos m WHERE m.slug = 'encerramento';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a apresentação do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Encaminho para as Comissões de: {comissoes}', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Coloco em votação o pedido de urgência do projeto.', 1 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 4, 'FALA', 'Pedido de urgência {resultado_urgencia} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 5, 'FALA', 'Dispenso a reunião das Comissões.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 6, 'FALA', 'Peço a leitura do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 7, 'INSTRUCAO', 'Peço a defesa do autor e abro a discussão do mérito.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 8, 'FALA', 'Faço a votação do {tipo} nº {numero}/{ano}. (Única votação)', 1 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 9, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_completo';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a apresentação do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Encaminho para as Comissões de: {comissoes}', 0 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Peço a leitura do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 4, 'INSTRUCAO', 'Peço a defesa do autor e abro a discussão do mérito.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 5, 'FALA', 'Faço a votação do {tipo} nº {numero}/{ano}. (Única votação)', 1 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 6, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'pl_complementar';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura da {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'indicacao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'INSTRUCAO', 'Peço a defesa do autor — Vereador {autor}.', 0 FROM conducao_modelos m WHERE m.slug = 'indicacao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Faço a votação da {tipo} nº {numero}/{ano}. (Única votação)', 1 FROM conducao_modelos m WHERE m.slug = 'indicacao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 4, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'indicacao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a apresentação do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'resolucao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Peço a leitura do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'resolucao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Faço a votação do {tipo} nº {numero}/{ano}.', 1 FROM conducao_modelos m WHERE m.slug = 'resolucao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 4, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'resolucao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura do {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'requerimento';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Faço a votação do {tipo} nº {numero}/{ano}.', 1 FROM conducao_modelos m WHERE m.slug = 'requerimento';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'requerimento';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura da {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'emenda';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'INSTRUCAO', 'Peço a defesa do autor — Vereador {autor}.', 0 FROM conducao_modelos m WHERE m.slug = 'emenda';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Faço a votação da {tipo} nº {numero}/{ano}.', 1 FROM conducao_modelos m WHERE m.slug = 'emenda';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 4, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'emenda';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Peço a leitura da {tipo} nº {numero}/{ano}.', 0 FROM conducao_modelos m WHERE m.slug = 'mocao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Faço a votação da {tipo} nº {numero}/{ano}.', 1 FROM conducao_modelos m WHERE m.slug = 'mocao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', '{tipo} {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'mocao';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 1, 'FALA', 'Passo referente a: {titulo}', 0 FROM conducao_modelos m WHERE m.slug = 'materia_generica';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 2, 'FALA', 'Faço a votação da matéria. (Única votação)', 1 FROM conducao_modelos m WHERE m.slug = 'materia_generica';

INSERT IGNORE INTO conducao_modelo_passos (modelo_id, ordem, tipo_passo, texto_template, gera_votacao)
SELECT m.id, 3, 'FALA', 'Matéria {resultado} por unanimidade.', 0 FROM conducao_modelos m WHERE m.slug = 'materia_generica';
