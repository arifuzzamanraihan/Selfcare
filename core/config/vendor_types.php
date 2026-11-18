<?php
// Selfcare original code. Vendor types and website templates.
//
// A "family" describes one website template: which sections the vendor website has,
// what they are called, and what the vendor admin can edit. A "type" (hospital,
// pharmacy, ...) points at one family and may override a few labels.
// Section kinds:
//   cards        repeating items (title, text, small badge such as a price, image)
//   faq / testimonials / gallery / news   repeating items with their own layout
//   team         doctors / staff list from the doctors table
//   departments  departments from the doctor_departments table
//   text         one editable text box (setting key in 'field')
//   hours        opening hours
// Editing this file is the easy way to change a template. New families need no other code.

declare(strict_types=1);

$common = [
    ['key' => 'testimonials', 'kind' => 'testimonials', 'label' => 'What people say', 'item' => 'Review', 'meta' => 'Rating (1-5)', 'metaHelp' => 'A number from 1 to 5.'],
    ['key' => 'faq', 'kind' => 'faq', 'label' => 'Questions and answers', 'item' => 'Question'],
    ['key' => 'gallery', 'kind' => 'gallery', 'label' => 'Gallery', 'item' => 'Photo'],
    ['key' => 'news', 'kind' => 'news', 'label' => 'News and notices', 'item' => 'Notice'],
];

