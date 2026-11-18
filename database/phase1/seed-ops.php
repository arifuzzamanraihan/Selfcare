<?php
// Selfcare original code. Sample work lists for the demo vendors (prescription orders, samples, blood units, trips ...).
//   php database/phase1/seed-ops.php [--only=medicopharma,lifeline]
// Safe to run twice: a module that already has rows for a vendor is skipped. Only the demo vendors are touched.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Ops'] as $f) {
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
$demo = ['pharmacy' => 'medicopharma', 'laboratory' => 'precisionlab', 'diagnostic-center' => 'diagnosticplus', 'blood-bank' => 'lifeline', 'ambulance' => 'rapidambulance'];
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
        $prefix = (string) (\Selfcare\Ops::all()[$module]['prefix'] ?? '');
        foreach ($rows as $r) {
            if ($prefix !== '' && empty($r['code'])) {
                $r['code'] = \Selfcare\Ops::code($db, $tenant, $module, $prefix);
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
echo $seeded ? 'seeded ' . implode(', ', $seeded) . "\n" : "nothing to seed\n";
