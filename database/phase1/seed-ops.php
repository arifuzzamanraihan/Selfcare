<?php
// Selfcare original code. Sample work lists for the demo vendors (prescription orders, samples, blood units, trips ...).
//   php database/phase1/seed-ops.php [--only=medicopharma,lifeline]
// Safe to run twice: a module that already has rows for a vendor is skipped. Only the demo vendors are touched.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi'] as $f) {
    if (is_file(dirname(__DIR__, 2) . '/core/' . $f . '.php')) {
        require_once dirname(__DIR__, 2) . '/core/' . $f . '.php';
    }
}
use Selfcare\App;

$app = new App(dirname(__DIR__, 2));
$db = $app->db;
$only = null;
foreach ($argv as $a) {
    if (preg_match('/^--only=(.*)$/', $a, $m)) {
        $only = array_filter(explode(',', $m[1]));
    }
}
$demo = ['pharmacy' => 'medicopharma', 'laboratory' => 'precisionlab', 'diagnostic-center' => 'diagnosticplus', 'blood-bank' => 'lifeline', 'ambulance' => 'rapidambulance', 'clinic' => 'sunriseclinic', 'hospital' => 'citygeneral', 'specialty-clinic' => 'heartcare', 'dental-clinic' => 'brightsmile', 'eye-clinic' => 'visionplus', 'imaging-center' => 'clearscan', 'dialysis-center' => 'renalcare', 'maternity-center' => 'motherhood', 'mental-health' => 'mindwell', 'alternative-medicine' => 'herbalcare', 'nursing-home' => 'evergreencare', 'rehabilitation' => 'movewell', 'telemedicine' => 'docnow', 'home-care' => 'carehome', 'equipment-supplier' => 'medequip'];
$day = static fn (int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
$now = date('Y-m-d H:i:s');

/** @var array<string,array<string,list<array<string,mixed>>>> $data  type => module => rows */
$data = [];
$data['pharmacy'] = [
    'rx' => [
        ['title' => 'Nusrat Jahan', 'phone' => '01711000111', 'party' => 'Dr Rahman, Square Hospital', 'cat' => 'Counter', 'date1' => $day(0), 'amount' => 18.4, 'status' => 'received', 'notes' => "Amoxicillin 500 mg x 15\nParacetamol 500 mg x 10"],
        ['title' => 'Habibur Rahman', 'phone' => '01822000222', 'party' => 'Dr Salma, Popular Diagnostic', 'cat' => 'Phone', 'date1' => $day(0), 'amount' => 41.0, 'status' => 'verified', 'notes' => "Insulin pen x 2\nOmeprazole 20 mg x 30"],
        ['title' => 'Sultana Razia', 'phone' => '01933000333', 'party' => 'Dr Karim', 'cat' => 'Website', 'date1' => $day(-1), 'amount' => 9.6, 'status' => 'dispensed', 'notes' => 'ORS sachet x 12'],
        ['title' => 'Md Alamgir', 'phone' => '01644000444', 'party' => 'Dr Hasan', 'cat' => 'Delivery', 'date1' => $day(-2), 'amount' => 27.5, 'status' => 'dispensed', 'notes' => 'Cough syrup x 2, Vitamin D3 x 1'],
        ['title' => 'Rokeya Begum', 'phone' => '01555000555', 'party' => 'Dr Nahar', 'cat' => 'Counter', 'date1' => $day(-3), 'amount' => 12.0, 'status' => 'cancelled', 'notes' => 'Patient did not return'],
    ],
    'suppliers' => [
        ['title' => 'Square Pharmaceuticals depot', 'party' => 'Mr Anwar', 'phone' => '01710000001', 'cat' => 'Medicines', 'place_a' => 'Tejgaon, Dhaka', 'amount' => 320.0, 'status' => 'active'],
        ['title' => 'Beximco wholesale', 'party' => 'Ms Farzana', 'phone' => '01710000002', 'cat' => 'Medicines', 'place_a' => 'Mitford, Dhaka', 'amount' => 0, 'status' => 'active'],
        ['title' => 'SurgiCare Supplies', 'party' => 'Mr Imran', 'phone' => '01710000003', 'cat' => 'Surgical items', 'place_a' => 'Banglamotor, Dhaka', 'amount' => 75.5, 'status' => 'active'],
        ['title' => 'Old Town Traders', 'party' => '', 'phone' => '01710000004', 'cat' => 'Other', 'place_a' => 'Chawkbazar, Dhaka', 'amount' => 0, 'status' => 'inactive'],
    ],
];

$samples = [
    ['title' => 'Farhana Akter', 'phone' => '01711220011', 'party' => 'Dr Salma', 'cat' => 'Complete blood count (CBC)', 'place_a' => 'Blood', 'date1' => $day(0), 'amount' => 6.0, 'status' => 'collected', 'notes' => null],
    ['title' => 'Mahbub Alam', 'phone' => '01822330022', 'party' => 'Dr Rahman', 'cat' => 'Lipid profile', 'place_a' => 'Blood', 'date1' => $day(0), 'amount' => 14.0, 'status' => 'processing', 'notes' => null],
    ['title' => 'Shirin Sultana', 'phone' => '01933440033', 'party' => 'Walk-in', 'cat' => 'Urine routine exam', 'place_a' => 'Urine', 'date1' => $day(-1), 'date2' => $day(0), 'amount' => 3.5, 'status' => 'reported', 'notes' => "Colour: pale yellow\nProtein: nil\nSugar: nil\nPus cells: 2-3 /HPF\nImpression: within normal limits"],
    ['title' => 'Rubel Hossain', 'phone' => '01644550044', 'party' => 'Dr Hasan', 'cat' => 'HbA1c', 'place_a' => 'Blood', 'date1' => $day(-3), 'date2' => $day(-2), 'amount' => 11.0, 'status' => 'delivered', 'notes' => "HbA1c: 6.1 %\nImpression: pre-diabetic range, advise diet control and repeat in 3 months"],
    ['title' => 'Tania Rahman', 'phone' => '01555660055', 'party' => 'Dr Karim', 'cat' => 'Thyroid profile (TSH)', 'place_a' => 'Blood', 'date1' => $day(-4), 'date2' => $day(-3), 'amount' => 12.0, 'status' => 'delivered', 'notes' => "TSH: 2.4 mIU/L (0.4 - 4.0)\nImpression: normal"],
    ['title' => 'Imran Kabir', 'phone' => '01766770066', 'party' => 'Dr Nahar', 'cat' => 'Stool routine exam', 'place_a' => 'Stool', 'date1' => $day(-2), 'amount' => 3.0, 'status' => 'rejected', 'notes' => 'Container leaked in transit. Please give a new sample.'],
];
$data['laboratory'] = ['samples' => $samples];
$data['diagnostic-center'] = ['samples' => array_slice($samples, 0, 4)];

$g = ['A+', 'B+', 'O+', 'AB+', 'O-', 'A-', 'B-', 'AB-'];
$donors = [];
foreach ([['Rafiq Ahmed', 'O+', -120, 3], ['Mehnaz Karim', 'A+', -30, 2], ['Shahid Sarker', 'B+', -200, 5], ['Lamia Chowdhury', 'O-', -95, 1], ['Arif Hossain', 'AB+', -10, 4], ['Nusrat Jahan', 'A-', null, 0], ['Imran Khan', 'B-', -400, 7], ['Sabbir Rahman', 'O+', -60, 2]] as $i => [$n, $grp, $last, $cnt]) {
    $next = $last === null ? null : $day($last + 90);
    $st = $last === null ? 'eligible' : ($last + 90 > 0 ? 'resting' : 'eligible');
    if ($n === 'Imran Khan') {
        $st = 'deferred';
    }
    $donors[] = ['title' => $n, 'phone' => '0171' . (1000000 + $i * 1111), 'cat' => $grp, 'place_a' => ['Dhanmondi', 'Mirpur', 'Uttara', 'Banani', 'Gulshan', 'Mohammadpur', 'Badda', 'Motijheel'][$i], 'qty' => $cnt, 'date1' => $last === null ? null : $day($last), 'date2' => $next, 'status' => $st, 'notes' => $n === 'Imran Khan' ? 'Low haemoglobin, review in 6 months.' : null];
}
$units = [];
foreach ([
    ['Rafiq Ahmed', 'O+', 'Whole blood', 450, -5, 'available', 'Fridge 1'], ['Mehnaz Karim', 'A+', 'Packed red cells', 300, -30, 'available', 'Fridge 1'], ['Shahid Sarker', 'B+', 'Whole blood', 450, -33, 'available', 'Fridge 2'],
    ['Lamia Chowdhury', 'O-', 'Platelets', 250, -3, 'available', 'Agitator'], ['Arif Hossain', 'AB+', 'Plasma', 200, -40, 'available', 'Freezer'], ['Sabbir Rahman', 'O+', 'Whole blood', 450, -1, 'quarantine', 'Screening shelf'],
    ['Nusrat Jahan', 'A-', 'Whole blood', 450, -2, 'quarantine', 'Screening shelf'], ['Walk-in donor', 'B+', 'Packed red cells', 300, -12, 'reserved', 'Fridge 2'], ['Rafiq Ahmed', 'O+', 'Whole blood', 450, -20, 'issued', 'Fridge 1'],
    ['Mehnaz Karim', 'A+', 'Whole blood', 450, -36, 'available', 'Fridge 1'], ['Shahid Sarker', 'B+', 'Platelets', 250, -4, 'discarded', 'Agitator'], ['Walk-in donor', 'AB-', 'Whole blood', 450, -10, 'available', 'Fridge 2'],
] as $i => [$n, $grp, $comp, $ml, $col, $st, $loc]) {
    $days = match ($comp) { 'Platelets' => 5, 'Plasma' => 365, default => 35 };
    $r = ['title' => $n, 'cat' => $grp, 'party' => $comp, 'qty' => $ml, 'date1' => $day($col), 'date2' => $day($col + $days), 'place_b' => $loc, 'status' => $st, 'notes' => null];
    if ($st === 'issued') {
        $r['place_a'] = 'Ahmed Hossain, Dhaka Medical';
    }
    if ($st === 'reserved') {
        $r['place_a'] = 'Ward 4, Square Hospital';
    }
    if ($st === 'discarded') {
        $r['notes'] = 'Bag leaked.';
    }
    $units[] = $r;
}
$orders = [
    ['title' => 'Kabir Hossain', 'party' => 'Dhaka Medical, Ward 7', 'phone' => '01711000777', 'cat' => 'O+', 'place_a' => 'Whole blood', 'qty' => 2, 'date1' => $day(0), 'status' => 'pending'],
    ['title' => 'Salma Begum', 'party' => 'Square Hospital, ICU', 'phone' => '01711000888', 'cat' => 'A-', 'place_a' => 'Packed red cells', 'qty' => 1, 'date1' => $day(0), 'status' => 'pending'],
    ['title' => 'Ahmed Hossain', 'party' => 'Dhaka Medical', 'phone' => '01711000999', 'cat' => 'O+', 'place_a' => 'Whole blood', 'qty' => 1, 'date1' => $day(-3), 'status' => 'fulfilled'],
    ['title' => 'Child, name withheld', 'party' => 'Shishu Hospital', 'phone' => '01711000555', 'cat' => 'AB-', 'place_a' => 'Platelets', 'qty' => 2, 'date1' => $day(-5), 'status' => 'rejected', 'notes' => 'Why not? No platelets of this group in stock.'],
];
$data['blood-bank'] = ['donors' => $donors, 'units' => $units, 'bloodreq' => $orders];

$data['ambulance'] = [
    'vehicles' => [
        ['code' => 'DHAKA-METRO-DA-11-2041', 'title' => 'Toyota HiAce ICU', 'cat' => 'ICU', 'party' => 'Karim Uddin', 'phone' => '01711234001', 'date1' => $day(40), 'date2' => $day(200), 'status' => 'on_trip'],
        ['code' => 'DHAKA-METRO-DA-13-5532', 'title' => 'Toyota HiAce ALS', 'cat' => 'Advanced life support', 'party' => 'Jamal Hossain', 'phone' => '01711234002', 'date1' => $day(9), 'date2' => $day(120), 'status' => 'available'],
        ['code' => 'DHAKA-METRO-CHA-11-7710', 'title' => 'Nissan Urvan BLS', 'cat' => 'Basic life support', 'party' => 'Rashed Mia', 'phone' => '01711234003', 'date1' => $day(60), 'date2' => $day(10), 'status' => 'available'],
        ['code' => 'DHAKA-METRO-DA-15-0987', 'title' => 'Neonatal transport unit', 'cat' => 'Neonatal', 'party' => 'Sohel Rana', 'phone' => '01711234004', 'date1' => $day(-2), 'date2' => $day(300), 'status' => 'maintenance', 'notes' => 'Brake pads being changed.'],
        ['code' => 'DHAKA-METRO-THA-12-3344', 'title' => 'Old Mitsubishi Delica', 'cat' => 'Patient transport', 'party' => '', 'phone' => '', 'date1' => null, 'date2' => $day(-90), 'status' => 'retired'],
    ],
    'trips' => [
        ['title' => 'Abdul Malek', 'phone' => '01811000101', 'party' => 'Son: Rubel', 'place_a' => 'Mirpur 10, Dhaka', 'place_b' => 'Dhaka Medical College Hospital', 'date1' => $day(0), 'tag' => 'Emergency', 'cat' => 'DHAKA-METRO-DA-11-2041', 'status' => 'onroute', 'notes' => 'Chest pain. Oxygen on board.'],
        ['title' => 'Rokeya Khatun', 'phone' => '01811000102', 'party' => 'Husband', 'place_a' => 'Uttara Sector 7', 'place_b' => 'Square Hospital, Panthapath', 'date1' => $day(0), 'tag' => 'Urgent', 'status' => 'requested', 'notes' => 'Post-surgery transfer, needs a stretcher.'],
        ['title' => 'Baby of Nasrin', 'phone' => '01811000103', 'party' => 'Nasrin', 'place_a' => 'Narayanganj Sadar', 'place_b' => 'Shishu Hospital, Sher-e-Bangla Nagar', 'date1' => $day(0), 'tag' => 'Emergency', 'status' => 'requested'],
        ['title' => 'Hasina Akter', 'phone' => '01811000104', 'party' => 'Self', 'place_a' => 'Dhanmondi 27', 'place_b' => 'Evercare Hospital, Bashundhara', 'date1' => $day(-1), 'tag' => 'Routine', 'cat' => 'DHAKA-METRO-DA-13-5532', 'status' => 'completed', 'qty' => 18.5, 'amount' => 45, 'notes' => 'Dialysis transfer.'],
        ['title' => 'Mofizul Islam', 'phone' => '01811000105', 'party' => 'Daughter', 'place_a' => 'Gazipur', 'place_b' => 'Dhaka Medical College Hospital', 'date1' => $day(-2), 'tag' => 'Urgent', 'cat' => 'DHAKA-METRO-DA-11-2041', 'status' => 'completed', 'qty' => 32, 'amount' => 80],
        ['title' => 'Shirin Begum', 'phone' => '01811000106', 'party' => 'Neighbour', 'place_a' => 'Savar', 'place_b' => 'CMH Dhaka', 'date1' => $day(-3), 'tag' => 'Routine', 'status' => 'cancelled', 'notes' => 'Family arranged a private car.'],
        ['title' => 'Ibrahim Khalil', 'phone' => '01811000107', 'party' => 'Self', 'place_a' => 'Banani', 'place_b' => 'Airport', 'date1' => $day(-6), 'tag' => 'Routine', 'cat' => 'DHAKA-METRO-CHA-11-7710', 'status' => 'completed', 'qty' => 14, 'amount' => 35, 'notes' => 'Medical evacuation flight.'],
    ],
];

// Wave 2 type-specific demo records
$tok = static function (array $people) use ($day): array {
    $rows = [];
    $states = ['waiting', 'waiting', 'called', 'in_consult', 'done', 'skipped'];
    $visits = ['New', 'Follow-up', 'New', 'Report show', 'New', 'Follow-up'];
    $prio = ['Normal', 'Urgent', 'Normal', 'Elderly', 'Normal', 'Normal'];
    foreach ($people as $i => [$name, $phone, $doc]) {
        $rows[] = ['title' => $name, 'phone' => $phone, 'party' => $doc, 'cat' => $visits[$i], 'tag' => $prio[$i], 'date1' => $day(0), 'status' => $states[$i], 'sort_order' => $i + 1, 'notes' => $i === 0 ? 'Fever for two days' : null];
    }
    return $rows;
};
$peopleA = [['Nusrat Jahan', '01711001001', 'Dr Rahman'], ['Karim Hossain', '01711001002', 'Dr Salma'], ['Farhana Akter', '01711001003', 'Dr Rahman'], ['Imran Chowdhury', '01711001004', 'Dr Hasan'], ['Mehnaz Begum', '01711001005', 'Dr Salma'], ['Rafiq Islam', '01711001006', 'Dr Rahman']];
foreach (['clinic' => 'sunriseclinic', 'hospital' => 'citygeneral', 'specialty-clinic' => 'heartcare', 'dental-clinic' => 'brightsmile', 'eye-clinic' => 'visionplus', 'maternity-center' => 'motherhood', 'alternative-medicine' => 'herbalcare'] as $type => $_slug) {
    $data[$type]['tokens'] = $tok($peopleA);
}

$data['specialty-clinic']['procedures'] = [
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Dr Hasan', 'cat' => 'ECG', 'place_a' => 'Room 2', 'date1' => $day(0), 'amount' => 15, 'status' => 'booked'],
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Dr Hasan', 'cat' => 'Echocardiogram', 'place_a' => 'Echo lab', 'date1' => $day(0), 'amount' => 45, 'status' => 'in_progress'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Rahman', 'cat' => 'Treadmill test', 'place_a' => 'Room 3', 'date1' => $day(0), 'amount' => 50, 'status' => 'completed', 'notes' => 'Exercise tolerance fair. No significant ST change.'],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Dr Hasan', 'cat' => 'Holter fitting', 'date1' => $day(-1), 'amount' => 60, 'status' => 'cancelled', 'notes' => 'Patient postponed.'],
];

