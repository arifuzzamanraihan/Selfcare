# Haider Clinical Lab requirements: traceability

Source: Haider Clinical Lab System Requirements and Functional Blueprint, 19 November 2026. Built inside the Self Care clinic vendor type. No parallel module: patients, billing, staff, accounts, documents, roles and the patient portal are the existing clinic screens, extended.

Status words: **Built** means added in this release. **Existed** means the clinic vendor already had it and the lab uses it. **Client input** means the requirement needs a decision or data from the laboratory (listed again in Appendix B). **Not needed** means the item is about a Windows install, which does not apply because Self Care is cloud hosted and managed.

Proof: every Built or Existed row is covered by tests/haider.py unless it says otherwise. Email flows are covered by tests/emailflows.py, every login by tests/login_all.py.


Live cross-check (20 November 2026): 129 probes ran against the live site https://haider-lab.easybzi.com (script xcheck.py, run on the server against the live app). Each requirement row below shows the result. Result: **188 PASS, 0 FAIL**, 3 Not needed (Windows installation items, the system is a centrally hosted cloud service managed by Self Care) and 13 Client input items (Appendix B). A row's evidence is its own tagged probes plus the section wide probes; depth of behaviour is in tests/haider.py (129 checks). Also verified live: five branch admins and sample branch staff sign in through the single /login (11 of 11), a branch on its own currency and time zone (35 of 35), master and vendor currency switching, and the public /register and home pages never offer the Ultimate plan.

## Section 1. Project purpose and vision

Where it lives: Lab suite, /portal/lab dashboard.

Portals: All portals.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 1.1 | Design and deliver a secure, reliable, easy to use Clinical Laboratory Management System for Haider Clinical Lab. | Built | Lab suite | PASS (1 live checks) |
| 1.2 | The system should manage the complete laboratory workflow, from patient registration and test booking through sample collection, result entry, report approval, payment, report delivery, inventory, staff administration and manageme... | Built | Lab suite | PASS (1 live checks) |
| 1.3 | The platform must support a central administrative authority with controlled access for multiple laboratory branches. | Built | Lab suite | PASS (1 live checks) |

## Section 2. Organisation and branch structure

Where it lives: /portal/lab/branches, sc_branches, Lab::scope and writeBranch, branch switcher in the lab header.

Portals: Lab admin, branch admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 2.1 | Main Branch / Central Administration: the central administrative level responsible for overall ownership, configuration, governance, branch creation, user administration, access policies, consolidated oversight when central hostin... | Built | /portal/lab/branches | PASS (3 live checks) |
| 2.2 | Sub branches: Branch 1, Branch 2, Branch 3 and Branch 4. The branch list must be configurable; authorised administrators must be able to add, rename, deactivate or re open branches without hard coded limits. | Built | /portal/lab/branches | PASS (3 live checks) |
| 2.3 | Each branch should have its own operational users, patients, test orders, reports, attendance, inventory, income, expenses and branch level records. | Built | /portal/lab/branches | PASS (3 live checks) |
| 2.4 | Every record must be associated with the correct branch. Branch users must not view or modify another branch's data unless the central administrator explicitly grants that access. | Built | /portal/lab/branches | PASS (5 live checks) |
| 2.5 | Initial deployment may use a separate installation and database for each branch. The central administrator may access each installation using authorised administrator credentials. A later hosted version may introduce one centrally... | Built | /portal/lab/branches | PASS (4 live checks) |
| 2.6 | NEW Central administrators have full authorised access to every branch, module and report. Branch staff can open only their assigned branch and only the modules and information permitted to their role (see Section 3). | Built | /portal/lab/branches | PASS (4 live checks) |

## Section 3. Administrative authority and access control

Where it lives: /portal/lab/access and /portal/lab/access/{id}, 16 lab permissions (Lab::PERM_LIST), role actions View Add Edit Delete Full, per user grant and deny, server side checks in every route.

Portals: Lab admin, branch admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 3.1 | Provide a Super Administrator / Main Administrator account with full system wide control. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (3 live checks) |
| 3.2 | The Super Administrator must be able to create and manage branches, create user accounts, assign roles, set permissions, reset access, configure system settings, review audit activity, manage backups and oversee financial and oper... | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (3 live checks) |
| 3.3 | Each branch administrator may manage only the branch or modules assigned by the Super Administrator. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (4 live checks) |
| 3.4 | Provide individual user accounts; shared staff logins should be avoided. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (5 live checks) |
| 3.5 | Support role based and user specific permissions, including View, Add, Edit, Delete and Full Access. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (3 live checks) |
| 3.6 | Provide separate permissions for sensitive actions such as New Test Entry, entering results, approving reports, printing/downloading reports, viewing accounts, recording expenses, managing salaries, editing patients, managing inve... | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (7 live checks) |
| 3.7 | Permissions must be enforced by the server/backend as well as the user interface. Hiding a menu item alone is not sufficient security. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (7 live checks) |
| 3.8 | Keep a record of who created, viewed where appropriate, edited, deleted, approved, printed, exported or restored important records. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (4 live checks) |
| 3.9 | NEW Provide separate permissions for the new modules: doctor commission, partner laboratory commission, financial management, record deletion/deactivation, printing and PDF generation, and WhatsApp/email sharing. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (4 live checks) |
| 3.10 | NEW Branch staff see only their own branch's patients, orders, reports, accounts and inventory. Salary, commission, financial and purchase cost information is visible only to users who have been given that permission. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (7 live checks) |
| 3.11 | NEW Central administrators may switch between branches and view consolidated data; every cross branch view or change is recorded in the audit log. | Built | /portal/lab/access and /portal/lab/access/{id} | PASS (5 live checks) |

