# Research notes: what each kind of organisation needs

How we decided the must-have features. Sources were checked on 18 Nov 2026: Bahmni/OpenMRS feature guide, LIS guidance (College of American Pathologists / APHL guidebook), Bangladeshi and Pakistani pharmacy and clinic products (SharpMed, Meezan ERP, ChamberBD, Nexteio, JahaSoft, Shafa HMS), blood bank products (NetBloodBank, Veona, BloodTrans, Hemosoft), EMS products (AngelTrack, CADMedic, Veona Ambulance, Traumasoft) and clinic/hospital accounting products (Medbus, OctoMediCare, XLinic).

## Findings that apply to every kind

| Finding | What it means for Self Care |
|---|---|
| Every product has an accounts layer: income and expenses, a daily cash book, dues (money owed to us and by us), and day-end totals. | **Accounts** for every vendor: expense entry, income from invoices, daily cash summary, dues, and a printable day report. |
| Money, stock and clinical records are linked, not typed twice. A drug order makes a draft bill, a sale takes stock out (first-expiry-first-out), a result goes back to the patient record. | Keep one id per patient, doctor, invoice and stock item, and write every stock change to the ledger (done for stock). |
| Doctor-facing products print a prescription (Bangla or English, A4/A5) and keep patient history. Chamber software also gives each doctor a token queue and a doctor-wise revenue share. | **Prescriptions**: structured medicine lines, printed as PDF, history per patient. Token queue and doctor-wise reports for clinics. |
| Every person who logs in sees only what their role allows, with an audit trail. | Roles with per-feature permissions, per vendor, changed from the portal. |
| Registers are inspected by regulators (controlled drugs, blood deferrals, issue logs). | Printable registers for pharmacy controlled drugs and blood bank issue and deferral logs. |

## By kind of organisation

### Hospital, clinic, specialty, dental, eye (Bahmni, OpenMRS, ChamberBD, Shafa)
Must have: registration with patient id and card; OPD queue/token; consultation with diagnosis and prescription; lab and imaging orders; billing from orders with discount, credit and tax; payments; admission, ward and bed management (hospital); discharge summary; doctor-wise revenue; daily collection.
Built: patients, doctors, requests, invoices and payments, 14 printed forms, reports.
Gap: structured prescription, token queue, IPD (beds, admissions), dental treatment plan, eye exam and optical order, accounts.

### Pharmacy (SharpMed, Meezan ERP, Shafa)
Must have: batch and expiry with FEFO selling; purchase and goods receipt (GRN); supplier ledger; reorder alerts; controlled-drug register; prescription queue; sale returns; customer credit (khata); near-expiry returns to supplier.
Built (live): prescription orders, suppliers, stock alerts (low, expiring, expired), stock movement ledger with adjust.
Gap: batch-level stock with FEFO, purchase/GRN with supplier ledger, controlled-drug register, sale returns, customer credit.

### Pathology laboratory, diagnostic centre (LIS guidance, OpenELIS)
Must have: order, accession number and label, specimen receipt, result entry with reference ranges and flags, validation by a senior, critical result alert, report with units and ranges, turnaround time per stage, workload queue.
Built (live): samples workflow with rejection reason, result entry and printable report.
Gap: test catalogue with units and reference ranges and abnormal flags, validation step, critical value flag, turnaround report, sample label print.

### Blood bank (NetBloodBank, Veona, BloodTrans)
Must have: donor eligibility and deferral with re-eligibility date; collection; TTI screening and quarantine; component preparation with expiry per component; stock by group and component with first-expiry-first-out; request, crossmatch, issue with two checks; transfusion reaction log; traceability bag to donor to recipient.
Built (live): donor eligibility (90-day rest), blood units with screening, release, reserve, issue, discard and automatic expiry, blood orders.
Gap: TTI results per bag, crossmatch record, reaction log, issue register print, deferral reasons.