$data['dental-clinic']['txplans'] = [
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Dr Karim', 'cat' => 'Pain in upper right', 'date1' => $day(0), 'amount' => 80, 'status' => 'proposed', 'meta' => json_encode(['teeth' => '16,15']), 'notes' => "16 — Examination and X-ray — 10\n16 — Root canal (first visit) — 70"],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Dr Karim', 'cat' => 'Cleaning', 'date1' => $day(0), 'amount' => 25, 'status' => 'accepted', 'meta' => json_encode(['teeth' => '11,12,21,22']), 'notes' => 'Full mouth scaling — 25'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Nadia', 'cat' => 'Decay', 'date1' => $day(-2), 'amount' => 40, 'status' => 'in_progress', 'meta' => json_encode(['teeth' => '36']), 'notes' => "36 — Composite filling — 40"],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Dr Karim', 'cat' => 'Extraction', 'date1' => $day(-10), 'amount' => 20, 'status' => 'completed', 'meta' => json_encode(['teeth' => '48']), 'notes' => '48 — Extraction — 20'],
];

$data['eye-clinic']['eyexams'] = [
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Dr Salma', 'date1' => $day(0), 'amount' => 20, 'status' => 'booked'],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Dr Salma', 'date1' => $day(0), 'amount' => 20, 'status' => 'in_exam'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Rahman', 'date1' => $day(0), 'amount' => 20, 'status' => 'completed', 'notes' => 'Advice: new progressive lenses. Review in 1 year.', 'meta' => json_encode(['va_r' => '6/9', 'va_l' => '6/12', 'sph_r' => '-1.25', 'cyl_r' => '-0.50', 'axis_r' => '90', 'add_r' => '+1.50', 'sph_l' => '-1.50', 'cyl_l' => '-0.75', 'axis_l' => '85', 'add_l' => '+1.50', 'iop_r' => '14', 'iop_l' => '15'])],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Dr Salma', 'date1' => $day(-1), 'amount' => 20, 'status' => 'cancelled'],
];
$data['eye-clinic']['optical'] = [
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Rahman', 'cat' => 'Progressive', 'place_a' => 'Metal frame, mid-index', 'date1' => $day(0), 'date2' => $day(5), 'amount' => 85, 'status' => 'in_lab', 'notes' => "R: -1.25 / -0.50 x 90 Add +1.50\nL: -1.50 / -0.75 x 85 Add +1.50\nAnti-reflective coating."],
    ['title' => 'Mehnaz Begum', 'phone' => '01711001005', 'party' => 'Dr Salma', 'cat' => 'Single vision', 'place_a' => 'Plastic frame', 'date1' => $day(-4), 'date2' => $day(-1), 'amount' => 35, 'status' => 'ready'],
    ['title' => 'Rafiq Islam', 'phone' => '01711001006', 'party' => 'Dr Salma', 'cat' => 'Single vision', 'place_a' => 'Ready-made +1.00', 'date1' => $day(-8), 'amount' => 12, 'status' => 'delivered'],
];

