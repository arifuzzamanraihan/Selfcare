# Self Care – vendor type status

Updated 2026-11-18. Common layer (all 20 types, LIVE as of commit ccae14a): website, portal, staff/roles, requests queue, invoices/forms/reports, currency, full-width dashboards, super-admin profile + impersonation.

| Wave | Type | Planned type-specific features | Website | Portal base | Type features | Reports/invoices/forms | Status |
|---|---|---|---|---|---|---|---|
| Wave 1 | Pharmacy | stock, expiry, batches, sales/POS, suppliers | done | done | **done**: prescription orders (verify, dispense), suppliers, stock alerts (low, expiring, expired), stock movement ledger with adjust, overview tiles and charts | done | **live** |
| Wave 1 | Pathology laboratory | samples, test catalogue, results, reports | done | done | **done**: samples workflow (collected, testing, result entered, delivered, rejected with reason), printable result report, overview tiles and charts (diagnostic centers get the same Samples list) | done | **live** |
| Wave 1 | Blood bank | donors, inventory by group, issue/crossmatch | done | done | **done**: donor eligibility (90-day rest, record donation, then add the bag), blood units (screening, release, reserve, issue to a named recipient, discard with reason, automatic expiry by component), blood orders (given or could not give), overview tiles and charts by blood group | done | **live** |
| Wave 1 | Ambulance service | fleet, trips, dispatch, billing | done | done | **done**: vehicles (plate, type, driver, service and papers due, workshop in and out), trips (priority Emergency/Urgent/Routine, made from a website request, dispatch only with a free vehicle plus crew note, picked up, completed with km and fare, cancel frees the vehicle), "Make invoice" from a completed trip (pre-filled, linked both ways, paid with the normal payment), history with times on every record, average dispatch time tile | done | **live** |
| Wave 2 | Hospital | OPD/IPD, beds, doctors, wards | done | done | pending | generic done, type-specific pending | pending |
| Wave 2 | Clinic | appointments, doctors, billing | done | done | pending | generic done, type-specific pending | pending |
| Wave 2 | Specialty clinic | appointments, procedures | done | done | pending | generic done, type-specific pending | pending |
| Wave 2 | Dental clinic | chart, treatment plans | done | done | pending | generic done, type-specific pending | pending |
| Wave 2 | Eye clinic | exams, optical | done | done | pending | generic done, type-specific pending | pending |
| Wave 3 | Diagnostic center | orders, reports | done | done | pending | generic done, type-specific pending | pending |
| Wave 3 | Imaging and radiology center | studies, reports | done | done | pending | generic done, type-specific pending | pending |
| Wave 3 | Dialysis center | sessions, machines, schedule | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Maternity and child care | antenatal, vaccination | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Mental health center | sessions, notes | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Traditional and alternative medicine | remedies, consultations | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Nursing home | residents, care plans | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Rehabilitation and physiotherapy | plans, sessions | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Telemedicine provider | video consults | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Home care and nursing | visits, caregivers | done | done | pending | generic done, type-specific pending | pending |
| Wave 4 | Medical equipment supplier | catalogue, rentals, orders | done | done | pending | generic done, type-specific pending | pending |

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
| Hospital | Y | P | P | Y | - | P | Y | structured prescription, IPD beds and admissions, token queue, accounts |
| Clinic | Y | P | P | Y | - | P | Y | structured prescription, token queue, doctor-wise share, accounts |
| Specialty clinic | Y | P | P | Y | - | P | Y | prescription, procedure records, accounts |
| Dental clinic | Y | P | P | Y | - | P | Y | treatment plan and tooth chart, prescription, accounts |
| Eye clinic | Y | P | P | Y | - | P | Y | eye exam, optical orders, prescription, accounts |
| Diagnostic centre | Y | Y (samples) | n/a | Y | - | P | Y | test catalogue with ranges, validation, accounts |
| Imaging centre | Y | - | n/a | Y | - | P | Y | study orders and radiology reports, accounts |
| Dialysis centre | Y | - | P | Y | - | P | Y | session schedule and machines, accounts |
| Maternity | Y | - | P | Y | - | P | Y | antenatal and vaccination schedules, accounts |
| Mental health | Y | - | P | Y | - | P | Y | session notes, treatment plans, accounts |
| Traditional medicine | Y | - | P | Y | - | P | Y | consultation with remedy prescription, accounts |
| Nursing home | Y | - | n/a | Y | - | P | Y | residents, care plans, medication chart, accounts |
| Rehabilitation | Y | - | n/a | Y | - | P | Y | plans and sessions, accounts |
| Pathology lab | Y | Y | n/a | Y | - | Y | Y | test catalogue with ranges, validation, critical values, accounts |
| Pharmacy | Y | Y | Y (orders) | Y | - | Y | Y | batch FEFO, purchase/GRN, controlled-drug register, accounts |
| Blood bank | Y | Y | n/a | Y | - | Y | Y | TTI results, crossmatch, reaction log, accounts |
| Ambulance | Y | Y (vehicles, trips, dispatch) | n/a | Y (from trip) | - | Y (history with times) | Y | crew roster, run sheet, maintenance log, accounts |
| Telemedicine | Y | - | P | Y | - | P | Y | slots and video link, prescription, accounts |
| Home care | Y | - | n/a | Y | - | P | Y | visit schedule, caregiver roster, accounts |
| Equipment supplier | Y | - | n/a | Y | - | P | Y | rentals and orders, service log, accounts |

Cross-cutting gaps, built in this order: (1) Accounts for every kind, (2) structured Prescriptions with PDF and history, (3) dynamic roles with per-feature permissions, (4) the type-specific rows above, wave by wave.

## Shared work-list features added with ambulance

- **History with times** on every work-list record (`sc_record_events`, removed with the record): added, each step, deleted, brought back, invoice made, with who and the time since the step before. Existing records got a first line on deploy.
- **Make invoice** from a record: ambulance trip, lab sample, blood order, pharmacy prescription order. The invoice form is pre-filled (name, phone, reference, trip fields, first line), the record keeps the invoice id and shows it with Paid/Unpaid. Another vendor's record cannot be used to pre-fill or link (tested in the isolation run).
- **Make trip / order / sample from a website request** on the request page, with name, phone, date and note already filled in.