### Ambulance (AngelTrack, CADMedic, Veona Ambulance)
Must have: call intake with caller, place and priority; dispatch of a unit and crew with timestamps (dispatched, en route, on scene, at destination); fleet status; run sheet; fare or per-km billing; fleet maintenance; response time report.
In progress: vehicles and trips with dispatch, start, complete and fare.
Gap: priority and timestamps, crew roster, run sheet, maintenance log, invoice from trip.

### Imaging, dialysis, maternity, mental health, traditional medicine, nursing home, rehabilitation, telemedicine, home care, equipment supplier
- Imaging: study order, modality, report by radiologist, printable report, turnaround.
- Dialysis: patient schedule, machine and slot allocation, session record (weights, vitals), consumables.
- Maternity: antenatal visit schedule, vaccination schedule, delivery record.
- Mental health: session notes with restricted access, treatment plan, follow-ups.
- Traditional medicine: consultation with herbal/remedy prescription, remedy stock.
- Nursing home: residents, care plans, medication administration chart, incident log.
- Rehabilitation: plan and goals, session log with progress, package billing.
- Telemedicine: appointment slot, video link, e-prescription, payment before the call.
- Home care: visit schedule, caregiver roster, visit log, package billing.
- Equipment supplier: catalogue, rental and sale orders, return and service log, customer dues.

### Accounts for clinics, hospitals and suppliers (Medbus, CluvixHealth, Shafa HMS, OctoMediCare, Yuktii; Nov 2026)
Must have: income posted from billing automatically (no re-entry); expense heads with party and method; supplier payables with due dates and payment history; patient receivables with ageing; cash book and daily cash summary by payment method; opening balance; profit and loss by period; refunds and credit notes carrying a reason; audit trail on every posting; PDF/export for the accountant.
Built (live): all of the above on a cash basis, with the cash book, daily summary, dues and four reports, per vendor.
Gap: day-end closing against the drawer count with locked periods, doctor payout shares, double-entry journals and trial balance, bank statement matching.

### Prescriptions (ISO 17523:2025, EU eHealth ePrescription guidelines, ISMP guidelines for safe electronic prescribing, Australian e-prescribing conformance profile; Nov 2026)
Must have: patient identity and weight; prescriber identity with registration number; medicine name, strength and form; dose per administration, route, frequency and timing; duration or quantity; indication; allergies shown while prescribing; no free-text-only doses; special instructions visible; privacy notice; structured fields so safety checks can run.
Built (live): all of the above, allergy and duplicate checks, locked issued prescriptions with cancel and copy, PDF, history and reports.
Gap: drug-interaction database, dose-by-weight calculator, controlled-drug registers and prescriber signature image, sending to a named pharmacy (cross-vendor sharing is deliberately not allowed).

## Wave 2 type-specific research (18 Nov 2026)

Sources reviewed (product sites, manuals and public workflow write-ups, plus Bangladesh / Pakistan / India practice): ChamberBD, DoctorLane, SehatDoc and Clinemo (token / serial queues); Open Dental, Dentrix and Practo Ray (FDI chart and treatment plans); Care’O, Medical365, EyeOps, MeDIC and Lifemaan (refraction to optical job); hospital information work in BD/PK/IN (token + OPD cash, not Western EHR-first); DGHS / EPI vaccination schedule (Bangladesh), Expanded Programme on Immunization (Pakistan, India); WHO ANC model (eight contacts) and standard ANC card fields (GA, BP, weight, FHR, next visit); dialysis units (Fresenius / Nipro clinic practice: machine assignment, pre/post weight, UF, session sheet); mental health (session notes kept off registers; treatment plans); Unani / Ayurveda / homeopathy clinic flow in South Asia (consultation + remedy list); nursing-home MAR and incident reporting (CMS-style eMAR ideas, kept simple); physiotherapy packages (session count + progress note); telemedicine payment-before-call (Practo, Sehat Kahani, Oladoc; we paste a meeting link rather than host video); home-care visit roster; equipment rental (reserve, out, due, overdue, service). Regulatory notes: no cross-vendor patient sharing; prescription rules already covered above; blood and lab already covered in Wave 1; hospital IPD/OT/ICU beds are out of this wave (Builder A).

