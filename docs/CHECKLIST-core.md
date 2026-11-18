# Checklist: core platform (Builder A scope)

Updated 2026-11-19 (Asia/Dhaka). Everything under "Done" is live and covered by tests in `tests/`.

## Done
- Medicine master: `sc_medicines` per vendor, 197 starter medicines, add/edit/delete, learns drugs typed in a prescription, autofill of strength, form, dose, frequency, timing, duration, route (tests/meds.py)
- Doctor workspace and auto-numbered drag-and-drop queue (tests/workspace.py)
- Prescription to pharmacy to invoice to accounts chain (tests/chain.py): patient consent, pharmacy order, verify, dispense, status back to the prescriber, invoice prefill, payment into accounts
- Patient portal "My care": prescriptions with PDF, bills, reports, visits, send my prescription to a pharmacy (tests/mycare.py)
- Hospital: wards and beds, admissions, emergency, theatre, 28 departments (tests/hospital.py, docs/CHECKLIST-hospital.md)
- Multi-currency (PKR default), dynamic roles, isolation suite, integrity check on every deploy

## Leftovers
1. Lab order chain: doctor orders a test, lab receives it, result entered, result visible in the doctor screen and the patient portal (not built; the lab modules exist separately)
2. Same-vendor in-house pharmacy for hospitals (dispense from the hospital's own pharmacy without sending to another vendor)
3. Patient account across vendors: one login that sees records from every vendor the patient has visited, with consent per vendor (today a patient account belongs to one vendor)
4. Patient self sign-up and booking with slots and capacity per type
5. Accounts: day-end closing, doctor payouts and commission statements
6. Hospital: daily bed charges, discharge summary linked to admission, TPA workflow, remaining support registers (see CHECKLIST-hospital.md)
7. Other vendor types: see the CHECKLIST-<type>.md files written by Builder C (next week)
8. Email relay for password reset and prescription sharing by mail
9. Import of old hospital data from the previous system
10. User manual PDF by Raihan and the vendor-only brochure/poster (when asked)