$catRows = [
    ['title' => 'Complete blood count (CBC)', 'cat' => 'Haematology', 'place_a' => 'Blood', 'place_b' => '—', 'amount' => 6, 'status' => 'active', 'notes' => 'Hb, WBC, platelets. Adult Hb 12–16 g/dL (F), 13–17 (M).'],
    ['title' => 'Fasting blood sugar', 'cat' => 'Biochemistry', 'place_a' => 'Blood', 'place_b' => 'mg/dL', 'amount' => 4, 'status' => 'active', 'notes' => '70–100 mg/dL fasting.'],
    ['title' => 'HbA1c', 'cat' => 'Biochemistry', 'place_a' => 'Blood', 'place_b' => '%', 'amount' => 11, 'status' => 'active', 'notes' => 'Non-diabetic < 5.7 %.'],
    ['title' => 'TSH', 'cat' => 'Immunology', 'place_a' => 'Blood', 'place_b' => 'mIU/L', 'amount' => 12, 'status' => 'active', 'notes' => '0.4–4.0 mIU/L (adult).'],
    ['title' => 'Urine routine exam', 'cat' => 'Haematology', 'place_a' => 'Urine', 'place_b' => '—', 'amount' => 3.5, 'status' => 'active'],
    ['title' => 'Old panel (discontinued)', 'cat' => 'Other', 'place_a' => 'Blood', 'amount' => 0, 'status' => 'inactive'],
];
$data['laboratory']['catalogue'] = $catRows;
$data['diagnostic-center']['catalogue'] = $catRows;

