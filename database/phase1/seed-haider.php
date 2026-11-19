<?php
// Selfcare original code. Sets up Haider Clinical Lab as a real clinic vendor with the laboratory suite.
//   php database/phase1/seed-haider.php --creds=/path/CREDENTIALS.txt [--slug=haider-lab] [--email=owner@example.com] [--name="Haider Clinical Lab"]
// Creates (once): the clinic vendor (PKR, Asia/Karachi), Main and Branch 1 to 4, departments, the roles named in the client spec,
// the Appendix A test catalogue (prices and ranges are SAMPLE values marked "pending client approval"), a default signatory, and lab settings.
// The owner password is generated and written only to the --creds file. Safe to run twice.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi', 'Lab', 'LabDocs'] as $f) {
    if (is_file(dirname(__DIR__, 2) . '/core/' . $f . '.php')) {
        require_once dirname(__DIR__, 2) . '/core/' . $f . '.php';
    }
}
use Selfcare\{App, Lab, Portal, Vendors};

$opt = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) {
        $opt[$m[1]] = $m[2];
    }
}
$slug = $opt['slug'] ?? 'haider-lab';
$name = $opt['name'] ?? 'Haider Clinical Lab';
$email = $opt['email'] ?? 'haiderlab09@gmail.com';
$app = new App(dirname(__DIR__, 2));
$db = $app->db;
$now = date('Y-m-d H:i:s');

// ---------------------------------------------------------------- vendor
if ((int) $db->val('SELECT COUNT(*) FROM tenants WHERE id = ?', [$slug]) === 0) {
    $c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pw = '';
    for ($i = 0; $i < 16; $i++) {
        $pw .= $c[random_int(0, strlen($c) - 1)];
    }
    $plan = $db->one("SELECT id FROM sc_plans WHERE is_active = 1 AND modules LIKE '%billing%' ORDER BY price DESC, id LIMIT 1");
    Vendors::create($app, ['slug' => $slug, 'type' => 'clinic', 'name' => $name, 'first_name' => 'Haider', 'last_name' => 'Clinical Lab', 'email' => $email, 'password' => $pw, 'city' => 'Karachi', 'phone' => '', 'currency' => 'PKR', 'country' => 'Pakistan', 'timezone' => 'Asia/Karachi', 'plan_id' => $plan ? (int) $plan['id'] : 0]);
    if (!empty($opt['creds'])) {
        file_put_contents($opt['creds'], sprintf("\nHAIDER CLINICAL LAB OWNER  slug=%s  login=%s  password=%s  (created %s)\n", $slug, $email, $pw, $now), FILE_APPEND | LOCK_EX);
        @chmod($opt['creds'], 0600);
    }
    echo "vendor created: $slug\n";
} else {
    echo "vendor exists: $slug\n";
}
$t = $slug;
$owner = (int) $db->val('SELECT id FROM users WHERE tenant_id = ? AND is_admin_default = 1 ORDER BY id LIMIT 1', [$t]);
$db->run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$owner]);
$S = static fn (string $k, string $v) => $app->site->set($t, $k, $v);
$S('tagline', 'Accurate reports. Trusted care.');
$S('about', 'Haider Clinical Lab is a clinical laboratory with a main branch and four more branches. We offer routine and specialised tests with home sample collection and secure online reports.');

$p = Portal::forUser($app, $owner);
$L = Lab::for($p); // creates Main branch, departments and the default signatory
$main = $L->mainId();
$db->run("UPDATE sc_branches SET name = 'Main Branch', code = 'MAIN' WHERE id = ?", [$main]);
foreach ([1, 2, 3, 4] as $i) {
    if (!(int) $db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ? AND code = ?', [$t, 'BR' . $i])) {
        $db->insert('sc_branches', ['tenant_id' => $t, 'code' => 'BR' . $i, 'name' => 'Branch ' . $i, 'is_main' => 0, 'active' => 1, 'sort_order' => $i, 'report_email' => null, 'created_at' => $now, 'updated_at' => $now]);
    }
}
$db->run("UPDATE sc_lab_signatories SET name = 'Authorised pathologist', title = 'Consultant Pathologist (name and registration pending client input)' WHERE tenant_id = ? AND is_default = 1", [$t]);
$L->setSetting('report_title', $name);
$L->setSetting('mr_prefix', 'HCL');
$L->setSetting('report_to', 'haiderlab09@gmail.com');
$L->setSetting('report_time', '21:00');
$L->setSetting('shift_start', '09:00');
$L->setSetting('home_charge', '300');

