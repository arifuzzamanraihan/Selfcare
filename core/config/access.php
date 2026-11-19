<?php
// Selfcare original code. Portal permissions and default role access.
//
// This follows the pattern that already exists in database/selfcare.sql:
//   permissions            (id, name, guard_name)           named like manage_appointments
//   departments            the roles (Admin, Doctor, ...)   used as role rows
//   role_has_permissions   (permission_id, role_id)         default grants per role
//   model_has_roles        (role_id, model_type, model_id)  which user has which role
//   <role>s tables         accountants, nurses, ... (id, user_id, tenant_id)
// New in phase 1, same style, tenant_id scoped:
//   tenant_role_has_permissions (tenant_id, role_id, permission_id)  what a vendor changed for a role
//   staff_access_windows / staff_shifts                              time based access
// A vendor gets a copy of the default grants for its type when it registers. After that its admin edits them.
declare(strict_types=1);

return [
    // permission name => [label, group, help, existing in database/selfcare.sql ?]
    'permissions' => [
        'manage_appointments'   => ['Requests (appointments, orders, bookings)', 'Requests', 'See and update the requests people send from the website.', true],
        'manage_patients'       => ['Patients and clients', 'People', 'See and register the people the vendor serves.', true],
        'manage_doctors'        => ['Doctors and clinical team', 'People', 'See and manage doctors, therapists and similar team members.', true],
        'manage_users'          => ['Staff accounts', 'People', 'Create staff accounts and choose their roles.', true],
        'manage_doctor_departments' => ['Departments', 'Website', 'Edit the department list.', true],
        'manage_notice_board'   => ['News and notices', 'Website', 'Post news, notices and camps.', true],
        'manage_settings'       => ['Website and profile', 'Website', 'Edit profile, hours, text, gallery, FAQ and reviews.', true],
        'manage_catalog'        => ['Services, products, tests and fleet', 'Website', 'Edit the main list shown on the website.', false],
        'manage_free_services'  => ['Free services', 'Website', 'Edit the free services the vendor offers.', false],
        'view_messages'         => ['Website messages', 'Messages', 'Read messages sent from the contact form.', false],
        'manage_access'         => ['Role access and time access', 'Access', 'Change what roles can do and when staff can sign in.', false],
        'view_plan'             => ['Plan and expiry', 'Plan', 'See the current subscription.', false],
        'manage_invoices'       => ['Invoices and payments', 'Money', 'Create invoices and receipts, record payments, download PDFs.', true],
        'manage_prescriptions'  => ['Clinical documents and forms', 'Records', 'Fill in prescriptions, notes, certificates and other forms.', true],
        'manage_medicines'      => ['Stock, store and registers', 'Records', 'Stock sheets, controlled drug register, challans and service tickets.', true],
        'manage_blood_bank'     => ['Blood bank records', 'Records', 'Donor, screening and blood issue records.', true],
        'manage_accounts'       => ['Accounts: cash book, expenses and dues', 'Money', 'Record expenses and other income, read the daily cash book and the dues, print the day report.', false],
        'manage_rx'             => ['Prescriptions: write, issue and print', 'Records', 'Write prescriptions with medicines, issue them, print the PDF and copy an old one.', false],
        'view_rx'               => ['Prescriptions: view and print', 'Records', 'Read and print prescriptions and a patient\'s prescription history.', false],
        'manage_ipd'            => ['Wards, admissions, emergency and theatre', 'Records', 'Bed board, inpatient admissions, emergency triage and operation theatre bookings.', false],
        'manage_reports'        => ['Reports', 'Money', 'Open and download reports.', true],
        'lab_booking'           => ['Lab: book tests and bills', 'Laboratory', 'Register patients, book tests, take payments, print booking slips and receipts.', false],
        'lab_samples'           => ['Lab: samples and home collection', 'Laboratory', 'Collect, receive and reject samples, run home collection requests.', false],
        'lab_results'           => ['Lab: enter results', 'Laboratory', 'Type results, send them for review and print draft sheets.', false],
        'lab_approve'           => ['Lab: approve reports', 'Laboratory', 'Review, approve, return and amend lab reports. Sensitive.', false],
        'lab_catalogue'         => ['Lab: test catalogue and prices', 'Laboratory', 'Edit tests, panels, reference ranges and branch prices.', false],
        'lab_referrers'         => ['Lab: referring doctors and partner labs', 'Laboratory', 'Manage referrers and their commission rules.', false],
        'lab_commission'        => ['Lab: commission statements and payments', 'Laboratory', 'See earned, pay and print doctor and partner commission. Sensitive.', false],
        'lab_finance'           => ['Lab: finance dashboard and expenses', 'Laboratory', 'Income, expenses, net income, payables and drill down. Sensitive.', false],
        'lab_inventory'         => ['Lab: inventory', 'Laboratory', 'Reagents and consumables: receive, issue, return, waste, transfer.', false],
        'lab_staff'             => ['Lab: employees', 'Laboratory', 'Staff profiles, branch assignment, transfer and deactivation.', false],
        'lab_attendance'        => ['Lab: attendance', 'Laboratory', 'Attendance register, PIN kiosk, corrections.', false],
        'lab_salary'            => ['Lab: salary', 'Laboratory', 'Salary sheets, advances, deductions, payment and slips. Sensitive.', false],
        'lab_share'             => ['Lab: share by WhatsApp and email', 'Laboratory', 'Send approved reports and receipts. Sensitive.', false],
        'lab_records'           => ['Lab: record history and voids', 'Laboratory', 'Activity history, deactivate, void and restore records.', false],
        'lab_branches'          => ['Lab: branches, users, settings and backups', 'Laboratory', 'Branches, user permissions, SMTP, schedules, backups. Sensitive.', false],
        'lab_reports'           => ['Lab: dashboards and reports', 'Laboratory', 'Daily, monthly, branch and consolidated reports with Excel and PDF.', false],
    ],

    // What each permission (module) can be done with. "view" is the grant itself. An action that does not apply is shown as a dash.
    'perm_actions' => [
        'manage_appointments' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_patients' => ['add', 'edit', 'delete', 'print', 'export'],
        'manage_doctors' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_users' => ['add', 'edit', 'delete'],
        'manage_doctor_departments' => ['add', 'edit', 'delete'], 'manage_notice_board' => ['add', 'edit', 'delete'],
        'manage_settings' => ['edit'], 'manage_catalog' => ['add', 'edit', 'delete'], 'manage_free_services' => ['add', 'edit', 'delete'],
        'view_messages' => ['edit', 'delete'], 'manage_access' => ['add', 'edit', 'delete'], 'view_plan' => [],
        'manage_invoices' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_prescriptions' => ['add', 'edit', 'delete', 'print', 'export'],
        'manage_medicines' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_blood_bank' => ['add', 'edit', 'delete', 'print', 'export'],
        'manage_accounts' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_rx' => ['add', 'edit', 'delete', 'print', 'export'], 'manage_ipd' => ['add', 'edit', 'delete', 'print', 'export'],
        'view_rx' => ['print'], 'manage_reports' => ['print', 'export'],
        'lab_booking' => ['add', 'edit', 'delete', 'print', 'export'], 'lab_samples' => ['add', 'edit', 'print'], 'lab_results' => ['add', 'edit', 'print'], 'lab_approve' => ['edit', 'print'],
        'lab_catalogue' => ['add', 'edit', 'delete', 'export'], 'lab_referrers' => ['add', 'edit', 'delete'], 'lab_commission' => ['add', 'edit', 'print', 'export'], 'lab_finance' => ['add', 'edit', 'delete', 'print', 'export'],
        'lab_inventory' => ['add', 'edit', 'delete', 'print', 'export'], 'lab_staff' => ['add', 'edit', 'delete', 'export'], 'lab_attendance' => ['add', 'edit', 'print', 'export'], 'lab_salary' => ['add', 'edit', 'print', 'export'],
        'lab_share' => ['add'], 'lab_records' => ['edit', 'delete', 'export'], 'lab_branches' => ['add', 'edit', 'delete', 'export'], 'lab_reports' => ['print', 'export'],
    ],

    // What a role may do inside the modules it has, until the vendor changes it. Anything not listed gets "default".
    // Deleting is for managers, accountants (voiding bills) and admins. Auditors only look, print and export.
    'action_profiles' => [
        'default' => ['add', 'edit', 'print'],
        'Medical Director' => ['add', 'edit', 'delete', 'print', 'export', 'manage_ipd'], 'Branch Manager' => ['add', 'edit', 'delete', 'print', 'export'],
        'HR Officer' => ['add', 'edit', 'print', 'export'], 'IT Administrator' => ['add', 'edit', 'delete', 'print', 'export'],
        'Compliance Officer' => ['print', 'export'], 'External Auditor' => ['print', 'export'],
        'Accountant' => ['add', 'edit', 'delete', 'print', 'export'], 'Billing Clerk' => ['add', 'edit', 'print', 'export'], 'Insurance Officer' => ['add', 'edit', 'print', 'export'],
        'Store Manager' => ['add', 'edit', 'delete', 'print', 'export'], 'Medical Records Officer' => ['add', 'edit', 'print', 'export'],
        'Pharmacist' => ['add', 'edit', 'print', 'export'], 'Pathologist' => ['add', 'edit', 'print', 'export'], 'Radiologist' => ['add', 'edit', 'print', 'export'],
        'Consultant' => ['add', 'edit', 'print', 'export', 'manage_ipd'], 'Doctor' => ['add', 'edit', 'print', 'export'],
        'Head Nurse' => ['add', 'edit', 'print', 'export', 'manage_ipd'],
        'Ward Attendant' => ['edit', 'print', 'manage_ipd'], 'Driver' => ['edit', 'print'], 'Caregiver' => ['add', 'edit', 'print'],
    ],

    // Default grants for roles that the legacy data did not cover or that need new permissions.
    // Admin always gets everything. Keys are role names from the departments table.
    'defaults' => [
        'Owner' => ['*'], 'Admin' => ['*'],
        'Medical Director' => ['manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_reports', 'manage_notice_board', 'manage_rx'],
        'Branch Manager' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_reports', 'view_messages', 'manage_accounts', 'view_rx', 'manage_ipd', 'lab_booking', 'lab_samples', 'lab_results', 'lab_approve', 'lab_catalogue', 'lab_referrers', 'lab_commission', 'lab_finance', 'lab_inventory', 'lab_staff', 'lab_attendance', 'lab_share', 'lab_records', 'lab_reports'],
        'Compliance Officer' => ['manage_reports'],
        'IT Administrator' => ['manage_users', 'manage_access'],
        'HR Officer' => ['manage_users', 'lab_staff', 'lab_attendance', 'lab_salary'],
        'External Auditor' => ['manage_reports'],
        'Consultant' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_notice_board', 'manage_rx'],
        'Doctor' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_notice_board', 'manage_rx', 'manage_ipd'],
        'Resident' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'manage_rx', 'manage_ipd'],
        'Nurse' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'view_rx', 'manage_ipd'],
        'Head Nurse' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'manage_users', 'view_rx'],
        'Midwife' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'view_rx', 'manage_ipd'],
        'Clinical Assistant' => ['manage_appointments', 'manage_patients', 'view_rx', 'manage_ipd'],
        'Optometrist' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'manage_rx'],
        'Therapist' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'view_rx'],
        'Counsellor' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'view_rx'],
        'Lab Technician' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'lab_samples', 'lab_results'],
        'Pathologist' => ['manage_patients', 'manage_prescriptions', 'lab_results', 'lab_approve', 'lab_share', 'lab_reports'],
        'Phlebotomist' => ['manage_appointments', 'manage_prescriptions', 'lab_samples'],
        'Radiologist' => ['manage_patients', 'manage_prescriptions'],
        'Imaging Technologist' => ['manage_appointments', 'manage_prescriptions'],
        'Pharmacist' => ['manage_appointments', 'manage_medicines', 'manage_invoices', 'manage_prescriptions'],
        'Pharmacy Assistant' => ['manage_appointments', 'manage_invoices'],
        'Blood Bank Technician' => ['manage_appointments', 'manage_patients', 'manage_blood_bank'],
        'Case Manager' => ['manage_appointments', 'manage_patients', 'view_rx', 'manage_ipd'],
        'Ward Attendant' => ['manage_appointments'],
        'Caregiver' => ['manage_appointments', 'manage_prescriptions'],
        'Dispatcher' => ['manage_appointments', 'view_messages'],
        'Driver' => ['manage_appointments'],
        'Paramedic' => ['manage_appointments', 'manage_prescriptions', 'manage_ipd'],
        'Receptionist' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'view_messages', 'manage_invoices', 'view_rx', 'manage_ipd', 'lab_booking', 'lab_samples', 'lab_share'],
        'Billing Clerk' => ['manage_invoices', 'manage_appointments'],
        'Accountant' => ['manage_invoices', 'manage_reports', 'manage_appointments', 'view_plan', 'manage_accounts', 'lab_finance', 'lab_commission', 'lab_salary', 'lab_reports'],
        'Insurance Officer' => ['manage_invoices', 'manage_appointments'],
        'Medical Records Officer' => ['manage_patients', 'manage_prescriptions', 'view_rx'],
        'Store Manager' => ['manage_medicines', 'manage_catalog', 'manage_invoices', 'lab_inventory'],
        'Sales Representative' => ['manage_appointments', 'manage_catalog', 'manage_invoices'],
        'Field Engineer' => ['manage_appointments', 'manage_medicines'],
        'Patient' => [], 'Family Member' => [], 'Referring Doctor' => [], 'B2B Customer' => [],
    ],

    // Added on top of the defaults for one kind of vendor (family in config/vendor_types.php).
    'family_defaults' => [
        'pharmacy' => ['Pharmacist' => ['manage_catalog', 'manage_free_services'], 'Pharmacy Assistant' => ['manage_catalog']],
        'diagnostics' => ['Lab Technician' => ['manage_catalog', 'manage_free_services', 'manage_invoices'], 'Pathologist' => ['manage_reports']],
        'bloodbank' => ['Blood Bank Technician' => ['manage_catalog', 'manage_notice_board']],
        'ambulance' => ['Dispatcher' => ['manage_invoices', 'manage_catalog']],
        'equipment' => ['Receptionist' => ['manage_catalog'], 'Field Engineer' => ['manage_catalog']],
        'clinical' => ['Doctor' => ['manage_free_services'], 'Receptionist' => ['manage_free_services']],
    ],

    // Which legacy staff table a role is saved in (same as the old app).
    'staff_tables' => [
        'Doctor' => 'doctors', 'Patient' => 'patients', 'Nurse' => 'nurses', 'Receptionist' => 'receptionists',
        'Pharmacist' => 'pharmacists', 'Accountant' => 'accountants', 'Lab Technician' => 'lab_technicians', 'Case Manager' => 'case_handlers',
    ],

    'role_help' => [
        'Admin' => 'Runs the vendor. Always has full access.',
        'Doctor' => 'Clinician. Sees requests, patients and the team.',
        'Nurse' => 'Care staff. Sees requests and patients.',
        'Receptionist' => 'Front desk. Handles requests and registers patients.',
        'Accountant' => 'Finance. Sees requests and the plan.',
        'Pharmacist' => 'Medicines. Handles orders and the product list in a pharmacy.',
        'Lab Technician' => 'Tests. Handles bookings and the test list in a laboratory.',
        'Case Manager' => 'Coordinates care and follow-up.',
        'Patient' => 'Sees only their own requests and profile.',
        'Owner' => 'Owns the account. Full access and cannot be removed.',
    ],

    // Role names shown in each kind of vendor.
    'role_labels' => [
        'ambulance' => ['Receptionist' => 'Dispatcher', 'Nurse' => 'Paramedic', 'Doctor' => 'Medical officer'],
        'pharmacy' => ['Receptionist' => 'Counter staff', 'Billing Clerk' => 'Cashier', 'Pharmacy Assistant' => 'Pharmacy assistant'],
        'diagnostics' => ['Doctor' => 'Pathologist / radiologist'],
        'rehab' => ['Doctor' => 'Therapist'],
        'homecare' => ['Nurse' => 'Home nurse'],
        'equipment' => ['Receptionist' => 'Sales staff'],
        'bloodbank' => ['Lab Technician' => 'Blood bank technician'],
    ],
];
