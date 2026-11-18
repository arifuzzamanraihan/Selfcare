# Self Care – vendor type status

Updated 2026-11-18 (evening, Wave 2 type work lists). Common layer (all 20 types, LIVE as of commit ccae14a): website, portal, staff/roles, requests queue, invoices/forms/reports, currency, full-width dashboards, super-admin profile + impersonation.

| Wave | Type | Planned type-specific features | Website | Portal base | Type features | Reports/invoices/forms | Status |
|---|---|---|---|---|---|---|---|
| Wave 1 | Pharmacy | stock, expiry, batches, sales/POS, suppliers | done | done | **done**: prescription orders (verify, dispense), suppliers, stock alerts (low, expiring, expired), stock movement ledger with adjust, overview tiles and charts | done | **live** |
| Wave 1 | Pathology laboratory | samples, test catalogue, results, reports | done | done | **done**: samples workflow (collected, testing, result entered, delivered, rejected with reason), printable result report, overview tiles and charts (diagnostic centers get the same Samples list) | done | **live** |
| Wave 1 | Blood bank | donors, inventory by group, issue/crossmatch | done | done | **done**: donor eligibility (90-day rest, record donation, then add the bag), blood units (screening, release, reserve, issue to a named recipient, discard with reason, automatic expiry by component), blood orders (given or could not give), overview tiles and charts by blood group | done | **live** |
| Wave 1 | Ambulance service | fleet, trips, dispatch, billing | done | done | **done**: vehicles (plate, type, driver, service and papers due, workshop in and out), trips (priority Emergency/Urgent/Routine, made from a website request, dispatch only with a free vehicle plus crew note, picked up, completed with km and fare, cancel frees the vehicle), "Make invoice" from a completed trip (pre-filled, linked both ways, paid with the normal payment), history with times on every record, average dispatch time tile | done | **live** |
| Wave 2 | Hospital | OPD/IPD, beds, doctors, wards | done | done | **partial**: OPD token queue (daily serial, drag-and-drop, Call next, now-serving board, print slip, Make invoice). Departments, IPD, ICU, OT, wards and beds belong to Builder A. | generic done, token register done | **checklist for next week** (departments: Builder A; OPD queue held on builder-c-wave2-wip) |
| Wave 2 | Clinic | appointments, doctors, billing | done | done | **done**: token queue (daily serial, drag-and-drop / arrows / Alt+Up+Down, Call next, now-serving board without names, print token, Make invoice, register). Website and staff booking, “Make token” from a request, patient add, prescriptions, billing, accounts and dynamic roles. | done | **live (this ship)** |
| Wave 2 | Specialty clinic | appointments, procedures | done | done | **done**: token queue (shared) and procedure list (booked → in progress → completed, note, fee, Make invoice, print after complete) | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 2 | Dental clinic | chart, treatment plans | done | done | **done**: token queue, FDI odontogram, treatment plans (proposed → accepted → in progress → completed/declined), print plan, Make invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 2 | Eye clinic | exams, optical | done | done | **done**: token queue, eye exams (VA, refraction, IOP in the record, print glasses Rx when complete), optical job orders (ordered → in lab → ready → delivered) | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 3 | Diagnostic center | orders, reports | done | done | **done**: samples (Wave 1), test catalogue, imaging studies with report/print and imaging invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 3 | Imaging and radiology center | studies, reports | done | done | **done**: imaging studies queue (booked → arrived → in progress → reported → delivered), printable radiology report, Make invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 3 | Dialysis center | sessions, machines, schedule | done | done | **done**: machines (available / in use / maintenance), session queue with machine lock, pre/post weight and UF, session sheet, Make invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Maternity and child care | antenatal, vaccination | done | done | **done**: token queue, ANC visits (GA, BP, weight, FHR), EPI vaccinations, delivery records, print notes | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Mental health center | sessions, notes | done | done | **done**: session queue and treatment plans. Session notes stay on the record (not listed in the public register). Make invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Traditional and alternative medicine | remedies, consultations | done | done | **done**: token queue, consultations with findings and remedy, printable advice, remedy catalogue | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Nursing home | residents, care plans | done | done | **done**: residents, care plans, medication rounds (MAR), incident reports, print each | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Rehabilitation and physiotherapy | plans, sessions | done | done | **done**: therapy plans, session queue with notes, session packages, Make invoice, print plan/note | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Telemedicine provider | video consults | done | done | **done**: consultation queue, paste a meeting link (we do not host video), payment-before-call states, consultation invoice, print summary after complete. Prescriptions stay on the usual Prescriptions page | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Home care and nursing | visits, caregivers | done | done | **done**: caregiver roster (asset lock while on a visit), home visit queue, visit log, visit invoice | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |
| Wave 4 | Medical equipment supplier | catalogue, rentals, orders | done | done | **done**: rental catalogue, rentals (reserved → out → returned, overdue when the due date passes), service jobs, rental/equipment invoices | done | **checklist for next week** (see docs/CHECKLIST-*.md; code held on builder-c-wave2-wip) |

