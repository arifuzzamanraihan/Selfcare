# Developer changelog (shared files others may touch)

## 2026-11-18 clinic-only ship (Builder C)
The work-list engine and the **clinic** token queue are in this deploy. Other type-specific lists are not switched on this week: their configs were removed from this branch (full copy kept on `builder-c-wave2-wip`). Checklists for the remaining types are in `docs/CHECKLIST-*.md`. Tokens are limited to vendor type `clinic`.

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
## 2026-11-18 – Wave 2 work-list engine and type-specific lists (Builder C)
### Engine (`sc_records`, `core/Ops.php`, `core/routes/ops.php`)
- Additive migration `database/phase1/migrate-20261118-225200-wave2-ops.sql`: `sort_order`, `meta` (JSON), `person_id` (patients.id), `staff_id` (doctors.id). Hooked from `schema.sql` and `install.php` after `migrate-r4.sql`.
- Daily numeric codes (`number => daily`) for token-style lists; empty codes autogenerate from the prefix or the daily serial.
- Queue view: today's list, drag-and-drop, ↑/↓, Alt+Up/Down, Call next, now-serving board (token + doctor, no patient names, 15s refresh). Nested forms were removed from the queue table so action buttons stay valid HTML. Reorder POST sends `_csrf`.
- Generic asset lock (`need` / `to`) so a machine or caregiver cannot be double-booked.
- `refresh()` expires every module that declares `expire` (not only blood units) — rentals become overdue past the due date.
- Field types: `teeth` (FDI 11–48 odontogram in `core/Crud.php`); dynamic `patients` / `doctors` options with fill-in of name and phone.
- `billPrefill` placeholders: `{title}`, `{party}`, `{place_a}`, `{place_b}` as well as `{code}`, `{cat}`, `{qty}`.
- Generic HTML print (letterhead, chosen rows, notes, sign box). Flow steps pack `meta` so ask-fields do not hit unknown columns.
- Reports: `Reports::forFamily` merges ops registers; `run()` can print any `sc_records` register a module declares.
- Integrity: records must point at a patient or doctor of the same vendor when those ids are set.
- Layout: `templates/dash/layout.php` gained an optional `$head` slot (board refresh) and loads `public/js/ops-queue.js`. Queue/board/odontogram styles live in `public/css/selfcare-dash.css`.

### Type configs (`core/config/ops/*.php`) and demo seed
New modules (37 keys globally unique; pharmacy, laboratory samples, blood bank and ambulance unchanged): `tokens`, `procedures`, `txplans`, `eyexams`, `optical`, `catalogue`, `studies`, `dmachines`, `dsessions`, `anc`, `vaccines`, `deliveries`, `mhsessions`, `mhplans`, `consults`, `remedies`, `residents`, `ncare`, `mar`, `incidents`, `rhplans`, `rhsessions`, `rhpacks`, `tlsessions`, `caregivers`, `hcvisits`, `eqitems`, `rentals`, `service`.
Permissions reused only (`manage_appointments`, `manage_prescriptions`, `manage_catalog`, `manage_medicines`). Demo rows added in `database/phase1/seed-ops.php` for every demo vendor of those kinds.

### Out of scope in this change
Hospital departments (OPD/IPD/ICU/OT/wards/beds), doctor portal, patient portal and booking remain Builder A follow-up. UI button pass remains Builder B.