$studies = [
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Dr Rahman', 'cat' => 'X-ray', 'place_a' => 'Chest PA', 'date1' => $day(0), 'amount' => 18, 'status' => 'booked'],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Dr Salma', 'cat' => 'Ultrasound', 'place_a' => 'USG abdomen', 'date1' => $day(0), 'amount' => 30, 'status' => 'in_progress'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Hasan', 'cat' => 'X-ray', 'place_a' => 'KUB', 'date1' => $day(-1), 'date2' => $day(0), 'amount' => 20, 'status' => 'reported', 'notes' => "No radio-opaque calculus.\nImpression: normal KUB radiograph."],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Dr Rahman', 'cat' => 'CT', 'place_a' => 'CT brain', 'date1' => $day(-3), 'date2' => $day(-2), 'amount' => 90, 'status' => 'delivered', 'notes' => "No acute haemorrhage or infarct.\nImpression: normal study."],
    ['title' => 'Mehnaz Begum', 'phone' => '01711001005', 'party' => 'Walk-in', 'cat' => 'Mammography', 'place_a' => 'Bilateral mammogram', 'date1' => $day(-2), 'amount' => 55, 'status' => 'cancelled', 'notes' => 'Patient rescheduled.'],
];
$data['imaging-center']['studies'] = $studies;
$data['diagnostic-center']['studies'] = array_slice($studies, 0, 3);

$data['dialysis-center']['dmachines'] = [
    ['code' => 'M-1', 'title' => 'Fresenius 4008', 'place_a' => 'Station 1', 'date1' => $day(20), 'status' => 'available'],
    ['code' => 'M-2', 'title' => 'Fresenius 4008', 'place_a' => 'Station 2', 'status' => 'in_use'],
    ['code' => 'M-3', 'title' => 'Nipro Surdial', 'place_a' => 'Station 3', 'status' => 'available'],
    ['code' => 'M-4', 'title' => 'Older unit', 'place_a' => 'Workshop', 'status' => 'maintenance', 'notes' => 'Pending pump service.'],
];
$data['dialysis-center']['dsessions'] = [
    ['title' => 'Shahid Sarker', 'phone' => '01711002001', 'party' => 'Nurse Nadia', 'cat' => 'M-2', 'tag' => 'Morning', 'date1' => $day(0), 'amount' => 40, 'status' => 'in_session', 'meta' => json_encode(['pre_wt' => '68.4', 'bp' => '140/90'])],
    ['title' => 'Lamia Chowdhury', 'phone' => '01711002002', 'party' => 'Nurse Nadia', 'cat' => 'M-1', 'tag' => 'Morning', 'date1' => $day(0), 'amount' => 40, 'status' => 'scheduled'],
    ['title' => 'Arif Hossain', 'phone' => '01711002003', 'party' => 'Nurse Karim', 'cat' => 'M-3', 'tag' => 'Afternoon', 'date1' => $day(0), 'amount' => 40, 'status' => 'scheduled'],
    ['title' => 'Mehnaz Karim', 'phone' => '01711002004', 'party' => 'Nurse Nadia', 'cat' => 'M-1', 'tag' => 'Morning', 'date1' => $day(-2), 'amount' => 40, 'status' => 'completed', 'meta' => json_encode(['pre_wt' => '62.0', 'post_wt' => '59.8', 'uf' => '2.2', 'bp' => '130/80'])],
    ['title' => 'Rafiq Ahmed', 'phone' => '01711002005', 'tag' => 'Morning', 'date1' => $day(-1), 'status' => 'missed', 'notes' => 'Did not arrive.'],
];

