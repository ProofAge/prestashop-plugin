CREATE TABLE IF NOT EXISTS `PREFIXproofage_verification` (
  `id_proofage_verification` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `verification_id` VARCHAR(64) NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `external_id` VARCHAR(255) NOT NULL,
  `id_customer` INT UNSIGNED NULL,
  `id_cart` INT UNSIGNED NULL,
  `session_token_hash` CHAR(64) NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `return_ref` CHAR(32) NOT NULL,
  `verification_url` VARCHAR(2048) NOT NULL,
  `status` VARCHAR(32) NOT NULL,
  `method` VARCHAR(16) NULL,
  `reason` VARCHAR(255) NULL,
  `origin_url` VARCHAR(2048) NOT NULL,
  `last_synced_at` DATETIME NULL,
  `decided_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_proofage_verification`),
  UNIQUE KEY `verification_id` (`verification_id`),
  UNIQUE KEY `return_ref` (`return_ref`),
  KEY `external_created` (`external_id`(191), `created_at`),
  KEY `ip_created` (`ip_hash`, `created_at`),
  KEY `id_cart` (`id_cart`)
) ENGINE=ENGINE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIXproofage_customer` (
  `id_customer` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `verification_id` VARCHAR(64) NOT NULL,
  `verified_at` DATETIME NOT NULL,
  `expires_at` DATETIME NULL,
  PRIMARY KEY (`id_customer`, `id_shop`),
  KEY `verification_id` (`verification_id`)
) ENGINE=ENGINE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIXproofage_webhook_delivery` (
  `delivery_id` VARCHAR(64) NOT NULL,
  `verification_id` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL,
  `received_at` DATETIME NOT NULL,
  PRIMARY KEY (`delivery_id`),
  KEY `received_at` (`received_at`)
) ENGINE=ENGINE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIXproofage_order` (
  `id_order` INT UNSIGNED NOT NULL,
  `required` TINYINT(1) NOT NULL,
  `verification_id` VARCHAR(64) NULL,
  `status` VARCHAR(16) NOT NULL,
  `method` VARCHAR(16) NULL,
  `verified_at` DATETIME NULL,
  PRIMARY KEY (`id_order`),
  KEY `verification_id` (`verification_id`)
) ENGINE=ENGINE DEFAULT CHARSET=utf8mb4;
