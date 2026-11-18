<?php
// Selfcare original code. Demo content for staging and local testing.
//   php database/phase1/seed-demo.php --creds=/path/to/CREDENTIALS.txt [--host-suffix=-staging-selfcare.easybzi.com --main=staging-selfcare.easybzi.com --scheme=https]
// Creates the Self Care super admin and 15 demo vendors (3 hospitals plus one of every other website template),
// each with a website, doctors, staff, patients, requests, free services, invoices, forms and stock.
// Safe to run twice: vendors that already exist are skipped. Random passwords are written only to the --creds file.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports'] as $f) {
    if (is_file(dirname(__DIR__, 2) . '/core/' . $f . '.php')) {
        require_once dirname(__DIR__, 2) . '/core/' . $f . '.php';
    }
}
use Selfcare\{App, Auth, Billing, Plans};

$root = dirname(__DIR__, 2);
$opt = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) {
        $opt[$m[1]] = $m[2];
    }
}
$creds = $opt['creds'] ?? '';
$app = new App($root);
$db = $app->db;
$bill = new Billing($db);
mt_srand(20261118);
$now = date('Y-m-d H:i:s');
$pw = static function (): string {
    $c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $o = '';
    for ($i = 0; $i < 14; $i++) {
        $o .= $c[random_int(0, strlen($c) - 1)];
    }
    return $o;
};
$credLines = [];
$base = 'https://' . ($opt['main'] ?? 'sc.test');
$site = static fn (string $slug): string => 'https://' . $slug . ($opt['host-suffix'] ?? '.sc.test');

// ------------------------------------------------------------------ plans: make sure the free plan has the catalog module
$db->run("UPDATE sc_plans SET modules = CONCAT(modules, ',catalog') WHERE name = 'Free' AND modules NOT LIKE '%catalog%'");
$planId = static fn (string $n): int => (int) $db->val('SELECT id FROM sc_plans WHERE name = ?', [$n]);

