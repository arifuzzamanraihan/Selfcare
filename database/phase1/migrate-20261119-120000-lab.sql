-- Clinic lab suite (branches, test catalogue, orders, samples, results, approval, commissions, finance, stock, staff, salary, records). All tables are tenant and branch scoped. Idempotent.
CREATE TABLE IF NOT EXISTS sc_branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  code VARCHAR(12) NOT NULL,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  report_email VARCHAR(190) NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY lab_br_code (tenant_id, code),
  KEY lab_br_t (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_user_branches (
  tenant_id VARCHAR(191) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL COMMENT '0 = every branch (central)',
  PRIMARY KEY (tenant_id, user_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_user_perms (
  tenant_id VARCHAR(191) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  perm VARCHAR(40) NOT NULL,
  mode VARCHAR(8) NOT NULL DEFAULT 'grant' COMMENT 'grant | deny',
  actions VARCHAR(120) NOT NULL DEFAULT '',
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (tenant_id, user_id, perm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY lab_dep_n (tenant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(160) NOT NULL,
  dept_id INT UNSIGNED NULL,
  specimen VARCHAR(60) NOT NULL DEFAULT 'Blood',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  tat_hours INT NOT NULL DEFAULT 24,
  instructions VARCHAR(500) NULL,
  is_panel TINYINT(1) NOT NULL DEFAULT 0,
  approved TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = confirmed by the authorised pathologist',
  sample_data TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = sample entry to be confirmed by the laboratory',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY lab_t_code (tenant_id, code),
  KEY lab_t_dep (tenant_id, dept_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_test_params (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  unit VARCHAR(30) NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'number' COMMENT 'number | text',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY lab_p_t (tenant_id, test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_ref_ranges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  param_id INT UNSIGNED NOT NULL,
  sex VARCHAR(2) NOT NULL DEFAULT 'A' COMMENT 'A any, M, F',
  age_min DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'years',
  age_max DECIMAL(6,2) NOT NULL DEFAULT 150,
  low DECIMAL(14,4) NULL,
  high DECIMAL(14,4) NULL,
  text VARCHAR(120) NULL,
  KEY lab_rr_p (tenant_id, param_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_branch_tests (
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NULL,
  available TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (tenant_id, branch_id, test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_referrers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'doctor' COMMENT 'doctor | partner',
  doctor_id BIGINT UNSIGNED NULL COMMENT 'doctors.id when the doctor is on the clinic team',
  name VARCHAR(160) NOT NULL,
  speciality VARCHAR(120) NULL,
  org VARCHAR(160) NULL,
  contact_person VARCHAR(120) NULL,
  phone VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  cnic VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  agreement VARCHAR(500) NULL,
  user_id BIGINT UNSIGNED NULL COMMENT 'users.id of a Referring Doctor login',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY lab_ref_t (tenant_id, kind, active),
  KEY lab_ref_d (tenant_id, doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_comm_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  referrer_id INT UNSIGNED NOT NULL,
  scope VARCHAR(8) NOT NULL DEFAULT 'default' COMMENT 'default | dept | test',
  dept_id INT UNSIGNED NULL,
  test_id INT UNSIGNED NULL,
  basis VARCHAR(8) NOT NULL DEFAULT 'test' COMMENT 'patient | test',
  vtype VARCHAR(5) NOT NULL DEFAULT 'pct' COMMENT 'pct | fixed',
  value DECIMAL(12,4) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  KEY lab_cr_r (tenant_id, referrer_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  order_no VARCHAR(40) NOT NULL,
  invoice_id BIGINT UNSIGNED NULL COMMENT 'sc_invoices.id, the bill of this order',
  invoice_no VARCHAR(40) NULL,
  patient_id INT UNSIGNED NOT NULL,
  order_date DATETIME NOT NULL,
  booked_by BIGINT UNSIGNED NULL,
  referral_type VARCHAR(8) NOT NULL DEFAULT 'none' COMMENT 'none | doctor | partner',
  referrer_id INT UNSIGNED NULL,
  collection VARCHAR(6) NOT NULL DEFAULT 'lab' COMMENT 'lab | home',
  home_id BIGINT UNSIGNED NULL,
  token_id BIGINT UNSIGNED NULL,
  rx_id BIGINT UNSIGNED NULL,
  ordered_by_doctor BIGINT UNSIGNED NULL COMMENT 'doctors.id for an order sent from the doctor workspace',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_reason VARCHAR(190) NULL,
  home_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'booked' COMMENT 'requested | booked | cancelled',
  cancel_reason VARCHAR(255) NULL,
  clinical_note VARCHAR(500) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY lab_o_no (tenant_id, order_no),
  KEY lab_o_b (tenant_id, branch_id, order_date),
  KEY lab_o_p (tenant_id, patient_id),
  KEY lab_o_ref (tenant_id, referral_type, referrer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  test_name VARCHAR(160) NOT NULL,
  dept_id INT UNSIGNED NULL,
  dept_name VARCHAR(100) NULL,
  specimen VARCHAR(60) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  net DECIMAL(12,2) NOT NULL DEFAULT 0,
  sample_id BIGINT UNSIGNED NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending collected received processing completed rejected recollect cancelled',
  report_status VARCHAR(14) NOT NULL DEFAULT 'none' COMMENT 'none draft pending_review approved delivered',
  version INT NOT NULL DEFAULT 1,
  signatory_id INT UNSIGNED NULL,
  entered_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  delivered_at DATETIME NULL,
  report_no VARCHAR(40) NULL,
  KEY lab_oi_o (tenant_id, order_id),
  KEY lab_oi_b (tenant_id, branch_id, status),
  KEY lab_oi_s (tenant_id, sample_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_samples (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  barcode VARCHAR(40) NOT NULL,
  specimen VARCHAR(60) NOT NULL,
  collection VARCHAR(6) NOT NULL DEFAULT 'lab',
  status VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending collected received processing completed rejected recollect',
  collected_at DATETIME NULL,
  collected_by BIGINT UNSIGNED NULL,
  received_at DATETIME NULL,
  received_by BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NULL,
  UNIQUE KEY lab_s_bc (tenant_id, barcode),
  KEY lab_s_o (tenant_id, order_id),
  KEY lab_s_b (tenant_id, branch_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  param_id INT UNSIGNED NOT NULL,
  param_name VARCHAR(120) NOT NULL,
  value VARCHAR(255) NULL,
  unit VARCHAR(30) NULL,
  ref_text VARCHAR(120) NULL,
  flag VARCHAR(2) NOT NULL DEFAULT '',
  comment VARCHAR(255) NULL,
  entered_by BIGINT UNSIGNED NULL,
  entered_at DATETIME NULL,
  UNIQUE KEY lab_r_ip (item_id, param_id),
  KEY lab_r_t (tenant_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_report_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  version INT NOT NULL,
  action VARCHAR(16) NOT NULL COMMENT 'submit approve amend reject',
  reason VARCHAR(255) NULL,
  snapshot MEDIUMTEXT NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  KEY lab_rl_i (tenant_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_signatories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  name VARCHAR(120) NOT NULL,
  title VARCHAR(160) NULL,
  reg_no VARCHAR(60) NULL,
  user_id BIGINT UNSIGNED NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  KEY lab_sg_t (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_commissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  referrer_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'accrual' COMMENT 'accrual | adjustment',
  amount DECIMAL(14,2) NOT NULL,
  base DECIMAL(14,2) NOT NULL DEFAULT 0,
  rule VARCHAR(160) NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NULL,
  KEY lab_cm_r (tenant_id, referrer_id),
  KEY lab_cm_o (tenant_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_home (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  req_no VARCHAR(40) NOT NULL,
  patient_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NOT NULL,
  address VARCHAR(255) NOT NULL,
  location_note VARCHAR(255) NULL,
  test_ids VARCHAR(500) NULL,
  collect_at DATETIME NULL,
  charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  collector_id BIGINT UNSIGNED NULL COMMENT 'users.id',
  status VARCHAR(12) NOT NULL DEFAULT 'scheduled' COMMENT 'scheduled assigned collected received completed cancelled',
  order_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  UNIQUE KEY lab_h_no (tenant_id, req_no),
  KEY lab_h_b (tenant_id, branch_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_delivery_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  doc_type VARCHAR(10) NOT NULL COMMENT 'report | receipt',
  doc_ref VARCHAR(60) NULL,
  channel VARCHAR(10) NOT NULL COMMENT 'whatsapp | email | print | download',
  recipient VARCHAR(190) NULL,
  recipient_name VARCHAR(160) NULL,
  relation VARCHAR(12) NOT NULL DEFAULT 'patient' COMMENT 'patient | guardian | other',
  message VARCHAR(1000) NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'queued' COMMENT 'queued sent failed opened',
  error VARCHAR(255) NULL,
  tries INT NOT NULL DEFAULT 1,
  token VARCHAR(64) NULL,
  expires_at DATETIME NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY lab_dl_tok (token),
  KEY lab_dl_o (tenant_id, order_id),
  KEY lab_dl_b (tenant_id, branch_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_report_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  report_date DATE NOT NULL,
  recipient VARCHAR(190) NULL,
  subject VARCHAR(255) NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'queued' COMMENT 'queued sent failed',
  error VARCHAR(255) NULL,
  tries INT NOT NULL DEFAULT 0,
  files VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  KEY lab_rr_b (tenant_id, branch_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_lab_backups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  file VARCHAR(190) NOT NULL,
  size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'manual',
  verified TINYINT(1) NOT NULL DEFAULT 0,
  action VARCHAR(10) NOT NULL DEFAULT 'backup' COMMENT 'backup | restore',
  status VARCHAR(8) NOT NULL DEFAULT 'ok',
  note VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  KEY lab_bk_t (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_record_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL DEFAULT 0,
  entity VARCHAR(20) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  action VARCHAR(16) NOT NULL,
  before_json TEXT NULL,
  after_json TEXT NULL,
  reason VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY lab_hi_e (tenant_id, entity, entity_id),
  KEY lab_hi_t (tenant_id, created_at),
  KEY lab_hi_a (tenant_id, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE patients ADD COLUMN IF NOT EXISTS guardian VARCHAR(160) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS age_value DECIMAL(6,2) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS age_unit VARCHAR(8) NOT NULL DEFAULT 'years';
ALTER TABLE patients ADD COLUMN IF NOT EXISTS cnic VARCHAR(40) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(40) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS reg_notes VARCHAR(500) NULL;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_invoices ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_invoice_payments ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_invoice_payments ADD COLUMN IF NOT EXISTS receipt_no VARCHAR(40) NULL;
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS source VARCHAR(14) NULL COMMENT 'salary commission stock, empty for a hand entered expense';
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS source_id BIGINT UNSIGNED NULL;
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS referrer_id INT UNSIGNED NULL;
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS void_reason VARCHAR(255) NULL;
ALTER TABLE sc_stock_items ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_stock_items ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL;
ALTER TABLE sc_stock_items ADD COLUMN IF NOT EXISTS supplier VARCHAR(160) NULL;
ALTER TABLE sc_stock_moves ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL;
ALTER TABLE sc_stock_moves ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(14,2) NULL;
ALTER TABLE sc_stock_moves ADD COLUMN IF NOT EXISTS supplier VARCHAR(160) NULL;

CREATE TABLE IF NOT EXISTS sc_staff_profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  staff_no VARCHAR(30) NOT NULL,
  designation VARCHAR(80) NULL,
  cnic VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  joining_date DATE NULL,
  branch_id INT UNSIGNED NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  salary_terms VARCHAR(255) NULL,
  pin_hash VARCHAR(100) NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY sp_no (tenant_id, staff_no),
  KEY sp_b (tenant_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  att_date DATE NOT NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'present' COMMENT 'present absent leave late',
  check_in DATETIME NULL,
  check_out DATETIME NULL,
  note VARCHAR(255) NULL,
  source VARCHAR(8) NOT NULL DEFAULT 'pin',
  UNIQUE KEY att_ud (tenant_id, user_id, att_date),
  KEY att_b (tenant_id, branch_id, att_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_salary (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  base DECIMAL(12,2) NOT NULL DEFAULT 0,
  bonus DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  advances DECIMAL(12,2) NOT NULL DEFAULT 0,
  net DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'due' COMMENT 'due partial paid',
  created_at DATETIME NULL,
  UNIQUE KEY sal_up (tenant_id, user_id, period),
  KEY sal_b (tenant_id, branch_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_salary_adj (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  salary_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL COMMENT 'bonus deduction advance',
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  KEY sa_s (tenant_id, salary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, guard_name, created_at, updated_at)
  SELECT n.name, 'web', NOW(), NOW() FROM (
    SELECT 'lab_booking' name UNION ALL SELECT 'lab_samples' UNION ALL SELECT 'lab_results' UNION ALL SELECT 'lab_approve' UNION ALL SELECT 'lab_catalogue'
    UNION ALL SELECT 'lab_referrers' UNION ALL SELECT 'lab_commission' UNION ALL SELECT 'lab_finance' UNION ALL SELECT 'lab_inventory' UNION ALL SELECT 'lab_staff'
    UNION ALL SELECT 'lab_attendance' UNION ALL SELECT 'lab_salary' UNION ALL SELECT 'lab_share' UNION ALL SELECT 'lab_records' UNION ALL SELECT 'lab_branches' UNION ALL SELECT 'lab_reports'
  ) n WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.name = n.name);

-- Clinics that already saved role grants get the lab modules for the matching roles (idempotent). Clinics that never changed a role follow the defaults in config/access.php.
INSERT INTO tenant_role_has_permissions (tenant_id, role_id, permission_id)
  SELECT g.tenant_id, g.role_id, p.id FROM (SELECT DISTINCT t.tenant_id, t.role_id, d.name rname FROM tenant_role_has_permissions t JOIN departments d ON d.id = t.role_id JOIN sc_vendors v ON v.tenant_id = t.tenant_id AND v.type_slug = 'clinic') g
  JOIN (
    SELECT 'Receptionist' r, 'lab_booking' n UNION ALL SELECT 'Receptionist', 'lab_samples' UNION ALL SELECT 'Receptionist', 'lab_share'
    UNION ALL SELECT 'Phlebotomist', 'lab_samples' UNION ALL SELECT 'Lab Technician', 'lab_samples' UNION ALL SELECT 'Lab Technician', 'lab_results'
    UNION ALL SELECT 'Pathologist', 'lab_results' UNION ALL SELECT 'Pathologist', 'lab_approve' UNION ALL SELECT 'Pathologist', 'lab_share' UNION ALL SELECT 'Pathologist', 'lab_reports'
    UNION ALL SELECT 'Accountant', 'lab_finance' UNION ALL SELECT 'Accountant', 'lab_commission' UNION ALL SELECT 'Accountant', 'lab_salary' UNION ALL SELECT 'Accountant', 'lab_reports'
    UNION ALL SELECT 'HR Officer', 'lab_staff' UNION ALL SELECT 'HR Officer', 'lab_attendance' UNION ALL SELECT 'HR Officer', 'lab_salary'
    UNION ALL SELECT 'Store Manager', 'lab_inventory'
    UNION ALL SELECT 'Branch Manager', 'lab_booking' UNION ALL SELECT 'Branch Manager', 'lab_samples' UNION ALL SELECT 'Branch Manager', 'lab_results' UNION ALL SELECT 'Branch Manager', 'lab_approve' UNION ALL SELECT 'Branch Manager', 'lab_catalogue' UNION ALL SELECT 'Branch Manager', 'lab_referrers' UNION ALL SELECT 'Branch Manager', 'lab_commission' UNION ALL SELECT 'Branch Manager', 'lab_finance' UNION ALL SELECT 'Branch Manager', 'lab_inventory' UNION ALL SELECT 'Branch Manager', 'lab_staff' UNION ALL SELECT 'Branch Manager', 'lab_attendance' UNION ALL SELECT 'Branch Manager', 'lab_share' UNION ALL SELECT 'Branch Manager', 'lab_records' UNION ALL SELECT 'Branch Manager', 'lab_reports'
  ) m ON m.r = g.rname
  JOIN permissions p ON p.name = m.n
  WHERE NOT EXISTS (SELECT 1 FROM tenant_role_has_permissions x WHERE x.tenant_id = g.tenant_id AND x.role_id = g.role_id AND x.permission_id = p.id);
