# Developer changelog (shared files others may touch)

## 2026-11-18 dynamic roles + multi-currency (builder A)
- NEW `core/RoleUi.php`, `core/routes/roletpl.php` (added to the route list in bootstrap), `tests/roles*.py`, `tests/currency*.{php,py}`.
- `Portal::boot` now calls `gate()` (action permissions) and sets `Portal::$current`; `$app->dash` runs `Portal::filterLinks()` on every portal page. New portal routes must keep the usual path names (`.../new`, `.../save`, `.../{id}/delete`, `.../pdf`, `.../print`, `format=csv`) so the action classifier (`Access::classify`) maps them correctly. A new module needs an entry in `Access::permForPath` and `perm_actions` (core/config/access.php).
- Money: **multi-currency was built in this change** (Currency::rate/convert/sql/normalize, per-row currency on sc_ledger/sc_records/sc_stock_items, rate column on sc_currencies, platform default PKR). Builder B: do not redo it; extend it.
- Migrations: schema.sql + migrate-r4.sql (idempotent ALTER ... IF NOT EXISTS). New migrations: use unique timestamped files and call them from install.php.
