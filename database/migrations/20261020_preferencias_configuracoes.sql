-- Prefer?ncias funcionais da tela Configura??es (sem integra??o banc?ria ou moedas).
CREATE TABLE IF NOT EXISTS `preferencias_usuario` (
  `usuario_id` INT NOT NULL,
  `notificar_orcamento` TINYINT(1) NOT NULL DEFAULT 1,
  `notificar_metas` TINYINT(1) NOT NULL DEFAULT 1,
  `notificar_gastos` TINYINT(1) NOT NULL DEFAULT 1,
  `notificar_revisao` TINYINT(1) NOT NULL DEFAULT 1,
  `notificar_agendamentos` TINYINT(1) NOT NULL DEFAULT 1,
  `notificar_economia` TINYINT(1) NOT NULL DEFAULT 1,
  `ocultar_valores` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_preferencias_usuario_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