$data['maternity-center']['anc'] = [
    ['title' => 'Sumaiya Begum', 'phone' => '01711003001', 'party' => 'Dr Nadia', 'cat' => 'Booking', 'date1' => $day(0), 'status' => 'booked'],
    ['title' => 'Tahmina Akter', 'phone' => '01711003002', 'party' => 'Dr Nadia', 'cat' => 'ANC 3', 'date1' => $day(0), 'date2' => $day(28), 'status' => 'seen', 'amount' => 15, 'meta' => json_encode(['ga' => '28', 'bp' => '110/70', 'weight' => '64', 'fhr' => '140']), 'notes' => 'Fetal movements good. Iron and calcium continued.'],
    ['title' => 'Aisha Rahman', 'phone' => '01711003003', 'party' => 'Midwife Farhana', 'cat' => '36 weeks', 'date1' => $day(-7), 'date2' => $day(7), 'status' => 'seen', 'amount' => 15, 'meta' => json_encode(['ga' => '36', 'bp' => '120/80', 'weight' => '71', 'fhr' => '138'])],
    ['title' => 'Lamia Islam', 'phone' => '01711003004', 'party' => 'Dr Nadia', 'cat' => 'ANC 2', 'date1' => $day(0), 'status' => 'missed'],
];
$data['maternity-center']['vaccines'] = [
    ['title' => 'Baby of Tahmina', 'phone' => '01711003002', 'cat' => 'BCG', 'qty' => 1, 'date1' => $day(-10), 'status' => 'given', 'party' => 'Nurse Karim'],
    ['title' => 'Baby of Tahmina', 'phone' => '01711003002', 'cat' => 'OPV', 'qty' => 1, 'date1' => $day(-10), 'date2' => $day(20), 'status' => 'given', 'party' => 'Nurse Karim'],
    ['title' => 'Baby of Aisha', 'phone' => '01711003003', 'cat' => 'Penta', 'qty' => 1, 'date1' => $day(0), 'status' => 'due'],
    ['title' => 'Sumaiya Begum', 'phone' => '01711003001', 'cat' => 'TT', 'qty' => 1, 'date1' => $day(0), 'status' => 'due'],
];
$data['maternity-center']['deliveries'] = [
    ['title' => 'Mehnaz Sultana', 'phone' => '01711003005', 'party' => 'Dr Nadia', 'cat' => 'Normal', 'place_a' => 'Live birth', 'qty' => 3.1, 'date1' => $day(0), 'status' => 'admitted'],
    ['title' => 'Farhana Begum', 'phone' => '01711003006', 'party' => 'Dr Nadia', 'cat' => 'C-section', 'place_a' => 'Live birth', 'qty' => 2.8, 'date1' => $day(-2), 'date2' => $day(1), 'amount' => 220, 'status' => 'delivered', 'notes' => 'Elective CS at 39 weeks. Mother and baby well.'],
    ['title' => 'Rokeya Akter', 'phone' => '01711003007', 'party' => 'Midwife Farhana', 'cat' => 'Normal', 'place_a' => 'Live birth', 'qty' => 3.4, 'date1' => $day(-8), 'date2' => $day(-6), 'amount' => 80, 'status' => 'discharged'],
];

$data['mental-health']['mhsessions'] = [
    ['title' => 'Anonymous client A', 'phone' => '01711004001', 'party' => 'Dr Salma', 'cat' => 'Counselling', 'date1' => $day(0), 'status' => 'booked'],
    ['title' => 'Anonymous client B', 'phone' => '01711004002', 'party' => 'Counsellor Karim', 'cat' => 'Follow-up', 'date1' => $day(0), 'amount' => 25, 'status' => 'completed', 'notes' => 'Mood improved. Sleep still broken. Continue weekly sessions.'],
    ['title' => 'Anonymous client C', 'phone' => '01711004003', 'party' => 'Dr Salma', 'cat' => 'Assessment', 'date1' => $day(0), 'status' => 'in_session'],
    ['title' => 'Anonymous client D', 'phone' => '01711004004', 'party' => 'Counsellor Karim', 'cat' => 'Counselling', 'date1' => $day(-1), 'status' => 'no_show'],
];
$data['mental-health']['mhplans'] = [
    ['title' => 'Anonymous client B', 'phone' => '01711004002', 'party' => 'Counsellor Karim', 'cat' => 'Anxiety', 'date1' => $day(-30), 'date2' => $day(14), 'status' => 'active', 'notes' => 'Weekly counselling. Breathing practice at home. Review medication with the doctor.'],
    ['title' => 'Anonymous client E', 'phone' => '01711004005', 'party' => 'Dr Salma', 'cat' => 'Depression', 'date1' => $day(-90), 'date2' => $day(-5), 'status' => 'completed', 'notes' => 'Goals met. Discharged to follow-up as needed.'],
];

$data['alternative-medicine']['consults'] = [
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Hakim Rahman', 'cat' => 'Unani', 'date1' => $day(0), 'status' => 'booked'],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Hakim Rahman', 'cat' => 'Unani', 'date1' => $day(0), 'amount' => 15, 'status' => 'seen', 'notes' => "Complaint: chronic indigestion.\nRemedy: Jawarish Amla 5 g after meals, 14 days."],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Nadia', 'cat' => 'Homeopathy', 'date1' => $day(-2), 'amount' => 12, 'status' => 'seen', 'notes' => 'Arnica 30, twice daily for 5 days after a sprain.'],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Hakim Rahman', 'cat' => 'Herbal', 'date1' => $day(0), 'status' => 'cancelled'],
];
$data['alternative-medicine']['remedies'] = [
    ['title' => 'Jawarish Amla', 'cat' => 'Powder', 'amount' => 8, 'status' => 'active', 'notes' => '5 g after meals.'],
    ['title' => 'Arnica 30', 'cat' => 'Tablet', 'amount' => 3, 'status' => 'active'],
    ['title' => 'Herbal oil (joint)', 'cat' => 'Oil', 'amount' => 12, 'status' => 'active'],
    ['title' => 'Old tonic', 'cat' => 'Syrup', 'amount' => 0, 'status' => 'inactive'],
];

$data['nursing-home']['residents'] = [
    ['title' => 'Abdul Karim, 78', 'phone' => '01711005001', 'party' => 'Son: Rafiq Karim', 'place_a' => 'A-12', 'cat' => 'Assisted', 'date1' => $day(-200), 'status' => 'admitted'],
    ['title' => 'Rahima Begum, 82', 'phone' => '01711005002', 'party' => 'Daughter: Nadia', 'place_a' => 'B-3', 'cat' => 'High', 'date1' => $day(-80), 'status' => 'admitted'],
    ['title' => 'Jamal Uddin, 74', 'phone' => '01711005003', 'party' => 'Wife: Tahmina', 'place_a' => 'A-7', 'cat' => 'Residential', 'date1' => $day(-40), 'status' => 'on_leave', 'notes' => 'With family until Friday.'],
    ['title' => 'Shahida Akter, 70', 'phone' => '01711005004', 'party' => 'Son: Imran', 'cat' => 'Respite', 'status' => 'enquiry'],
    ['title' => 'Old resident', 'phone' => '01711005005', 'place_a' => 'C-1', 'cat' => 'Residential', 'date1' => $day(-400), 'date2' => $day(-20), 'status' => 'discharged'],
];
$data['nursing-home']['ncare'] = [
    ['title' => 'Abdul Karim, 78', 'party' => 'Nurse Nadia', 'cat' => 'Mobility', 'date1' => $day(-30), 'date2' => $day(10), 'status' => 'active', 'notes' => 'Walker for corridor walks twice a day. Watch for falls at night.'],
    ['title' => 'Rahima Begum, 82', 'party' => 'Nurse Nadia', 'cat' => 'Nutrition', 'date1' => $day(-14), 'date2' => $day(-1), 'status' => 'reviewed', 'notes' => 'Soft diet, fluids encouraged. Weight weekly.'],
];
$data['nursing-home']['mar'] = [
    ['title' => 'Abdul Karim, 78', 'cat' => 'Amlodipine 5 mg', 'tag' => 'Morning', 'date1' => $day(0), 'status' => 'due'],
    ['title' => 'Rahima Begum, 82', 'cat' => 'Thyroxine 50 mcg', 'tag' => 'Morning', 'date1' => $day(0), 'status' => 'given', 'party' => 'Nurse Nadia'],
    ['title' => 'Rahima Begum, 82', 'cat' => 'Paracetamol 500 mg', 'tag' => 'Noon', 'date1' => $day(0), 'status' => 'withheld', 'party' => 'Nurse Nadia', 'notes' => 'No pain today.'],
    ['title' => 'Abdul Karim, 78', 'cat' => 'Atorvastatin 10 mg', 'tag' => 'Night', 'date1' => $day(-1), 'status' => 'given', 'party' => 'Nurse Karim'],
];
$data['nursing-home']['incidents'] = [
    ['title' => 'Abdul Karim, 78', 'cat' => 'Fall', 'date1' => $day(-3), 'party' => 'Nurse Karim', 'status' => 'reviewed', 'notes' => 'Slip in the bathroom. No fracture. Family informed. Non-slip mat fitted.'],
    ['title' => 'Jamal Uddin, 74', 'cat' => 'Behaviour', 'date1' => $day(0), 'party' => 'Caregiver Imran', 'status' => 'reported', 'notes' => 'Became distressed at visiting time. Settled after a walk.'],
];

