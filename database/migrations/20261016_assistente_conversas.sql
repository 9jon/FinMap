CREATE TABLE `assistente_conversas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT NOT NULL,
  `titulo` VARCHAR(80) NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assistente_conversas_usuario_data` (`usuario_id`, `updated_at`),
  CONSTRAINT `fk_assistente_conversas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assistente_mensagens` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `conversa_id` INT NOT NULL,
  `papel` ENUM('user','assistant') NOT NULL,
  `conteudo` TEXT NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assistente_mensagens_conversa_data` (`conversa_id`, `id`),
  CONSTRAINT `fk_assistente_mensagens_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `assistente_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;