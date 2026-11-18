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