$data['rehabilitation']['rhplans'] = [
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Nadia Chowdhury', 'cat' => 'Physiotherapy', 'date1' => $day(-14), 'date2' => $day(14), 'qty' => 10, 'amount' => 150, 'status' => 'active', 'notes' => 'Post-fracture knee. Goal: walk unaided in 4 weeks.'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Nadia Chowdhury', 'cat' => 'Speech therapy', 'date1' => $day(-40), 'qty' => 8, 'amount' => 120, 'status' => 'completed', 'notes' => 'Goals met.'],
];
$data['rehabilitation']['rhsessions'] = [
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Nadia Chowdhury', 'cat' => 'Physiotherapy', 'date1' => $day(0), 'qty' => 6, 'amount' => 15, 'status' => 'booked'],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Therapist Arif', 'cat' => 'Physiotherapy', 'date1' => $day(0), 'qty' => 2, 'status' => 'booked'],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Nadia Chowdhury', 'cat' => 'Physiotherapy', 'date1' => $day(-3), 'qty' => 5, 'amount' => 15, 'status' => 'done', 'notes' => 'Pain 3/10 after, was 6/10. Home: straight-leg raise 10 reps.'],
    ['title' => 'Mehnaz Begum', 'phone' => '01711001005', 'party' => 'Therapist Arif', 'cat' => 'Occupational therapy', 'date1' => $day(-1), 'status' => 'missed'],
];
$data['rehabilitation']['rhpacks'] = [
    ['title' => 'Physio 10-session pack', 'cat' => 'Physiotherapy', 'qty' => 10, 'amount' => 150, 'status' => 'active'],
    ['title' => 'Speech 8-session pack', 'cat' => 'Speech therapy', 'qty' => 8, 'amount' => 120, 'status' => 'active'],
    ['title' => 'Old intro pack', 'cat' => 'Physiotherapy', 'qty' => 4, 'amount' => 0, 'status' => 'inactive'],
];

$data['telemedicine']['tlsessions'] = [
    ['title' => 'Nusrat Jahan', 'phone' => '01711001001', 'party' => 'Dr Rahman', 'cat' => 'Video', 'place_a' => 'https://meet.example.com/room-a', 'date1' => $day(0), 'amount' => 20, 'status' => 'waiting'],
    ['title' => 'Karim Hossain', 'phone' => '01711001002', 'party' => 'Dr Salma', 'cat' => 'Video', 'place_a' => 'https://meet.example.com/room-b', 'date1' => $day(0), 'amount' => 20, 'status' => 'booked'],
    ['title' => 'Farhana Akter', 'phone' => '01711001003', 'party' => 'Dr Rahman', 'cat' => 'Audio', 'date1' => $day(0), 'amount' => 15, 'status' => 'completed', 'notes' => 'Follow-up for gastritis. Continue omeprazole. Review if pain returns.'],
    ['title' => 'Imran Chowdhury', 'phone' => '01711001004', 'party' => 'Dr Hasan', 'cat' => 'Video', 'date1' => $day(-1), 'status' => 'no_show'],
];

$data['home-care']['caregivers'] = [
    ['code' => 'CG-01', 'title' => 'Nurse Nadia', 'phone' => '01711006001', 'cat' => 'Nurse', 'place_a' => 'Gulshan, Banani', 'status' => 'on_visit'],
    ['code' => 'CG-02', 'title' => 'Caregiver Karim', 'phone' => '01711006002', 'cat' => 'Caregiver', 'place_a' => 'Dhanmondi, Lalmatia', 'status' => 'available'],
    ['code' => 'CG-03', 'title' => 'Therapist Arif', 'phone' => '01711006003', 'cat' => 'Therapist', 'place_a' => 'Uttara', 'status' => 'available'],
    ['code' => 'CG-04', 'title' => 'Caregiver Farhana', 'phone' => '01711006004', 'cat' => 'Caregiver', 'place_a' => 'Mirpur', 'status' => 'leave'],
];
$data['home-care']['hcvisits'] = [
    ['title' => 'Abdul Karim', 'phone' => '01711005001', 'place_a' => '12 Gulshan Avenue', 'cat' => 'CG-01', 'date1' => $day(0), 'amount' => 25, 'status' => 'in_progress'],
    ['title' => 'Rahima Begum', 'phone' => '01711005002', 'place_a' => '8 Dhanmondi Road 6', 'cat' => 'CG-02', 'date1' => $day(0), 'amount' => 20, 'status' => 'scheduled'],
    ['title' => 'Jamal Uddin', 'phone' => '01711005003', 'place_a' => 'Uttara Sector 7', 'cat' => 'CG-03', 'date1' => $day(-1), 'date2' => $day(6), 'amount' => 30, 'status' => 'completed', 'notes' => 'Physio exercises done. Family taught the home programme.'],
    ['title' => 'Shahida Akter', 'phone' => '01711005004', 'place_a' => 'Mirpur 10', 'date1' => $day(-2), 'status' => 'missed', 'notes' => 'No one at home.'],
];

