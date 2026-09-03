CREATE TABLE `importacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT NOT NULL,
  `nome_arquivo` VARCHAR(255) NOT NULL,
  `tipo_arquivo` VARCHAR(12) NOT NULL,
  `hash_arquivo` CHAR(64) NOT NULL,
  `total_lidos` INT NOT NULL DEFAULT 0,
  `total_importados` INT NOT NULL DEFAULT 0,
  `total_duplicados` INT NOT NULL DEFAULT 0,
  `total_invalidos` INT NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_importacoes_usuario_data` (`usuario_id`, `criado_em`),
  CONSTRAINT `fk_importacoes_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `transacoes`
  ADD COLUMN `importacao_id` INT NULL AFTER `usuario_id`,
  ADD COLUMN `hash_importacao` CHAR(64) NULL AFTER `observacao_captura`,
  ADD KEY `idx_transacoes_importacao` (`importacao_id`),
  ADD UNIQUE KEY `uq_transacoes_usuario_hash_importacao` (`usuario_id`, `hash_importacao`),
  ADD CONSTRAINT `fk_transacoes_importacao`
    FOREIGN KEY (`importacao_id`) REFERENCES `importacoes` (`id`) ON DELETE SET NULL;
