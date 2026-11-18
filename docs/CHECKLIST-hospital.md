# Checklist: Hospital

Status: **built and live** (Builder A). Items that are still open are listed at the bottom and in docs/CHECKLIST-core.md.

## Done
- Vendor website, portal, staff accounts, dynamic roles (45 roles, 40 enabled for hospitals), per-role menus and permissions
- Departments: 28 real medical departments seeded for every hospital (OPD specialities, emergency, ICU, NICU, theatre support, radiology, pathology, dialysis, rehabilitation, nutrition). The vendor edits the list.
- OPD token queue with automatic daily numbers, drag-and-drop and arrow reorder, call next, skip, back to waiting, and a now-serving board (shared with clinics)
- Doctor one-screen workspace: queue on the left, patient card (allergies, vitals, history), prescription editor, "Issue and call next patient"
- Medicine master (197 starter medicines, per-vendor, learns new drugs) with autofill in the prescription
- Prescriptions with PDF, allergy checks, send to a pharmacy with the patient's consent
- Wards and beds: bed master per ward with daily charge and four states (free, occupied, cleaning, repair)
- Admissions (IPD): book bed, admit (bed becomes occupied), move to another bed (old bed goes to cleaning), discharge (bed to cleaning, stay charged: days x bed rate), IPD final bill, admission slip
- Emergency triage board: Red/Orange/Yellow/Green/Blue, automatic numbers, drag reorder, treat, send home, send to admission, refer with a reason, bill, slip, waiting-room board without names
- Operation theatre: bookings by theatre and time (no double booking), start, finish with notes, postpone, procedure bill, OT slip
- Bed occupancy report reads the live bed board
- Invoices, payments, receipts, accounts (cash book, expenses, dues), multi-currency (PKR default)
- Patient login: own prescriptions (PDF), bills, reports and visits only
- Isolation between vendors (tests/hospital.py and the full isolation suite)

## Still open (next)
- Discharge summary generated from the admission record (the document form exists; it is not linked to the admission yet)
- Daily bed charge posting while the patient is in hospital (charges are calculated once, at discharge)
- Doctor round notes and nursing observation chart per admission (nursing notes form exists under documents)
- Lab and radiology order from the doctor screen with results back to the doctor and patient (see CHECKLIST-core.md)
- In-house pharmacy of the same hospital dispensing the prescription (today a prescription goes to any listed pharmacy vendor)
- Blood request to a blood bank vendor, ambulance request to an ambulance vendor
- Insurance / TPA claim workflow with approval status
- Mortuary, housekeeping, dietary, CSSD and medical records registers
