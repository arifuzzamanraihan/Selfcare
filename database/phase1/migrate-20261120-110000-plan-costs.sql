-- Plans: free trial length, price of one extra branch (per month), monthly email allowance already exists; subscriptions: extra branches bought
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS trial_days INT UNSIGNED NULL;
ALTER TABLE sc_plans ADD COLUMN IF NOT EXISTS extra_branch_price DECIMAL(10,2) NULL;
ALTER TABLE sc_subscriptions ADD COLUMN IF NOT EXISTS extra_branches INT UNSIGNED NOT NULL DEFAULT 0;
