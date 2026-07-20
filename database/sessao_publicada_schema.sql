-- Bloqueio de edição após publicar sessão (pauta + roteiro)
ALTER TABLE sessoes
    ADD COLUMN publicada_em TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD COLUMN publicada_por INT NULL DEFAULT NULL AFTER publicada_em;
