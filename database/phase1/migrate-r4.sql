-- Round 4 changes for databases that already exist. Safe to run twice (MariaDB: ADD COLUMN IF NOT EXISTS).
ALTER TABLE sc_vendors ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL COMMENT 'soft delete time, NULL while the vendor exists';
ALTER TABLE sc_vendors ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;
ALTER TABLE sc_vendors ADD COLUMN IF NOT EXISTS prev_status VARCHAR(16) NULL COMMENT 'status before the soft delete, used by Restore';
ALTER TABLE sc_login_tokens ADD COLUMN IF NOT EXISTS impersonator_id BIGINT UNSIGNED NULL COMMENT 'super admin who opened this vendor portal (Open portal)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL COMMENT 'last real sign in (not Open portal)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL COMMENT 'soft delete time (staff, doctors, patients)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS other_prices TEXT NULL COMMENT 'extra prices, one per line like PKR 8000';
ALTER TABLE sc_appointment_requests ADD COLUMN IF NOT EXISTS reason VARCHAR(240) NULL COMMENT 'why it was rejected or cancelled';
ALTER TABLE sc_appointment_requests ADD COLUMN IF NOT EXISTS decided_by BIGINT UNSIGNED NULL;
ALTER TABLE sc_appointment_requests ADD COLUMN IF NOT EXISTS decided_at DATETIME NULL;
