-- A prescription sent to a pharmacy with the patient's agreement: who sent it, to whom, and the pharmacy order it became (sc_records, module rx, of the pharmacy).
CREATE TABLE IF NOT EXISTS sc_rx_shares (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL COMMENT 'vendor that wrote the prescription',
  prescription_id BIGINT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NULL,
  to_tenant VARCHAR(191) NOT NULL COMMENT 'the pharmacy',
  record_id BIGINT UNSIGNED NULL COMMENT 'sc_records.id of the order in the pharmacy',
  sent_by VARCHAR(10) NOT NULL DEFAULT 'patient' COMMENT 'patient | staff',
  sent_user_id BIGINT UNSIGNED NULL,
  consent TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  KEY sc_rxs_rx (tenant_id, prescription_id),
  KEY sc_rxs_to (to_tenant, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
