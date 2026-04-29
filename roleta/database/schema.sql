-- ============================================================
--  DON SPIN — Schema SQL Completo
--  Compatível com MySQL 5.7+ e MariaDB 10.3+
--  Executar no phpMyAdmin do cPanel ou via CLI
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET foreign_key_checks = 1;

-- ------------------------------------------------------------
-- 1. ADMINS
--    Guarda as contas dos administradores.
--    Senha armazenada com password_hash() (BCRYPT/ARGON2ID).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(100)    NOT NULL,
  `email`      VARCHAR(191)    NOT NULL,
  `senha_hash` VARCHAR(255)    NOT NULL,
  `ativo`      TINYINT(1)      NOT NULL DEFAULT 1,
  `criado_em`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. CONFIGURACOES
--    Uma única linha com todas as configurações da roleta.
--    Usa INSERT ... ON DUPLICATE KEY UPDATE para upsert.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave`             VARCHAR(100) NOT NULL,
  `valor`             TEXT         NULL,
  `atualizado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PREMIOS (fatias da roleta)
--    Cada linha é uma fatia da roleta.
--    Probabilidade em ponto flutuante (0-100).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `premios` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `texto`         VARCHAR(100)    NOT NULL COMMENT 'Texto exibido na fatia',
  `tipo`          ENUM('cupom','premio','sem-premio') NOT NULL DEFAULT 'premio',
  `valor`         VARCHAR(100)    NULL     COMMENT 'Código do cupom ou detalhe do prêmio',
  `url`           VARCHAR(500)    NULL     COMMENT 'Link de resgate (opcional)',
  `probabilidade` DECIMAL(8,4)    NOT NULL DEFAULT 10.0000 COMMENT 'Peso relativo (%)',
  `cor`           VARCHAR(20)     NOT NULL DEFAULT '#6366F1' COMMENT 'Cor hexadecimal da fatia',
  `ordem`         SMALLINT        NOT NULL DEFAULT 0,
  `ativo`         TINYINT(1)      NOT NULL DEFAULT 1,
  `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. GIROS
--    Log de cada giro realizado na roleta pública.
--    Permite auditoria completa dos resultados.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giros` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `premio_id`       INT UNSIGNED    NULL     COMMENT 'FK para premios (NULL se prêmio deletado)',
  `premio_texto`    VARCHAR(100)    NOT NULL COMMENT 'Snapshot do texto no momento do giro',
  `premio_tipo`     VARCHAR(20)     NOT NULL,
  `premio_valor`    VARCHAR(100)    NULL,
  `identificador`   VARCHAR(128)    NOT NULL COMMENT 'Cookie/IP hash do usuário',
  `ip_hash`         VARCHAR(64)     NULL     COMMENT 'SHA256 do IP (não armazena IP direto)',
  `ganhou`          TINYINT(1)      NOT NULL DEFAULT 0,
  `girado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_giros_identificador` (`identificador`),
  KEY `idx_giros_girado_em` (`girado_em`),
  CONSTRAINT `fk_giros_premio` FOREIGN KEY (`premio_id`) REFERENCES `premios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. METRICAS
--    Contadores agregados em tempo real.
--    Uma única linha atualizada a cada evento relevante.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metricas` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitas`     BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `giros`       BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `ganhos`      BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `copias`      BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `atualizado_em` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. CONTROLE DE GIROS POR USUÁRIO
--    Impede que um mesmo usuário gire além do limite configurado.
--    Limpeza periódica pode ser feita por cron job.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `spin_control` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identificador`  VARCHAR(128)    NOT NULL COMMENT 'Mesmo identificador de giros',
  `total_giros`    SMALLINT        NOT NULL DEFAULT 0,
  `primeiro_em`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_em`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spin_control_ident` (`identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DADOS INICIAIS
-- ------------------------------------------------------------

-- Linha de métricas inicial (sempre terá id=1)
INSERT IGNORE INTO `metricas` (`id`, `visitas`, `giros`, `ganhos`, `copias`)
VALUES (1, 0, 0, 0, 0);

-- Configurações padrão
INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
  ('title',            'Gira e Ganha! 🎡'),
  ('message',          'Tente a sua sorte e ganhe prêmios incríveis!'),
  ('winTitle',         'Parabéns! 🎉'),
  ('winMessage',       'Você ganhou um prêmio incrível!'),
  ('loseTitle',        'Quase lá! 😅'),
  ('loseMessage',      'Não foi desta vez. Boa sorte na próxima!'),
  ('popupBtnText',     'Fechar'),
  ('spinLimit',        '1'),
  ('locked',           '0'),
  ('background',       '#0B0914'),
  ('cardColor',        '#151226'),
  ('cardBorderColor',  'rgba(255,255,255,0.06)'),
  ('textColor',        '#F8F9FA'),
  ('accentColor',      '#6366F1'),
  ('pointerColor',     '#EC4899'),
  ('loginBgColor',     '#05040A'),
  ('loginCardColor',   '#0E0C1A'),
  ('logo',             ''),
  ('loginLogo',        '')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- Prêmios padrão
INSERT INTO `premios` (`texto`, `tipo`, `valor`, `url`, `probabilidade`, `cor`, `ordem`) VALUES
  ('R$ 50 OFF',        'cupom',      'PROMO50',   'https://exemplo.com', 20.0000, '#6366F1', 1),
  ('Frete Grátis',     'premio',     'FRETEFREE', '',                    30.0000, '#EC4899', 2),
  ('Tente Novamente',  'sem-premio', '',          '',                    50.0000, '#1E1A36', 3)
ON DUPLICATE KEY UPDATE `texto` = VALUES(`texto`);