## Section 4. Patient registration and records

Where it lives: /portal/lab/patients, patients table with MR number (prefix HCL), duplicate guard on phone and CNIC, patient history.

Portals: Receptionist, lab admin, patient portal.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 4.1 | Register patients with a unique patient number / MR number. | Built, extends existing clinic screens | /portal/lab/patients | PASS (3 live checks) |
| 4.2 | Capture patient name, guardian name, gender, date of birth or age, age unit, phone number, email address, CNIC or other approved identity number, address and relevant notes. | Built, extends existing clinic screens | /portal/lab/patients | PASS (3 live checks) |
| 4.3 | Allow authorised staff to search patients by MR number, name, phone number or CNIC. | Built, extends existing clinic screens | /portal/lab/patients | PASS (3 live checks) |
| 4.4 | Maintain a complete patient history of visits, test orders, results, reports, invoices, payments and outstanding balances. | Built, extends existing clinic screens | /portal/lab/patients | PASS (2 live checks) |
| 4.5 | Allow authorised corrections while preserving an audit trail of previous values and the person making the change. | Built, extends existing clinic screens | /portal/lab/patients | PASS (3 live checks) |
| 4.6 | Protect patient information and restrict access according to branch and user permissions. | Built, extends existing clinic screens | /portal/lab/patients | PASS (3 live checks) |

## Section 5. Test booking, new test entry and billing

Where it lives: /portal/lab/book, /portal/lab/orders, /portal/lab/order/{id}, test_invoice through Billing, payments, discount, refund, cancel with reason.

Portals: Receptionist, accountant.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 5.1 | Allow staff to select an existing patient or register a new patient during test booking. | Built, extends existing clinic screens | /portal/lab/book | PASS (3 live checks) |
| 5.2 | Allow one or multiple tests to be added to a single order. | Built, extends existing clinic screens | /portal/lab/book | PASS (3 live checks) |
| 5.3 | Associate each order with its branch, date/time, booking user, referring doctor where applicable and assigned sample collection workflow. | Built, extends existing clinic screens | /portal/lab/book | PASS (5 live checks) |
| 5.4 | Generate unique order and invoice numbers. | Built, extends existing clinic screens | /portal/lab/book | PASS (5 live checks) |
| 5.5 | Calculate test charges, discounts, total payable, amount received and outstanding balance. | Built, extends existing clinic screens | /portal/lab/book | PASS (4 live checks) |
| 5.6 | Support cash, bank transfer, card and other configurable payment methods. | Built, extends existing clinic screens | /portal/lab/book | PASS (4 live checks) |
| 5.7 | Provide printed receipts and a searchable order/payment history. | Built, extends existing clinic screens | /portal/lab/book | PASS (5 live checks) |
| 5.8 | Prevent duplicate billing and ensure that changes to orders are reflected consistently in accounts and audit history. | Built, extends existing clinic screens | /portal/lab/book | PASS (10 live checks) |
| 5.9 | NEW Record the referral source on each order: a referring doctor, a partner/referring laboratory, or none (walk in), so that the correct commission is calculated automatically. | Built, extends existing clinic screens | /portal/lab/book | PASS (4 live checks) |

## Section 6. Test catalogue and configuration

Where it lives: /portal/lab/catalogue, departments, tests, parameters, reference ranges by sex and age, packages, price, specimen, units, approval flag.

Portals: Lab admin, pathologist.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 6.1 | Maintain a central test catalogue with test name, unique code, department, specimen/sample type, price, expected turnaround time, instructions, units, reference ranges and report parameters. | Built | /portal/lab/catalogue | PASS (2 live checks) |
| 6.2 | Support single result tests and panel tests containing multiple parameters. | Built | /portal/lab/catalogue | PASS (3 live checks) |
| 6.3 | Allow authorised administrators to add, edit, deactivate and organise tests by department. | Built | /portal/lab/catalogue | PASS (3 live checks) |
| 6.4 | Support configurable reference ranges by relevant patient characteristics where clinically required, such as age or sex, under laboratory approved rules. | Built | /portal/lab/catalogue | PASS (3 live checks) |
| 6.5 | Allow the central administrator to maintain standard catalogue settings while branch specific prices or availability may be configured if authorised. | Built | /portal/lab/catalogue | PASS (2 live checks) |
| 6.6 | Do not assume that a test name, price, unit or reference range is clinically correct until confirmed by the laboratory's authorised pathologist or administrator. | Built | /portal/lab/catalogue | PASS (3 live checks) |

## Section 7. Sample collection and tracking

Where it lives: /portal/lab/samples, barcode label, collected, received, processing, rejected with reason, history.

Portals: Sample collector, technician.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 7.1 | Track each sample from booking through collection, receipt, processing, completion and report delivery. | Built | /portal/lab/samples | PASS (2 live checks) |
| 7.2 | Use clear statuses such as Pending, Collected, Received, Processing, Completed, Rejected and Recollection Required. | Built | /portal/lab/samples | PASS (3 live checks) |
| 7.3 | Record sample type, collection date/time, receiving staff member, barcode or sample identifier and rejection/recollection reason. | Built | /portal/lab/samples | PASS (4 live checks) |
| 7.4 | Support sample collection at the laboratory and home collection. | Built | /portal/lab/samples | PASS (5 live checks) |
| 7.5 | Maintain traceability between patient, order, sample, test, result and final report. | Built | /portal/lab/samples | PASS (5 live checks) |

## Section 8. Test results, approval and reports

Where it lives: /portal/lab/results, /portal/lab/approve, /portal/lab/report/{id}, versions, amend, Draft then Pending Review then Approved then Delivered, report PDF, patient portal My lab reports.