## Open platform items
- SSL: per-host HTTP-01 automation is LIVE (cron every 3 min; batches up to 10 new vendor addresses per certificate, never re-issues a valid certificate, stops at 40 new certificates/week, cleans up purged vendors). Tested with a throwaway vendor pair. The 23 original vendor hosts use the shared SAN certificate. No wildcard certificate is needed.
- Outgoing mail relay (port 25 blocked).
- Old live hospital data not migrated.
- Repo cleanup: not started.


## Isolation and integrity (18 Nov 2026)

- **Isolation test** (`isolation.py` in the test harness): signs in as the admin of each of the 23 demo vendors and attacks every other vendor: opens and changes their invoices, payments, PDFs, forms, stock, work-list records, requests, people, website items, messages and web addresses by putting their ids in URLs and forms, and opens pages that belong to other kinds of organisation (work lists, stock, patients, doctors, invoice types, forms, reports, website sections) and the super admin area. A table checksum before and after proves nothing changed.
  Result after the fixes below: 27,014 of 27,014 checks passed (26,990 attack attempts, 23 vendors). Earlier runs only failed on the three issues below and on test artefacts.
  Fixes found by the test: the Stock pages were open to vendor kinds that keep no stock (hospital, clinic and so on), the Departments list was open to kinds without departments, and opening another vendor's stock item id showed an empty form instead of a 404.
- **Integrity check** (`bin/integrity-check.php`, read-only, runs at the end of every deploy): 40+ checks for orphans, links across vendors, invoice and payment totals, stock ledger, work-list states and codes. `--fix-orphans` removes link rows that point at a deleted user.

## Workflow coverage per kind (what a vendor can do end to end today, and what is still missing)

Legend: **Y** works end to end today, **P** partly, **-** missing. Intake = registration/request; Service = the type's own work; Rx = structured prescription; Bill = invoice + payment; Accounts = expenses, cash book, dues; Records = history and printable forms; Reports = reports and PDFs.

| Kind | Intake | Service | Rx | Bill | Accounts | Records | Reports | Main gaps (research in RESEARCH.md) |
|---|---|---|---|---|---|---|---|---|
| Hospital | Y | P | Y | Y | Y | P | Y | IPD, emergency, ICU, OT, wards and beds (Builder A). OPD token queue is built. |
| Clinic | Y | Y | Y | Y | Y | Y | Y | doctor-wise payout shares (later) |
| Specialty clinic | Y | Y | Y | Y | Y | Y | Y | none material; richer procedure templates later |
| Dental clinic | Y | Y | Y | Y | Y | Y | Y | perio chart and imaging attach later |
| Eye clinic | Y | Y | Y | Y | Y | Y | Y | auto-create optical job from a completed Rx later |
| Diagnostic centre | Y | Y | n/a | Y | Y | Y | Y | result validation and critical-value alerts later |
| Imaging centre | Y | Y | n/a | Y | Y | Y | Y | DICOM / PACS link later (out of scope for a no-CDN app) |
| Dialysis centre | Y | Y | Y | Y | Y | Y | Y | recurring schedule templates later |
| Maternity | Y | Y | Y | Y | Y | Y | Y | partograph and newborn ID later |
| Mental health | Y | Y | Y | Y | Y | Y | Y | locked psychotherapy notes / extra confidentiality later |
| Traditional medicine | Y | Y | Y | Y | Y | Y | Y | none material |
| Nursing home | Y | Y | n/a | Y | Y | Y | Y | eMAR signing and bed board later |
| Rehabilitation | Y | Y | n/a | Y | Y | Y | Y | automatic session countdown on packages later |
| Pathology lab | Y | Y | n/a | Y | Y | Y | Y | result validation and critical-value alerts later; catalogue is built |
| Pharmacy | Y | Y | Y (orders) | Y | Y | Y | Y | batch FEFO, purchase/GRN, controlled-drug register |
| Blood bank | Y | Y | n/a | Y | Y | Y | Y | TTI results, crossmatch, reaction log |
| Ambulance | Y | Y (vehicles, trips, dispatch) | n/a | Y | Y | Y (history with times) | Y | crew roster, run sheet, maintenance log |
| Telemedicine | Y | Y | Y | Y | Y | Y | Y | built-in video room later (today: paste a meeting link) |
| Home care | Y | Y | n/a | Y | Y | Y | Y | GPS/check-in later |
| Equipment supplier | Y | Y | n/a | Y | Y | Y | Y | inventory serial numbers and deposits later |

