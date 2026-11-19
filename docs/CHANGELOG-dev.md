# Developer changelog (shared files others may touch)

## 2026-11-19 Haider lab suite, email flows, unified login (clinic vendor type)
- NEW `core/Lab.php`, `core/LabDocs.php`, `core/routes/lab.php`, `lab2.php`: laboratory suite inside the clinic type (branches, MR patients, booking on `test_invoice`, samples, results and approval, sharing, commission, finance, inventory, attendance, salary, home collection, reports, backups, audit). Migration `migrate-20261119-120000-lab.sql`. Clinic type now enables HR Officer, Pathologist, Phlebotomist, Store Manager (`config/roles.php`).
- Clinic integration: doctor workspace Lab tests box and `POST /portal/workspace/{id}/lab-order`, `GET /portal/workspace/lab/{id}/pdf` (`routes/workspace.php`); patient `My lab reports` at `/portal/my/lab` (`routes/mycare.php`, `Portal::nav`); `test_invoice` offered in the clinical document family.
- NEW `core/Mail.php` (branded mails, queue, retry, tokens), `bin/mail-queue.php`, `bin/lab-cron.php`; `Mailer` multipart; migration `migrate-20261119-130000-email-flows.sql` (`users.email_verified_at`, `pending_email`, `sc_email_tokens`, `sc_mail_queue`). Auth routes: verify, resend, reset notice, email change; `/admin/mail` delivery log.
- Unified login: `/login` is the only form; 14 legacy login paths redirect to it; `next` and host hand off carry the user to the right portal. `Portal::boot` sends guests to `login?next=`.
- Tests: `tests/haider.py`, `tests/emailflows.py`, `tests/login_all.py`. Doc: `docs/REQ-haider-lab-traceability.md`.

## 2026-11-19 hospital (Builder A, touches Builder C's engine)
- NEW `core/config/ops/hospital.php` (modules beds, admissions, emergency, theatre) for type `hospital`.
- `core/config/ops/clinic.php`: the `tokens` module is now also for `hospital` (OPD queue + doctor workspace). Menu group shows "Hospital" for hospitals (`Portal.php`).
- `core/routes/ops.php`: three small engine options, all optional and unused by other modules: flow `calc => 'bed_days'` (days x bed rate into qty/amount), `asset.release` (old asset goes to a status when the asset changes, used for bed transfer), module `slot` (one active booking per resource and time, used for theatres); `admissions` edit cannot change the bed of an admitted patient.
- `core/Vendors.php`: `HOSPITAL_DEPARTMENTS` (28) seeded on registration; migration `migrate-20261119-000500-hospital-departments.sql` fills existing hospitals that still have the short list.
- `core/Reports.php`: bed occupancy report reads the bed board when present.
- New permission `manage_ipd` (Wards, admissions, emergency and theatre) for hospital roles: `config/access.php` (permissions, perm_actions, defaults), `Access::permsForType`, `RoleUi::MODULE`, migration `migrate-20261119-001500-manage-ipd.sql`.
- Docs: `docs/CHECKLIST-core.md` (new), `docs/CHECKLIST-hospital.md` (rewritten). Tests: `tests/hospital.py`.

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

## 2026-11-18 UI/UX standing rules (builder B)

Shared layout rules for page actions, applied first to Clinic, Hospital and Pharmacy portals plus the public home and register pages. Other screens inherit the components and are listed in `docs/CHECKLIST-uiux.md` for next week.

- `templates/dash/layout.php`: the page primary moves into the sticky `.d-top` header (title and subtitle on the left, actions on the right, before the theme and user). The old `.d-phead` action row is gone.
- `core/Listing.php`: more than two row actions keep the first two in the row and put the rest in a kebab (`.d-more`). Filter lists with more than 12 options are searchable.
- `core/Crud.php`: form footer is sticky, Cancel on the left, primary on the right. Selects with more than 12 options are searchable (`data-searchable`).
- `public/css/selfcare-dash.css`, `public/css/selfcare-ui.css`, `public/js/selfcare-app.js`: 44px touch targets, overflow menu positioning, searchable select UI, one visible primary in the header (later primaries are visually demoted).
- Public header: Sign in (soft) + Register (primary) in `.sc-nav-cta`. Vendor sites: Book (primary) + Sign in (soft). Register: Cancel + Create my account in a sticky `.sc-form-actions` bar.
- `core/helpers.php`: `more` icon for the kebab.
- Tests: `tests/uiux_layout.py`. Screenshots: `shots/uiux/before/` and `shots/uiux/after/`.


## 2026-11-19 phone header, filters, cards and impersonation banner (builder B)

