-- Plans: audience (who it is for), feature lines, size label, private flag (hidden from the website and registration); registration remembers monthly or yearly
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS audience VARCHAR(80) NULL;
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS size_label VARCHAR(60) NULL;
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS features TEXT NULL;
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS is_private TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE sc_registration_requests ADD COLUMN IF NOT EXISTS billing_period VARCHAR(10) NULL;
