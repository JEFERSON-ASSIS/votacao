-- Plenario — schema completo do banco (MySQL 8+ / MariaDB)
-- Aplicado por: php scripts/migrate.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `auditoria_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_nome` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acao` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int DEFAULT NULL,
  `detalhe` text COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_auditoria_user` (`user_id`),
  CONSTRAINT `fk_auditoria_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conducao_modelo_passos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modelo_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `tipo_passo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FALA',
  `texto_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `gera_votacao` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_passo_modelo` (`modelo_id`),
  CONSTRAINT `fk_passo_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `conducao_modelos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conducao_modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MATERIA',
  `tipo_materia_vinculo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_despachos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `comissao` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `situacao` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_despacho_item` (`item_id`),
  CONSTRAINT `fk_despacho_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_doc` date DEFAULT NULL,
  `autor` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_documento_item` (`item_id`),
  CONSTRAINT `fk_documento_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `tipo_materia` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Materia',
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ementa` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDENTE',
  `texto_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_apresentacao` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_apresentacao` date DEFAULT NULL,
  `numero_protocolo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autor` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regime_tramitacao` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL,
  `materia_polemica` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nao',
  `em_tramitacao` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nao',
  `eh_complementar` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nao',
  `dados_extras_json` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `numero` int DEFAULT NULL,
  `ano` int DEFAULT NULL,
  `sapl_id` int DEFAULT NULL,
  `apelido` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indexacao` text COLLATE utf8mb4_unicode_ci,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `objeto` text COLLATE utf8mb4_unicode_ci,
  `resultado` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_prazo` int DEFAULT NULL,
  `data_fim_prazo` date DEFAULT NULL,
  `tipo_sapl` int DEFAULT NULL,
  `regime_tramitacao_id` int DEFAULT NULL,
  `numero_origem_externa` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ano_origem_externa` int DEFAULT NULL,
  `data_origem_externa` date DEFAULT NULL,
  `tipo_origem_externa` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_origem_externa` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autores_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pauta_sessao` (`sessao_id`),
  CONSTRAINT `fk_pauta_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_legislacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `norma` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposicao` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_legislacao_item` (`item_id`),
  CONSTRAINT `fk_legislacao_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_numeracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `tipo_materia` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ano` int DEFAULT NULL,
  `data_doc` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_numeracao_item` (`item_id`),
  CONSTRAINT `fk_numeracao_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_relatorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `relator` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_relatoria` date DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_relatoria_item` (`item_id`),
  CONSTRAINT `fk_relatoria_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pauta_tramitacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `data_tramitacao` date DEFAULT NULL,
  `unidade_local` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_destino` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tramitacao_item` (`item_id`),
  CONSTRAINT `fk_tramitacao_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_cerimonial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRIBUNA',
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_convidado` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tema` text COLLATE utf8mb4_unicode_ci,
  `tempo_min` int DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cerimonial_sessao` (`sessao_id`),
  CONSTRAINT `fk_cerimonial_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_conducao` (
  `sessao_id` int NOT NULL,
  `titular_user_id` int NOT NULL,
  `condutor_user_id` int NOT NULL,
  `atualizado_por` int NOT NULL,
  `destaque_user_id` int DEFAULT NULL,
  `destaque_em` timestamp NULL DEFAULT NULL,
  `destaque_tempo_seg` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sessao_id`),
  KEY `fk_conducao_titular` (`titular_user_id`),
  KEY `fk_conducao_condutor` (`condutor_user_id`),
  KEY `fk_conducao_autor` (`atualizado_por`),
  CONSTRAINT `fk_conducao_autor` FOREIGN KEY (`atualizado_por`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_conducao_condutor` FOREIGN KEY (`condutor_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_conducao_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conducao_titular` FOREIGN KEY (`titular_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_conducao_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condutor_anterior_id` int NOT NULL,
  `condutor_novo_id` int NOT NULL,
  `realizado_por` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_conducao_hist_sessao` (`sessao_id`),
  KEY `fk_conducao_hist_anterior` (`condutor_anterior_id`),
  KEY `fk_conducao_hist_novo` (`condutor_novo_id`),
  KEY `fk_conducao_hist_autor` (`realizado_por`),
  CONSTRAINT `fk_conducao_hist_anterior` FOREIGN KEY (`condutor_anterior_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_conducao_hist_autor` FOREIGN KEY (`realizado_por`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_conducao_hist_novo` FOREIGN KEY (`condutor_novo_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_conducao_hist_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_eventos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_evento_sessao` (`sessao_id`),
  KEY `fk_evento_item` (`item_id`),
  CONSTRAINT `fk_evento_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_evento_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_presentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `user_id` int NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_presenca_sessao_user` (`sessao_id`,`user_id`),
  KEY `fk_presenca_user` (`user_id`),
  CONSTRAINT `fk_presenca_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presenca_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_roteiro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `tipo_item` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONDUCAO',
  `pauta_item_id` int DEFAULT NULL,
  `modelo_id` int DEFAULT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texto_fala` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `texto_instrucao` text COLLATE utf8mb4_unicode_ci,
  `tipo_passo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FALA',
  `gera_votacao` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `status_execucao` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDENTE',
  `dados_json` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_roteiro_sessao` (`sessao_id`),
  KEY `fk_roteiro_pauta` (`pauta_item_id`),
  KEY `fk_roteiro_modelo` (`modelo_id`),
  CONSTRAINT `fk_roteiro_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `conducao_modelos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roteiro_pauta` FOREIGN KEY (`pauta_item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roteiro_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_roteiro_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `roteiro_passo_id` int NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_bytes` bigint DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_roteiro_anexo_passo` (`roteiro_passo_id`,`id`),
  KEY `fk_roteiro_anexo_sessao` (`sessao_id`),
  KEY `fk_roteiro_anexo_usuario` (`criado_por`),
  CONSTRAINT `fk_roteiro_anexo_passo` FOREIGN KEY (`roteiro_passo_id`) REFERENCES `sessao_roteiro` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roteiro_anexo_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roteiro_anexo_usuario` FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_votacao_eleitores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rodada_id` int NOT NULL,
  `user_id` int NOT NULL,
  `situacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDENTE',
  `origem` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRESENCA_SESSAO',
  `incluido_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `alterado_em` timestamp NULL DEFAULT NULL,
  `alterado_por` int DEFAULT NULL,
  `justificativa` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rodada_eleitor` (`rodada_id`,`user_id`),
  KEY `fk_eleitor_user` (`user_id`),
  KEY `fk_eleitor_operador` (`alterado_por`),
  CONSTRAINT `fk_eleitor_operador` FOREIGN KEY (`alterado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_eleitor_rodada` FOREIGN KEY (`rodada_id`) REFERENCES `sessao_votacao_rodadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eleitor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_votacao_rodadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `roteiro_passo_id` int DEFAULT NULL,
  `item_id` int DEFAULT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABERTA',
  `resultado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_limite_seg` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `encerrada_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rodada_sessao` (`sessao_id`),
  KEY `fk_rodada_passo` (`roteiro_passo_id`),
  KEY `fk_rodada_item` (`item_id`),
  CONSTRAINT `fk_rodada_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rodada_passo` FOREIGN KEY (`roteiro_passo_id`) REFERENCES `sessao_roteiro` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rodada_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_votos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `rodada_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `voto` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voto_rodada_user` (`rodada_id`,`user_id`),
  KEY `fk_voto_sessao` (`sessao_id`),
  KEY `fk_voto_user` (`user_id`),
  KEY `idx_voto_item_user` (`item_id`,`user_id`),
  CONSTRAINT `fk_voto_item` FOREIGN KEY (`item_id`) REFERENCES `pauta_itens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voto_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voto_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessao_votos_desempate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `rodada_id` int NOT NULL,
  `condutor_user_id` int NOT NULL,
  `voto` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rodada_id` (`rodada_id`),
  KEY `fk_desempate_sessao` (`sessao_id`),
  KEY `fk_desempate_condutor` (`condutor_user_id`),
  CONSTRAINT `fk_desempate_condutor` FOREIGN KEY (`condutor_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_desempate_rodada` FOREIGN KEY (`rodada_id`) REFERENCES `sessao_votacao_rodadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_desempate_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ORDINARIA',
  `numero` int NOT NULL DEFAULT '1',
  `ano` int NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RASCUNHO',
  `publicada_em` timestamp NULL DEFAULT NULL,
  `publicada_por` int DEFAULT NULL,
  `sessao_anterior_id` int DEFAULT NULL,
  `ata_sessao_anterior` text COLLATE utf8mb4_unicode_ci,
  `ata_sessao_anterior_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ata_conteudo` text COLLATE utf8mb4_unicode_ci,
  `ata_conteudo_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissoes` (
  `user_id` int NOT NULL,
  `permissao` varchar(80) NOT NULL,
  PRIMARY KEY (`user_id`,`permissao`),
  CONSTRAINT `fk_user_permissoes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USER',
  `partido` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `homepage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gabinete` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biografia` text COLLATE utf8mb4_unicode_ci,
  `naturalidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `estado_civil` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocupacao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cor` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genero` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identidade_genero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orientacao_sexual` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grau_instrucao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `historico_politico` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_users_cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modelos de condução (dados iniciais)
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (1,'abertura','Abertura da sessão','SESSAO',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (2,'ata','Ata da sessão anterior','SESSAO',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (3,'pauta_dia','Leitura da pauta do dia','SESSAO',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (4,'tribuna','Tribuna livre','SESSAO',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (5,'encerramento','Encerramento da sessão','SESSAO',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (6,'pl_completo','Projeto de Lei - fluxo completo','MATERIA','Projeto de Lei Ordinaria',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (7,'pl_complementar','Projeto de Lei Complementar','MATERIA','Projeto de Lei Complementar',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (8,'indicacao','Indicação — votação única','MATERIA','Indicacao',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (9,'resolucao','Projeto de Resolução','MATERIA','Projeto de Resolucao',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (10,'requerimento','Requerimento','MATERIA','Requerimento',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (11,'materia_generica','Matéria genérica','MATERIA',NULL,1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (34,'emenda','Emenda','MATERIA','Emenda',1,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelos` (`id`, `slug`, `nome`, `categoria`, `tipo_materia_vinculo`, `ativo`, `created_at`) VALUES (35,'mocao','Moção','MATERIA','Mocao',1,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (1,1,1,'FALA','Gostaria de dar boas-vindas e agradecer a presência de todos.\n\nInvocando a proteção divina sobre os nossos trabalhos, declaro aberta a presente sessão.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (3,2,1,'FALA','Peço a leitura da ata da sessão anterior.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (4,2,2,'INSTRUCAO','Coloco em discussão e faço a votação.',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (5,2,3,'FALA','Ata da sessão anterior aprovada.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (6,3,1,'FALA','Peço a leitura da pauta do dia.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (7,4,1,'FALA','Concedo palavra aos Vereadores inscritos na tribuna.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (8,5,1,'FALA','Não havendo mais nada a tratar, agradeço a presência de todos e declaro encerrada a sessão.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (9,6,1,'FALA','Peço a apresentação do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (10,6,2,'FALA','Encaminho para as Comissões de: {comissoes}',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (11,6,3,'FALA','Coloco em votação o pedido de urgência do projeto.',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (13,6,4,'FALA','Pedido de urgência {resultado_urgência} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (14,6,5,'FALA','Dispenso a reunião das Comissões.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (15,6,6,'FALA','Peço a leitura do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (16,6,7,'INSTRUCAO','Peço a defesa do autor e abro a discussão do mérito.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (17,6,8,'FALA','Faço a votação do {tipo} nº {numero}/{ano}. (Única votação)',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (19,6,9,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (20,7,1,'FALA','Peço a apresentação do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (21,7,2,'FALA','Encaminho para as Comissões de: {comissoes}',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (22,7,3,'FALA','Peço a leitura do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (23,7,4,'INSTRUCAO','Peço a defesa do autor e abro a discussão do mérito.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (24,7,5,'FALA','Faço a votação do {tipo} nº {numero}/{ano}. (Única votação)',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (25,7,6,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (26,8,1,'FALA','Peço a leitura da {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (27,8,2,'INSTRUCAO','Peço a defesa do autor - Vereador {autor}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (28,8,3,'FALA','Faço a votação da {tipo} nº {numero}/{ano}. (Única votação)',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (30,8,4,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (31,9,1,'FALA','Peço a apresentação do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (32,9,2,'FALA','Peço a leitura do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (33,9,3,'FALA','Faço a votação do {tipo} nº {numero}/{ano}.',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (34,9,4,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (35,10,1,'FALA','Peço a leitura do {tipo} nº {numero}/{ano}.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (36,10,2,'FALA','Faço a votação do {tipo} nº {numero}/{ano}.',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (37,10,3,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (38,11,1,'FALA','Passo referente a: {titulo}',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (39,11,2,'FALA','Faço a votação da materia. (Única votação)',1,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (40,11,3,'FALA','Matéria {resultado} por unanimidade.',0,'2026-05-24 16:37:17');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (121,34,1,'FALA','Peço a leitura da {tipo} nº {numero}/{ano}.',0,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (122,34,2,'INSTRUCAO','Peço a defesa do autor - Vereador {autor}.',0,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (123,34,3,'FALA','Faço a votação da {tipo} nº {numero}/{ano}.',1,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (124,34,4,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (125,35,1,'FALA','Peço a leitura da {tipo} nº {numero}/{ano}.',0,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (126,35,2,'FALA','Faço a votação da {tipo} nº {numero}/{ano}.',1,'2026-05-24 17:35:09');
INSERT IGNORE INTO `conducao_modelo_passos` (`id`, `modelo_id`, `ordem`, `tipo_passo`, `texto_template`, `gera_votacao`, `created_at`) VALUES (127,35,3,'FALA','{tipo} {resultado} por unanimidade.',0,'2026-05-24 17:35:09');

SET FOREIGN_KEY_CHECKS = 1;
