-- MATIQ / Meta Ads Tracker
-- MySQL schema for cPanel (phpMyAdmin compatible)
-- Engine: InnoDB, Charset: utf8mb4
-- Import this file after selecting the target database in phpMyAdmin.

SET NAMES utf8mb4;
SET time_zone = '+00:00';


CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `import_batch_id` VARCHAR(64) DEFAULT NULL,
  `period_label` VARCHAR(64) DEFAULT NULL,
  `campaign_name` VARCHAR(255) NOT NULL,
  `spend` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `impressions` BIGINT NOT NULL DEFAULT 0,
  `ctr` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `results` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `revenue` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `roas` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `cpm` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `reach` BIGINT NOT NULL DEFAULT 0,
  `freq` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `atc` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `cpa` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `date_start` VARCHAR(32) DEFAULT NULL,
  `date_end` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_user_id` (`user_id`),
  KEY `idx_campaigns_import_batch` (`import_batch_id`),
  KEY `idx_campaigns_name` (`campaign_name`),
  KEY `idx_campaigns_created_at` (`created_at`),
  CONSTRAINT `fk_campaigns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `adsets` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `import_batch_id` VARCHAR(64) DEFAULT NULL,
  `period_label` VARCHAR(64) DEFAULT NULL,
  `campaign_name` VARCHAR(255) NOT NULL,
  `adset_name` VARCHAR(255) NOT NULL,
  `spend` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `impressions` BIGINT NOT NULL DEFAULT 0,
  `ctr` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `results` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `revenue` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `roas` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `cpm` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `reach` BIGINT NOT NULL DEFAULT 0,
  `freq` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `atc` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `cpa` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `date_start` VARCHAR(32) DEFAULT NULL,
  `date_end` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adsets_user_id` (`user_id`),
  KEY `idx_adsets_import_batch` (`import_batch_id`),
  KEY `idx_adsets_campaign_name` (`campaign_name`),
  KEY `idx_adsets_name` (`adset_name`),
  KEY `idx_adsets_created_at` (`created_at`),
  CONSTRAINT `fk_adsets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `ads` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `import_batch_id` VARCHAR(64) DEFAULT NULL,
  `period_label` VARCHAR(64) DEFAULT NULL,
  `campaign_name` VARCHAR(255) NOT NULL,
  `adset_name` VARCHAR(255) NOT NULL,
  `ad_name` VARCHAR(255) NOT NULL,
  `spend` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `impressions` BIGINT NOT NULL DEFAULT 0,
  `ctr` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `results` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `revenue` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `roas` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `cpm` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `reach` BIGINT NOT NULL DEFAULT 0,
  `freq` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `atc` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `cpa` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `date_start` VARCHAR(32) DEFAULT NULL,
  `date_end` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ads_user_id` (`user_id`),
  KEY `idx_ads_import_batch` (`import_batch_id`),
  KEY `idx_ads_campaign_name` (`campaign_name`),
  KEY `idx_ads_adset_name` (`adset_name`),
  KEY `idx_ads_name` (`ad_name`),
  KEY `idx_ads_created_at` (`created_at`),
  CONSTRAINT `fk_ads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `thresholds` (
  `metric_key` VARCHAR(32) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `rule_type` ENUM('min','max') NOT NULL,
  `value` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `label` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notes` (
  `id` VARCHAR(600) NOT NULL,
  `entity_level` ENUM('campaign','adset','ad') NOT NULL,
  `entity_name` VARCHAR(255) NOT NULL,
  `note_text` TEXT NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notes_entity` (`entity_level`,`entity_name`),
  KEY `idx_notes_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key_name` VARCHAR(128) NOT NULL,
  `key_value` TEXT,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_batch_id` VARCHAR(64) NOT NULL,
  `level` ENUM('campaign','adset','ad') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `row_count` INT NOT NULL DEFAULT 0,
  `imported_at` DATETIME(3) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'ok',
  `message` TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_import_logs_batch` (`import_batch_id`),
  KEY `idx_import_logs_imported_at` (`imported_at`),
  KEY `idx_import_logs_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `salt` VARCHAR(64) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `role` ENUM('admin','user') NOT NULL DEFAULT 'user',
  `payment_status` ENUM('LUNAS','PENDING','NONE') NOT NULL DEFAULT 'NONE',
  `mailketing_list_id` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `last_login` DATETIME(3) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_payment_status` (`payment_status`),
  KEY `idx_users_mailketing_list_id` (`mailketing_list_id`),
  KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `token_id` VARCHAR(128) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `role` ENUM('admin','user') NOT NULL,
  `payment_status` ENUM('LUNAS','PENDING','NONE') NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`token_id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_expires_at` (`expires_at`),
  KEY `idx_sessions_is_revoked` (`is_revoked`),
  CONSTRAINT `fk_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_contacts` (
  `user_id` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone_number` VARCHAR(32) NOT NULL,
  `is_whatsapp_opt_in` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_contacts_phone` (`phone_number`),
  CONSTRAINT `fk_user_contacts_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `channel` ENUM('email','whatsapp') NOT NULL,
  `recipient` VARCHAR(191) NOT NULL,
  `status` ENUM('sent','failed','queued','retry') NOT NULL,
  `attempt` INT NOT NULL DEFAULT 1,
  `provider` VARCHAR(64) NOT NULL,
  `http_status` VARCHAR(16) DEFAULT NULL,
  `error_message` VARCHAR(500) DEFAULT NULL,
  `response_excerpt` TEXT,
  `queue_id` VARCHAR(64) DEFAULT NULL,
  `user_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_logs_created_at` (`created_at`),
  KEY `idx_notification_logs_channel_status` (`channel`,`status`),
  KEY `idx_notification_logs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_queue` (
  `queue_id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `phone_number` VARCHAR(32) NOT NULL,
  `message_type` VARCHAR(64) NOT NULL,
  `message_payload` LONGTEXT,
  `status` ENUM('pending','retry','sent','failed') NOT NULL DEFAULT 'pending',
  `attempt_count` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `next_retry_at` DATETIME(3) NOT NULL,
  `last_error` VARCHAR(500) DEFAULT NULL,
  `provider_message_id` VARCHAR(128) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `idx_whatsapp_queue_status_retry` (`status`,`next_retry_at`),
  KEY `idx_whatsapp_queue_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `token_id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `used_at` DATETIME(3) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`token_id`),
  KEY `idx_password_reset_tokens_user` (`user_id`),
  KEY `idx_password_reset_tokens_email` (`email`),
  KEY `idx_password_reset_tokens_token` (`token`),
  KEY `idx_password_reset_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_password_reset_tokens_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_tokens` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `provider` VARCHAR(32) NOT NULL,
  `access_token` TEXT,
  `refresh_token` TEXT,
  `expires_at` DATETIME(3) DEFAULT NULL,
  `token_type` VARCHAR(32) DEFAULT 'Bearer',
  `scope` VARCHAR(512) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oauth_tokens_user_provider` (`user_id`, `provider`),
  KEY `idx_oauth_tokens_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `thresholds` (`metric_key`, `enabled`, `rule_type`, `value`, `label`) VALUES
  ('roas', 1, 'min', 1.500000, 'ROAS min'),
  ('cpa', 0, 'max', 150000.000000, 'CPA max'),
  ('ctr', 1, 'min', 1.000000, 'CTR min %'),
  ('cpm', 0, 'max', 60000.000000, 'CPM max')
ON DUPLICATE KEY UPDATE
  `enabled` = VALUES(`enabled`),
  `rule_type` = VALUES(`rule_type`),
  `value` = VALUES(`value`),
  `label` = VALUES(`label`);