$data['equipment-supplier']['eqitems'] = [
    ['code' => 'EQ-0001', 'title' => 'Standard wheelchair', 'cat' => 'Mobility', 'amount' => 8, 'qty' => 6, 'status' => 'active'],
    ['code' => 'EQ-0002', 'title' => 'Oxygen concentrator', 'cat' => 'Respiratory', 'amount' => 25, 'qty' => 3, 'status' => 'active'],
    ['code' => 'EQ-0003', 'title' => 'Hospital bed (manual)', 'cat' => 'Bed and support', 'amount' => 15, 'qty' => 2, 'status' => 'active'],
    ['code' => 'EQ-0004', 'title' => 'Old nebuliser model', 'cat' => 'Respiratory', 'amount' => 5, 'qty' => 0, 'status' => 'inactive'],
];
$data['equipment-supplier']['rentals'] = [
    ['title' => 'City General Hospital', 'phone' => '01711007001', 'cat' => 'EQ-0002', 'place_a' => 'OC-118', 'place_b' => '14 Lake Road, Dhanmondi', 'date1' => $day(-10), 'date2' => $day(20), 'amount' => 500, 'status' => 'out', 'notes' => 'Deposit 100 held.'],
    ['title' => 'Aisha Rahman', 'phone' => '01711007002', 'cat' => 'EQ-0001', 'place_a' => 'WC-22', 'date1' => $day(-3), 'date2' => $day(-1), 'amount' => 24, 'status' => 'overdue'],
    ['title' => 'Karim Hossain', 'phone' => '01711007003', 'cat' => 'EQ-0001', 'place_a' => 'WC-09', 'date1' => $day(1), 'date2' => $day(14), 'amount' => 80, 'status' => 'reserved'],
    ['title' => 'Farhana Akter', 'phone' => '01711007004', 'cat' => 'EQ-0003', 'place_a' => 'HB-04', 'date1' => $day(-20), 'date2' => $day(-5), 'amount' => 225, 'status' => 'returned', 'notes' => 'Returned in good condition.'],
];
$data['equipment-supplier']['service'] = [
    ['title' => 'City General Hospital', 'phone' => '01711007001', 'cat' => 'Oxygen concentrator OC-118', 'place_a' => 'Maintenance', 'date1' => $day(0), 'status' => 'open'],
    ['title' => 'Aisha Rahman', 'phone' => '01711007002', 'cat' => 'Wheelchair WC-22', 'place_a' => 'Repair', 'date1' => $day(-2), 'amount' => 15, 'status' => 'done', 'notes' => 'Replaced a wheel bearing.'],
    ['title' => 'Lakeside Medical', 'phone' => '01711007005', 'cat' => 'Patient monitor', 'place_a' => 'Installation', 'date1' => $day(0), 'status' => 'in_progress'],
];


