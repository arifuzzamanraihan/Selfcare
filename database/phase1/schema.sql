-- Selfcare phase 1 tables. All new tables use the sc_ prefix so they never
-- clash with the existing schema in database/selfcare.sql.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS sc_vendor_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  plural VARCHAR(120) NOT NULL,
  family VARCHAR(30) NOT NULL DEFAULT 'clinical',
  icon VARCHAR(30) NOT NULL DEFAULT 'building',
  tagline VARCHAR(190) NULL,
  description TEXT NULL,
  serves TEXT NULL COMMENT 'one bullet per line',
  body TEXT NULL COMMENT 'long text for the type page on the main website',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY sc_type_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra facts about a tenant (the existing tenants table is left untouched).
CREATE TABLE IF NOT EXISTS sc_vendors (
  tenant_id VARCHAR(191) PRIMARY KEY,
  type_slug VARCHAR(60) NOT NULL DEFAULT 'hospital',
  status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active | suspended',
  city VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  is_listed TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'show in the public directory',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sc_vendor_type (type_slug, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'USD',
  price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'per month',
  price_year DECIMAL(10,2) NULL COMMENT 'per year, NULL = not offered',
  price_label VARCHAR(40) NULL COMMENT 'shown instead of the price, e.g. Custom',
  duration_days INT UNSIGNED NULL COMMENT 'NULL = unlimited',
  max_staff INT UNSIGNED NULL COMMENT 'active staff users, NULL = unlimited',
  max_branches INT UNSIGNED NULL,
  max_patients INT UNSIGNED NULL COMMENT 'patient / customer records',
  max_appointments INT UNSIGNED NULL COMMENT 'requests per month',
  storage_gb DECIMAL(8,2) NULL,
  sms_per_month INT UNSIGNED NULL,
  email_per_month INT UNSIGNED NULL,
  custom_domains INT UNSIGNED NULL,
  custom_roles INT UNSIGNED NULL,
  audit_days INT UNSIGNED NULL COMMENT 'audit log retention',
  support_level VARCHAR(60) NULL,
  modules VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'comma separated module keys, empty = all',
  types VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'comma separated vendor type slugs, empty = all types',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL COMMENT 'NULL = never expires',
  status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active | revoked | replaced',
  note VARCHAR(255) NULL,
  assigned_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sc_sub_tenant (tenant_id, status),
  KEY sc_sub_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Website settings use the existing front_settings table (key, value, type, tenant_id). tenant_id NULL = main website.

-- Repeating website content (services, FAQ, news, gallery, features ...).
CREATE TABLE IF NOT EXISTS sc_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NULL,
  section VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  meta VARCHAR(190) NULL,
  image VARCHAR(255) NULL,
  published_at DATE NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sc_item_scope (tenant_id, section, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_appointment_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(191) NULL,
  doctor_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  preferred_date DATE NOT NULL,
  message VARCHAR(1000) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'new' COMMENT 'new | confirmed | done | cancelled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sc_appt_tenant (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(191) NULL,
  phone VARCHAR(40) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sc_msg_tenant (tenant_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY sc_reset_hash (token_hash),
  KEY sc_reset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time tokens that carry a signed-in user from the main site to a hospital site.
CREATE TABLE IF NOT EXISTS sc_login_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY sc_ltok_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_throttle (
  tkey CHAR(64) PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  first_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default plans (from the feature spec, section 9) are added by Plans::seedDefaults() when the table is empty.

-- ---------------------------------------------------------------------------
-- Role access. Mirrors the existing role_has_permissions pattern, scoped by tenant_id.
-- Default grants still live in the existing role_has_permissions table.
CREATE TABLE IF NOT EXISTS tenant_role_has_permissions (
  tenant_id VARCHAR(191) NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tenant_id, role_id, permission_id),
  KEY trhp_role (role_id),
  KEY trhp_perm (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seasonal or temporary access for one staff member. No row means normal access.
CREATE TABLE IF NOT EXISTS staff_access_windows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  valid_from DATETIME NULL,
  valid_until DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(190) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY saw_user (user_id),
  KEY saw_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly shifts for one staff member. day_of_week 0 = Sunday. No rows means any time.
CREATE TABLE IF NOT EXISTS staff_shifts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  KEY ss_user (user_id),
  KEY ss_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free services a vendor offers (tenant scoped like front_services).
CREATE TABLE IF NOT EXISTS free_services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  schedule VARCHAR(190) NULL,
  eligibility VARCHAR(190) NULL,
  icon VARCHAR(30) NULL,
  image VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  KEY fs_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- New permission names (existing ones are not touched).
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT n, 'web', NOW(), NOW() FROM (
  SELECT 'manage_catalog' AS n UNION ALL SELECT 'manage_free_services' UNION ALL SELECT 'view_messages'
  UNION ALL SELECT 'manage_access' UNION ALL SELECT 'view_plan'
) x WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.name = x.n);

-- ---------------------------------------------------------------------------
-- Roles from the feature spec (44 in total). The roles table is the existing "departments" table.
INSERT INTO departments (name, is_active, guard_name, created_at, updated_at)
SELECT n, 1, 'web', NOW(), NOW() FROM (
  SELECT 'Owner' AS n UNION ALL SELECT 'Medical Director' UNION ALL SELECT 'Branch Manager' UNION ALL SELECT 'Compliance Officer' UNION ALL SELECT 'IT Administrator' UNION ALL SELECT 'HR Officer' UNION ALL SELECT 'External Auditor' UNION ALL SELECT 'Consultant' UNION ALL SELECT 'Resident' UNION ALL SELECT 'Head Nurse' UNION ALL SELECT 'Midwife' UNION ALL SELECT 'Clinical Assistant' UNION ALL SELECT 'Optometrist' UNION ALL SELECT 'Therapist' UNION ALL SELECT 'Counsellor' UNION ALL SELECT 'Pathologist' UNION ALL SELECT 'Phlebotomist' UNION ALL SELECT 'Radiologist' UNION ALL SELECT 'Imaging Technologist' UNION ALL SELECT 'Pharmacy Assistant' UNION ALL SELECT 'Blood Bank Technician' UNION ALL SELECT 'Ward Attendant' UNION ALL SELECT 'Caregiver' UNION ALL SELECT 'Dispatcher' UNION ALL SELECT 'Driver' UNION ALL SELECT 'Paramedic' UNION ALL SELECT 'Billing Clerk' UNION ALL SELECT 'Insurance Officer' UNION ALL SELECT 'Medical Records Officer' UNION ALL SELECT 'Store Manager' UNION ALL SELECT 'Sales Representative' UNION ALL SELECT 'Field Engineer' UNION ALL SELECT 'Family Member' UNION ALL SELECT 'Referring Doctor' UNION ALL SELECT 'B2B Customer'
) x WHERE NOT EXISTS (SELECT 1 FROM departments d WHERE d.name = x.n);

-- Which roles a vendor switched on or off (the shipped defaults per vendor type are in core/config/roles.php).
CREATE TABLE IF NOT EXISTS tenant_roles (
  tenant_id VARCHAR(191) NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (tenant_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change of roles, grants, staff roles and plans. Append only.
CREATE TABLE IF NOT EXISTS permission_change_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  target_user_id BIGINT UNSIGNED NULL,
  role_name VARCHAR(191) NULL,
  action VARCHAR(40) NOT NULL,
  before_value TEXT NULL,
  after_value TEXT NULL,
  reason VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  KEY pcl_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Billing and printed documents. Same style as the existing bills / bill_items tables:
-- a header table and an items table, tenant_id on every row, readable numbers per tenant.
-- Invoice prefixes live in the existing "settings" table (key invoice_prefix_<type>, tenant_id).

-- Next number per tenant and document type. For platform (subscription) invoices tenant_id is '_platform'.
CREATE TABLE IF NOT EXISTS sc_number_sequences (
  tenant_id VARCHAR(191) NOT NULL,
  doc_type VARCHAR(40) NOT NULL,
  last_no INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tenant_id, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tenant_id is the vendor the invoice belongs to (for platform invoices: the vendor that is billed).
CREATE TABLE IF NOT EXISTS sc_invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  issuer VARCHAR(10) NOT NULL DEFAULT 'vendor' COMMENT 'vendor | platform',
  doc_type VARCHAR(40) NOT NULL,
  invoice_no VARCHAR(60) NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  party_name VARCHAR(190) NOT NULL,
  party_phone VARCHAR(40) NULL,
  party_address VARCHAR(255) NULL,
  patient_id INT UNSIGNED NULL COMMENT 'existing patients.id when the party is a registered patient',
  doctor_id BIGINT UNSIGNED NULL COMMENT 'existing doctors.id',
  related_id BIGINT UNSIGNED NULL COMMENT 'credit note: the invoice it reduces',
  reference VARCHAR(190) NULL,
  meta TEXT NULL COMMENT 'extra header fields as JSON',
  currency VARCHAR(8) NOT NULL DEFAULT 'USD',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(40) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'issued' COMMENT 'issued | paid | void',
  note VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY sc_inv_no (tenant_id, issuer, invoice_no),
  KEY sc_inv_date (tenant_id, invoice_date),
  KEY sc_inv_type (tenant_id, doc_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  code VARCHAR(60) NULL,
  item_name VARCHAR(255) NOT NULL,
  category VARCHAR(80) NULL COMMENT 'department / service group for revenue reports',
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(20) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  stock_item_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  KEY sc_ii_inv (invoice_id),
  CONSTRAINT sc_ii_inv_fk FOREIGN KEY (invoice_id) REFERENCES sc_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sc_invoice_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  paid_at DATETIME NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  method VARCHAR(40) NOT NULL DEFAULT 'Cash',
  reference VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  KEY sc_ip_inv (invoice_id),
  KEY sc_ip_tenant (tenant_id, paid_at),
  CONSTRAINT sc_ip_inv_fk FOREIGN KEY (invoice_id) REFERENCES sc_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forms that used to be filled in by hand. The filled values are kept as JSON in "data".
CREATE TABLE IF NOT EXISTS sc_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  doc_type VARCHAR(40) NOT NULL,
  doc_no VARCHAR(60) NOT NULL,
  doc_date DATE NOT NULL,
  party_name VARCHAR(190) NOT NULL,
  party_phone VARCHAR(40) NULL,
  patient_id INT UNSIGNED NULL COMMENT 'existing patients.id when linked',
  data LONGTEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'final' COMMENT 'final | void',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY sc_doc_no (tenant_id, doc_no),
  KEY sc_doc_type (tenant_id, doc_type, doc_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock for pharmacies, blood banks and equipment suppliers (the existing medicines table has no batch, expiry or reorder level).
CREATE TABLE IF NOT EXISTS sc_stock_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(191) NOT NULL,
  name VARCHAR(190) NOT NULL,
  sku VARCHAR(60) NULL,
  batch_no VARCHAR(60) NULL,
  unit VARCHAR(20) NULL,
  expiry_date DATE NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  sell_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  KEY sc_stock_tenant (tenant_id, name),
  KEY sc_stock_exp (tenant_id, expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
