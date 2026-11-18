-- Doctors' medicine master: one private, editable list per vendor (seeded from core/config/medicines.php the first time it is opened).
CREATE TABLE IF NOT EXISTS sc_medicines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  generic VARCHAR(190) NOT NULL,
  brand VARCHAR(190) NULL,
  strength VARCHAR(60) NULL,
  form VARCHAR(40) NULL,
  drug_class VARCHAR(80) NULL,
  route VARCHAR(30) NULL,
  dose VARCHAR(60) NULL,
  freq VARCHAR(60) NULL,
  timing VARCHAR(40) NULL,
  dur_n SMALLINT UNSIGNED NULL,
  dur_unit VARCHAR(10) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  source VARCHAR(8) NOT NULL DEFAULT 'vendor' COMMENT 'seed | vendor | auto',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  KEY sc_med_tenant (tenant_id, active, generic),
  KEY sc_med_brand (tenant_id, brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