// ---------------------------------------------------------------- roles of the client spec
$all = Lab::PERM_LIST;
$std = ['manage_patients', 'manage_invoices', 'manage_reports'];
$full = ['add', 'edit', 'delete', 'print', 'export'];
$roles = [
    'Branch Admin' => ['Runs one branch: bookings, results, staff, stock and money of that branch.', array_merge($std, ['manage_accounts', 'manage_users', 'manage_appointments'], array_values(array_diff($all, ['lab_branches']))), $full],
    'Attendant' => ['Front helper: books tests, takes payments, prints slips.', array_merge(['manage_patients', 'manage_invoices', 'manage_appointments'], ['lab_booking', 'lab_samples']), ['add', 'edit', 'print']],
    'Sample Collector' => ['Collects samples at the lab or at home.', ['manage_appointments', 'lab_samples'], ['add', 'edit', 'print']],
];
foreach ($roles as $rn => [$desc, $perms, $acts]) {
    if ($app->access->roleId($rn, $t) === null) {
        $map = [];
        foreach ($perms as $pm) {
            $map[$pm] = array_values(array_intersect($acts, $app->access->permActions($pm)));
        }
        $app->access->createRole($t, $rn, $desc, $perms, $map);
        echo "role created: $rn\n";
    }
}

// ---------------------------------------------------------------- Appendix A catalogue (sample values, pending client approval)
$dept = static function (string $n) use ($db, $t): int {
    $id = (int) $db->val('SELECT id FROM sc_lab_departments WHERE tenant_id = ? AND name = ?', [$t, $n]);
    return $id ?: $db->insert('sc_lab_departments', ['tenant_id' => $t, 'name' => $n, 'sort_order' => 50, 'active' => 1]);
};
// code, name, department, specimen, sample price, turnaround hours, instructions, params [name, unit, sex, low, high, text]
$cat = [
    ['CBC', 'CBC (Hb)', 'Hematology', 'Blood', 600, 6, null, [['Hemoglobin', 'g/dL', 'M', 13.0, 17.0], ['Hemoglobin', 'g/dL', 'F', 12.0, 15.0], ['Total WBC count', 'x10^3/uL', 'A', 4.0, 11.0], ['RBC count', 'x10^6/uL', 'A', 4.2, 6.1], ['Platelet count', 'x10^3/uL', 'A', 150, 450], ['Hematocrit', '%', 'A', 38, 52]]],
    ['ESR', 'ESR', 'Hematology', 'Blood', 300, 4, null, [['ESR (1st hour)', 'mm/hr', 'A', 0, 20]]],
    ['LFT', 'LFT', 'Biochemistry', 'Serum', 1200, 8, null, [['ALT (SGPT)', 'U/L', 'A', 7, 56], ['AST (SGOT)', 'U/L', 'A', 10, 40], ['Alkaline phosphatase', 'U/L', 'A', 44, 147], ['Total bilirubin', 'mg/dL', 'A', 0.1, 1.2]]],
    ['ALT', 'ALT', 'Biochemistry', 'Serum', 450, 6, null, [['ALT (SGPT)', 'U/L', 'A', 7, 56]]],
    ['RFT', 'RFT', 'Biochemistry', 'Serum', 1000, 8, null, [['Urea', 'mg/dL', 'A', 15, 40], ['Creatinine', 'mg/dL', 'A', 0.6, 1.3], ['Uric acid', 'mg/dL', 'A', 3.5, 7.2]]],
    ['FBS', 'Fasting Blood Sugar', 'Biochemistry', 'Blood', 250, 4, 'Fast for 8 to 10 hours. Water is allowed.', [['Fasting glucose', 'mg/dL', 'A', 70, 100]]],
    ['BS', 'Blood Sugar', 'Biochemistry', 'Blood', 200, 4, null, [['Random glucose', 'mg/dL', 'A', 70, 140]]],
    ['LIPID', 'Lipid Profile', 'Biochemistry', 'Serum', 1400, 8, 'Fast for 10 to 12 hours.', [['Total cholesterol', 'mg/dL', 'A', 0, 200], ['Triglycerides', 'mg/dL', 'A', 0, 150], ['HDL cholesterol', 'mg/dL', 'A', 40, 90], ['LDL cholesterol', 'mg/dL', 'A', 0, 130]]],
    ['TSH', 'TSH', 'Hormones', 'Serum', 900, 12, null, [['TSH', 'uIU/mL', 'A', 0.4, 4.0]]],
    ['THY', 'Thyroid Profile', 'Hormones', 'Serum', 2200, 12, null, [['TSH', 'uIU/mL', 'A', 0.4, 4.0], ['Free T3', 'pg/mL', 'A', 2.3, 4.2], ['Free T4', 'ng/dL', 'A', 0.8, 1.8]]],
    ['VITD', 'Vitamin D', 'Biochemistry', 'Serum', 3000, 24, null, [['Vitamin D (25 OH)', 'ng/mL', 'A', 30, 100]]],
    ['WIDAL', 'Widal', 'Serology', 'Serum', 500, 6, null, [['Salmonella typhi O', '', 'A', null, null, 'Less than 1 in 80'], ['Salmonella typhi H', '', 'A', null, null, 'Less than 1 in 80']]],
    ['HBSAG', 'HBsAg', 'Serology', 'Serum', 700, 6, null, [['HBsAg', '', 'A', null, null, 'Non reactive']]],
    ['HCV', 'Anti-HCV', 'Serology', 'Serum', 900, 6, null, [['Anti-HCV', '', 'A', null, null, 'Non reactive']]],
    ['HIV', 'HIV', 'Serology', 'Serum', 900, 6, null, [['HIV 1 and 2', '', 'A', null, null, 'Non reactive']]],
    ['URE', 'Urine R/E', 'Urine / Stool', 'Urine', 250, 3, null, [['Pus cells', '/HPF', 'A', 0, 5], ['Red cells', '/HPF', 'A', 0, 2], ['Specific gravity', '', 'A', 1.005, 1.030]]],
    ['STOOL', 'Stool Tests', 'Urine / Stool', 'Stool', 300, 4, null, [['Ova and parasites', '', 'A', null, null, 'Not seen'], ['Occult blood', '', 'A', null, null, 'Negative']]],
    ['CRP', 'CRP', 'Immunology', 'Serum', 800, 6, null, [['C reactive protein', 'mg/L', 'A', 0, 6]]],
    ['CULT', 'Culture', 'Microbiology', 'Swab', 1500, 72, null, [['Organism isolated', '', 'A', null, null, 'No growth'], ['Sensitivity', '', 'A', null, null, '']]],
    ['BIOPSY', 'Biopsy', 'Histopathology', 'Tissue', 3500, 120, null, [['Gross description', '', 'A', null, null, ''], ['Microscopic findings', '', 'A', null, null, ''], ['Diagnosis', '', 'A', null, null, '']]],
    ['CA125', 'Tumour Marker CA 125', 'Tumour markers', 'Serum', 2500, 24, null, [['CA 125', 'U/mL', 'A', 0, 35]]],
    ['AFP', 'Tumour Marker AFP', 'Tumour markers', 'Serum', 2200, 24, null, [['AFP', 'ng/mL', 'A', 0, 8.5]]],
    ['AUTO', 'Autoimmune Tests', 'Autoimmune', 'Serum', 3500, 48, null, [['ANA', '', 'A', null, null, 'Negative']]],
    ['DENGUE', 'Dengue', 'Serology', 'Serum', 1800, 6, null, [['Dengue NS1', '', 'A', null, null, 'Negative'], ['Dengue IgM', '', 'A', null, null, 'Negative']]],
    ['MALARIA', 'Malaria', 'Parasitology', 'Blood', 600, 4, null, [['Malaria parasite', '', 'A', null, null, 'Not seen']]],
    ['HORM', 'Hormone Tests', 'Hormones', 'Serum', 2000, 24, null, [['Hormone value', '', 'A', null, null, '']]],
];
$n = 0;
foreach ($cat as [$code, $tn, $dn, $spec, $price, $tat, $ins, $params]) {
    if ((int) $db->val('SELECT COUNT(*) FROM sc_lab_tests WHERE tenant_id = ? AND code = ?', [$t, $code])) {
        continue;
    }
    $tid = $db->insert('sc_lab_tests', ['tenant_id' => $t, 'code' => $code, 'name' => $tn, 'dept_id' => $dept($dn), 'specimen' => $spec, 'price' => $price, 'tat_hours' => $tat, 'instructions' => $ins, 'is_panel' => count($params) > 2 ? 1 : 0, 'approved' => 0, 'sample_data' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $seen = [];
    foreach ($params as $i => $pr) {
        [$pn, $unit, $sex, $lo, $hi] = $pr;
        $txt = $pr[5] ?? null;
        if (!isset($seen[$pn])) {
            $seen[$pn] = $db->insert('sc_lab_test_params', ['tenant_id' => $t, 'test_id' => $tid, 'name' => $pn, 'unit' => $unit ?: null, 'kind' => 'number', 'sort_order' => $i + 1, 'active' => 1]);
        }
        $db->insert('sc_lab_ref_ranges', ['tenant_id' => $t, 'param_id' => $seen[$pn], 'sex' => $sex, 'age_min' => 0, 'age_max' => 150, 'low' => $lo, 'high' => $hi, 'text' => $txt ?: null]);
    }
    $n++;
}
echo "catalogue tests added: $n\n";
echo "done\n";
