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

ALTER TABLE sc_domains ADD COLUMN IF NOT EXISTS cert_name VARCHAR(190) NULL AFTER cert_at;

ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS tag VARCHAR(40) NULL COMMENT 'priority or label, set by the module' AFTER notes;
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS invoice_id BIGINT UNSIGNED NULL COMMENT 'sc_invoices.id of the bill made from this record' AFTER tag;

-- every work-list record gets a first history line when it has none (idempotent)
INSERT INTO sc_record_events (tenant_id, record_id, module, action, from_status, to_status, user_id, created_at)
  SELECT r.tenant_id, r.id, r.module, 'created', NULL, r.status, r.created_by, COALESCE(r.created_at, NOW()) FROM sc_records r
  WHERE NOT EXISTS (SELECT 1 FROM sc_record_events e WHERE e.record_id = r.id);
