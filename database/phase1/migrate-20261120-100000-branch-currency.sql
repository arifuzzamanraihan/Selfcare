-- Each clinic branch can have its own currency and time zone (empty = the vendor's). Lab money rows record their own currency.
ALTER TABLE sc_branches ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_branches ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) NULL;
ALTER TABLE sc_lab_orders ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_lab_order_items ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_commissions ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_lab_home ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_staff_profiles ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_salary ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_salary_adj ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
