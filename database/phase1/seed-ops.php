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
            $db->insert('sc_records', $r + ['tenant_id' => $tenant, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]);
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