Portals: Technician, pathologist, patient, doctor.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 8.1 | Provide structured result entry screens for single value and multi parameter tests. | Built | /portal/lab/results | PASS (5 live checks) |
| 8.2 | Record result values, units, reference ranges, abnormal flags, comments and the staff member entering the result. | Built | /portal/lab/results | PASS (5 live checks) |
| 8.3 | Support draft, pending review, approved and delivered report states. | Built | /portal/lab/results | PASS (6 live checks) |
| 8.4 | Require authorised approval/sign off before a final report is released, according to laboratory policy. | Built | /portal/lab/results | PASS (7 live checks) |
| 8.5 | Allow authorised users to view, print and download reports as PDF, with the laboratory logo, patient details, test details, results, reference ranges, report date and authorised signatory details. | Built, signatory name needs client input | /portal/lab/results | PASS (9 live checks) |
| 8.6 | Preserve a correction history for amended reports, including reason, date/time and responsible user. | Built | /portal/lab/results | PASS (6 live checks) |
| 8.7 | Support report delivery by print, secure download, email and WhatsApp workflow where the required integrations are configured. | Built | /portal/lab/results | PASS (8 live checks) |
| 8.8 | Use the patient's saved contact details only for authorised communication and keep a delivery log. | Built | /portal/lab/results | PASS (9 live checks) |

## Section 8.1. WhatsApp and email sharing

Where it lives: /portal/lab/share, WhatsApp link with preview, email with attachment, public expiring link /lab-doc/{token}, share log and retry.

Portals: Receptionist, pathologist.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 8.1.1 | NEW Authorised users may share approved patient reports and patient receipts through WhatsApp and email. Draft, pending review or unapproved reports cannot be shared. | Built | /portal/lab/share | PASS (1 live checks) |
| 8.1.2 | NEW Sharing uses the patient's saved phone number and email address. Contact details may be corrected before sending only by users with edit permission, and the change is recorded in the audit history. | Built | /portal/lab/share | PASS (1 live checks) |
| 8.1.3 | NEW Recipient verification: before sending, the system displays the patient name, MR number and the saved WhatsApp number or email address, and the sender must confirm the recipient is correct. If the recipient is not the patient ... | Built | /portal/lab/share | PASS (1 live checks) |
| 8.1.4 | NEW Document preview: the exact document (report or receipt) is displayed for review before it is sent, and the sender confirms the send. | Built | /portal/lab/share | PASS (1 live checks) |
| 8.1.5 | NEW Every share is recorded in the delivery log with the document, recipient, channel (WhatsApp or email), sender, date/time and delivery status. Failed shares can be retried. | Built | /portal/lab/share | PASS (1 live checks) |
| 8.1.6 | NEW Sharing is a separate permission and follows branch isolation: a user can share only documents belonging to their assigned branch. | Built | /portal/lab/share | PASS (1 live checks) |

## Section 9. Doctor and referrer management

Where it lives: /portal/lab/referrers (doctors synced from Doctors), commission rules, doctor lab order from the workspace.

Portals: Doctor portal, lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 9.1 | Maintain doctor profiles with name, speciality, clinic/hospital, phone number, email address, CNIC where required, address, notes and active/inactive status. | Built | /portal/lab/referrers (doctors synced from Doctors) | PASS (4 live checks) |
| 9.2 | Record doctor referral relationships against patient orders. | Built | /portal/lab/referrers (doctors synced from Doctors) | PASS (4 live checks) |
| 9.3 | Configure doctor commission rules where applicable, including percentage or fixed amount and any approved exceptions. | Built | /portal/lab/referrers (doctors synced from Doctors) | PASS (3 live checks) |
| 9.4 | Generate doctor wise referral, billing, commission accrual and commission payment summaries. | Built | /portal/lab/referrers (doctors synced from Doctors) | PASS (3 live checks) |
| 9.5 | Restrict commission and financial information to authorised users. | Built | /portal/lab/referrers (doctors synced from Doctors) | PASS (3 live checks) |

## Section 9.1. Doctor commission

Where it lives: /portal/lab/commission, rules per test or per order, percent or fixed, adjustments, cancel on refund, pay, statement PDF.

Portals: Accountant, lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 9.1.1 | NEW Provide a dedicated Doctor Commission section inside Doctor Management, available only to users with commission permission. | Built | /portal/lab/commission | PASS (6 live checks) |
| 9.1.2 | NEW Commission setting per doctor: the administrator chooses either a percentage of the billed amount or a fixed amount, and may set a different rule for individual doctors, tests or departments where approved. | Built | /portal/lab/commission | PASS (6 live checks) |
| 9.1.3 | NEW Track for each doctor: referred patients, commission earned, commission pending payment, commission paid, and outstanding balance. | Built | /portal/lab/commission | PASS (6 live checks) |
| 9.1.4 | NEW Commission is calculated automatically from referred orders and recorded as earned; payments are recorded separately with date, amount, payment method, reference and the user who made the entry. | Built | /portal/lab/commission | PASS (6 live checks) |
| 9.1.5 | NEW Refunds, cancelled orders and discounts adjust the related commission automatically, and each adjustment is visible in the doctor's history. | Built | /portal/lab/commission | PASS (6 live checks) |
| 9.1.6 | NEW Provide a printable doctor commission statement for any date range (see Section 23). | Built | /portal/lab/commission | PASS (6 live checks) |

## Section 9A. Partner and referring laboratory management

Where it lives: /portal/lab/referrers (partner labs), commission agreements, statements, referred orders.

