-- Generated from templates/Db Ads Tracker.xlsx
-- Import into selected database in phpMyAdmin
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS campaigns (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_campaigns_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adsets (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  adset_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_adsets_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  adset_name VARCHAR(255) NOT NULL,
  ad_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ads_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thresholds (
  metric_key VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  rule_type ENUM('min','max') NOT NULL,
  value DECIMAL(18,6) NOT NULL DEFAULT 0,
  label VARCHAR(128) NOT NULL,
  PRIMARY KEY (metric_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
  id VARCHAR(600) NOT NULL,
  entity_level ENUM('campaign','adset','ad') NOT NULL,
  entity_name VARCHAR(255) NOT NULL,
  note_text TEXT NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  key_name VARCHAR(128) NOT NULL,
  key_value TEXT,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
  import_batch_id VARCHAR(64) NOT NULL,
  level ENUM('campaign','adset','ad') NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  row_count INT NOT NULL DEFAULT 0,
  imported_at DATETIME(3) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ok',
  message TEXT,
  KEY idx_import_logs_batch (import_batch_id),
  KEY idx_import_logs_imported_at (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(64) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  salt VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  payment_status ENUM('LUNAS','PENDING','NONE') NOT NULL DEFAULT 'NONE',
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  last_login DATETIME(3) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token_id VARCHAR(128) NOT NULL,
  user_id VARCHAR(64) NOT NULL,
  email VARCHAR(191) NOT NULL,
  role ENUM('admin','user') NOT NULL,
  payment_status ENUM('LUNAS','PENDING','NONE') NOT NULL,
  created_at DATETIME(3) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (token_id),
  KEY idx_sessions_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readme_meta (
  field_name VARCHAR(191) NOT NULL,
  field_value TEXT,
  PRIMARY KEY (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE TABLE campaigns;
TRUNCATE TABLE adsets;
TRUNCATE TABLE ads;
TRUNCATE TABLE thresholds;
TRUNCATE TABLE notes;
TRUNCATE TABLE settings;
TRUNCATE TABLE import_logs;
TRUNCATE TABLE users;
TRUNCATE TABLE sessions;
TRUNCATE TABLE readme_meta;
INSERT INTO `campaigns` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('cmp_001', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 1200000, 120000, 1.8, 18, 3600000, 3, 10000, 95000, 1.9, 220, 66667, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `campaigns` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('cmp_002', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 950000, 98000, 1.2, 8, 1280000, 1.3474, 9694, 81000, 2.6, 120, 118750, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `campaigns` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('cmp_003', 'batch_seed_2b71b391', '2026-04', 'C3 - Retargeting', 450000, 30000, 2.9, 15, 2250000, 5, 15000, 25000, 3.2, 90, 30000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');

INSERT INTO `adsets` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ads_001', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 'AS1 - Women 24-34', 650000, 60000, 1.9, 10, 2100000, 3.2308, 10833, 47000, 1.8, 120, 65000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `adsets` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ads_002', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 'AS2 - Broad', 550000, 60000, 1.7, 8, 1500000, 2.7273, 9167, 48000, 2.1, 100, 68750, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `adsets` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ads_003', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 'AS3 - Interest Fitness', 600000, 58000, 1.1, 5, 750000, 1.25, 10345, 50000, 2.7, 65, 120000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `adsets` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ads_004', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 'AS4 - Lookalike 1%', 350000, 40000, 1.3, 3, 530000, 1.5143, 8750, 31000, 2.4, 55, 116667, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `adsets` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ads_005', 'batch_seed_2b71b391', '2026-04', 'C3 - Retargeting', 'AS5 - Website ATC', 450000, 30000, 2.9, 15, 2250000, 5, 15000, 25000, 3.2, 90, 30000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');

INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_001', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 'AS1 - Women 24-34', 'Ad 1 - Problem/Solution', 330000, 32000, 2, 5, 990000, 3, 10313, 25500, 1.7, 63, 66000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_002', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 'AS1 - Women 24-34', 'Ad 2 - Testimonial', 320000, 28000, 1.8, 5, 1110000, 3.4688, 11429, 21500, 1.9, 57, 64000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_003', 'batch_seed_2b71b391', '2026-04', 'C1 - Skincare Sale', 'AS2 - Broad', 'Ad 3 - UGC Hook', 550000, 60000, 1.7, 8, 1500000, 2.7273, 9167, 48000, 2.1, 100, 68750, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_004', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 'AS3 - Interest Fitness', 'Ad 4 - Curiosity Hook', 300000, 30000, 0.9, 2, 280000, 0.9333, 10000, 26000, 2.9, 25, 150000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_005', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 'AS3 - Interest Fitness', 'Ad 5 - Offer Angle', 300000, 28000, 1.3, 3, 470000, 1.5667, 10714, 24000, 2.5, 40, 100000, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_006', 'batch_seed_2b71b391', '2026-04', 'C2 - Supplement New', 'AS4 - Lookalike 1%', 'Ad 6 - Benefit List', 350000, 40000, 1.3, 3, 530000, 1.5143, 8750, 31000, 2.4, 55, 116667, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_007', 'batch_seed_2b71b391', '2026-04', 'C3 - Retargeting', 'AS5 - Website ATC', 'Ad 7 - Reminder', 230000, 16000, 3.1, 8, 1280000, 5.5652, 14375, 14000, 3.1, 46, 28750, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');
INSERT INTO `ads` (`id`, `import_batch_id`, `period_label`, `campaign_name`, `adset_name`, `ad_name`, `spend`, `impressions`, `ctr`, `results`, `revenue`, `roas`, `cpm`, `reach`, `freq`, `atc`, `cpa`, `date_start`, `date_end`, `created_at`) VALUES ('ad_008', 'batch_seed_2b71b391', '2026-04', 'C3 - Retargeting', 'AS5 - Website ATC', 'Ad 8 - Social Proof', 220000, 14000, 2.7, 7, 970000, 4.4091, 15714, 12000, 3.4, 44, 31429, '2026-04-01', '2026-04-30', '2026-04-04T23:41:18Z');

INSERT INTO `thresholds` (`metric_key`, `enabled`, `rule_type`, `value`, `label`) VALUES ('roas', 1, 'min', 1.5, 'ROAS min');
INSERT INTO `thresholds` (`metric_key`, `enabled`, `rule_type`, `value`, `label`) VALUES ('cpa', 0, 'max', 150000, 'CPA max');
INSERT INTO `thresholds` (`metric_key`, `enabled`, `rule_type`, `value`, `label`) VALUES ('ctr', 1, 'min', 1, 'CTR min %');
INSERT INTO `thresholds` (`metric_key`, `enabled`, `rule_type`, `value`, `label`) VALUES ('cpm', 0, 'max', 60000, 'CPM max');

INSERT INTO `notes` (`id`, `entity_level`, `entity_name`, `note_text`, `updated_at`) VALUES ('ad::Ad 4 - Curiosity Hook', 'ad', 'Ad 4 - Curiosity Hook', 'CTR rendah 3 hari, butuh hook baru', '2026-04-04T23:41:18Z');
INSERT INTO `notes` (`id`, `entity_level`, `entity_name`, `note_text`, `updated_at`) VALUES ('campaign::C2 - Supplement New', 'campaign', 'C2 - Supplement New', 'Uji offer bundling minggu ini', '2026-04-04T23:41:18Z');

INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('WORKER_URL', 'https://ads.cepat.top');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('WORKER_TOKEN', 'SET_ME');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('WORKER_SIGNING_SECRET', 'SET_ME');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('AI_MODE', 'gpt-4o-mini');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('DB_SHEET_ID', '1hbhtYLqzSIRlZoIiB0my-05tSIXdgAOjPbgpf7dJIEs');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('GAS_WEB_APP_URL', 'https://script.google.com/macros/s/AKfycbyEQM12lmuZ_Q7NrBC_OVEHXDHN49oLEe52GLuMbFbSiH3HSzz6PK1S7DULwnfuTp4U/exec');
INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('AUTH_PASSWORD_MODE', 'PLAINTEXT');

INSERT INTO `import_logs` (`import_batch_id`, `level`, `file_name`, `row_count`, `imported_at`, `status`, `message`) VALUES ('batch_seed_2b71b391', 'campaign', 'seed_campaigns.csv', 3, '2026-04-04T23:41:18Z', 'success', 'seeded from workbook');
INSERT INTO `import_logs` (`import_batch_id`, `level`, `file_name`, `row_count`, `imported_at`, `status`, `message`) VALUES ('batch_seed_2b71b391', 'adset', 'seed_adsets.csv', 5, '2026-04-04T23:41:18Z', 'success', 'seeded from workbook');
INSERT INTO `import_logs` (`import_batch_id`, `level`, `file_name`, `row_count`, `imported_at`, `status`, `message`) VALUES ('batch_seed_2b71b391', 'ad', 'seed_ads.csv', 8, '2026-04-04T23:41:18Z', 'success', 'seeded from workbook');

INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_admin_seed_001', 'admin@cepat.top', 'Admin1234', 'PLAINTEXT', 'Mark Musk', 'admin', 'LUNAS', '2026-03-25T23:41:18Z', '2026-04-06T06:26:49.800Z', '2026-04-06T06:26:49.799Z', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_user_seed_001', 'user@cepat.top', 'User1234', 'PLAINTEXT', 'BMW KUNING', 'user', 'LUNAS', '2026-03-30T23:41:18Z', '2026-04-06T01:15:39.570Z', '2026-04-06T01:15:39.570Z', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_e3459288-0006-461d-a08d-fba8a7c85854', 'maspram@mail.com', '933d09a20839561eeb26751d6c447dee6c5b3af072908adb7a13642fb327821f', 'aywQpifFjZdtetmifKgFeUpFDzlY4Nl7', 'Demo Mas Pram', 'user', 'LUNAS', '2026-04-05T08:11:38.729Z', '2026-04-05T08:16:19.326Z', '2026-04-05T08:16:19.324Z', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_12164f4f-66b7-4cf7-8212-c1dbb6b5876c', 'gelarsatyapradana@gmail.com', 'c1f67cb60ea868dcd755d0d48cf5e47d06d869b4727071e6508d993ee9a16109', 'syZHXNZv8V1KmDb4XjsZYKmqobUwYOWS', 'Gelar perdana', 'user', 'LUNAS', '2026-04-05T10:20:17.276Z', '2026-04-05T10:20:17.276Z', '1', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_5b4884c3-0010-4f0f-8c44-98e87bfd9e20', 'imronamirullah9@gmail.com', 'AllahuAkbar123', 'PLAINTEXT', 'Imron', 'user', 'LUNAS', '2026-04-05T10:20:25.222Z', '2026-04-05T12:01:31.812Z', '1', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_4051cce2-5f0f-4003-8a01-be1b495a3b7b', 'andikasokosari@gmail.com', 'Mei2021#', 'PLAINTEXT', 'Andika', 'user', 'LUNAS', '2026-04-05T10:42:50.771Z', '2026-04-05T12:01:51.408Z', '1', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_bfe78ef9-4df4-460a-b48a-2676db98e801', 'muhammadsyaifulbahri2@gmail.com', '#Menikah2025', 'PLAINTEXT', 'Muhammad Syaiful Bahri', 'user', 'LUNAS', '2026-04-05T11:11:03.115Z', '2026-04-05T12:02:12.188Z', '0', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_22445d29-e859-4f0b-9800-0736604a3fdf', '32hermit_testers@icloud.com', 'mycdas-1wohno-geqceW', 'PLAINTEXT', 'rz', 'user', 'LUNAS', '2026-04-05T13:57:41.641Z', '2026-04-05T14:14:15.678Z', '2026-04-05T13:58:36.416Z', 0);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_47876dba-5051-42ae-b85a-1bf2ac2dfabf', 'fadhelmuhammad118@gmail.com', 'fadhelmn06', 'PLAINTEXT', 'Fadhel muhammad noor', 'user', 'LUNAS', '2026-04-05T14:17:16.489Z', '2026-04-05T14:53:28.747Z', '0', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_f606c551-5d23-4047-90f8-52090cb82fc0', 'kolegadigital@gmail.com', 'masuk123@', 'PLAINTEXT', 'Gusti Agung S', 'user', 'LUNAS', '2026-04-05T14:56:43.808Z', '2026-04-05T15:44:26.394Z', '2026-04-05T15:00:11.520Z', 0);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_907a8727-5ad5-4c00-8708-78bdaff11ab8', 'rijalyusuf78@gmail.com', '100jutasebulan', 'PLAINTEXT', 'rijalyusuf78@gmail.com', 'user', 'LUNAS', '2026-04-05T21:34:08.485Z', '2026-04-06T02:34:49.973Z', '0', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_7c1804d8-776c-4ba7-aa73-2e97d223ce28', 'it.fnb.semesta@gmail.com', 'B1sm1ll4h*', 'PLAINTEXT', 'It fnb semesta', 'user', 'LUNAS', '2026-04-05T21:58:37.096Z', '2026-04-06T02:35:31.612Z', '0', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_d0c79989-399c-481d-aba8-c8b051533e4d', 'putravito44@gmail.com', 'Putravito44', 'PLAINTEXT', 'Vito', 'user', 'LUNAS', '2026-04-06T05:19:49.773Z', '2026-04-06T06:28:28.634Z', '0', 1);
INSERT INTO `users` (`id`, `email`, `password_hash`, `salt`, `name`, `role`, `payment_status`, `created_at`, `updated_at`, `last_login`, `is_active`) VALUES ('usr_a3cda169-520d-4772-827a-8156328fa9d9', 'goodplantindonesia@gmail.com', 'G00dplant', 'PLAINTEXT', 'GOODPLANT Indonesia', 'user', 'NONE', '2026-04-06T08:44:45.784Z', '2026-04-06T08:47:44.266Z', '2026-04-06T08:47:44.265Z', 1);

INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_c817adfa-bef6-4606-9e91-b9fb6f4501ff', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T01:55:13.992Z', '2026-04-06T01:55:13.992Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_89878e10-f0d8-4dab-b965-cf33bc641f82', 'usr_f0811d86-58b7-4876-b586-dd5b399c2aaf', 'livecheck_1775354231088@example.com', 'user', 'NONE', '2026-04-05T01:57:14.777Z', '2026-04-06T01:57:14.777Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_531a2f6b-ac77-49d5-8a05-654c8f49cf94', 'usr_5bf58692-cb28-40af-9707-4389e0fad20d', 'livefix_1775354368875@example.com', 'user', 'NONE', '2026-04-05T01:59:31.055Z', '2026-04-06T01:59:31.055Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_7ec76b68-31ff-4afd-8ed7-d93b1a1d2c63', 'usr_7b62cc08-39e4-4958-bd31-fcbde3ac8961', 'probeactive_1775354559410@example.com', 'user', 'NONE', '2026-04-05T02:02:42.588Z', '2026-04-06T02:02:42.588Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_7a9ba9ed-da44-4c63-841f-6d79b8e714ff', 'usr_user_seed_001', 'user@cepat.top', 'user', 'NONE', '2026-04-05T02:03:17.620Z', '2026-04-06T02:03:17.620Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_01c86919-93c0-451a-9d82-817820c84269', 'usr_22dbe12c-7cf8-4fc9-8fcb-2a84f23bfa5b', 'quickok_1775362633775@example.com', 'user', 'NONE', '2026-04-05T04:17:14.299Z', '2026-04-06T04:17:14.299Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_5a3f9de6-692b-45c3-a2e3-0682608687b8', 'usr_e17af987-8273-4e77-a1b8-75e66030a64b', 'versionprobe_1775363030730@example.com', 'user', 'NONE', '2026-04-05T04:23:51.842Z', '2026-04-06T04:23:51.842Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_2a2de710-3284-4fb7-b903-1b3f68f81807', 'usr_f6bb8f70-63f2-4e16-a576-f69f5016a1df', 'lp_1775363050840@example.com', 'user', 'NONE', '2026-04-05T04:24:11.646Z', '2026-04-06T04:24:11.646Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_4768b859-80b6-4f07-834f-2ae10926bb62', 'usr_cb7fdde9-f1d9-4630-b9aa-a6364589596e', 'verifyprobe_1775363078662@example.com', 'user', 'NONE', '2026-04-05T04:24:40.010Z', '2026-04-06T04:24:40.010Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_2a8a22db-8193-4ad0-8020-efed6122709d', 'usr_user_seed_001', 'user@cepat.top', 'user', 'NONE', '2026-04-05T04:24:43.524Z', '2026-04-06T04:24:43.524Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_04e99a2f-07f4-4d63-aaf7-f227ce81e548', 'usr_user_seed_001', 'user@cepat.top', 'user', 'NONE', '2026-04-05T04:29:24.334Z', '2026-04-06T04:29:24.334Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_4277b201-50db-4ce7-940c-64cc689adc91', 'usr_user_seed_001', 'user@cepat.top', 'user', 'NONE', '2026-04-05T04:32:05.595Z', '2026-04-06T04:32:05.595Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_d15de5b2-1f8e-4f84-9803-446c6fbf9795', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T04:32:53.617Z', '2026-04-06T04:32:53.617Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_11932a02-38db-4b0d-93a8-3db85f74495d', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T04:33:22.773Z', '2026-04-06T04:33:22.773Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_0a6375e2-a133-45b9-b14e-c62efad1d563', 'usr_user_seed_001', 'user@cepat.top', 'user', 'NONE', '2026-04-05T05:31:04.518Z', '2026-04-06T05:31:04.518Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_5e0039a2-b80d-409b-bb11-dc0f36449cb2', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T05:32:01.034Z', '2026-04-06T05:32:01.034Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_1c6e597b-845b-4a78-a12b-66450ad6ef4c', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T06:57:15.227Z', '2026-04-06T06:57:15.227Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_dd692fa5-bdd5-4007-a0eb-ad5e3266767b', 'usr_user_seed_001', 'user@cepat.top', 'user', 'LUNAS', '2026-04-05T07:29:20.951Z', '2026-04-06T07:29:20.951Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_987a56d6-89ae-4007-8332-c7de78a5f078', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T07:32:28.491Z', '2026-04-06T07:32:28.491Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_b4e8397d-be94-401a-940c-9ae54b82c7b5', 'usr_58caacc2-295c-466d-9f99-3a15ce4a3ccd', 'e2e_1775375100786@example.com', 'user', 'NONE', '2026-04-05T07:45:13.699Z', '2026-04-06T07:45:13.699Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_08e7ba7d-8095-484b-9408-00351687adb9', 'usr_58caacc2-295c-466d-9f99-3a15ce4a3ccd', 'e2e_1775375100786@example.com', 'user', 'NONE', '2026-04-05T07:45:21.223Z', '2026-04-06T07:45:21.223Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_90db7c7b-1c52-4483-a84a-161f01f7cbca', 'usr_user_seed_001', 'user@cepat.top', 'user', 'LUNAS', '2026-04-05T07:46:25.325Z', '2026-04-06T07:46:25.325Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_638a88b8-7cb4-4fba-99e9-d2267ffbc656', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T07:48:41.233Z', '2026-04-06T07:48:41.233Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_0bcc7c8a-2772-40df-87d8-822172077f70', 'usr_ab3ff6a3-c273-475a-b43c-4a431a840187', 'e2e_fix_1775375314854@example.com', 'user', 'NONE', '2026-04-05T07:49:07.921Z', '2026-04-06T07:49:07.921Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_ebb67343-270e-4d1a-a8c9-4fb9f2c1330b', 'usr_ab3ff6a3-c273-475a-b43c-4a431a840187', 'e2e_fix_1775375314854@example.com', 'user', 'NONE', '2026-04-05T07:49:15.417Z', '2026-04-06T07:49:15.417Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_43d9ae75-3ccb-4840-9343-4b8c58f054c8', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T07:51:40.251Z', '2026-04-06T07:51:40.251Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_7a650c76-5b6d-47c9-8ba1-6dbdad1e6b5a', 'usr_e3459288-0006-461d-a08d-fba8a7c85854', 'maspram@mail.com', 'user', 'LUNAS', '2026-04-05T08:15:40.745Z', '2026-04-06T08:15:40.745Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_6762c290-750a-4ea8-8125-4f505408f660', 'usr_e3459288-0006-461d-a08d-fba8a7c85854', 'maspram@mail.com', 'user', 'LUNAS', '2026-04-05T08:16:19.506Z', '2026-04-06T08:16:19.506Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_6537b9e8-aec9-4dde-ad56-c3a11d7c34bb', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T10:19:09.801Z', '2026-04-06T10:19:09.801Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_499cdf60-3d8e-455b-a8c1-70545a82e318', 'usr_5b4884c3-0010-4f0f-8c44-98e87bfd9e20', 'imronamirullah9@gmail.com', 'user', 'NONE', '2026-04-05T10:20:25.327Z', '2026-04-06T10:20:25.327Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_6a705b31-a30b-49ac-bcd1-67251a9d5e7f', 'usr_4051cce2-5f0f-4003-8a01-be1b495a3b7b', 'andikasokosari@gmail.com', 'user', 'NONE', '2026-04-05T10:42:50.908Z', '2026-04-06T10:42:50.908Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_171061bb-10ad-49e2-83c0-16ebff893326', 'usr_bfe78ef9-4df4-460a-b48a-2676db98e801', 'muhammadsyaifulbahri2@gmail.com', 'user', 'NONE', '2026-04-05T11:11:03.190Z', '2026-04-06T11:11:03.190Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_a14d2623-d3eb-4204-8423-97370ae09724', 'usr_22445d29-e859-4f0b-9800-0736604a3fdf', '32hermit_testers@icloud.com', 'user', 'NONE', '2026-04-05T13:57:41.916Z', '2026-04-06T13:57:41.916Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_d97f258d-be7b-47ef-81d5-3dc5d4cbf6fa', 'usr_22445d29-e859-4f0b-9800-0736604a3fdf', '32hermit_testers@icloud.com', 'user', 'NONE', '2026-04-05T13:58:36.694Z', '2026-04-06T13:58:36.694Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_03b603e4-854b-4851-8cb9-1f907247a7eb', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-05T14:13:35.011Z', '2026-04-06T14:13:35.011Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_01de2af7-aecf-4336-bfd6-d0a4cb50f237', 'usr_47876dba-5051-42ae-b85a-1bf2ac2dfabf', 'fadhelmuhammad118@gmail.com', 'user', 'NONE', '2026-04-05T14:17:16.671Z', '2026-04-06T14:17:16.671Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_e61f68cd-08d3-4b8d-a2ba-55ede22585c8', 'usr_f606c551-5d23-4047-90f8-52090cb82fc0', 'kolegadigital@gmail.com', 'user', 'NONE', '2026-04-05T14:56:43.976Z', '2026-04-06T14:56:43.976Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_69031067-7d7e-4a73-aacf-43a95ce22034', 'usr_f606c551-5d23-4047-90f8-52090cb82fc0', 'kolegadigital@gmail.com', 'user', 'NONE', '2026-04-05T15:00:11.895Z', '2026-04-06T15:00:11.895Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_d285ac5b-6480-4dda-bcbb-b4109ca99eb5', 'usr_907a8727-5ad5-4c00-8708-78bdaff11ab8', 'rijalyusuf78@gmail.com', 'user', 'NONE', '2026-04-05T21:34:08.601Z', '2026-04-06T21:34:08.601Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_bbd7fe60-074a-4554-80d9-38fb979ed813', 'usr_7c1804d8-776c-4ba7-aa73-2e97d223ce28', 'it.fnb.semesta@gmail.com', 'user', 'NONE', '2026-04-05T21:58:37.276Z', '2026-04-06T21:58:37.276Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_a7fd68f6-4d4e-4202-9df9-2dd12a5837bf', 'usr_user_seed_001', 'user@cepat.top', 'user', 'LUNAS', '2026-04-06T01:15:39.852Z', '2026-04-07T01:15:39.852Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_bd4a43b3-3815-46c2-b71b-7cb0363f95ad', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T04:26:46.230Z', '2026-04-07T04:26:46.230Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_5592ffc7-82ce-4cf7-b5d9-73c919e405b5', 'usr_d0c79989-399c-481d-aba8-c8b051533e4d', 'putravito44@gmail.com', 'user', 'NONE', '2026-04-06T05:19:49.990Z', '2026-04-07T05:19:49.990Z', 1);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_f468edd1-868f-4cdb-be12-e0e0d3696d34', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T05:25:37.049Z', '2026-04-07T05:25:37.049Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_9a32f88c-d5b7-4159-871b-aebafcf50e23', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T06:15:48.988Z', '2026-04-07T06:15:48.988Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_9389fc6b-0fef-4879-b457-9f4dadd873c0', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T06:17:52.653Z', '2026-04-07T06:17:52.653Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_9c1c79a6-4c6e-4f6a-ad4e-74e186940951', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T06:21:59.329Z', '2026-04-07T06:21:59.329Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_d0ba4483-9ff0-4ed9-b520-1cd8fd2f6953', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T06:26:06.409Z', '2026-04-07T06:26:06.409Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_2f4dec56-41d5-490b-a7b8-eefd585809df', 'usr_admin_seed_001', 'admin@cepat.top', 'admin', 'LUNAS', '2026-04-06T06:26:49.944Z', '2026-04-07T06:26:49.944Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_3e0045ce-8a60-4020-b83f-9517a35c95fa', 'usr_a3cda169-520d-4772-827a-8156328fa9d9', 'goodplantindonesia@gmail.com', 'user', 'NONE', '2026-04-06T08:44:45.937Z', '2026-04-07T08:44:45.937Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_bd5d5ec2-1927-4c07-82be-de12970b14be', 'usr_a3cda169-520d-4772-827a-8156328fa9d9', 'goodplantindonesia@gmail.com', 'user', 'NONE', '2026-04-06T08:47:08.610Z', '2026-04-07T08:47:08.610Z', 0);
INSERT INTO `sessions` (`token_id`, `user_id`, `email`, `role`, `payment_status`, `created_at`, `expires_at`, `is_revoked`) VALUES ('tok_562795a9-88c8-412b-835f-b72e47507ab6', 'usr_a3cda169-520d-4772-827a-8156328fa9d9', 'goodplantindonesia@gmail.com', 'user', 'NONE', '2026-04-06T08:47:44.454Z', '2026-04-07T08:47:44.454Z', 0);

INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('target_sheet_id', '1hbhtYLqzSIRlZoIiB0my-05tSIXdgAOjPbgpf7dJIEs');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('gas_web_app', 'https://script.google.com/macros/s/AKfycbyEQM12lmuZ_Q7NrBC_OVEHXDHN49oLEe52GLuMbFbSiH3HSzz6PK1S7DULwnfuTp4U/exec');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('dummy_admin_email', 'admin@cepat.top');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('dummy_admin_password', 'Admin1234');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('dummy_user_email', 'user@cepat.top');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('dummy_user_password', 'User1234');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('auth_password_mode', 'PLAINTEXT');
INSERT INTO `readme_meta` (`field_name`, `field_value`) VALUES ('note', 'Copy each worksheet content (including header row) into matching tab in Google Sheets target.');

SET FOREIGN_KEY_CHECKS = 1;