Central layout fix so phone screens stay on one header row everywhere (portal and super admin).

- Sticky header stays one row on phones (menu, title, optional kebab for page actions, theme, avatar). Extra header actions go in a kebab so the title stays readable. Max header height is about 72px.
- Stat and type tiles stay two per row above 340px.
- Listing filters: label above the select, Role and State side by side, Apply under them, aligned.
- Phone table cards wrap long values (email addresses) instead of overflowing.
- The "viewing as super admin" bar is one compact line with a small Return button.

## 2026-11-19 phone titles wrap on two lines (builder B)

Long page titles on phones wrap onto a second line instead of being cut off with an ellipsis. The header stays one row and under 88px. Full-site layout crawl at 360, 390, 768 and 1440 in light and dark passed with 0 failures.

## 2026-11-19 type card names wrap at words (builder B)

Vendor type cards on phones wrap their names at word boundaries instead of mid-word. Below 380px the name sits on its own row so short labels such as Hospital stay intact.

## 2026-11-18 medicine master, doctor workspace, patient "My care", send-to-pharmacy (builder A)
- Migrations are now picked up by `install.php` with `glob('migrate-2*.sql')` in name order (builder C: you can drop your explicit line, it is harmless either way). New: `migrate-20261118-231000-medicine-master.sql` (sc_medicines), `-233000-rx-queue-link.sql` (sc_prescriptions.queue_record_id), `-234500-rx-shares.sql` (sc_rx_shares).
- **Doctor workspace builds on builder C's clinic work list "tokens"** (sc_records module `tokens`, daily numbers, `sort_order` drag reorder, `Ops::event`). Nothing in the queue engine was changed. New: `core/routes/workspace.php` (`/portal/workspace`, call-next, start/skip/requeue/finish), `Rx::queueAdvance/queueStartNext`. The prescription editor (`core/routes/rx.php`) is exposed as `$app->rxForm` and shows "Issue and call next patient" when it gets `_queue`. If you change token statuses (`waiting, called, in_consult, done, skipped`), update `Rx::queueAdvance`.
- **Patient "My care"** (role Patient on the vendor site): `core/routes/mycare.php` (`/portal/my/prescriptions|bills|records|visits`, PDFs). Reads only rows with `patients.user_id = me`. Sidebar for Patient is built in `Portal::nav`.
- **Send to a pharmacy**: `Rx::sendToPharmacy` creates a `rx` order (status `received`) in the pharmacy with the patient's agreement, `sc_rx_shares` keeps the link. The pharmacy order flow (verify, dispense, Make invoice) is unchanged.
- App has three new public closures set by routes: `$app->rxForm`, `$app->rxModel`, `$app->myOverview` (declared in `core/App.php`).
- Tests: `tests/meds.py`, `tests/workspace.py`, `tests/mycare.py`, `tests/chain.py` (each prints N/N).

## 2026-11-19 wave 2 ship: medicine master, doctor workspace, patient My care, hospital IPD (builder A)
- Shipped together on top of builder B's phone layout commits: medicine master, doctor one-screen workspace, patient "My care", hospital wards/beds/admissions/emergency/theatre/28 departments, `manage_ipd`.
- Merge notes: `public/css/selfcare-dash.css` now ends with the phone block (closed) followed by the doctor workspace `.ws-*` block; keep new rules after the closing brace of the `@media (max-width:700px)` block.
- Builder B: `public/js/selfcare-app.js` had a dead line `if (overflows() ...)` in `packTopActions` that threw "overflows is not defined" on every phone-width page load (it stopped the rest of the script, including resize handlers and slug helpers). Removed in this ship. If you touch `packTopActions`, run `tests/r4.py` (it reports page errors).
- The doctor workspace and the clinic token queue are one list: both read and write `sc_records` module `tokens` (same daily numbers, `sort_order`, `Ops::event`). Nothing is duplicated; `Rx::queueAdvance` and `routes/workspace.php` only change `status` and `sort_order`.
- Tests (local, port of wt-a): meds 28/28, workspace 32/32, mycare 27/27, chain 22/22, hospital 56/56, isolation 45295/45295, r4 0 problems, wave1 25/25, wave2 clinic 20/20, roles_dyn 48/48, uiux_layout 144/144, mobile 412 light/dark 0, sidebar 47/47. Test edits: `roles_dyn.py` ignores `manage_ipd` (copied from the Receptionist template, not in a clinic's grid); `isolation.py` skips the "form not prefilled" needle when the viewer owns an item with the same title (demo vendors share names such as "Complete blood count (CBC)"). `wave2.py all` still fails for the 15 types whose modules are intentionally not shipped.
