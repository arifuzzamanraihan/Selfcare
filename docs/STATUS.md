# Self Care – vendor type status

Updated 2026-11-18. Common layer (all 20 types, LIVE as of commit ccae14a): website, portal, staff/roles, requests queue, invoices/forms/reports, currency, full-width dashboards, super-admin profile + impersonation.

| Wave | Type | Planned type-specific features | Website | Portal base | Type features | Reports/invoices/forms | Status |
|---|---|---|---|---|---|---|---|
| Wave 1 | Pharmacy | stock, expiry, batches, sales/POS, suppliers | done | done | **done**: prescription orders (verify, dispense), suppliers, stock alerts (low, expiring, expired), stock movement ledger with adjust, overview tiles and charts | done | **live** |
| Wave 1 | Pathology laboratory | samples, test catalogue, results, reports | done | done | **done**: samples workflow (collected, testing, result entered, delivered, rejected with reason), printable result report, overview tiles and charts (diagnostic centers get the same Samples list) | done | **live** |
| Wave 1 | Blood bank | donors, inventory by group, issue/crossmatch | done | done | **done**: donor eligibility (90-day rest, record donation, then add the bag), blood units (screening, release, reserve, issue to a named recipient, discard with reason, automatic expiry by component), blood orders (given or could not give), overview tiles and charts by blood group | done | **live** |
| Wave 1 | Ambulance service | fleet, trips, dispatch, billing | done | done | pending | generic done, type-specific pending | pending |
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