Mark: **done**, **partial**, **missing**. Counts are of realistic must-have workflow items, not every possible future add-on.

### Hospital
Sources: South-Asian HMS (token OPD, IPD admission, bed/ward, OT list, emergency), plus the platform items already live.
- Token / OPD serial, drag to reorder, Call next, now-serving board (no names) — **done**
- Print token slip and OPD bill — **done**
- Link token to patient and doctor — **done**
- History of queue actions — **done**
- IPD admission, wards, beds, transfer — **missing** (Builder A)
- Emergency / triage board — **missing** (Builder A)
- ICU / OT list, OT booking — **missing** (Builder A)
- Discharge summary as IPD record — **missing** (Builder A)
- Nurse station / ward census — **missing** (Builder A)
Checklist: 4 done, 5 missing (departments). Token queue is live in this branch; departments are not this builder's work.

### Clinic
- Daily token serial (not only appointment slots) — **done**
- Drag-and-drop / arrows / keyboard reorder — **done**
- Call next and now-serving board — **done**
- Walk-in or registered patient, doctor optional — **done**
- Print token, Make invoice, token register — **done**
- Website / staff booking of visits — **partial** (generic requests already exist; patient self-booking is Builder A)
Checklist: 5 done, 1 partial.

### Specialty clinic
- Token queue (shared with clinic) — **done**
- Procedure list with room, specialist, fee — **done**
- Workflow booked → in progress → completed / cancelled — **done**
- Printable procedure note after complete, Make invoice — **done**
- Procedure templates / consent packs — **missing** (later)
Checklist: 4 done, 1 missing.

### Dental clinic
Sources: Open Dental, Dentrix, Practo Ray; FDI numbering is the usual chart in BD/PK/IN.
- Token queue — **done**
- FDI odontogram (teeth 11–48) on the treatment plan — **done**
- Treatment plan states (proposed, accepted, in progress, completed, declined) — **done**
- Print plan and Make invoice — **done**
- Link to patient and dentist — **done**
- Periodontal chart, odontogram colours per surface, attach x-ray — **missing**
Checklist: 5 done, 3 missing (advanced charting).

### Eye clinic
Sources: Care’O, Medical365, EyeOps, MeDIC, Lifemaan.
- Token queue — **done**
- Visual acuity, refraction (sph/cyl/axis/add), IOP left and right — **done**
- Print glasses Rx when the exam is complete — **done**
- Optical job order (lens type, frame, lab workflow) — **done**
- Make invoice for exam / job — **done**
- Auto-create an optical job from a completed Rx — **missing**
- Auto-refractor / retinoscopy device import — **missing**
Checklist: 5 done, 2 missing.

### Pathology laboratory (Wave 1 + catalogue)
- Sample workflow and printable result — **done** (Wave 1)
- Test catalogue with fee, specimen and reference range — **done**
- Result validation / pathologist sign-off and critical-value alerts — **missing**
Checklist: 2 done, 2 missing.

### Diagnostic center
- Samples (same as the lab) — **done** (Wave 1)
- Test catalogue — **done**
- Imaging studies and reports (same module as imaging centres) — **done**
- Combined order covering lab + imaging in one basket — **missing**
Checklist: 3 done, 1 missing.

### Imaging and radiology center
- Study queue with modality and study name — **done**
- States booked → arrived → in progress → reported → delivered — **done**
- Printable radiology report (findings and impression, radiologist) — **done**
- Make invoice — **done**
- DICOM / PACS and image viewer — **missing** (out of scope for a no-CDN, no-library app)
Checklist: 4 done, 1 missing.