$seeded = [];
foreach ($data as $type => $modules) {
    $tenant = $demo[$type] ?? null;
    if ($tenant === null || ($only !== null && !in_array($tenant, $only, true))) {
        continue;
    }
    if (!$db->val('SELECT COUNT(*) FROM sc_vendors WHERE tenant_id = ? AND type_slug = ?', [$tenant, $type])) {
        continue;
    }
    foreach ($modules as $module => $rows) {
        if ($db->val('SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = ?', [$tenant, $module])) {
            continue;
        }
        $mod = \Selfcare\Ops::all()[$module] ?? [];
        if (!$mod) {
            continue; // module not shipped in this build
        }
        $prefix = (string) ($mod['prefix'] ?? '');
        foreach ($rows as $i => $r) {
            if ($prefix !== '' && empty($r['code'])) {
                $r['code'] = \Selfcare\Ops::code($db, $tenant, $module, $prefix);
            } elseif (empty($r['code']) && ($mod['number'] ?? '') === 'daily') {
                $r['code'] = \Selfcare\Ops::dailyCode($db, $tenant, $module, (string) ($r['date1'] ?? date('Y-m-d')));
            }
            if (!isset($r['sort_order'])) {
                $r['sort_order'] = $i + 1;
            }
            $rid = $db->insert('sc_records', $r + ['tenant_id' => $tenant, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]);
            \Selfcare\Ops::event($db, $tenant, (int) $rid, $module, 'created', null, (string) $r['status'], null);
        }
        $seeded[] = "$tenant/$module=" . count($rows);
    }
}
// stock ledger: one opening line per stock item that has no history yet (the pharmacy demo)
foreach ($db->all("SELECT id, tenant_id, quantity FROM sc_stock_items WHERE tenant_id IN ('medicopharma') AND id NOT IN (SELECT item_id FROM sc_stock_moves)") as $it) {
    if ($only !== null && !in_array($it['tenant_id'], $only, true)) {
        continue;
    }
    $db->insert('sc_stock_moves', ['tenant_id' => $it['tenant_id'], 'item_id' => (int) $it['id'], 'change_qty' => $it['quantity'], 'balance' => $it['quantity'], 'reason' => 'opening', 'note' => 'Opening stock', 'user_id' => null, 'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))]);
    $seeded[] = 'ledger#' . $it['id'];
}
// accounts: a month of expenses, one donation and one bill to pay for each demo vendor (only vendors that have none yet)
$demoTenants = ['brightsmile', 'carehome', 'citygeneral', 'clearscan', 'diagnosticplus', 'docnow', 'evergreencare', 'greenvalley', 'heartcare', 'herbalcare', 'lakeside', 'lifeline', 'medequip', 'medicopharma', 'mindwell', 'motherhood', 'movewell', 'precisionlab', 'rapidambulance', 'renalcare', 'sunriseclinic', 'tinyclinic', 'visionplus'];
$acc = new \Selfcare\Accounts($db, new \Selfcare\Billing($db));
foreach ($demoTenants as $vi => $t) {
    if (($only !== null && !in_array($t, $only, true)) || !$db->val('SELECT COUNT(*) FROM sc_vendors WHERE tenant_id = ?', [$t]) || $db->val('SELECT COUNT(*) FROM sc_ledger WHERE tenant_id = ?', [$t])) {
        continue;
    }
    $type = (string) $db->val('SELECT type_slug FROM sc_vendors WHERE tenant_id = ?', [$t]);
    $special = match ($type) { 'ambulance' => ['Fuel and vehicle costs', 'Fuel for the fleet', 'Padma Oil', 310.0], 'pharmacy' => ['Medicines and consumables', 'Stock from the depot', 'Square Pharmaceuticals depot', 540.0], 'blood-bank' => ['Medical supplies', 'Blood bags and test kits', 'LabAid Supplies', 420.0], 'laboratory', 'diagnostic-center', 'imaging-center' => ['Medical supplies', 'Reagents and film', 'BioTech Supplies', 380.0], 'equipment-supplier' => ['Equipment and repairs', 'Spare parts', 'Parts Hub', 260.0], default => ['Medical supplies', 'Gloves, syringes and dressings', 'City Medical Store', 180.0] };
    $rows = [
        ['expense', 'Salaries and wages', 'Staff salaries', 'Payroll', 1200.0 + $vi * 15, 'Bank transfer', 'paid', -26],
        ['expense', 'Rent', 'Monthly rent', 'Landlord', 450.0 + $vi * 5, 'Bank transfer', 'paid', -24],
        ['expense', 'Electricity, water and gas', 'Utility bills', 'DESCO', 95.5, 'Cash', 'paid', -12],
        ['expense', 'Internet and phone', 'Broadband and phones', 'Link3', 30.0, 'Mobile banking', 'paid', -9],
        ['expense', $special[0], $special[1], $special[2], $special[3], 'Cash', 'paid', -5],
        ['expense', 'Cleaning and laundry', 'Weekly cleaning', 'CleanCo', 40.0, 'Cash', 'paid', -2],
        ['expense', $special[0], $special[1] . ' (invoice to pay)', $special[2], 150.0, 'Cash', 'due', -3],
        ['income', 'Donations and grants', 'Community donation', 'Well-wisher', 250.0, 'Cash', 'paid', -7],
    ];
    foreach ($rows as [$kind, $cat, $desc, $party, $amt, $meth, $st, $off]) {
        $d = $day($off);
        $db->insert('sc_ledger', ['tenant_id' => $t, 'kind' => $kind, 'code' => $acc->code($t, $kind), 'entry_date' => $d, 'category' => $cat, 'party' => $party, 'description' => $desc, 'amount' => $amt, 'method' => $meth, 'status' => $st, 'due_date' => $st === 'due' ? $day(7) : null, 'paid_at' => $st === 'paid' ? $d . ' 11:00:00' : null, 'created_at' => $now, 'updated_at' => $now]);
    }
    $seeded[] = "$t/ledger=" . count($rows);
}

// prescriptions: four sample prescriptions (three issued, one draft) for each demo vendor that writes them
$rxe = new \Selfcare\Rx($db);
$sets = [
    ['Fever and body ache for 2 days', 'Viral fever', [['Paracetamol 500 mg', '500 mg', 'Tablet', '1 tablet', '1+1+1 (three times daily)', 'After food', 3, 'days'], ['Oral rehydration salts (ORS)', '', 'Sachet', '1 sachet in 1 glass of water', 'When needed (PRN)', '', 3, 'days']], 'Rest, drink plenty of fluids. Come back if the fever lasts more than 3 days.'],
    ['Heartburn and acidity after meals', 'Gastritis', [['Omeprazole 20 mg', '20 mg', 'Capsule', '1 capsule', '1+0+0 (morning)', 'Empty stomach', 14, 'days'], ['Antacid suspension', '', 'Suspension', '10 ml', '1+1+1 (three times daily)', 'After food', 7, 'days']], 'Avoid spicy and oily food. Do not lie down right after eating.'],
    ['Seasonal allergy, sneezing and itchy eyes', 'Allergic rhinitis', [['Cetirizine 10 mg', '10 mg', 'Tablet', '1 tablet', '0+0+1 (night)', '', 7, 'days'], ['Normal saline nasal drops', '', 'Drops', '2 drops each nostril', '1+0+1 (twice daily)', '', 7, 'days']], 'Avoid dust and cold drinks.'],
    ['High blood pressure check, mild headache', 'Hypertension', [['Amlodipine 5 mg', '5 mg', 'Tablet', '1 tablet', '1+0+0 (morning)', '', 0, 'ongoing']], 'Reduce salt. Walk 30 minutes daily. Check blood pressure weekly.'],
];
foreach ($demoTenants as $vi => $t) {
    $type = (string) $db->val('SELECT type_slug FROM sc_vendors WHERE tenant_id = ?', [$t]);
    if (($only !== null && !in_array($t, $only, true)) || \Selfcare\Rx::forType($type) === null || $db->val('SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ?', [$t])) {
        continue;
    }
    $docs = $rxe->doctors($t);
    $pts = $rxe->patients($t, 8);
    if (!$docs || !$pts) {
        continue;
    }
    $doc0 = $docs[0];
    $db->update('doctors', ['reg_no' => 'BMDC-' . (10000 + $vi * 137)], 'id = ? AND tenant_id = ? AND reg_no IS NULL', [(int) $doc0['id'], $t]);
    $docs = $rxe->doctors($t);
    foreach ($sets as $i => [$compl, $diag, $lines, $advice]) {
        $pt = $pts[$i % count($pts)];
        $d = $docs[$i % count($docs)];
        $date = $day(-($i * 4 + 1));
        $id = $db->insert('sc_prescriptions', ['tenant_id' => $t, 'rx_no' => $rxe->code($t), 'rx_date' => $date, 'patient_id' => $pt['id'], 'patient_name' => trim($pt['first_name'] . ' ' . $pt['last_name']), 'patient_phone' => $pt['phone'], 'age' => \Selfcare\Rx::age($pt['dob']) ?: (string) (24 + $i * 9), 'sex' => [0 => 'Male', 1 => 'Female', 2 => 'Other'][(int) $pt['gender']] ?? null, 'weight' => 58 + $i * 6,
            'doctor_id' => $d['id'], 'doctor_name' => \Selfcare\Rx::doctorLabel($d), 'doctor_info' => \Selfcare\Rx::doctorInfo($d), 'vitals' => json_encode(['bp' => '120/80', 'pulse' => (string) (72 + $i), 'temp' => $i === 0 ? '101 F' : '98.4 F']), 'allergies' => $i === 2 ? 'Penicillin' : null,
            'complaints' => $compl, 'diagnosis' => $diag, 'advice' => $advice, 'followup_date' => $i === 3 ? null : $day(7 - $i * 4), 'status' => $i === 3 ? 'draft' : 'issued', 'issued_at' => $i === 3 ? null : $date . ' 11:00:00', 'issued_by' => null, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($lines as $n => [$drug, $str, $form, $dose, $freq, $timing, $dn, $unit]) {
            $db->insert('sc_prescription_items', ['tenant_id' => $t, 'prescription_id' => $id, 'line_no' => $n + 1, 'drug' => $drug, 'strength' => $str ?: null, 'form' => $form, 'route' => 'PO (by mouth)', 'dose' => $dose, 'freq' => $freq, 'timing' => $timing ?: null, 'dur_n' => $dn ?: null, 'dur_unit' => $unit]);
        }
        if ($i === 2) {
            $db->update('patients', ['allergies' => 'Penicillin'], 'id = ? AND tenant_id = ? AND allergies IS NULL', [(int) $pt['id'], $t]);
        }
    }
    $seeded[] = "$t/prescriptions=4";
}

// an opening cash balance that keeps the demo cash book above zero (only where the vendor has not set one)
foreach ($demoTenants as $t) {
    if (($only !== null && !in_array($t, $only, true)) || !$db->val('SELECT COUNT(*) FROM sc_ledger WHERE tenant_id = ?', [$t]) || $db->val("SELECT COUNT(*) FROM settings WHERE tenant_id = ? AND `key` = 'acc_opening'", [$t])) {
        continue;
    }
    $bal = $acc->balanceBefore($t, date('Y-m-d', strtotime('+1 day')));
    if ($bal < 2000) {
        (new \Selfcare\Billing($db))->setSetting($t, 'acc_opening', (string) ((int) ceil((2000 - $bal) / 1000) * 1000));
        $seeded[] = "$t/opening";
    }
}
echo $seeded ? 'seeded ' . implode(', ', $seeded) . "\n" : "nothing to seed\n";