$families = [
    'clinical' => [
        'label' => 'Hospital and clinic',
        'team' => 'Doctors', 'people' => 'Patients', 'requests' => 'Appointments',
        'request' => ['title' => 'Book an appointment', 'cta' => 'Book appointment', 'submit' => 'Send appointment request', 'done' => 'Thank you. We have your request and will call you to confirm the time.', 'doctor' => true, 'date' => 'Preferred date', 'note' => 'Reason for visit'],
        'profile' => ['emergency_phone' => 'Emergency phone'],
        'sections' => array_merge([
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Our services', 'item' => 'Service', 'meta' => 'Fee (optional)', 'icon' => 'stethoscope'],
            ['key' => 'departments', 'kind' => 'departments', 'label' => 'Departments'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our doctors'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
    'diagnostics' => [
        'label' => 'Laboratory and diagnostics',
        'team' => 'Specialists', 'people' => 'Clients', 'requests' => 'Test bookings',
        'request' => ['title' => 'Book a test or sample pickup', 'cta' => 'Book a test', 'submit' => 'Send booking request', 'done' => 'Thank you. We have your booking and will call you to confirm the time.', 'doctor' => false, 'date' => 'Preferred date', 'note' => 'Tests needed or pickup address'],
        'profile' => ['emergency_phone' => 'Helpline'],
        'sections' => array_merge([
            ['key' => 'tests', 'kind' => 'cards', 'label' => 'Tests we offer', 'item' => 'Test', 'meta' => 'Price', 'icon' => 'flask'],
            ['key' => 'packages', 'kind' => 'cards', 'label' => 'Health check packages', 'item' => 'Package', 'meta' => 'Price', 'icon' => 'shield'],
            ['key' => 'collection', 'kind' => 'text', 'label' => 'Sample collection', 'field' => 'collection_info', 'help' => 'How and where samples are collected, home pickup areas and times.'],
            ['key' => 'reports', 'kind' => 'text', 'label' => 'Reports', 'field' => 'report_info', 'help' => 'How long reports take and how patients receive them.'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our specialists'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
    'pharmacy' => [
        'label' => 'Pharmacy',
        'team' => 'Pharmacists', 'people' => null, 'requests' => 'Orders',
        'request' => ['title' => 'Order medicines', 'cta' => 'Order medicines', 'submit' => 'Send order request', 'done' => 'Thank you. We received your order and will call you to confirm it.', 'doctor' => false, 'date' => 'Needed by', 'note' => 'Medicines and quantities, delivery address'],
        'profile' => ['emergency_phone' => 'Night helpline'],
        'sections' => array_merge([
            ['key' => 'products', 'kind' => 'cards', 'label' => 'Popular products', 'item' => 'Product', 'meta' => 'Price', 'icon' => 'pill'],
            ['key' => 'categories', 'kind' => 'cards', 'label' => 'Shop by category', 'item' => 'Category', 'icon' => 'box'],
            ['key' => 'delivery', 'kind' => 'text', 'label' => 'Home delivery', 'field' => 'delivery_info', 'help' => 'Delivery areas, charges and times.'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
    'residential' => [
        'label' => 'Residential and elder care',
        'team' => 'Care team', 'people' => 'Residents', 'requests' => 'Enquiries',
        'request' => ['title' => 'Arrange a visit', 'cta' => 'Arrange a visit', 'submit' => 'Send enquiry', 'done' => 'Thank you. Our team will call you to arrange a visit.', 'doctor' => false, 'date' => 'Preferred visit date', 'note' => 'Tell us about the person needing care'],
        'profile' => ['emergency_phone' => 'Care line'],
        'sections' => array_merge([
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Care we provide', 'item' => 'Care service', 'icon' => 'heart'],
            ['key' => 'rooms', 'kind' => 'cards', 'label' => 'Rooms and fees', 'item' => 'Room type', 'meta' => 'Monthly fee', 'icon' => 'bed'],
            ['key' => 'facilities', 'kind' => 'cards', 'label' => 'Facilities', 'item' => 'Facility', 'icon' => 'building'],
            ['key' => 'visiting', 'kind' => 'text', 'label' => 'Visiting', 'field' => 'visiting_info', 'help' => 'Visiting hours and rules for families.'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our care team'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Office hours'],
        ], $common),
    ],
    'rehab' => [
        'label' => 'Rehabilitation',
        'team' => 'Therapists', 'people' => 'Clients', 'requests' => 'Session bookings',
        'request' => ['title' => 'Book a session', 'cta' => 'Book a session', 'submit' => 'Send booking request', 'done' => 'Thank you. We will call you to confirm your session.', 'doctor' => true, 'date' => 'Preferred date', 'note' => 'Condition or goal'],
        'profile' => ['emergency_phone' => 'Helpline'],
        'sections' => array_merge([
            ['key' => 'programs', 'kind' => 'cards', 'label' => 'Programs', 'item' => 'Program', 'meta' => 'Fee (optional)', 'icon' => 'heart'],
            ['key' => 'facilities', 'kind' => 'cards', 'label' => 'Facilities and equipment', 'item' => 'Facility', 'icon' => 'building'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our therapists'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
    'bloodbank' => [
        'label' => 'Blood bank',
        'team' => 'Staff', 'people' => 'Donors', 'requests' => 'Blood requests',
        'request' => ['title' => 'Request blood or book a donation', 'cta' => 'Request blood', 'submit' => 'Send request', 'done' => 'Thank you. Our team will contact you right away.', 'doctor' => false, 'date' => 'Needed by', 'note' => 'Blood group, units needed and hospital'],
        'profile' => ['emergency_phone' => 'Emergency hotline'],
        'sections' => array_merge([
            ['key' => 'stock', 'kind' => 'cards', 'label' => 'Blood stock', 'item' => 'Blood group', 'meta' => 'Units available', 'icon' => 'drop', 'help' => 'Title is the blood group, for example A+.'],
            ['key' => 'donation', 'kind' => 'text', 'label' => 'How to donate', 'field' => 'donation_info', 'help' => 'Who can donate and what to expect.'],
            ['key' => 'camps', 'kind' => 'news', 'label' => 'Donation camps', 'item' => 'Camp'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], [$common[1], $common[2], $common[3]]),
    ],
    'ambulance' => [
        'label' => 'Ambulance service',
        'team' => 'Crew', 'people' => null, 'requests' => 'Dispatch requests',
        'request' => ['title' => 'Request an ambulance', 'cta' => 'Request ambulance', 'submit' => 'Send request', 'done' => 'Thank you. For an emergency please also call our hotline. We will contact you now.', 'doctor' => false, 'date' => 'Pickup date', 'note' => 'Pickup address, destination and patient condition'],
        'profile' => ['emergency_phone' => 'Emergency hotline'],
        'sections' => array_merge([
            ['key' => 'fleet', 'kind' => 'cards', 'label' => 'Our fleet', 'item' => 'Vehicle', 'meta' => 'Base fare', 'icon' => 'truck'],
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Services', 'item' => 'Service', 'icon' => 'siren'],
            ['key' => 'coverage', 'kind' => 'text', 'label' => 'Coverage area', 'field' => 'coverage_info', 'help' => 'Cities and areas you cover, response time.'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Availability'],
        ], $common),
    ],
    'telemedicine' => [
        'label' => 'Telemedicine',
        'team' => 'Specialists', 'people' => 'Patients', 'requests' => 'Consultations',
        'request' => ['title' => 'Book an online consultation', 'cta' => 'Book consultation', 'submit' => 'Send request', 'done' => 'Thank you. We will send you the video link after we confirm the time.', 'doctor' => true, 'date' => 'Preferred date', 'note' => 'Reason for consultation'],
        'profile' => ['emergency_phone' => 'Support line'],
        'sections' => array_merge([
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Consultations', 'item' => 'Consultation', 'meta' => 'Fee', 'icon' => 'stethoscope'],
            ['key' => 'steps', 'kind' => 'cards', 'label' => 'How it works', 'item' => 'Step', 'icon' => 'check'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our specialists'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Available hours'],
        ], $common),
    ],
    'homecare' => [
        'label' => 'Home care',
        'team' => 'Caregivers', 'people' => 'Clients', 'requests' => 'Visit requests',
        'request' => ['title' => 'Request a home visit', 'cta' => 'Request a visit', 'submit' => 'Send request', 'done' => 'Thank you. We will call you to plan the visit.', 'doctor' => false, 'date' => 'Preferred date', 'note' => 'Care needed and address'],
        'profile' => ['emergency_phone' => 'Care line'],
        'sections' => array_merge([
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Care at home', 'item' => 'Service', 'meta' => 'Rate', 'icon' => 'home2'],
            ['key' => 'coverage', 'kind' => 'text', 'label' => 'Areas we cover', 'field' => 'coverage_info', 'help' => 'Cities and areas you visit.'],
            ['key' => 'team', 'kind' => 'team', 'label' => 'Our caregivers'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
    'equipment' => [
        'label' => 'Medical equipment',
        'team' => 'Staff', 'people' => null, 'requests' => 'Quote requests',
        'request' => ['title' => 'Request a quote', 'cta' => 'Request a quote', 'submit' => 'Send quote request', 'done' => 'Thank you. Our sales team will send you a quote.', 'doctor' => false, 'date' => 'Needed by', 'note' => 'Equipment, quantity, buy or rent'],
        'profile' => ['emergency_phone' => 'Sales line'],
        'sections' => array_merge([
            ['key' => 'products', 'kind' => 'cards', 'label' => 'Equipment', 'item' => 'Product', 'meta' => 'Price or rent', 'icon' => 'box'],
            ['key' => 'categories', 'kind' => 'cards', 'label' => 'Categories', 'item' => 'Category', 'icon' => 'layers'],
            ['key' => 'services', 'kind' => 'cards', 'label' => 'Rental and maintenance', 'item' => 'Service', 'icon' => 'settings'],
            ['key' => 'delivery', 'kind' => 'text', 'label' => 'Delivery and warranty', 'field' => 'delivery_info', 'help' => 'Delivery, installation and warranty terms.'],
            ['key' => 'hours', 'kind' => 'hours', 'label' => 'Opening hours'],
        ], $common),
    ],
];

// slug => [name, plural, family, icon, tagline, description, serves, enabled, overrides]
$types = [
    'hospital' => ['Hospital', 'Hospitals', 'clinical', 'building', 'Full-service inpatient and outpatient care.', 'General and multi-specialty hospitals with wards, emergency care, theatres and many departments.', ['Departments and doctors', 'Appointments and admissions', 'Emergency contact', 'News and notices'], 1, ['request.cta' => 'Book appointment']],
    'clinic' => ['Clinic', 'Clinics', 'clinical', 'stethoscope', 'Everyday outpatient care close to home.', 'General practice and family clinics for check-ups, minor treatment and follow-up care.', ['Doctors and timings', 'Online appointment requests', 'Services and fees'], 1, []],
    'specialty-clinic' => ['Specialty clinic', 'Specialty clinics', 'clinical', 'heart', 'Focused care from specialists.', 'Clinics focused on one field such as heart, skin, bones, kidney or diabetes care.', ['Specialist profiles', 'Conditions treated', 'Appointment requests'], 1, []],
    'dental-clinic' => ['Dental clinic', 'Dental clinics', 'clinical', 'stethoscope', 'Healthy teeth, simple booking.', 'Dental practices for check-ups, cleaning, fillings, braces and cosmetic dentistry.', ['Treatments and prices', 'Dentist profiles', 'Before and after gallery'], 1, ['team' => 'Dentists', 'sections.team.label' => 'Our dentists', 'sections.services.label' => 'Dental treatments']],
    'eye-clinic' => ['Eye clinic', 'Eye clinics', 'clinical', 'eye', 'Clear vision, expert eye care.', 'Eye hospitals and optical clinics for tests, surgery, glasses and contact lenses.', ['Eye tests and surgery', 'Specialist profiles', 'Appointment requests'], 1, ['team' => 'Eye specialists', 'sections.team.label' => 'Our eye specialists']],
    'maternity-center' => ['Maternity and child care', 'Maternity centers', 'clinical', 'heart', 'Care for mothers and children.', 'Maternity homes, birthing centers and child health clinics.', ['Antenatal and delivery care', 'Packages and fees', 'Vaccination services'], 1, []],
    'mental-health' => ['Mental health center', 'Mental health centers', 'clinical', 'brain', 'Kind, private support for mind health.', 'Counselling, psychiatry and therapy centers.', ['Therapists and counsellors', 'Programs and fees', 'Private booking'], 1, ['team' => 'Therapists', 'sections.team.label' => 'Our therapists and doctors', 'sections.services.label' => 'Support we offer']],
    'alternative-medicine' => ['Traditional and alternative medicine', 'Alternative medicine clinics', 'clinical', 'leaf', 'Ayurveda, homeopathy, unani and more.', 'Clinics offering traditional and complementary medicine.', ['Practitioner profiles', 'Treatments', 'Appointment requests'], 1, []],
    'dialysis-center' => ['Dialysis center', 'Dialysis centers', 'clinical', 'drop', 'Safe, comfortable dialysis sessions.', 'Outpatient renal dialysis centers with recurring chair and shift sessions.', ['Session booking', 'Nephrologist profiles', 'Machine and water safety'], 1, ['team' => 'Nephrologists', 'sections.team.label' => 'Our nephrologists', 'sections.services.label' => 'Dialysis services']],
    'diagnostic-center' => ['Diagnostic center', 'Diagnostic centers', 'diagnostics', 'scan', 'Tests and scans under one roof.', 'Centers offering pathology and imaging together, with health check packages.', ['Test list and prices', 'Health packages', 'Report delivery'], 1, []],
    'laboratory' => ['Pathology laboratory', 'Laboratories', 'diagnostics', 'flask', 'Accurate tests, quick reports.', 'Blood, urine and other clinical laboratory tests, with home sample collection.', ['Test list and prices', 'Home sample collection', 'Online report delivery'], 1, []],
    'imaging-center' => ['Imaging and radiology center', 'Imaging centers', 'diagnostics', 'scan', 'X-ray, ultrasound, CT and MRI.', 'Radiology and imaging centers.', ['Scan list and prices', 'Radiologist profiles', 'Booking and report delivery'], 1, ['sections.tests.label' => 'Scans we offer', 'sections.tests.item' => 'Scan']],
    'pharmacy' => ['Pharmacy', 'Pharmacies', 'pharmacy', 'pill', 'Medicines and health products, delivered.', 'Retail and hospital pharmacies, with prescription orders and home delivery.', ['Products and prices', 'Order requests', 'Home delivery areas'], 1, []],
    'nursing-home' => ['Nursing home', 'Nursing homes', 'residential', 'bed', 'Round-the-clock nursing care.', 'Nursing homes and elder care homes for people who need daily care.', ['Rooms and monthly fees', 'Care team', 'Visiting rules'], 1, []],
    'rehabilitation' => ['Rehabilitation and physiotherapy', 'Rehabilitation centers', 'rehab', 'heart', 'Recover, move and live better.', 'Physiotherapy, occupational therapy and rehabilitation centers.', ['Programs and fees', 'Therapist profiles', 'Session booking'], 1, []],
    'blood-bank' => ['Blood bank', 'Blood banks', 'bloodbank', 'drop', 'Safe blood, when it is needed.', 'Blood banks and donation centers with live stock by blood group.', ['Blood stock by group', 'Donation camps', 'Emergency requests'], 1, []],
    'ambulance' => ['Ambulance service', 'Ambulance services', 'ambulance', 'truck', 'Fast, equipped, always ready.', 'Emergency and patient transport services.', ['Fleet and base fares', 'Coverage area', '24/7 hotline'], 1, []],
    'telemedicine' => ['Telemedicine provider', 'Telemedicine providers', 'telemedicine', 'globe', 'See a doctor from home.', 'Online consultation platforms and virtual clinics.', ['Consultation types and fees', 'Specialist profiles', 'How it works'], 1, []],
    'home-care' => ['Home care and nursing', 'Home care providers', 'homecare', 'home2', 'Care that comes to you.', 'Home nursing, caregiver and post-surgery care at home.', ['Care services and rates', 'Areas covered', 'Caregiver profiles'], 1, []],
    'equipment-supplier' => ['Medical equipment supplier', 'Equipment suppliers', 'equipment', 'box', 'Equipment for clinics and homes.', 'Suppliers of medical devices, consumables, wheelchairs and oxygen equipment, with rental and service.', ['Equipment catalog', 'Rental and maintenance', 'Quote requests'], 1, []],
];

return ['families' => $families, 'types' => $types];