### Dialysis center
- Machine list and status (available, in a session, maintenance) — **done**
- Session queue, machine cannot be double-booked — **done**
- Pre/post weight, UF, blood pressure on the session — **done**
- Session sheet and Make invoice — **done**
- Recurring weekly schedule templates — **missing**
Checklist: 4 done, 1 missing.

### Maternity and child care
Sources: WHO ANC contacts; DGHS EPI (BD); national EPI (PK, IN).
- Token queue — **done**
- ANC visit with gestation, BP, weight, fetal heart rate, next visit — **done**
- Vaccination list (BCG and the usual EPI antigens), due / given — **done**
- Delivery record (type, outcome, birth weight) — **done**
- Print visit / immunisation / delivery notes — **done**
- Partograph, newborn ID / hearing screen — **missing**
Checklist: 5 done, 2 missing.

### Mental health center
- Session queue, clinician, fee, Make invoice — **done**
- Session notes captured on the record (not shown as a register column) — **done**
- Treatment / care plans — **done**
- Extra lock on psychotherapy notes (role-restricted beyond the usual permission) — **missing**
Checklist: 3 done, 1 missing.

### Traditional and alternative medicine
- Token queue — **done**
- Consultation (Unani / Ayurveda / homeopathy / other) with findings and remedy — **done**
- Printable advice, Make invoice — **done**
- Remedy catalogue — **done**
Checklist: 4 done, 0 missing.

### Nursing home
- Resident register (room, care level, next of kin, admit / leave / discharge) — **done**
- Care plans — **done**
- Medication rounds (due / given / withheld / missed) — **done**
- Incident reports (fall and similar) — **done**
- Print plan, MAR entry and incident — **done**
- eMAR signing and a bed / room board — **missing**
Checklist: 5 done, 2 missing.

### Rehabilitation and physiotherapy
- Therapy plan with goals and review date — **done**
- Session queue and progress note — **done**
- Session packages (count and fee) — **done**
- Make invoice, print plan and session note — **done**
- Automatic remaining-session countdown when a session is completed — **missing**
Checklist: 4 done, 1 missing.

### Telemedicine provider
Sources: Practo, Oladoc, Sehat Kahani (pay, then join).
- Consultation queue for the day — **done**
- Meeting link field (we do not host the video ourselves) — **done**
- States booked / waiting-paid / in the call / completed / no-show — **done**
- Consultation invoice and print summary after complete — **done**
- Prescriptions through the usual Prescriptions page — **done**
- Built-in video room — **missing** (deliberate: no third-party SDK)
Checklist: 5 done, 1 missing.

### Home care and nursing
- Caregiver roster — **done**
- Visit queue, address, caregiver lock while on a visit — **done**
- Visit log, next visit, visit invoice — **done**
- GPS / clock-in — **missing**
Checklist: 3 done, 1 missing.

### Medical equipment supplier
- Rental catalogue — **done**
- Rentals reserved → out → returned, overdue when the due date passes — **done**
- Service jobs — **done**
- Rental and equipment invoices — **done**
- Serial-number inventory and deposits — **missing**
Checklist: 4 done, 2 missing.

### Pharmacy, blood bank, ambulance
Already researched and marked in the earlier sections. No change in this wave besides the shared engine columns (sort order, patient/staff links) which those lists can use later if needed.

### Shared engine rules used by every new list
- One-screen work queue where the type needs a today-list, with drag-and-drop, ↑/↓ and Alt+Up/Down, status filters, Call next and a now-serving board when a serial is public.
- History log (`sc_record_events`) on every record.
- Optional link to `patients.id` and `doctors.id` of the same vendor only (integrity-checked).
- Make invoice where a real business would bill; print where they would print.
- Dashboard tiles, SVG charts and type registers.
- Isolation: another vendor and another kind of organisation cannot open the list or a record.

**Totals for the 16 types that were pending (hospital departments excluded):** 75 done, 2 partial, 22 missing (the missing items are deliberate later work: PACS, built-in video, partograph, device import, and similar). Hospital departments (5 missing) stay with Builder A.
