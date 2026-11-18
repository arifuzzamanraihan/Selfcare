# Developer changelog (shared files others may touch)

## 2026-11-18 dynamic roles + multi-currency (builder A)
- NEW `core/RoleUi.php`, `core/routes/roletpl.php` (added to the route list in bootstrap), `tests/roles*.py`, `tests/currency*.{php,py}`.
- `Portal::boot` now calls `gate()` (action permissions) and sets `Portal::$current`; `$app->dash` runs `Portal::filterLinks()` on every portal page. New portal routes must keep the usual path names (`.../new`, `.../save`, `.../{id}/delete`, `.../pdf`, `.../print`, `format=csv`) so the action classifier (`Access::classify`) maps them correctly. A new module needs an entry in `Access::permForPath` and `perm_actions` (core/config/access.php).
- Money: multi-currency foundations (Currency::rate/convert/sql/normalize, per-row currency on sc_ledger/sc_records/sc_stock_items, rate column on sc_currencies, platform default PKR).
- Migrations: schema.sql + migrate-r4.sql (idempotent ALTER ... IF NOT EXISTS). New migrations: use unique timestamped files and call them from install.php.

## 2026-11-18 multi-currency extensions (builder B)

Extends the currency work already on main. Super admin manages any ISO 4217 currency (code, symbol, position, decimals, separators, exchange rate, enabled). Major world currencies are seeded. Platform and **new** vendors default to PKR (Rs), country Pakistan, timezone Asia/Karachi. Existing vendors stay on the currency they were already showing.

Added or tightened:

- `Currency::stamp` / `visitor()` cache; public pricing cookie is set in `App` before HTML is sent.
- `Database::insert()` writes the vendor currency onto money rows that omit it (including invoices).
- `Plans::yearPrice()`; pricing page yearly amounts follow the chosen currency.
- Reports convert remaining money totals (dues, invoice/payments/stock/trip registers, MRR, subscription lists). Platform reports use the platform currency (PKR). `sumCol` strips any symbol so PKR totals add up.
- `Ops` money tiles convert. `Billing::create` defaults to the vendor currency.
- New `database/phase1/migrate-20261118-currency-rates.sql` (idempotent; also still covered by migrate-r4.sql).
- Vendor settings: country and time zone. `/pricing?cur=` is kept on redirect.

Tests: `tests/currency.php`, `tests/currency_web.py` (set `SELFCARE_ROOT` and `SCPORT` when running from a worktree).
