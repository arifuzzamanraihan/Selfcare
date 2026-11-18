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
        'manage_reports'        => ['Reports', 'Money', 'Open and download reports.', true],
    ],

    // Default grants for roles that the legacy data did not cover or that need new permissions.
    // Admin always gets everything. Keys are role names from the departments table.
    'defaults' => [
        'Owner' => ['*'], 'Admin' => ['*'],
        'Medical Director' => ['manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_reports', 'manage_notice_board'],
        'Branch Manager' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_reports', 'view_messages'],
        'Compliance Officer' => ['manage_reports'],
        'IT Administrator' => ['manage_users', 'manage_access'],
        'HR Officer' => ['manage_users'],
        'External Auditor' => ['manage_reports'],
        'Consultant' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_notice_board'],
        'Doctor' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'manage_prescriptions', 'manage_notice_board'],
        'Resident' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Nurse' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Head Nurse' => ['manage_appointments', 'manage_patients', 'manage_prescriptions', 'manage_users'],
        'Midwife' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Clinical Assistant' => ['manage_appointments', 'manage_patients'],
        'Optometrist' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Therapist' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Counsellor' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Lab Technician' => ['manage_appointments', 'manage_patients', 'manage_prescriptions'],
        'Pathologist' => ['manage_patients', 'manage_prescriptions'],
        'Phlebotomist' => ['manage_appointments', 'manage_prescriptions'],
        'Radiologist' => ['manage_patients', 'manage_prescriptions'],
        'Imaging Technologist' => ['manage_appointments', 'manage_prescriptions'],
        'Pharmacist' => ['manage_appointments', 'manage_medicines', 'manage_invoices', 'manage_prescriptions'],
        'Pharmacy Assistant' => ['manage_appointments', 'manage_invoices'],
        'Blood Bank Technician' => ['manage_appointments', 'manage_patients', 'manage_blood_bank'],
        'Case Manager' => ['manage_appointments', 'manage_patients'],
        'Ward Attendant' => ['manage_appointments'],
        'Caregiver' => ['manage_appointments', 'manage_prescriptions'],
        'Dispatcher' => ['manage_appointments', 'view_messages'],
        'Driver' => ['manage_appointments'],
        'Paramedic' => ['manage_appointments', 'manage_prescriptions'],
        'Receptionist' => ['manage_appointments', 'manage_patients', 'manage_doctors', 'view_messages', 'manage_invoices'],
        'Billing Clerk' => ['manage_invoices', 'manage_appointments'],
        'Accountant' => ['manage_invoices', 'manage_reports', 'manage_appointments', 'view_plan'],
        'Insurance Officer' => ['manage_invoices', 'manage_appointments'],
        'Medical Records Officer' => ['manage_patients', 'manage_prescriptions'],
        'Store Manager' => ['manage_medicines', 'manage_catalog', 'manage_invoices'],
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
        'pharmacy' => ['Receptionist' => 'Counter staff'],
        'diagnostics' => ['Doctor' => 'Pathologist / radiologist'],
        'rehab' => ['Doctor' => 'Therapist'],
        'homecare' => ['Nurse' => 'Home nurse'],
        'equipment' => ['Receptionist' => 'Sales staff'],
        'bloodbank' => ['Lab Technician' => 'Blood bank technician'],
    ],
];