Cross-cutting gaps, built in this order: (1) Accounts for every kind (done, live), (2) structured Prescriptions with PDF and history (done, live), (3) dynamic roles with per-feature permissions, (4) the type-specific rows above, wave by wave.

## Shared work-list features added with ambulance

- **History with times** on every work-list record (`sc_record_events`, removed with the record): added, each step, deleted, brought back, invoice made, with who and the time since the step before. Existing records got a first line on deploy.
- **Make invoice** from a record: ambulance trip, lab sample, blood order, pharmacy prescription order, and Wave 2 records that declare a bill (token, procedure, treatment plan, exam, study, dialysis session, visit, rental, teleconsult, and so on). The invoice form is pre-filled (name, phone, reference, trip fields, first line), the record keeps the invoice id and shows it with Paid/Unpaid. Another vendor's record cannot be used to pre-fill or link (tested in the isolation run).
- **Make trip / order / sample from a website request** on the request page, with name, phone, date and note already filled in.

## Accounts for every kind (step 2)

Every vendor type has **Accounts** (Money → Accounts), permission `manage_accounts` (Accountant and Branch Manager by default, Admin always; module `billing`, so every type has it):
- Cash basis. Money in = invoice payments (voided invoices left out, credit notes applied to an invoice are not cash) + other income typed in; money out = expenses paid, refunds paid ('Refund paid' notes), supplier invoices paid.
- Overview: cash on hand, in/out today, income/expenses/profit this month, to collect (unpaid invoices), to pay (bills and supplier invoices), 6-month charts, spending by category, latest movements.
- Daily cash summary per day (start, in, out, end, by payment method) with PDF and print; cash book for any period with PDF; opening balance set once by the vendor.
- Expenses and other income (`sc_ledger`, codes EXP-/INC-, categories, party, method, attachment-free): add, edit, soft delete and restore; bills to pay later with due date; **Dues** page (to collect with ageing, to pay) and a Pay now action that stamps method and time.
- Reports: Cash book, Income and expenses by category, Expense register, Payables; on the Reports page of every kind.
- Overview tiles on the portal home: Cash in today, Expenses this month.
- Integrity checks: ledger tenant, amounts, status/paid_at, codes unique per vendor and kind (5 checks). Isolation test: ledger ids of another vendor refused on view/pay/delete/restore/save; accounts pages and PDFs never contain another vendor's money.
- Tests: accounts end to end (36 checks) and all 23 demo vendors open every accounts page; isolation 31,486 checks.
- Not built yet (future): day-end closing with drawer count and period lock, doctor payout shares, double-entry journals / trial balance.

## Prescriptions (step 3)

