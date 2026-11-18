-- 2026-11-18 Builder B: worldwide multi-currency (rates + a currency code on money rows).
-- Idempotent. Does not change amounts already stored. pinExisting() in install.php then
-- writes a currency code onto money rows that have none, using each vendor's current currency.

ALTER TABLE sc_currencies ADD COLUMN IF NOT EXISTS rate DECIMAL(20,8) NOT NULL DEFAULT 1 COMMENT 'units of this currency for 1 US dollar (used to convert, never to change stored amounts)';
ALTER TABLE sc_currencies ADD COLUMN IF NOT EXISTS rate_updated_at DATETIME NULL;
ALTER TABLE sc_ledger ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_records ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;
ALTER TABLE sc_stock_items ADD COLUMN IF NOT EXISTS currency VARCHAR(8) NULL;

-- New rows without an explicit code follow the platform default (PKR). Existing invoice and plan rows keep the code they already have.
ALTER TABLE sc_invoices MODIFY currency VARCHAR(8) NOT NULL DEFAULT 'PKR';
ALTER TABLE sc_plans MODIFY currency VARCHAR(8) NOT NULL DEFAULT 'PKR';