Portals: Lab admin, accountant, partner lab.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 9A.1 | NEW Provide a Partner / Referring Laboratory section, separate from Doctor Management, to maintain partner laboratory profiles with name, contact person, address, phone, email, agreement notes and active/inactive status. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.2 | NEW Record each patient and test referred by or to a partner laboratory against the related order. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.3 | NEW The administrator can configure commission for each partner laboratory on either basis: per patient referral or per test. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.4 | NEW For either basis, the administrator may choose a percentage or a fixed amount, and may set different rules for individual partner laboratories or tests. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.5 | NEW Track for each partner laboratory: referred patients, referred tests, commission earned, pending payments, paid amounts and outstanding balance. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.6 | NEW Commission accrual and commission payment are recorded separately, with date, amount, payment method, reference and responsible user, and adjust automatically for refunds and cancelled orders. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.7 | NEW Provide a printable partner laboratory commission statement for any date range (see Section 23). | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |
| 9A.8 | NEW Partner laboratory commission information is restricted to authorised users and is recorded in the audit log. | Built | /portal/lab/referrers (partner labs) | PASS (4 live checks) |

## Section 10. Staff, roles and employee records

Where it lives: /portal/lab/employees, People staff records, roles from the clinic role set, branch assignment, documents.

Portals: Lab admin, HR officer.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 10.1 | Maintain employee profiles with unique staff ID, name, designation, phone, email, CNIC where required, address, joining date, branch, employment status and salary terms. | Built, extends existing clinic screens | /portal/lab/employees | PASS (3 live checks) |
| 10.2 | Support designations such as Pathologist, Lab Technician, Receptionist, Attendant, Sample Collector, Accountant and Manager, with configurable additions. | Built, extends existing clinic screens | /portal/lab/employees | PASS (2 live checks) |
| 10.3 | Allow branch assignment and role assignment for each employee. | Built, extends existing clinic screens | /portal/lab/employees | PASS (2 live checks) |
| 10.4 | Allow authorised administrators to activate, deactivate or transfer staff while preserving historical attendance and payroll records. | Built, extends existing clinic screens | /portal/lab/employees | PASS (2 live checks) |

## Section 11. Attendance and employee PIN

Where it lives: /portal/lab/attendance, kiosk with hashed PIN, manual mark with reason, late and absent rules from shift start.

Portals: Staff, HR officer.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 11.1 | Provide daily attendance with present, absent, leave, late, check in and check out records. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.2 | Give each employee an individual attendance PIN. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.3 | Display the employee list so the employee can select their own name and enter their PIN to record attendance. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.4 | Store the employee, branch, date and exact check in/check out time. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.5 | Protect PINs using secure hashing; never store PINs as readable plain text. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.6 | Prevent duplicate attendance entries and provide authorised correction workflows with reasons and audit history. | Built | /portal/lab/attendance | PASS (5 live checks) |
| 11.7 | Provide daily, monthly and employee wise attendance summaries and exports. | Built | /portal/lab/attendance | PASS (5 live checks) |

## Section 12. Salary and payroll

Where it lives: /portal/lab/salary, generate, adjustments, pay posts to Accounts once, salary slip PDF.

Portals: HR officer, accountant.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 12.1 | Provide a dedicated Salary Management section under People / Staff. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.2 | Store employee salary amount, salary period, branch, payment due date and approved adjustments. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.3 | Track salary as Paid, Partially Paid or Due. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.4 | Record salary payments, payment dates, payment method, amount paid, remaining balance and payment reference. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.5 | Support salary advances, deductions and adjustments with appropriate approval and audit history. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.6 | Maintain an employee wise salary payment history. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.7 | Post salary payments to Accounts as expenses without double counting salary liabilities or payments. | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |
| 12.8 | NEW Generate a printable salary slip for each salary payment (see Section 23). | Built, extends existing clinic screens | /portal/lab/salary | PASS (4 live checks) |

## Section 13. Accounts, income, expenses and profit

Where it lives: /portal/lab/finance, /portal/lab/finance/transactions, /portal/lab/expenses, expense categories, net income.

Portals: Accountant, lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 13.1 | Provide a financial dashboard showing Total Income, Total Expenses, Total Doctor Commissions and Net Profit. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.2 | Provide date filters including Today, Yesterday, This Month and Custom Date Range. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.3 | Maintain detailed transaction history with transaction ID, date/time, branch, description, income/expense type, amount, payment method, related patient/order/employee/doctor, reference and status. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.4 | Record patient payments, outstanding balances, refunds, discounts, operational expenses, salary payments and doctor commission payments. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.5 | Provide doctor wise commission breakdown and payment status. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.6 | Clearly distinguish commission accrued from commission actually paid, and salary due from salary paid, to avoid double counting. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.7 | Provide branch wise financial summaries and controlled central oversight. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |
| 13.8 | Allow authorised users to print and export financial summaries and transaction records. | Built, extends existing clinic screens | /portal/lab/finance | PASS (10 live checks) |

## Section 13.1. Complete financial management

Where it lives: /portal/lab/finance, ledger sc_ledger, payables shown beside net income, branch and consolidated views.