// ------------------------------------------------------------------ super admin
if (!$db->val('SELECT COUNT(*) FROM users WHERE is_super_admin_default = 1')) {
    $p = $pw();
    $rid = (int) $db->val("SELECT id FROM departments WHERE name = 'Super Admin'");
    $uid = $db->insert('users', ['department_id' => $rid, 'first_name' => 'Self Care', 'last_name' => 'Administrator', 'email' => 'superadmin@selfcare.example', 'password' => password_hash($p, PASSWORD_BCRYPT), 'is_super_admin_default' => 1, 'gender' => 0, 'is_admin_default' => 1, 'status' => 1, 'language' => 'en', 'hospital_name' => 'Self Care', 'created_at' => $now, 'updated_at' => $now]);
    $db->insert('model_has_roles', ['role_id' => $rid, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
    $credLines[] = "SUPER ADMIN  $base/login  superadmin@selfcare.example  $p";
}
// main-site message so the inbox is not empty
if (!$db->val('SELECT COUNT(*) FROM sc_messages WHERE tenant_id IS NULL')) {
    foreach ([['Rahim Uddin', 'rahim@clinic.example', 'We run a 12 bed clinic in Dhaka. Can we move our old records into Self Care?'], ['Dr. Sara Khan', 'sara@labs.example', 'Do you support home sample collection invoices for pathology labs?'], ['Newsletter sign-up', 'news@reader.example', 'Newsletter sign-up']] as $i => $r) {
        $db->insert('sc_messages', ['tenant_id' => null, 'name' => $r[0], 'email' => $r[1], 'message' => $r[2], 'is_read' => $i === 2 ? 1 : 0, 'created_at' => date('Y-m-d H:i:s', time() - $i * 86400)]);
    }
}

// ------------------------------------------------------------------ images (gradient PNGs, no photos needed)
$imgDir = $root . '/public/uploads/demo';
@mkdir($imgDir, 0775, true);
$mkImg = static function (string $name, int $w, int $h, array $c1, array $c2, string $label) use ($imgDir): string {
    $file = $imgDir . '/' . $name . '.png';
    if (!is_file($file)) {
        $im = imagecreatetruecolor($w, $h);
        // flat colour only (no gradients); the label keeps the picture readable
        $g = 70 + (crc32($name) % 5) * 14; // neutral grey, varied per picture
        imagefill($im, 0, 0, imagecolorallocate($im, $g, $g, $g));
        $fg = imagecolorallocate($im, 255, 255, 255);
        imagestring($im, 5, 18, $h - 30, $label, $fg);
        imagepng($im, $file, 7);
        imagedestroy($im);
    }
    return 'uploads/demo/' . $name . '.png';
};
$hues = [[16, 120, 110], [30, 90, 160], [110, 70, 150], [170, 80, 60], [20, 110, 80], [150, 60, 90]];

// ------------------------------------------------------------------ vendors
// slug, type, name, city, street, phone, plan, plan days (null = never ends)
$vendors = [
    ['citygeneral', 'hospital', 'City General Hospital', 'Dhaka', '14 Lake Road, Dhanmondi', '+880 2 5501 1000', 'Professional', 365],
    ['greenvalley', 'hospital', 'Green Valley Hospital', 'Chattogram', '82 Hill View Avenue, Nasirabad', '+880 31 255 7788', 'Business', 365],
    ['lakeside', 'hospital', 'Lakeside Medical Center', 'Sylhet', '5 Surma Bank Road, Zindabazar', '+880 821 71 5566', 'Starter', 30],
    ['sunriseclinic', 'clinic', 'Sunrise Family Clinic', 'Dhaka', '9 Green Road, Farmgate', '+880 2 5501 2200', 'Business', 365],
    ['tinyclinic', 'clinic', 'Tiny Town Clinic', 'Rangpur', '2 Market Road', '+880 521 66 1122', 'Free', null],
    ['brightsmile', 'dental-clinic', 'BrightSmile Dental Care', 'Khulna', '31 KDA Avenue', '+880 41 72 3344', 'Business', 365],
    ['precisionlab', 'laboratory', 'Precision Pathology Lab', 'Dhaka', '27 Mirpur Road, Shyamoli', '+880 2 5501 3300', 'Professional', 365],
    ['clearscan', 'imaging-center', 'ClearScan Imaging Center', 'Rajshahi', '4 Station Road', '+880 721 77 8899', 'Business', 8],
    ['medicopharma', 'pharmacy', 'Medico 24 Pharmacy', 'Dhaka', '2 Elephant Road', '+880 2 5501 4400', 'Business', 365],
    ['evergreencare', 'nursing-home', 'Evergreen Nursing Home', 'Gazipur', '60 Garden Lane, Tongi', '+880 2 5501 5500', 'Business', 365],
    ['movewell', 'rehabilitation', 'MoveWell Physiotherapy', 'Dhaka', '18 Banani Road 11', '+880 2 5501 6600', 'Business', 365],
    ['lifeline', 'blood-bank', 'Lifeline Blood Bank', 'Dhaka', '3 Shahbagh Square', '+880 2 5501 7700', 'Professional', 365],
    ['rapidambulance', 'ambulance', 'Rapid Ambulance Service', 'Dhaka', '1 Airport Road, Uttara', '+880 1711 000 999', 'Business', 365],
    ['docnow', 'telemedicine', 'DocNow Online Clinic', 'Dhaka', 'Online, Dhaka', '+880 1711 123 456', 'Business', 365],
    ['carehome', 'home-care', 'CareAtHome Nursing', 'Dhaka', '44 Gulshan Avenue', '+880 2 5501 8800', 'Business', 365],
    ['heartcare', 'specialty-clinic', 'HeartCare Specialty Clinic', 'Dhaka', '7 Kakrail Road', '+880 2 5501 2300', 'Business', 365],
    ['visionplus', 'eye-clinic', 'VisionPlus Eye Clinic', 'Dhaka', '21 Mohakhali DOHS', '+880 2 5501 2400', 'Business', 365],
    ['motherhood', 'maternity-center', 'Motherhood Maternity Center', 'Chattogram', '15 GEC Circle', '+880 31 255 9090', 'Business', 365],
    ['mindwell', 'mental-health', 'MindWell Counselling Center', 'Dhaka', '30 Lalmatia Block D', '+880 2 5501 2500', 'Business', 365],
    ['herbalcare', 'alternative-medicine', 'Herbal Care Unani Clinic', 'Dhaka', '8 Old Town Lane', '+880 2 5501 2600', 'Business', 365],
    ['renalcare', 'dialysis-center', 'RenalCare Dialysis Center', 'Dhaka', '52 Panthapath', '+880 2 5501 2700', 'Business', 365],
    ['diagnosticplus', 'diagnostic-center', 'Diagnostic Plus Center', 'Dhaka', '90 Kalabagan', '+880 2 5501 2800', 'Business', 365],
    ['medequip', 'equipment-supplier', 'MedEquip Supplies', 'Dhaka', '12 Tejgaon Industrial Area', '+880 2 5501 9900', 'Professional', 365],
];
$names = ['Aisha', 'Karim', 'Nadia', 'Imran', 'Farhana', 'Tanvir', 'Sumaiya', 'Rafiq', 'Mehnaz', 'Shahid', 'Lamia', 'Arif', 'Nusrat', 'Jamal', 'Tahmina', 'Sabbir'];
$last = ['Rahman', 'Hossain', 'Ahmed', 'Chowdhury', 'Akter', 'Islam', 'Khan', 'Begum', 'Sarker', 'Mia'];
$person = static function (int $i) use ($names, $last): array {
    return [$names[$i % count($names)], $last[($i * 3 + 1) % count($last)]];
};
$specialties = ['clinical' => ['Cardiology', 'Medicine', 'Pediatrics', 'Orthopedics', 'Gynecology', 'ENT'], 'diagnostics' => ['Pathology', 'Radiology', 'Microbiology'], 'pharmacy' => ['Clinical pharmacy', 'Dispensing'], 'residential' => ['Geriatrics', 'General nursing'], 'rehab' => ['Physiotherapy', 'Occupational therapy', 'Speech therapy'], 'bloodbank' => ['Transfusion medicine'], 'ambulance' => ['Emergency medicine'], 'telemedicine' => ['General physician', 'Dermatology', 'Mental health', 'Pediatrics'], 'homecare' => ['Home nursing', 'Elder care'], 'equipment' => ['Biomedical engineering']];
$staffByFamily = [
    'clinical' => ['Receptionist', 'Nurse', 'Accountant', 'Pharmacist', 'Lab Technician'],
    'diagnostics' => ['Receptionist', 'Lab Technician', 'Pathologist', 'Accountant'],
    'pharmacy' => ['Pharmacist', 'Pharmacy Assistant', 'Accountant'],
    'residential' => ['Receptionist', 'Nurse', 'Caregiver', 'Accountant'],
    'rehab' => ['Receptionist', 'Therapist', 'Accountant'],
    'bloodbank' => ['Blood Bank Technician', 'Receptionist', 'Accountant'],
    'ambulance' => ['Dispatcher', 'Driver', 'Paramedic', 'Accountant'],
    'telemedicine' => ['Receptionist', 'Accountant'],
    'homecare' => ['Case Manager', 'Caregiver', 'Accountant'],
    'equipment' => ['Store Manager', 'Sales Representative', 'Accountant'],
];
$content = [
    'services' => [['General check-up', 'A full review with a doctor, including blood pressure, weight and a plan for the next steps.', '20'], ['Emergency care', 'Round-the-clock emergency desk with trained staff and a short wait.', ''], ['Diagnostic tests', 'Blood tests, X-ray and ultrasound with reports the same day.', '15'], ['Vaccination', 'Child and adult vaccines with a printed card.', '10'], ['Health check package', 'A yearly package with tests and a doctor consultation.', '80']],
    'tests' => [['Complete blood count (CBC)', 'Counts red cells, white cells and platelets.', '6'], ['Blood sugar (fasting)', 'Checks sugar after eight hours without food.', '3'], ['Lipid profile', 'Cholesterol and triglycerides.', '12'], ['Liver function test', 'Checks how the liver is working.', '14'], ['Thyroid profile (TSH)', 'Thyroid hormone level.', '10'], ['Urine routine', 'A simple urine check.', '3']],
    'packages' => [['Basic health check', 'CBC, sugar, urine and lipid profile.', '30'], ['Diabetes care package', 'Sugar, HbA1c, kidney and urine tests.', '28'], ['Senior health package', 'A wide set of tests for people over 50.', '70']],
    'products' => [['Paracetamol 500 mg (strip of 10)', 'Pain and fever relief.', '0.40'], ['Oral rehydration salts', 'Replaces water and salts after diarrhoea.', '0.15'], ['Digital thermometer', 'Fast and accurate.', '4'], ['Blood pressure monitor', 'Upper arm automatic monitor.', '28'], ['Vitamin D3 (30 tablets)', 'Daily supplement.', '5']],
    'categories' => [['Medicines', 'Prescription and over-the-counter medicines.', ''], ['Baby care', 'Diapers, feeds and care items.', ''], ['Devices', 'Monitors, nebulisers and more.', '']],
    'rooms' => [['Shared room', 'Two beds, attendant space and a bathroom.', '400 per month'], ['Private room', 'A single room with a TV and a visitor chair.', '750 per month']],
    'facilities' => [['Garden and walking path', 'A calm outdoor space.', ''], ['Dining hall', 'Three meals a day with diet plans.', ''], ['Therapy room', 'Equipment for exercises and recovery.', '']],
    'programs' => [['Back and neck pain program', 'Six sessions of treatment and exercises.', '90'], ['Post-surgery recovery', 'Guided recovery after knee or hip surgery.', '150'], ['Child development therapy', 'Play based therapy for children.', '120']],
    'stock' => [['A+', '', '14'], ['O+', '', '22'], ['B+', '', '9'], ['AB+', '', '4'], ['O-', '', '3']],
    'fleet' => [['ICU ambulance', 'Ventilator, monitor and a paramedic on board.', '120'], ['Basic life support', 'Oxygen, stretcher and first aid.', '45'], ['Freezer van', 'For safe transfer of the deceased.', '60']],
    'steps' => [['Book a time', 'Choose a doctor and a time on this website.', ''], ['Meet your doctor', 'Join the video call from your phone.', ''], ['Get your prescription', 'We send it by email right after the call.', '']],
    'coverage' => [],
];
$texts = ['collection_info' => 'Home sample collection is available every morning from 7 am to 11 am in the city. Call us to book a time.', 'report_info' => 'Most reports are ready on the same day. You can collect a printed report or get it by email.', 'delivery_info' => 'Free delivery within 5 km for orders above 20. We deliver every day until 10 pm.', 'visiting_info' => 'Visiting hours are 4 pm to 7 pm every day. Please wash your hands and keep voices low.', 'donation_info' => 'You can donate if you are 18 to 60 years old, weigh at least 50 kg and feel well. It takes about 30 minutes.', 'coverage_info' => 'We cover the whole city and nearby areas. Average response time is 12 minutes.'];
$free = [
    'clinical' => [['Free eye and BP check camp', 'A free blood pressure and eye check for everyone.', 'Every Friday, 9 am to 12 pm', 'Open to all'], ['Free child vaccination day', 'Free routine vaccines for children under 5.', 'First Saturday of the month', 'Children under 5'], ['Free first consultation for seniors', 'First visit is free.', 'Weekdays', 'Age 65 and over']],
    'diagnostics' => [['Free blood sugar test', 'A free fasting blood sugar test.', 'Every Monday morning', 'Open to all'], ['Free home sample pickup', 'We collect your sample at home at no charge.', 'Daily, 7 am to 11 am', 'Orders above 15']],
    'pharmacy' => [['Free home delivery for seniors', 'We bring medicines to your door.', 'Daily', 'Age 65 and over'], ['Free blood pressure check', 'Walk in and ask for a check.', 'Daily', 'Open to all']],
    'residential' => [['Free family counselling', 'Guidance for families choosing elder care.', 'Saturday mornings', 'Open to all']],
    'rehab' => [['Free posture screening', 'A 15 minute posture check.', 'Every Wednesday', 'Open to all']],
    'bloodbank' => [['Free blood group test', 'Know your blood group for free.', 'Daily', 'Open to all'], ['Free blood for thalassemia patients', 'Blood units free of charge.', 'Daily', 'With a doctor note']],
    'ambulance' => [['Free ambulance for road accidents', 'No charge for the first 10 km.', '24 hours', 'Road accident victims']],
    'telemedicine' => [['Free first online consultation', 'Your first video consultation is free.', 'Daily, 9 am to 9 pm', 'New users']],
    'homecare' => [['Free home safety visit', 'We check your home for fall risks.', 'By appointment', 'Age 65 and over']],
    'equipment' => [['Free wheelchair loan for 7 days', 'Borrow a wheelchair after surgery.', 'On request', 'With a doctor note']],
];
$invItems = [
    'clinical' => [['Consultation fee', 20], ['Dressing', 8], ['Injection', 4], ['ECG', 15], ['X-ray chest', 18], ['Blood test (CBC)', 6], ['Bed charge per day', 45], ['Nursing charge', 12], ['Operation theatre charge', 220], ['Surgeon fee', 300], ['Medicine and consumables', 60]],
    'diagnostics' => [['Complete blood count', 6], ['Blood sugar fasting', 3], ['Lipid profile', 12], ['Liver function test', 14], ['X-ray chest PA', 10], ['Ultrasound whole abdomen', 25], ['Home collection charge', 4]],
    'pharmacy' => [['Paracetamol 500 mg strip', 0.4], ['ORS sachet', 0.15], ['Amoxicillin 500 mg strip', 2.5], ['Omeprazole 20 mg strip', 1.2], ['Vitamin D3 pack', 5], ['Digital thermometer', 4]],
    'residential' => [['Room charge per month', 750], ['Nursing care per month', 200], ['Physiotherapy session', 15], ['Meals per month', 120]],
    'rehab' => [['Physiotherapy session', 18], ['Assessment', 25], ['Ultrasound therapy', 12], ['Program package', 150]],
    'bloodbank' => [['Whole blood unit', 25], ['Platelet concentrate', 60], ['Fresh frozen plasma', 30], ['Processing charge', 5]],
    'ambulance' => [['ICU ambulance base fare', 120], ['Per km charge', 2], ['Oxygen supply', 10], ['Waiting charge per hour', 6]],
    'telemedicine' => [['Online consultation', 12], ['Follow-up consultation', 6], ['Prescription copy', 1]],
    'homecare' => [['Home nursing visit', 25], ['Caregiver per day', 40], ['Injection at home', 8], ['Dressing at home', 10]],
    'equipment' => [['Wheelchair (foldable)', 90], ['Oxygen concentrator 5L', 520], ['Hospital bed (manual)', 380], ['Nebuliser', 35], ['Pulse oximeter', 12]],
];
$stockSeed = [
    'pharmacy' => [['Paracetamol 500 mg', 'tablet strip', 400, 100, 0.25, 0.4, 600], ['ORS sachet', 'sachet', 800, 200, 0.08, 0.15, 500], ['Amoxicillin 500 mg', 'strip', 120, 60, 1.6, 2.5, 25], ['Omeprazole 20 mg', 'strip', 40, 60, 0.7, 1.2, 12], ['Insulin pen', 'pen', 18, 20, 6, 9, -10], ['Cough syrup 100 ml', 'bottle', 75, 30, 1.1, 1.9, 90]],
    'bloodbank' => [['Whole blood A+', 'unit', 14, 6, 15, 25, 20], ['Whole blood O+', 'unit', 22, 8, 15, 25, 18], ['Whole blood B+', 'unit', 9, 6, 15, 25, 7], ['Whole blood O-', 'unit', 3, 5, 15, 25, 15], ['Platelet concentrate', 'unit', 6, 4, 35, 60, 3]],
    'equipment' => [['Wheelchair (foldable)', 'piece', 14, 5, 60, 90, 9999], ['Oxygen concentrator 5L', 'piece', 6, 3, 380, 520, 9999], ['Hospital bed (manual)', 'piece', 4, 4, 250, 380, 9999], ['Pulse oximeter', 'piece', 40, 15, 7, 12, 700]],
];
$sample = static function (string $key, string $label, string $type, ?array $opts, array $v): string {
    if ($type === 'select') {
        return (string) ($opts[0] ?? '');
    }
    if ($type === 'date') {
        return date('Y-m-d', strtotime('-2 days'));
    }
    if ($type === 'time') {
        return '10:30';
    }
    $map = ['age' => (string) (28 + strlen($v['party']) % 30), 'weight' => '68', 'bp' => '120/80', 'pulse' => '76', 'temp' => '98.4 F', 'allergies' => 'None known', 'conditions' => 'None', 'complaints' => 'Fever and body ache for two days', 'diagnosis' => 'Viral fever', 'tests' => 'CBC, CRP', 'advice' => 'Rest, plenty of fluids, review in 3 days', 'address' => 'House 12, Road 5, ' . $v['city'], 'ward' => 'Ward 2', 'bed' => 'B-12', 'doctor' => 'Dr. ' . $v['doctor'], 'procedure' => 'Appendicectomy', 'surgeon' => 'Dr. ' . $v['doctor'], 'witness' => 'Rina Akter', 'guardian' => 'Hasan Ali', 'guardian_rel' => 'Father', 'guardian_phone' => '+880 1711 000 111', 'kin_name' => 'Hasan Ali', 'kin_rel' => 'Spouse', 'kin_phone' => '+880 1711 000 111', 'nid' => '1990 4567 8901', 'occupation' => 'Teacher', 'insurance' => 'POL-2026-55102'];
    return $map[$key] ?? (stripos($label, 'phone') !== false ? '+880 1711 000 000' : (stripos($label, 'no') !== false ? 'A-' . strlen($key) . '01' : 'Sample ' . strtolower($label)));
};

foreach ($vendors as $vi => [$slug, $typeSlug, $name, $city, $street, $phone, $planName, $planDays]) {
    if ($db->val('SELECT COUNT(*) FROM tenants WHERE id = ?', [$slug])) {
        echo "skip $slug (exists)\n";
        continue;
    }
    $type = $app->typeRow($typeSlug);
    $family = (string) Selfcare\VendorTypes::template($type)['family'];
    $tpl = Selfcare\VendorTypes::template($type);
    $adminPw = $pw();
    $adminEmail = 'admin@' . $slug . '.example';
    $t = Selfcare\Vendors::create($app, ['slug' => $slug, 'type' => $typeSlug, 'name' => $name, 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => $adminEmail, 'password' => $adminPw, 'city' => $city, 'phone' => $phone, 'plan_id' => planId_of($db, 'Free')]);
    $credLines[] = sprintf('%-22s %-22s %s  %s  %s', strtoupper($family), $name, $site($slug) . '/login', $adminEmail, $adminPw);
    $S = static fn (string $k, string $v) => $app->site->set($t, $k, $v);
    $S('tagline', $type['tagline']);
    $S('about', $name . ' is a ' . strtolower($type['name']) . ' in ' . $city . '. We focus on clear prices, short waits and kind, honest care. Our team has served families here for many years and we keep improving the way we work.');
    $S('address', $street);
    $S('phone', $phone);
    $S('email', 'hello@' . $slug . '.example');
    $S('emergency_phone', '+880 1700 ' . sprintf('%06d', 100000 + $vi * 7311));
    $S('license_no', 'DGHS-' . strtoupper(substr($slug, 0, 3)) . '-' . (2000 + $vi * 13));
    $S('social_facebook', 'https://facebook.com/' . $slug);
    $S('logo', $mkImg('logo-' . $slug, 240, 240, $hues[$vi % 6], [255 - $hues[$vi % 6][0], 255 - $hues[$vi % 6][1], 255 - $hues[$vi % 6][2]], strtoupper(substr($slug, 0, 12))));
    $S('cover_image', $mkImg('cover-' . $slug, 1400, 600, $hues[$vi % 6], [min(255, $hues[$vi % 6][0] + 90), min(255, $hues[$vi % 6][1] + 70), min(255, $hues[$vi % 6][2] + 90)], $name));
    foreach ($texts as $k => $v) {
        foreach ($tpl['sections'] as $s) {
            if (($s['field'] ?? '') === $k) {
                $S($k, $v);
            }
        }
    }
    // sections
    $sort = [];
    foreach ($tpl['sections'] as $s) {
        $key = (string) $s['key'];
        $kind = (string) $s['kind'];
        if ($kind === 'cards' && isset($content[$key])) {
            foreach ($content[$key] as $i => $r) {
                $meta = $r[2];
                $db->insert('sc_items', ['tenant_id' => $t, 'section' => $key, 'title' => $r[0], 'body' => $r[1] ?: null, 'meta' => $meta !== '' ? $meta : null, 'sort_order' => $i, 'is_active' => 1]);
            }
        } elseif ($kind === 'testimonials') {
            foreach ([['Nasrin A.', 'Quick service and a very polite team. I got my report on the same day.', '5'], ['Mohammad R.', 'Clear prices and no surprise charges. I would recommend them to my family.', '5'], ['Salma K.', 'Clean place and helpful staff. Waiting time was short.', '4']] as $i => $r) {
                $db->insert('sc_items', ['tenant_id' => $t, 'section' => $key, 'title' => $r[0], 'body' => $r[1], 'meta' => $r[2], 'sort_order' => $i, 'is_active' => 1]);
            }
        } elseif ($kind === 'faq') {
            foreach ([['Do I need an appointment?', 'Walk-ins are welcome, but an appointment saves you waiting time. You can send a request from this page.'], ['What payment methods do you accept?', 'Cash, cards and mobile banking. You always get a printed invoice.'], ['Can I get a copy of my documents?', 'Yes. Ask the front desk and we will print or email your documents.']] as $i => $r) {
                $db->insert('sc_items', ['tenant_id' => $t, 'section' => $key, 'title' => $r[0], 'body' => $r[1], 'sort_order' => $i, 'is_active' => 1]);
            }
        } elseif ($kind === 'news') {
            foreach ([['Free health camp this Friday', 'Join our free check-up camp. No booking needed.'], ['New opening hours', 'We now open early on weekdays to serve you better.']] as $i => $r) {
                $db->insert('sc_items', ['tenant_id' => $t, 'section' => $key, 'title' => $r[0], 'body' => $r[1], 'published_at' => date('Y-m-d', strtotime('-' . ($i * 9 + 2) . ' days')), 'sort_order' => $i, 'is_active' => 1]);
            }
        } elseif ($kind === 'gallery') {
            foreach (['Reception', 'Waiting area', 'Our team'] as $i => $cap) {
                $db->insert('sc_items', ['tenant_id' => $t, 'section' => $key, 'title' => $cap, 'image' => $mkImg("gal-$slug-$i", 800, 560, $hues[($vi + $i) % 6], [240, 245, 250], $cap), 'sort_order' => $i, 'is_active' => 1]);
            }
        }
    }
    foreach ($free[$family] ?? [] as $i => $r) {
        $db->insert('free_services', ['tenant_id' => $t, 'title' => $r[0], 'description' => $r[1], 'schedule' => $r[2], 'eligibility' => $r[3], 'icon' => 'heart', 'is_active' => 1, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
    }
    // people
    $mkUser = static function (string $role, int $n, string $first, string $lastName, ?string $email = null) use ($db, $app, $t, $name, $city, $pw, $now, &$credLines, $slug, $site): int {
        $rid = (int) $app->access->roleId($role);
        $email = $email ?? strtolower(preg_replace('/[^A-Za-z]/', '', $first . '.' . $role)) . $n . '@' . $slug . '.example';
        $pass = $pw();
        $uid = $db->insert('users', ['department_id' => $rid, 'first_name' => $first, 'last_name' => $lastName, 'email' => $email, 'city' => $city, 'password' => password_hash($pass, PASSWORD_BCRYPT), 'phone' => '+880 1711 ' . sprintf('%06d', 200000 + $n * 137), 'is_super_admin_default' => 0, 'gender' => $n % 2, 'is_admin_default' => 0, 'status' => 1, 'language' => 'en', 'hospital_name' => $name, 'tenant_id' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        $db->insert('model_has_roles', ['role_id' => $rid, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
        $table = $app->access->staffTable($role);
        if ($table !== null && $table !== 'doctors' && $table !== 'patients') {
            $db->insert($table, ['user_id' => $uid, 'tenant_id' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        }
        if ($role === 'Receptionist' || $role === 'Accountant' || $role === 'Pharmacist' || $role === 'Patient') {
            $GLOBALS['sc_creds'][] = sprintf('  %-12s %-34s %s', $role, $email, $pass);
        }
        return $uid;
    };
    $GLOBALS['sc_creds'] = [];
    $doctors = [];
    $specs = $specialties[$family] ?? ['General'];
    $docRole = in_array('Doctor', $app->access->enabledRoles($t, $typeSlug), true) ? 'Doctor' : null;
    $nDoc = $planName === 'Free' ? 1 : ($typeSlug === 'hospital' ? 6 : (in_array($family, ['clinical', 'diagnostics', 'rehab', 'telemedicine'], true) ? 3 : 0));
    if ($docRole === null && $family !== 'clinical') {
        $docRole = in_array('Doctor', $app->access->offeredRoles($typeSlug) ? array_keys($app->access->offeredRoles($typeSlug)) : [], true) ? 'Doctor' : null;
    }
    $deptIds = array_column($db->all('SELECT id, title FROM doctor_departments WHERE tenant_id = ? ORDER BY id', [$t]), 'id', 'title');
    for ($i = 0; $i < $nDoc; $i++) {
        [$fn, $ln] = $person($vi * 5 + $i);
        $uid = $mkUser('Doctor', 100 + $i, $fn, $ln);
        $spec = $specs[$i % count($specs)];
        $deptId = $deptIds[$spec] ?? ($deptIds ? array_values($deptIds)[$i % count($deptIds)] : (int) ($db->val('SELECT id FROM doctor_departments WHERE tenant_id = ? LIMIT 1', [$t]) ?: $db->insert('doctor_departments', ['title' => 'General', 'tenant_id' => $t, 'created_at' => $now, 'updated_at' => $now])));
        $did = $db->insert('doctors', ['user_id' => $uid, 'doctor_department_id' => $deptId, 'specialist' => $spec, 'description' => 'Dr. ' . $fn . ' ' . $ln . ' has ' . (6 + $i * 3) . ' years of experience in ' . strtolower($spec) . ' and sees patients every weekday.', 'tenant_id' => $t, 'created_at' => $now, 'updated_at' => $now, 'appointment_charge' => 15 + $i * 5]);
        $doctors[] = ['id' => $did, 'name' => $fn . ' ' . $ln];
    }
    foreach (array_slice($staffByFamily[$family] ?? [], 0, $planName === 'Free' ? 1 : 99) as $i => $role) {
        [$fn, $ln] = $person($vi * 7 + $i + 3);
        $uid = $mkUser($role, 10 + $i, $fn, $ln);
        if ($vi === 0 && $role === 'Receptionist') { // a locum whose access has ended, and a night shift only account
            $db->insert('staff_access_windows', ['tenant_id' => $t, 'user_id' => $uid, 'valid_from' => date('Y-m-d H:i:s', strtotime('-60 days')), 'valid_until' => date('Y-m-d H:i:s', strtotime('-3 days')), 'is_active' => 1, 'note' => 'Locum cover (ended)', 'created_at' => $now, 'updated_at' => $now]);
        }
        if ($vi === 0 && $role === 'Accountant') {
            $db->insert('staff_access_windows', ['tenant_id' => $t, 'user_id' => $uid, 'valid_from' => date('Y-m-d H:i:s', strtotime('-30 days')), 'valid_until' => date('Y-m-d H:i:s', strtotime('+90 days')), 'is_active' => 1, 'note' => 'Contract until the end of the quarter', 'created_at' => $now, 'updated_at' => $now]);
            foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
                $db->insert('staff_shifts', ['tenant_id' => $t, 'user_id' => $uid, 'day_of_week' => $d, 'start_time' => '00:00:00', 'end_time' => '23:59:00', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
    $patients = [];
    $nPat = isset($type['family']) && ($tpl['people'] ?? null) ? 8 : 0;
    for ($i = 0; $i < $nPat; $i++) {
        [$fn, $ln] = $person($vi * 3 + $i + 7);
        $uid = $mkUser('Patient', 300 + $i, $fn, $ln);
        $pid = $db->insert('patients', ['user_id' => $uid, 'tenant_id' => $t, 'patient_unique_id' => strtoupper(substr($slug, 0, 3)) . '-' . sprintf('%05d', 1000 + $i), 'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 6) . ' days')), 'updated_at' => $now]);
        $patients[] = ['id' => $pid, 'name' => $fn . ' ' . $ln, 'user' => $uid];
    }
    $adminUid = (int) $db->val("SELECT id FROM users WHERE tenant_id = ? AND is_admin_default = 1 ORDER BY id LIMIT 1", [$t]);
    // requests and messages
    foreach (['new', 'new', 'confirmed', 'done', 'cancelled', 'new'] as $i => $st) {
        [$fn, $ln] = $person($vi + $i * 2 + 1);
        $db->insert('sc_appointment_requests', ['tenant_id' => $t, 'name' => "$fn $ln", 'phone' => '+880 1811 ' . sprintf('%06d', 500000 + $i * 211), 'email' => strtolower($fn) . $i . '@mail.example', 'doctor_id' => $doctors ? $doctors[$i % count($doctors)]['id'] : null, 'department_id' => null, 'preferred_date' => date('Y-m-d', strtotime('+' . ($i - 2) . ' days')), 'message' => 'Please call me to confirm the time.', 'status' => $st, 'created_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days'))]);
    }
    foreach ([['Mina Das', 'Do you have a night shift doctor?', 0], ['Jahid Hasan', 'What is the price for a full health package?', 1]] as $i => $r) {
        $db->insert('sc_messages', ['tenant_id' => $t, 'name' => $r[0], 'email' => 'visitor' . $i . '@mail.example', 'message' => $r[1], 'is_read' => $r[2], 'created_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days'))]);
    }
    // settings
    $bill->setSetting($t, 'currency', 'USD');
    if ($typeSlug === 'hospital') {
        $bill->setSetting($t, 'bed_capacity', (string) (60 + $vi * 20));
        $bill->setSetting($t, 'doctor_commission_pct', '30');
    }
    if ($vi === 0) {
        $bill->setSetting($t, 'invoice_prefix_opd_bill', 'CGH-OPD-');
    }
    // stock
    $stockIds = [];
    foreach ($stockSeed[$family] ?? [] as $r) {
        $stockIds[] = ['id' => $db->insert('sc_stock_items', ['tenant_id' => $t, 'name' => $r[0], 'sku' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $r[0]), 0, 4)) . random_int(100, 999), 'batch_no' => 'B' . random_int(1000, 9999), 'unit' => $r[1], 'expiry_date' => date('Y-m-d', strtotime(($r[6] >= 0 ? '+' : '') . $r[6] . ' days')), 'quantity' => $r[2], 'reorder_level' => $r[3], 'cost_price' => $r[4], 'sell_price' => $r[5], 'created_at' => $now, 'updated_at' => $now]), 'name' => $r[0], 'price' => $r[5]];
    }
    // subscription (vendor creation gave Free; now the planned one)
    if ($planName !== 'Free') {
        $app->plans->assign($t, planId_of($db, $planName), $planDays, 'Demo plan', null);
    }
    // invoices and forms
    $cat = $bill->catalog($typeSlug, $family);
    $items = $invItems[$family];
    $payers = $patients ?: [['id' => null, 'name' => 'Walk-in customer'], ['id' => null, 'name' => 'City Health Corporation']];
    $methods = ['Cash', 'Card', 'Mobile banking', 'Bank transfer', 'Insurance'];
    $k = 0;
    $firstOf = [];
    foreach ($cat['invoices'] as $dt => $cfg) {
        $reps = in_array($cfg['kind'], ['invoice', 'receipt'], true) ? 3 : 1;
        for ($r = 0; $r < $reps; $r++) {
            $k++;
            $payer = $payers[$k % count($payers)];
            $lines = [];
            $n = 1 + ($k % 3);
            for ($j = 0; $j < $n; $j++) {
                $it = $items[($k + $j * 2) % count($items)];
                $stk = $stockIds ? $stockIds[($k + $j) % count($stockIds)] : null;
                $line = ['item_name' => $stk && !empty($cfg['stock']) ? $stk['name'] : $it[0], 'qty' => 1 + (($k + $j) % 3), 'price' => $stk && !empty($cfg['stock']) ? ($cfg['stock'] === 'in' ? $stk['price'] * 0.6 : $stk['price']) : $it[1], 'tax_pct' => !empty($cfg['tax']) ? 5 : 0];
                if ($stk && !empty($cfg['stock'])) {
                    $line['stock_item_id'] = $stk['id'];
                    $line['qty'] = $cfg['stock'] === 'out' ? 1 + ($k % 2) : 10;
                }
                $lines[] = $line;
            }
            $in = ['party_name' => $payer['name'], 'patient_id' => $payer['id'], 'doctor_id' => ($cfg['party'] ?? '') === 'Patient' && $doctors ? $doctors[$k % count($doctors)]['id'] : null, 'invoice_date' => date('Y-m-d', strtotime('-' . (($k * 4 + $r * 7) % 45) . ' days')), 'discount' => $k % 4 === 0 && !empty($cfg['discount']) ? 2 : 0, 'payment_method' => $methods[$k % 5], 'party_phone' => '+880 1911 ' . sprintf('%06d', 300000 + $k * 17), 'related_id' => $cfg['kind'] === 'credit' ? ($firstOf[array_key_first($firstOf)] ?? null) : null];
            foreach ($cfg['x'] as $x) {
                $d1 = date('d M Y', strtotime('-' . (3 + $k % 5) . ' days'));
                $in['x_' . $x[0]] = match ($x[0]) {
                    'admission_no' => 'ADM-' . (1000 + $k), 'reason' => 'Service not used', 'doctor', 'consultant', 'surgeon', 'prescriber' => 'Dr. ' . ($doctors[$k % max(1, count($doctors))]['name'] ?? 'Karim Hossain'),
                    'visit_type' => 'New visit', 'ward_bed' => 'Ward 2 / B-' . (10 + $k % 9), 'admit_date', 'trip_date', 'procedure_date', 'rental_from' => $d1, 'discharge_date', 'report_date', 'delivery_date', 'rental_to', 'valid_until' => date('d M Y', strtotime('+3 days')),
                    'purpose' => 'Treatment cost', 'procedure' => 'Appendicectomy', 'anaesthesia' => 'Spinal', 'package' => 'Normal delivery package', 'inclusions' => 'Room 2 nights, medicines, nursing and doctor fees',
                    'patient_name' => $payer['name'], 'policy_no' => 'POL-2026-' . (5000 + $k), 'claim_no' => 'CLM-' . (800 + $k), 'diagnosis' => 'Acute appendicitis', 'referred_by' => 'Dr. Salma Begum',
                    'sample_id' => 'SMP-' . (9000 + $k), 'sample_type' => 'Blood (EDTA)', 'collected_at' => $d1 . ', 08:30', 'modality' => 'X-ray chest PA', 'rx_no' => 'RX-' . (300 + $k), 'ship_to' => 'Ward 3, ' . $city,
                    'po_no' => 'PO-' . (200 + $k), 'supplier_invoice_no' => 'SI-' . (7000 + $k), 'batch_note' => 'Expiry Dec 2027', 'resident_id' => 'RES-' . (100 + $k), 'room' => 'Room 4 / Bed A', 'period' => date('F Y'),
                    'therapist' => 'Nadia Chowdhury', 'sessions' => '6', 'blood_group' => ['A+', 'O+', 'B+'][$k % 3], 'bag_no' => 'BG-' . (4000 + $k), 'hospital' => 'Ward 3, ' . $city . ' Medical', 'crossmatch_no' => 'XM-' . (50 + $k),
                    'vehicle_no' => 'DHAKA-METRO-' . (11 + $k % 40) . '-' . (2000 + $k), 'driver' => 'Jamal Mia', 'pickup' => 'House 12, Road 5, ' . $city, 'destination' => 'City General Hospital', 'distance_km' => (string) (8 + $k % 20),
                    'session_at' => $d1 . ', 11:00', 'channel' => 'Video call', 'caregiver' => 'Farhana Akter', 'address' => 'Flat 4B, Road 9, ' . $city, 'po_ref' => 'CPO-' . (600 + $k), 'serial_nos' => 'SN-' . (77000 + $k), 'warranty' => '12 months',
                    'deposit' => '50', 'delivery_time' => '10:00 to 12:00', 'plan_name' => 'Professional',
                    default => 'Sample ' . strtolower((string) $x[1]),
                };
            }
            $total = 0;
            foreach ($lines as $l) {
                $total += $l['qty'] * $l['price'];
            }
            $in['paid_now'] = in_array($k % 3, [0, 1], true) ? ($k % 3 === 0 ? $total : round($total * 0.5, 2)) : 0;
            try {
                $id = $bill->create($t, 'vendor', $dt, $in, $lines, $adminUid);
                if ($cfg['kind'] === 'invoice' && !isset($firstOf[$dt]) && $in['paid_now'] < $total) {
                    $firstOf[$dt] = $id;
                }
                // spread the payment dates over the same days as the invoice
                $db->run("UPDATE sc_invoice_payments SET paid_at = CONCAT(?, ' ', TIME(paid_at)) WHERE invoice_id = ?", [$in['invoice_date'], $id]);
            } catch (\InvalidArgumentException $e) {
                echo "  note $slug $dt: " . $e->getMessage() . "\n";
            }
        }
    }
    foreach ($cat['forms'] as $ft => $form) {
        $reps = in_array($ft, ['registration_card', 'prescription', 'free_service_delivery'], true) ? 3 : 1;
        for ($r = 0; $r < $reps; $r++) {
            $payer = $payers[($vi + $r) % count($payers)];
            $v = ['party' => $payer['name'], 'city' => $city, 'doctor' => $doctors[0]['name'] ?? 'Karim Hossain'];
            $data = ['patient_ref' => $patients ? 'P-' . (1000 + $r) : ''];
            foreach ($form['sections'] as $sec) {
                if (isset($sec['rows'])) {
                    $rows = [];
                    for ($i = 0; $i < 3; $i++) {
                        $row = [];
                        foreach ($sec['cols'] as $c) {
                            $row[$c[0]] = match ($c[0]) {
                                'drug' => ['Paracetamol 500 mg', 'Omeprazole 20 mg', 'ORS sachet'][$i], 'dose' => ['1 tablet', '1 capsule', '1 sachet'][$i], 'freq' => ['3 times a day', 'Before breakfast', 'After each loose stool'][$i], 'dur' => ['5 days', '14 days', '2 days'][$i],
                                'time' => sprintf('%02d:00', 8 + $i * 4), 'bp' => '120/80', 'pulse' => (string) (72 + $i), 'temp' => '98.' . $i, 'spo2' => '98%', 'rr' => '16',
                                'test' => [['Haemoglobin', 'White cell count', 'Platelet count'], ['Complete blood count', 'Blood sugar fasting', 'Lipid profile']][$sec['rows'] === 'results' ? 0 : 1][$i],
                                'result' => ['12.8', '7.2', '265'][$i], 'unit' => ['g/dL', 'x10^9/L', 'x10^9/L'][$i], 'range' => ['12.0 - 16.0', '4.0 - 11.0', '150 - 400'][$i], 'flag' => 'N',
                                'sample' => ['Blood (EDTA)', 'Blood (fluoride)', 'Serum'][$i], 'remark' => ['Fasting', 'Routine', 'Urgent'][$i], 'route' => ['Oral', 'IV', 'Oral'][$i], 'times' => ['08:00 / 20:00', '06:00', '14:00'][$i], 'given' => ['RN, 08:05', 'RN, 06:10', ''][$i],
                                'item' => ['Surgical gloves (box)', 'Syringe 5 ml', 'Sterile gauze'][$i], 'batch' => 'B' . (4100 + $i), 'system' => (string) (40 + $i), 'counted' => (string) (40 + $i - ($i === 2 ? 1 : 0)), 'variance' => $i === 2 ? '-1' : '0', 'serial' => 'SN-' . (77120 + $i), 'qty' => '1', 'condition' => 'Good', 'ok' => ['OK', 'OK', 'Not OK'][$i], 'gcs' => '15', 'need' => ['Wound dressing', 'Medicine reminder', 'Blood pressure check'][$i], 'action' => ['Change dressing', 'Give tablets after meals', 'Record BP'][$i], 'who' => 'Nurse',
                                default => 'Sample ' . strtolower((string) $c[1]) . ' ' . ($i + 1),
                            };
                        }
                        $rows[] = $row;
                    }
                    $data[$sec['rows']] = $rows;
                    continue;
                }
                foreach ($sec['f'] as $f) {
                    $data[$f[0]] = $sample($f[0], (string) $f[1], (string) ($f[2] ?? 'text'), $f[3] ?? null, $v);
                }
            }
            $db->insert('sc_documents', ['tenant_id' => $t, 'doc_type' => $ft, 'doc_no' => $bill->nextNo($t, $ft), 'doc_date' => date('Y-m-d', strtotime('-' . (($vi + $r * 5 + 2) % 30) . ' days')), 'party_name' => $payer['name'], 'party_phone' => '+880 1911 000 ' . sprintf('%03d', $r), 'patient_id' => $payer['id'], 'data' => json_encode($data), 'status' => 'saved', 'created_by' => $adminUid, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
    // platform subscription documents for paid plans
    if ($planName !== 'Free') {
        $pl = $app->plans->find(planId_of($db, $planName));
        foreach (['subscription_invoice', 'subscription_receipt'] as $j => $pt) {
            $bill->create($t, 'platform', $pt, ['party_name' => $name, 'invoice_date' => date('Y-m-d', strtotime('-' . (20 + $j) . ' days')), 'x_plan_name' => $pl['name'], 'x_period' => '1 year', 'paid_now' => $pt === 'subscription_invoice' ? 0 : 0, 'payment_method' => 'Bank transfer'], [['item_name' => 'Self Care ' . $pl['name'] . ' plan, 1 year', 'qty' => 1, 'price' => (float) ($pl['price_year'] ?: $pl['price'] * 12), 'tax_pct' => 0]], null, 'USD');
        }
    }
    foreach ($GLOBALS['sc_creds'] as $l) {
        $credLines[] = $l;
    }
    echo "seeded $slug ($typeSlug)\n";
}
// a platform credit note for the first paid vendor
if (!$db->val("SELECT COUNT(*) FROM sc_invoices WHERE issuer = 'platform' AND doc_type = 'platform_credit_note'")) {
    $row = $db->one("SELECT t.id, t.hospital_name FROM tenants t JOIN sc_invoices i ON i.tenant_id = t.id AND i.issuer = 'platform' ORDER BY i.id LIMIT 1");
    if ($row) {
        $bill->create((string) $row['id'], 'platform', 'platform_credit_note', ['party_name' => $row['hospital_name'], 'invoice_date' => date('Y-m-d', strtotime('-5 days')), 'x_plan_name' => 'Professional', 'x_period' => 'Unused 2 months', 'payment_method' => 'Bank transfer'], [['item_name' => 'Credit for two unused months', 'qty' => 1, 'price' => 198, 'tax_pct' => 0]], null, 'USD');
    }
}
// one vendor whose paid plan has ended, to show the read-only state
$db->run("UPDATE sc_subscriptions SET ends_at = DATE_SUB(NOW(), INTERVAL 4 DAY), starts_at = DATE_SUB(NOW(), INTERVAL 34 DAY) WHERE tenant_id = 'lakeside' AND status = 'active' AND ends_at IS NOT NULL AND ends_at > NOW()");

if ($creds !== '' && $credLines) {
    file_put_contents($creds, "\n# --- Self Care demo accounts (seed-demo.php, " . date('Y-m-d H:i') . " Asia/Dhaka) ---\n" . implode("\n", $credLines) . "\n", FILE_APPEND | LOCK_EX);
    @chmod($creds, 0600);
    echo count($credLines) . " credential lines written to the creds file\n";
}

function planId_of(Selfcare\Database $db, string $n): int
{
    return (int) $db->val('SELECT id FROM sc_plans WHERE name = ?', [$n]);
}
