-- Script di creazione tabella per il servizio /services/
-- Eseguire questo script sul database MySQL del server godrop.me

CREATE TABLE IF NOT EXISTS `aziende` (
    `id_azienda`  INT UNSIGNED  NOT NULL,
    `azienda`     VARCHAR(255)  NOT NULL,
    `server`      VARCHAR(45)   NOT NULL COMMENT 'Indirizzo IP (max IPv6 45 char)',
  `porta`       SMALLINT UNSIGNED NOT NULL COMMENT 'Porta di ascolto server (1-65535)',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_azienda`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro aziende per il servizio GetBarcodeServices';
