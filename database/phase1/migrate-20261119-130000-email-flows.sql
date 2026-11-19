-- Email verification, change of email, branded mail queue with retry and delivery log
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_email VARCHAR(191) NULL;
UPDATE users SET email_verified_at = COALESCE(created_at, NOW()) WHERE email_verified_at IS NULL AND created_at < '2026-11-19 12:00:00';

CREATE TABLE IF NOT EXISTS sc_email_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purpose VARCHAR(20) NOT NULL COMMENT 'verify | change | register',
  user_id BIGINT UNSIGNED NULL,
  ref_id BIGINT UNSIGNED NULL,
  email VARCHAR(191) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY et_hash (token_hash),
  KEY et_user (user_id, purpose),
  KEY et_ref (ref_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_mail_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NULL,
  user_id BIGINT UNSIGNED NULL,
  kind VARCHAR(30) NOT NULL,
  to_email VARCHAR(191) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_text MEDIUMTEXT NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'queued' COMMENT 'queued | sent | failed | dead',
  tries TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  next_try_at DATETIME NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY mq_status (status, next_try_at),
  KEY mq_tenant (tenant_id, created_at),
  KEY mq_to (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sc_registration_requests ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;