Kinds that write them: hospital, clinic, specialty clinic, dental, eye, maternity, mental health, traditional medicine, dialysis, telemedicine (others have the page refused and no menu entry). Permissions `manage_rx` (write/issue/copy/cancel: Doctor, Consultant, Medical Director, Resident, Optometrist) and `view_rx` (read/print: nurses, receptionist, records, branch manager and similar).
- Structured medicine lines: medicine, strength, form (per kind), dose, how often, route, when to take, duration or quantity, reason, special instruction.
- Patient (registered, linked to patients.id, or walk-in), doctor (doctors.id, name, speciality, registration number printed), examination (BP, pulse, temperature, SpO2, height, weight), complaints, diagnosis, tests advised, advice, follow-up date, per-kind fields (tooth, eye, gestation, dry weight, consultation mode ...).
- Safety: allergies recorded on the patient and checked against the medicines (direct and by class: penicillin, sulfa, NSAID ...); an issue is blocked until the doctor confirms the warning; same medicine twice is flagged; issue needs dose, frequency and duration or quantity on every line.
- Lifecycle: draft (edit, delete) -> issued (locked) -> cancelled with reason; a change is a copy (parent link). Numbers RX-00001 per vendor.
- PDF with letterhead, barcode, signature and stamp box; blank prescription pad PDF; saved sets of medicines; patient history page (medicines now, all prescriptions, invoices, forms) with PDF; card on the patient profile; bill from a prescription (patient, doctor and number pre-filled).
- Reports: Prescription register, Most prescribed medicines, Prescriptions by doctor.
- Research: ISO 17523, EU eHealth ePrescription guidelines, ISMP safe electronic prescribing, Australian e-prescribing profile (see RESEARCH.md).

## Dynamic roles (step 4)

- Every vendor can switch catalogue roles on or off, **make its own roles** (name, purpose, copy from an existing role), delete unused ones, and for each module choose **View / Add / Edit / Delete / Print / Export**. Only modules of the vendor's kind and plan appear; boxes above the editor's own access are greyed. Edits apply at once and are written to the change log.
- Central enforcement: `Portal::boot` -> `Portal::gate()` maps every portal address to a module (`Access::permForPath`) and the kind of action (`Access::classify`: view/add/edit/delete/print/export) and answers 403 when the role was not given it. `Portal::filterLinks()` hides the buttons and forms a role may not use.
- Custom roles are rows of `departments` with `tenant_id` set (unique name per vendor, never equal to a catalogue role); grants in `tenant_role_has_permissions`, actions in `sc_role_actions`. Another vendor can never see, edit, delete or hand out the role (tests).
- Self Care: Admin > Role templates. Per vendor type: what each role starts with, extra template roles (e.g. pharmacy "Cashier"), reset to built-in, **apply to existing vendors**. New vendors are seeded from the template (`Access::seedTenant($slug, $family, $type)`).
- Fixes found by the per-role sweep: the "Forms and records" menu link no longer shows for roles that have no form to fill.
- Tests: `tests/roles.py` (a test user for every switched-on role of every type, 19,000+ checks: sign in, menu links open, modules refused, each action refused/allowed as configured), `tests/roles_dyn.py` (48 checks: create/copy/rename/save/assign/delete, edits effective at once, other vendors isolated, templates apply). Role-by-feature matrix: `docs/ROLES.md`.
- Not yet: per-role dashboards/landing pages (step: per-role portals), approval workflows.

## Multi-currency (Pakistan default)

- `sc_currencies`: 49 world currencies seeded (PKR, BDT, INR, USD, EUR, GBP, AED, SAR, CAD, AUD, MYR, SGD, NPR, LKR, ...), each with symbol, position, decimals, separators, digit grouping, words and an **exchange rate per 1 USD** (editable by Self Care; rates are indicative until edited). Platform default currency, country and time zone are PKR, Pakistan and Asia/Karachi (new vendors get them; existing vendors were pinned to the currency they were showing, amounts never change).
- Every money row carries its code: invoices and plans already did; `sc_ledger`, `sc_records`, `sc_stock_items` now do (filled automatically in `Database::insert`). Totals that mix currencies are converted with the rates (`Currency::sql`, `Currency::normalize`): cash book, daily/monthly collection, revenue by item, doctor revenue, expense register, receivables/payables, portal tiles and charts, platform reports.
- Vendor settings: currency, country, time zone (applied to the vendor's pages and PDFs). Pricing page: visitor currency switcher (default PKR, remembered in a cookie), plan prices converted when a plan has no price in that currency; subscription invoices default to the vendor's own currency.
- Tests: `tests/currency.php` (43), `tests/currency_web.py` (14).

## Next week (owner-approved build)
Type-specific work for every vendor except pharmacy, pathology lab, blood bank, ambulance and clinic is paused. See `docs/CHECKLIST-*.md`. Held implementation lives on git branch `builder-c-wave2-wip` and will be switched on when the owner says to build that type.