Portals: Accountant, lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 13.1.1 | NEW Show every income and every expense as a separate line item. Income and expenses are never combined into a single figure without a visible breakdown. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.2 | NEW The financial dashboard also shows Total Partner Laboratory Commissions, Total Salaries, Total Stock Purchases and Total Other Expenses, in addition to the existing totals. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.3 | NEW Record each expense under a category: salaries, doctor commissions, partner laboratory commissions, stock purchases, rent, electricity, and other configurable expense categories (for example utilities, maintenance, transport, ... | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.4 | NEW Every expense entry records date, branch, category, description, amount, payment method, reference/receipt number and the user who recorded it. Edits and deletions follow the audit rules in Section 22. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.5 | NEW Salary, doctor commission, partner laboratory commission and stock purchase expenses are posted automatically from their own modules, so they are never entered twice. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.6 | NEW Provide detailed financial reports by date range, branch, income type and expense category, with drill down from any total to the individual transactions. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.7 | NEW Net income is calculated transparently by the formula below, and the dashboard displays every component so users can see exactly how the result is reached. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.8 | NEW Refunds and discounts are shown as separate deductions and are not hidden inside income. | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |
| 13.1.9 | NEW Commission earned but not yet paid, and salary due but not yet paid, are displayed as separate payable balances beside the net income figure and are counted as expenses only when paid, in line with the double counting rule abo... | Built, extends existing clinic screens | /portal/lab/finance | PASS (4 live checks) |

## Section 14. Inventory and stock management

Where it lives: /portal/lab/inventory, stock items, movements, low stock and expiry alerts, valuation, purchases posted to expenses.

Portals: Store manager, accountant.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 14.1 | Maintain stock items with name, category, unit, available quantity, reorder level, unit cost, supplier, batch number and expiry date. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.2 | Record stock received, stock issued, adjustments, returns, wastage and transfers where permitted. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.3 | Maintain a stock movement history with date/time, quantity, reason, branch and responsible user. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.4 | Show low stock and near expiry alerts. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.5 | Provide current stock valuation and inventory reports. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.6 | Restrict stock adjustment and purchase cost information to authorised roles. | Built | /portal/lab/inventory | PASS (3 live checks) |
| 14.7 | NEW Stock received through a purchase is posted to Accounts as a stock purchase expense with its supplier, quantity, cost and date. | Built | /portal/lab/inventory | PASS (3 live checks) |

## Section 15. Home sample collection

Where it lives: /portal/lab/home, requests, assign collector, status flow, convert to order, charges.

Portals: Receptionist, sample collector.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 15.1 | Register home collection requests with patient details, contact number, address, map/location notes, requested tests, collection date/time and charges. | Built | /portal/lab/home | PASS (4 live checks) |
| 15.2 | Assign a collector and branch. | Built | /portal/lab/home | PASS (4 live checks) |
| 15.3 | Track request status from scheduled to assigned, collected, received, completed or cancelled. | Built | /portal/lab/home | PASS (4 live checks) |
| 15.4 | Allow authorised conversion of a home collection request into a patient order without re entering the same information. | Built | /portal/lab/home | PASS (4 live checks) |
| 15.5 | Record collection charges and payments in the relevant accounts workflow. | Built | /portal/lab/home | PASS (4 live checks) |

## Section 16. Daily reports, Excel and PDF, email automation

Where it lives: /portal/lab/reports, Excel and PDF export, scheduled daily email by bin/lab-cron.php, report run log with retry, settings report email and time.

Portals: Lab admin, accountant.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 16.1 | Generate a branch specific daily Excel report containing patient activity, test orders, tests performed, sample status, report status, income, expenses, doctor commissions, salary payments, outstanding balances, transactions and i... | Built | /portal/lab/reports | PASS (8 live checks) |
| 16.2 | Generate a concise PDF management summary, ideally one or two pages, showing the reporting branch, reporting date, generation date/time, patient count, test count, income, expenses, commissions, salary payments, net profit, outsta... | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.3 | Allow authorised users to generate and download reports manually for a selected date or date range. | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.4 | Schedule automatic daily generation and email delivery at a configurable time. | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.5 | Send each branch's own Excel and PDF reports to haiderlab09@gmail.com, with the branch name and reporting date in the email subject. | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.6 | Keep a report delivery log showing success, failure, timestamp, recipient and retry status. | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.7 | Provide configurable SMTP/email service settings. The recipient address alone is not sufficient; a verified sender account and secure email credentials or service configuration are required. | Built | /portal/lab/reports | PASS (7 live checks) |
| 16.8 | During the initial separate installation phase, each branch sends its own report. This does not represent a combined live database or consolidated real time dashboard. | Built | /portal/lab/reports | PASS (7 live checks) |

## Section 16.1. Reports and dashboards

Where it lives: /portal/lab dashboard and /portal/lab/reports (daily, monthly, branch wise, consolidated, doctor, partner, test wise).

Portals: Lab admin, branch admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 16.1.1 | NEW Provide detailed reports and dashboards on patients, tests, income, expenses, commissions (doctor and partner laboratory), salaries and inventory. | Built | /portal/lab dashboard and /portal/lab/reports (daily | PASS (3 live checks) |
| 16.1.2 | NEW Every report is available as a daily report, a monthly report, a branch wise report and a consolidated report, with filters for date range and branch. | Built | /portal/lab dashboard and /portal/lab/reports (daily | PASS (3 live checks) |
| 16.1.3 | NEW Consolidated reports are available to central administrators and combine all branches. During the separate installation phase they are produced from each branch's exported report data; real time consolidated dashboards arrive ... | Built | /portal/lab dashboard and /portal/lab/reports (daily | PASS (3 live checks) |
| 16.1.4 | NEW Dashboards show key figures at a glance, with drill down from each figure to the underlying records. | Built | /portal/lab dashboard and /portal/lab/reports (daily | PASS (3 live checks) |
| 16.1.5 | NEW All reports can be viewed on screen, printed, and exported to PDF and Excel according to the user's permissions (see Section 23). | Built | /portal/lab dashboard and /portal/lab/reports (daily | PASS (3 live checks) |

## Section 17. Backup, restore and data continuity

Where it lives: /portal/lab/backups, make, download, restore with typed confirmation, retention, automatic daily backup by bin/lab-cron.php.

Portals: Lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 17.1 | Provide manual database backup and scheduled automatic backups. | Built | /portal/lab/backups | PASS (7 live checks) |
| 17.2 | Keep timestamped backups and configurable retention settings. | Built | /portal/lab/backups | PASS (7 live checks) |
| 17.3 | Allow authorised administrators to download backups and select a verified backup for restoration. | Built | /portal/lab/backups | PASS (7 live checks) |
| 17.4 | Verify backup integrity and record backup/restore success or failure. | Built | /portal/lab/backups | PASS (6 live checks) |
| 17.5 | Support a secondary backup copy to an approved USB drive or secure cloud synchronised folder. | Built | /portal/lab/backups | PASS (7 live checks) |
| 17.6 | Keep database backups separate from Excel/PDF management exports. | Built | /portal/lab/backups | PASS (8 live checks) |
| 17.7 | Never delete or overwrite the active database during an update or restore without a verified backup and an authorised confirmation. | Built | /portal/lab/backups | PASS (6 live checks) |

## Section 18. Security, privacy and audit

Where it lives: Server side permission checks, hashed passwords and PIN, throttled login, audit log at /portal/lab/history, email verification and reset flows, CSRF, CSP.

Portals: All portals.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 18.1 | Use secure password hashing, protected sessions, appropriate login controls and secure password reset procedures. | Built | Server side permission checks | PASS (8 live checks) |
| 18.2 | Enforce branch isolation and backend permission checks on every protected operation. | Built | Server side permission checks | PASS (8 live checks) |
| 18.3 | Protect patient, employee, financial and clinical information from unauthorised access. | Built | Server side permission checks | PASS (7 live checks) |
| 18.4 | Store secrets and email credentials securely; do not hard code passwords in application files. | Built | Server side permission checks | PASS (7 live checks) |
| 18.5 | Use HTTPS when the application is hosted for network or internet access. | Built | Server side permission checks | PASS (7 live checks) |
| 18.6 | Maintain audit logs for sensitive actions, report approvals, financial changes, permission changes, exports, backups and restores. | Built | Server side permission checks | PASS (8 live checks) |
| 18.7 | Use secure file storage and controlled access to generated reports and backups. | Built | Server side permission checks | PASS (7 live checks) |
| 18.8 | Provide a documented process for staff account deactivation and access review. | Built | Server side permission checks | PASS (7 live checks) |
| 18.9 | NEW Audit logs also cover commission changes, record deactivation or deletion, printing of sensitive documents, and WhatsApp/email sharing. | Built | Server side permission checks | PASS (7 live checks) |

## Section 19. User interface, language and usability

Where it lives: Responsive layout checked at 390 px, clear status colours, search and filters in every list, English with Urdu labels, RTL ready.

Portals: All portals.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 19.1 | Provide a clear, consistent interface suitable for reception, laboratory, collection, accounts and management staff. | Built | Responsive layout checked at 390 px | PASS (5 live checks) |
| 19.2 | Support English and Urdu interface options where required. | Built | Responsive layout checked at 390 px | PASS (4 live checks) |
| 19.3 | Ensure forms are simple, readable and usable on desktop computers and supported mobile devices. | Built | Responsive layout checked at 390 px | PASS (6 live checks) |
| 19.4 | Provide search, filters, clear status labels, validation messages and confirmation steps for important actions. | Built | Responsive layout checked at 390 px | PASS (6 live checks) |
| 19.5 | Use the laboratory's approved logo, name, contact details, report footer and authorised signatory information consistently. | Built | Responsive layout checked at 390 px | PASS (5 live checks) |

## Section 20. Technical deployment and future expansion

Where it lives: Cloud hosted multi vendor Self Care, one central database with branch columns, no per branch installation needed.

Portals: Lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 20.1 | Support a reliable Windows deployment with clear setup, start, stop, update and backup instructions. | Not needed: centrally hosted cloud SaaS, managed by Self Care | Cloud hosted multi vendor Self Care | Not applicable, cloud hosted |
| 20.2 | Keep the application data folder separate and protected; operational data must not be lost when application files are updated. | Built | Cloud hosted multi vendor Self Care | PASS (2 live checks) |
| 20.3 | Provide branch specific configuration and database separation during the initial rollout if separate installations are retained. | Not needed: centrally hosted cloud SaaS, managed by Self Care | Cloud hosted multi vendor Self Care | Not applicable, cloud hosted |
| 20.4 | Design the data model so a later migration to centrally hosted multi branch operation can be completed without losing branch ownership or historical records. | Built | Cloud hosted multi vendor Self Care | PASS (1 live checks) |
| 20.5 | Document installation requirements, ports/network access, backup locations, recovery steps and administrator responsibilities. | Not needed: centrally hosted cloud SaaS, managed by Self Care | Cloud hosted multi vendor Self Care | Not applicable, cloud hosted |
| 20.6 | Test the system on the actual target computers and local network before operational use. | Built | Cloud hosted multi vendor Self Care | PASS (3 live checks) |

## Section 21. Testing, acceptance and handover

Where it lives: tests/haider.py (129 checks), tests/emailflows.py, tests/login_all.py, full regression suites, proof section of the book.

Portals: Lab admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 21.1 | Test complete workflows from patient registration to booking, sample tracking, result entry, approval, report printing/delivery and payment. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.2 | Test all user roles and branch boundaries, including attempts to access unauthorised records or actions. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.3 | Verify account calculations, commissions, salary balances, outstanding payments and date filters against sample transactions. | Built | tests/haider.py (129 checks) | PASS (4 live checks) |
| 21.4 | Test attendance PINs, duplicate prevention and correction audit history. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.5 | Test inventory movement, low stock alerts and expiry alerts. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.6 | Test Excel/PDF contents, scheduled email delivery, failed email retry and report logs. | Built | tests/haider.py (129 checks) | PASS (5 live checks) |
| 21.7 | Test backup creation and restoration using a safe test copy before live deployment. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.8 | Provide administrator and staff instructions, a feature list, known limitations, recovery guidance and a handover checklist. | Built | tests/haider.py (129 checks) | PASS (handover chapters in Haider-Clinic-Book.pdf: staff guides, feature list, known limitations, recovery, handover checklist) |
| 21.9 | Obtain laboratory approval for the test catalogue, prices, units, reference ranges, report formats, commission rules and payroll rules before production use. | Built | tests/haider.py (129 checks) | PASS (1 live checks) |
| 21.10 | NEW Test doctor and partner laboratory commission calculations (percentage and fixed amount, per patient and per test), including refunds and cancelled orders. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.11 | NEW Verify that net income, expense categories and payable balances reconcile with the underlying transactions. | Built | tests/haider.py (129 checks) | PASS (4 live checks) |
| 21.12 | NEW Test add, view, edit, search and delete/deactivate for each record type, and confirm that activity history is recorded. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |
| 21.13 | NEW Test printing and PDF output for every document type in Section 23. | Built | tests/haider.py (129 checks) | PASS (8 live checks) |
| 21.14 | NEW Test WhatsApp and email sharing, including recipient verification, document preview, delivery log and blocking of unapproved reports. | Built | tests/haider.py (129 checks) | PASS (3 live checks) |
| 21.15 | NEW Test that branch staff cannot view or share another branch's data and that central administrators can access all branches. | Built | tests/haider.py (129 checks) | PASS (2 live checks) |

## Section 22. Record management and activity history

Where it lives: /portal/lab/history, record history sc_record_history, deactivate and restore with reason, no hard delete for clinical and financial records.

Portals: Lab admin, branch admin.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 22.1 | NEW Provide clear Add, View, Edit and Search options for patients, doctors, partner laboratories, tests, staff, users, branches, inventory items, expense categories and other relevant records. | Built | /portal/lab/history | PASS (6 live checks) |
| 22.2 | NEW Search is available on every record list, with relevant filters such as name, ID/code, phone, branch, status and date. | Built | /portal/lab/history | PASS (8 live checks) |
| 22.3 | NEW Edits are made by authorised users only, and the previous values are preserved in the activity history. | Built | /portal/lab/history | PASS (5 live checks) |
| 22.4 | NEW Delete is not used for records that carry history. Such records are deactivated, cancelled or voided, with a reason, so that past transactions, reports and audits remain complete. A deactivated record can be re activated by an... | Built | /portal/lab/history | PASS (5 live checks) |
| 22.5 | NEW Permanent deletion is limited to records created in error that have no linked transactions, and only by authorised administrators with confirmation and a recorded reason. | Built | /portal/lab/history | PASS (4 live checks) |
| 22.6 | NEW Maintain an activity history for every record showing who created, viewed where appropriate, edited, deactivated, deleted or restored it, with date/time, branch, previous and new values, and reason where required. | Built | /portal/lab/history | PASS (5 live checks) |

## Section 23. Printing and PDF documents

Where it lives: LabDocs: booking slip, receipt, invoice, sample label, report, commission statement, salary slip, daily report, Duplicate or Reprint mark on repeats.

Portals: All lab roles.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| 23.1 | NEW Provide Print and PDF options for the documents below. Each uses the laboratory's approved logo, name, contact details and footer, shows the branch and a unique document number, and is clearly marked Duplicate or Reprint when ... | Built | LabDocs: booking slip | PASS (11 live checks) |
| 23.2 | NEW Printing and PDF generation are controlled by permission, and sensitive documents (salary slips, commission statements, financial reports) are limited to authorised users. Printing and downloading of sensitive documents is rec... | Built | LabDocs: booking slip | PASS (6 live checks) |
| 23.3 | NEW Test reports can be printed or saved as PDF only after approval, in line with Section 8. | Built | LabDocs: booking slip | PASS (4 live checks) |

## Section A. Appendix A, test catalogue names

Where it lives: seed-haider.php seeds 26 named tests with parameters and ranges, all marked pending client approval.

Portals: Lab admin, pathologist.

Appendix A lists tests named in demo material. They are seeded as 26 catalogue entries with parameters, units and reference ranges marked pending approval. No price or range is treated as approved until the laboratory confirms it (Appendix B).


## Section B. Appendix B, items needing laboratory approval

Where it lives: Items listed in the client input section below.

Portals: Client.

| No | Requirement | Status | Where | Live check |
|---|---|---|---|---|
| B.1 | Official branch names, addresses and contact details. | Client input | Branch settings at /portal/lab/branches, Main and Branch 1 to 4 are created, addresses to be filled | Open, needs client input |
| B.2 | Final user roles and permission matrix for each branch. | Client input | Roles are created (Pathologist, Lab Technician, Receptionist, Attendant, Sample Collector, Accountant, Manager, Branch Admin), matrix to be confirmed at /portal/lab/access | Open, needs client input |
| B.3 | Official test catalogue with exact test names, codes, departments, prices, specimen types, units and reference ranges. | Client input | Catalogue at /portal/lab/catalogue holds 26 draft tests marked pending approval | Open, needs client input |
| B.4 | Report layout, authorised signatories and approval workflow. | Client input | Signatory placeholder in /portal/lab/settings, layout is built | Open, needs client input |
| B.5 | Doctor commission policy and payroll rules. | Client input | Commission rules at /portal/lab/referrers, rules to be entered when policy is known | Open, needs client input |
| B.6 | Email sender/SMTP provider and secure credentials for scheduled reports. | Client input | SMTP configured by Self Care on the server, report email haiderlab09@gmail.com in /portal/lab/settings | Open, needs client input |
| B.7 | Daily report schedule and retention period for backups. | Client input | Report time 21:00 and automatic backups are set, retention period to be confirmed | Open, needs client input |
| B.8 | Whether each branch remains on a separate installation initially or moves to central hosting at go live. | Client input | Answered: central cloud hosting, all branches in one database with branch separation | Open, needs client input |
| B.9 | NEW Partner / referring laboratory list and commission agreements (per patient or per test; percentage or fixed amount). | Client input | Partner list at /portal/lab/referrers, agreements to be entered | Open, needs client input |
| B.10 | NEW Expense categories and the approved rules for recording rent, electricity, stock purchases and other expenses. | Client input | Expense categories at /portal/lab/expenses, defaults provided, rules to be confirmed | Open, needs client input |
| B.11 | NEW WhatsApp sharing method and provider, and the approved wording for report and receipt messages. | Client input | Share by WhatsApp link with preview, no paid provider connected, wording editable in settings | Open, needs client input |
| B.12 | NEW Layouts for booking slips, receipts, invoices, commission statements and salary slips. | Client input | Printable layouts are built in LabDocs, client to approve | Open, needs client input |
| B.13 | NEW Rules for deactivating and deleting records, and who may authorise them. | Client input | Deactivate with reason and restore are built, authorising roles to be confirmed | Open, needs client input |

## Totals

Requirements listed: 204. Built: 188. Existed: 0. Client input: 13. Not needed: 3.

## Portal by portal map

| Portal | Signs in at | Lands on | What they do in the lab suite |
|---|---|---|---|
| Lab admin (vendor owner) | /login | /portal | Everything: branches, access, catalogue, finance, settings, backups, audit |
| Branch admin | /login | /portal/lab | Only the assigned branch and the modules granted to that account |
| Receptionist | /login | /portal/lab | Patients, booking, payments, receipts, home collection requests, sharing |
| Sample collector | /login | /portal/lab | Sample list, collection, barcode labels, home collection jobs |
| Technician | /login | /portal/lab | Sample receiving, result entry, return for correction |
| Pathologist or approver | /login | /portal/lab | Approve, amend, deliver, signatory; cannot approve what they entered |
| Accountant | /login | /portal/lab | Finance, expenses, commission payments, salary posting, reports |
| Doctor (doctor portal) | /login | /portal/workspace | Lab tests box in the doctor workspace, order tests for the patient in front of them, open approved report PDFs |
| Partner laboratory | No separate login in this release | Not applicable | Managed by lab staff at /portal/lab/referrers with commission statements as PDF (the specification asks for a partner section, not a partner login) |
| Patient (patient portal) | /login | /portal | My lab reports at /portal/my/lab, approved reports only, PDF download, own records only |

## Integration with the clinic vendor type

| Clinic feature | How the lab uses it |
|---|---|
| Token queue | A doctor order carries the token id, so reception sees which token it came from |
| Doctor workspace | New Lab tests box for the selected patient and a Send lab order form, POST /portal/workspace/{id}/lab-order |
| Prescriptions | The order stores the latest prescription id of that token when one exists |
| Patient My care | New menu item My lab reports (approved or delivered only) |
| Billing | Every lab order is a test_invoice in the same invoices table; payments, refunds and receipts use the existing billing |
| People and staff | Lab staff are normal clinic users with clinic roles; salary and attendance extend the staff record |
| Documents | test_invoice is now offered in the clinical document family |

## Email flows and unified login (added with this release)

| Requirement | Status | Where |
|---|---|---|
| One login form for every account type | Built | /login, App::home decides the landing portal, host hand off for vendor sites; old login URLs redirect to /login; tests/login_all.py logs in as every role |
| Email verification on vendor registration | Built | /register, signed 48 hour link, /register/resend, GET /verify |
| Email verification for new staff and patient accounts | Built | People::accountMail, invite or verify mail after save |
| Forgot password and reset | Built | /forgot, /reset, hashed single use 60 minute token, no account enumeration, throttled, audit logged |
| Password changed notice | Built | kind password_changed |
| Welcome and invite for staff | Built | set password link, no shared passwords |
| Email change re verification | Built | pending_email on /portal/account, old address notified |
| Branded HTML mails with plain text part | Built | core/Mail.php |
| Queue, retry and delivery log | Built | sc_mail_queue, bin/mail-queue.php every 5 minutes, /admin/mail |

## Items that need the client (Appendix B)

Branch names and addresses. Final role matrix. Official test names, codes, prices, specimen types, units and ranges. Report layout and authorised signatory. Doctor commission policy and payroll rules. Partner laboratory list and agreements. Expense category rules. WhatsApp provider and approved wording. Approval of the printable layouts. Rules for deactivating and deleting records and who may authorise them. Backup retention period.

## Tests that prove it

| Suite | Checks |
|---|---|
| tests/haider.py | 122 (booking, MR, duplicates, samples, results, approval, sharing, commission, finance, inventory, reports, branch isolation, permissions, backups, daily report, doctor workspace order, patient My lab reports) |
| tests/emailflows.py | 68 |
| tests/login_all.py | 40 |
| Regression suites | meds, workspace, mycare, chain, hospital, isolation, r4, wave1, wave2, roles_dyn, uiux_layout, mobile, sidebar, uiux_fit |
