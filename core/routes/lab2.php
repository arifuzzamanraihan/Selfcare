<?php
// Selfcare original routes: the laboratory suite inside the clinic portal (part 2).
// Catalogue, referrers and commission, finance and expenses, inventory, employees, attendance and salary, reports, history, branches, user access, settings, backups.
// Uses the helpers that lab.php put on $app->lab. Logic is in core/Lab.php, documents in core/LabDocs.php.
declare(strict_types=1);

use Selfcare\{Accounts, Billing, Crud, Documents, Lab, LabDocs, Portal};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, money, redirect, url};

['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$db = $app->db;
['boot' => $boot, 'post' => $post, 'page' => $page, 'badge' => $badge, 'tone' => $tone, 'label' => $label, 'tbl' => $tbl, 'pf' => $pf, 'hid' => $hid, 'tabs' => $tabs, 'dateIn' => $dateIn, 'periodBar' => $periodBar, 'send' => $send] = $app->lab;
$fld = static fn (string $n, array $f, string $v = ''): string => Crud::field($n, $f, $v);
$form = static fn (string $action, string $inner, string $submit, string $extra = '', string $confirm = ''): string => '<form class="d-form" method="post" action="' . e($action) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . ' autocomplete="off">' . csrf_field() . $inner . '<div class="d-form-actions">' . $extra . '<button class="d-btn d-btn-primary" type="submit">' . e($submit) . '</button></div></form>';
$num = static fn ($v): ?float => ($v === '' || $v === null) ? null : (float) $v;
$xlsxSend = static function (array $sheets, string $name): never {
    $b = LabDocs::xlsx($sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) . '.xlsx"');
    header('Content-Length: ' . strlen($b));
    echo $b;
    exit;
};
$app->lab['xlsxSend'] = $xlsxSend;

// ================================================================ catalogue
$catTabs = static fn (string $on): string => $tabs(['tests' => ['Tests', url('portal/lab/catalogue')], 'depts' => ['Departments', url('portal/lab/catalogue', ['tab' => 'depts'])]], $on);
$app->router->get('/portal/lab/catalogue', static function () use ($boot, $page, $btn, $db, $tbl, $badge, $catTabs, $fld, $form, $pf): void {
    [$p, $L] = $boot('catalogue');
    $tab = ($_GET['tab'] ?? '') === 'depts' ? 'depts' : 'tests';
    if ($tab === 'depts') {
        $rows = [];
        foreach ($db->all('SELECT d.*, (SELECT COUNT(*) FROM sc_lab_tests t WHERE t.dept_id = d.id AND t.active = 1) n FROM sc_lab_departments d WHERE d.tenant_id = ? ORDER BY d.sort_order, d.name', [$p->tenantId]) as $d) {
            $rows[] = [e((string) $d['name']), e((string) $d['n']), $d['active'] ? 'Active' : 'Off', $pf(url('portal/lab/catalogue/dept/' . $d['id'] . '/toggle'), $d['active'] ? 'Switch off' : 'Switch on')];
        }
        $page($p, $L, 'catalogue', 'Departments', $catTabs('depts') . '<section class="d-card">' . $form(url('portal/lab/catalogue/dept'), $fld('name', ['label' => 'New department', 'required' => true, 'max' => 100]), 'Add department') . '</section>' . $tbl([['Department'], ['Tests', 'R'], ['Status'], [' ']], $rows, 'No departments.'));
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $dp = (int) ($_GET['dept'] ?? 0);
    $w = 't.tenant_id = ?';
    $a = [$p->tenantId];
    if ($q !== '') {
        $w .= ' AND (t.name LIKE ? OR t.code LIKE ?)';
        array_push($a, "%$q%", "%$q%");
    }
    if ($dp) {
        $w .= ' AND t.dept_id = ?';
        $a[] = $dp;
    }
    $rows = [];
    foreach ($db->all("SELECT t.*, d.name dept, (SELECT COUNT(*) FROM sc_lab_test_params x WHERE x.test_id = t.id AND x.active = 1) np FROM sc_lab_tests t LEFT JOIN sc_lab_departments d ON d.id = t.dept_id WHERE $w ORDER BY t.active DESC, d.sort_order, t.name LIMIT 300", $a) as $t) {
        $flag = ($t['approved'] ? '' : $badge('Price pending approval', 'warn') . ' ') . ($t['sample_data'] ? $badge('Sample data', 'info') : '');
        $rows[] = ['<a href="' . e(url('portal/lab/catalogue/' . $t['id'])) . '">' . e((string) $t['code']) . '</a>', e((string) $t['name']) . ($t['is_panel'] ? ' <small>(panel)</small>' : ''), e((string) $t['dept']), e((string) $t['specimen']), e(money((float) $t['price'], \Selfcare\Currency::forTenant($L->t))), e($t['tat_hours'] ? $t['tat_hours'] . ' h' : ''), e((string) $t['np']), $t['active'] ? $flag : $badge('Inactive', 'mute')];
    }
    $opts = '<option value="0">All departments</option>';
    foreach ($db->all('SELECT id, name FROM sc_lab_departments WHERE tenant_id = ? AND active = 1 ORDER BY sort_order, name', [$p->tenantId]) as $d) {
        $opts .= '<option value="' . $d['id'] . '"' . ($dp === (int) $d['id'] ? ' selected' : '') . '>' . e($d['name']) . '</option>';
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/catalogue')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Name or code</span><input type="search" name="q" value="' . e($q) . '"></label><label class="d-field" style="margin:0"><span class="d-help">Department</span><select name="dept" onchange="this.form.submit()">' . $opts . '</select></label><button class="d-btn d-btn-primary" type="submit">Find</button></form>';
    $page($p, $L, 'catalogue', 'Test catalogue', $catTabs('tests') . $f . $tbl([['Code'], ['Test'], ['Department'], ['Specimen'], ['Price', 'R'], ['Turnaround'], ['Fields', 'R'], ['Notes']], $rows, 'No tests yet.', 'layers'), 'Price and ranges marked pending approval are sample values until the client confirms.', $btn('Add test', url('portal/lab/catalogue/new')));
});
$app->router->post('/portal/lab/catalogue/dept', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('catalogue');
    $post($p);
    $n = trim((string) ($_POST['name'] ?? ''));
    if ($n !== '') {
        $db->run('INSERT IGNORE INTO sc_lab_departments (tenant_id, name, sort_order) VALUES (?, ?, 99)', [$p->tenantId, mb_substr($n, 0, 100)]);
        flash('Department added.', 'ok');
    }
    redirect(url('portal/lab/catalogue', ['tab' => 'depts']));
});
$app->router->post('/portal/lab/catalogue/dept/{id}/toggle', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('catalogue');
    $post($p);
    $db->run('UPDATE sc_lab_departments SET active = 1 - active WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    redirect(url('portal/lab/catalogue', ['tab' => 'depts']));
});
$testForm = static function (Portal $p, Lab $L, ?array $t) use ($db, $fld): string {
    $dep = [];
    foreach ($db->all('SELECT id, name FROM sc_lab_departments WHERE tenant_id = ? AND active = 1 ORDER BY sort_order, name', [$p->tenantId]) as $d) {
        $dep[(string) $d['id']] = $d['name'];
    }
    return '<div class="d-form-grid">' . $fld('code', ['label' => 'Code', 'required' => true, 'max' => 30], (string) ($t['code'] ?? '')) . $fld('name', ['label' => 'Test name', 'required' => true, 'max' => 160], (string) ($t['name'] ?? ''))
        . $fld('dept_id', ['label' => 'Department', 'type' => 'select', 'options' => $dep, 'required' => true], (string) ($t['dept_id'] ?? '')) . $fld('specimen', ['label' => 'Specimen', 'type' => 'select', 'options' => array_combine(Lab::SPECIMENS, Lab::SPECIMENS), 'required' => true], (string) ($t['specimen'] ?? 'Blood'))
        . $fld('price', ['label' => 'Price', 'type' => 'number', 'step' => '0.01', 'required' => true], (string) ($t['price'] ?? '')) . $fld('tat_hours', ['label' => 'Turnaround (hours)', 'type' => 'number'], (string) ($t['tat_hours'] ?? ''))
        . $fld('instructions', ['label' => 'Patient instructions (fasting, timing)', 'type' => 'textarea', 'rows' => 2, 'max' => 500], (string) ($t['instructions'] ?? ''))
        . $fld('is_panel', ['label' => 'This is a panel (several result fields)', 'type' => 'toggle'], (string) ($t['is_panel'] ?? '0')) . $fld('approved', ['label' => 'Price and ranges approved by the client', 'type' => 'toggle'], (string) ($t['approved'] ?? '0')) . '</div>';
};
$app->router->get('/portal/lab/catalogue/new', static function () use ($boot, $page, $card, $testForm, $form): void {
    [$p, $L] = $boot('catalogue');
    $page($p, $L, 'catalogue', 'Add test', $card('', $form(url('portal/lab/catalogue/save'), $testForm($p, $L, null), 'Save and add result fields')));
});
$app->router->get('/portal/lab/catalogue/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $tbl, $badge, $testForm, $form, $fld, $pf): void {
    [$p, $L] = $boot('catalogue');
    $t = $db->one('SELECT * FROM sc_lab_tests WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($t === null) {
        $app->error(404, 'Test not found.');
    }
    $id = (int) $t['id'];
    $base = 'portal/lab/catalogue/' . $id;
    $h = $card('Test', $form(url('portal/lab/catalogue/save'), '<input type="hidden" name="id" value="' . $id . '">' . $testForm($p, $L, $t), 'Save test', $t['active'] ? '' : ''))
        . '<p>' . $pf(url($base . '/toggle'), $t['active'] ? 'Make inactive' : 'Make active again') . '</p>';
    $rows = [];
    foreach ($L->params($id) as $pa) {
        $rg = [];
        foreach ($db->all('SELECT * FROM sc_lab_ref_ranges WHERE param_id = ? ORDER BY sex, age_min', [(int) $pa['id']]) as $r) {
            $rg[] = e(($r['sex'] === 'A' ? 'Any sex' : ($r['sex'] === 'M' ? 'Male' : 'Female')) . ', age ' . rtrim(rtrim((string) $r['age_min'], '0'), '.') . ' to ' . rtrim(rtrim((string) $r['age_max'], '0'), '.') . ': ' . Lab::rangeText($r)) . ' ' . $pf(url($base . '/range/' . $r['id'] . '/delete'), 'Remove', 'd-btn-soft', 'Remove this range?');
        }
        $rows[] = [e((string) $pa['name']), e((string) $pa['unit']), implode('<br>', $rg) ?: '<span class="d-mute">No range</span>', '<form class="d-inline" method="post" action="' . e(url($base . '/range')) . '">' . csrf_field() . '<input type="hidden" name="param_id" value="' . (int) $pa['id'] . '"><select name="sex"><option value="A">Any</option><option value="M">Male</option><option value="F">Female</option></select> <input name="age_min" type="number" step="0.01" placeholder="Age from" style="width:5rem"> <input name="age_max" type="number" step="0.01" placeholder="to" style="width:4.5rem"> <input name="low" type="number" step="any" placeholder="Low" style="width:5rem"> <input name="high" type="number" step="any" placeholder="High" style="width:5rem"> <input name="text" placeholder="Or text" style="width:6rem"><button class="d-btn d-btn-soft" type="submit">Add range</button></form>', $pf(url($base . '/param/' . $pa['id'] . '/delete'), 'Remove field', 'd-btn-soft', 'Remove this result field?')];
    }
    $pf1 = $card('Result fields and reference ranges', $tbl([['Field'], ['Unit'], ['Ranges'], ['Add'], [' ']], $rows, 'No result fields yet. Add the first one below.') . $form(url($base . '/param'), '<div class="d-form-grid">' . $fld('name', ['label' => 'New result field', 'required' => true, 'max' => 120], '') . $fld('unit', ['label' => 'Unit', 'max' => 30], '') . '</div>', 'Add field'));
    $br = [];
    foreach ($L->branches(true) as $b) {
        $o = $db->one('SELECT price, available FROM sc_lab_branch_tests WHERE tenant_id = ? AND branch_id = ? AND test_id = ?', [$p->tenantId, (int) $b['id'], $id]);
        $br[] = [e((string) $b['name']), '<form class="d-inline" method="post" action="' . e(url($base . '/branch')) . '">' . csrf_field() . '<input type="hidden" name="branch_id" value="' . (int) $b['id'] . '"><input name="price" type="number" step="0.01" value="' . e((string) ($o['price'] ?? '')) . '" placeholder="Same as main price" style="width:9rem"> <label><input type="checkbox" name="available" value="1"' . (!$o || $o['available'] ? ' checked' : '') . '> Available</label> <button class="d-btn d-btn-soft" type="submit">Save</button></form>'];
    }
    $page($p, $L, 'catalogue', $t['name'], $h . $pf1 . $card('Branch prices and availability', $tbl([['Branch'], ['Price and availability']], $br, 'No branches.')), (string) $t['code']);
});
$app->router->post('/portal/lab/catalogue/save', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('catalogue');
    $post($p);
    $id = (int) ($_POST['id'] ?? 0);
    $d = ['code' => strtoupper(mb_substr(trim((string) ($_POST['code'] ?? '')), 0, 30)), 'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 160), 'dept_id' => (int) ($_POST['dept_id'] ?? 0) ?: null, 'specimen' => in_array($_POST['specimen'] ?? '', Lab::SPECIMENS, true) ? (string) $_POST['specimen'] : 'Blood', 'price' => max(0, round((float) ($_POST['price'] ?? 0), 2)), 'tat_hours' => (int) ($_POST['tat_hours'] ?? 0) ?: null, 'instructions' => mb_substr(trim((string) ($_POST['instructions'] ?? '')), 0, 500) ?: null, 'is_panel' => (int) ($_POST['is_panel'] ?? 0), 'approved' => (int) ($_POST['approved'] ?? 0), 'updated_at' => date('Y-m-d H:i:s')];
    if ($d['code'] === '' || $d['name'] === '') {
        flash('Enter the code and the name.', 'bad');
        redirect(url('portal/lab/catalogue/' . ($id ?: 'new')));
    }
    $dup = (int) $db->val('SELECT COUNT(*) FROM sc_lab_tests WHERE tenant_id = ? AND code = ? AND id <> ?', [$p->tenantId, $d['code'], $id]);
    if ($dup) {
        flash('That code is already used by another test.', 'bad');
        redirect(url('portal/lab/catalogue/' . ($id ?: 'new')));
    }
    if ($id) {
        $old = $db->one('SELECT * FROM sc_lab_tests WHERE id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        if ($old === null) {
            redirect(url('portal/lab/catalogue'));
        }
        [$b, $a] = Lab::diff($old, $d);
        $d['sample_data'] = $old['sample_data'] && !$d['approved'] ? 1 : 0;
        $db->update('sc_lab_tests', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        $L->log('test', $id, 'edited', $b, $a, null, $L->mainId());
    } else {
        $id = $db->insert('sc_lab_tests', $d + ['tenant_id' => $p->tenantId, 'active' => 1, 'sample_data' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        $L->log('test', $id, 'created', null, ['code' => $d['code']], null, $L->mainId());
    }
    flash('Test saved.', 'ok');
    redirect(url('portal/lab/catalogue/' . $id));
});
$app->router->post('/portal/lab/catalogue/{id}/{act}', static function (array $a) use ($app, $boot, $post, $db, $num): void {
    [$p, $L] = $boot('catalogue');
    $post($p);
    $id = (int) $a['id'];
    $t = $db->one('SELECT * FROM sc_lab_tests WHERE id = ? AND tenant_id = ?', [$id, $p->tenantId]);
    if ($t === null) {
        redirect(url('portal/lab/catalogue'));
    }
    $back = url('portal/lab/catalogue/' . $id);
    switch ((string) $a['act']) {
        case 'toggle':
            $db->run('UPDATE sc_lab_tests SET active = 1 - active WHERE id = ?', [$id]);
            $L->log('test', $id, $t['active'] ? 'deactivated' : 'restored', null, null, null, $L->mainId());
            break;
        case 'param':
            $n = trim((string) ($_POST['name'] ?? ''));
            if ($n !== '') {
                $db->insert('sc_lab_test_params', ['tenant_id' => $p->tenantId, 'test_id' => $id, 'name' => mb_substr($n, 0, 120), 'unit' => mb_substr(trim((string) ($_POST['unit'] ?? '')), 0, 30) ?: null, 'kind' => 'number', 'sort_order' => 1 + (int) $db->val('SELECT COUNT(*) FROM sc_lab_test_params WHERE test_id = ?', [$id]), 'active' => 1]);
            }
            break;
        case 'range':
            $pid = (int) ($_POST['param_id'] ?? 0);
            if ($db->val('SELECT COUNT(*) FROM sc_lab_test_params WHERE id = ? AND test_id = ?', [$pid, $id])) {
                $db->insert('sc_lab_ref_ranges', ['tenant_id' => $p->tenantId, 'param_id' => $pid, 'sex' => in_array($_POST['sex'] ?? '', ['A', 'M', 'F'], true) ? (string) $_POST['sex'] : 'A', 'age_min' => $num($_POST['age_min'] ?? '') ?? 0, 'age_max' => $num($_POST['age_max'] ?? '') ?? 150, 'low' => $num($_POST['low'] ?? ''), 'high' => $num($_POST['high'] ?? ''), 'text' => mb_substr(trim((string) ($_POST['text'] ?? '')), 0, 120) ?: null]);
            }
            break;
        case 'branch':
            $bid = (int) ($_POST['branch_id'] ?? 0);
            if ($L->branch($bid)) {
                $db->run('INSERT INTO sc_lab_branch_tests (tenant_id, branch_id, test_id, price, available) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE price = VALUES(price), available = VALUES(available)', [$p->tenantId, $bid, $id, $num($_POST['price'] ?? ''), (int) ($_POST['available'] ?? 0)]);
                $L->log('test', $id, 'edited', null, ['branch' => $bid, 'price' => $_POST['price'] ?? ''], 'Branch price', $bid);
            }
            break;
        default:
            if (preg_match('#^(param|range)$#', (string) $a['act'])) {
                break;
            }
    }
    redirect($back);
});
$app->router->post('/portal/lab/catalogue/{id}/{kind}/{sub}/delete', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('catalogue');
    $post($p);
    if ($a['kind'] === 'param') {
        $db->run('UPDATE sc_lab_test_params SET active = 0 WHERE id = ? AND test_id = ? AND tenant_id = ?', [(int) $a['sub'], (int) $a['id'], $p->tenantId]);
    } elseif ($a['kind'] === 'range') {
        $db->run('DELETE r FROM sc_lab_ref_ranges r JOIN sc_lab_test_params x ON x.id = r.param_id WHERE r.id = ? AND x.test_id = ? AND r.tenant_id = ?', [(int) $a['sub'], (int) $a['id'], $p->tenantId]);
    }
    redirect(url('portal/lab/catalogue/' . (int) $a['id']));
});

// ================================================================ referrers and commission rules
$sq = static fn (): string => trim((string) ($_GET['q'] ?? ''));
$searchBar = static fn (string $path, string $hint, array $keep = []): string => '<form class="d-queue-toolbar" method="get" action="' . e(url($path)) . '">' . implode('', array_map(static fn ($k, $v) => '<input type="hidden" name="' . e((string) $k) . '" value="' . e((string) $v) . '">', array_keys($keep), $keep)) . '<label class="d-field" style="margin:0;flex:1"><span class="d-help">' . e($hint) . '</span><input type="search" name="q" value="' . e(trim((string) ($_GET['q'] ?? ''))) . '" autocomplete="off"></label><button class="d-btn d-btn-primary" type="submit">Search</button></form>';
$rowFilter = static function (array $rows, string $q): array {
    if ($q === '') {
        return $rows;
    }
    return array_values(array_filter($rows, static fn (array $r): bool => stripos(html_entity_decode(strip_tags(implode(' ', array_map('strval', $r)))), $q) !== false));
};
$app->router->get('/portal/lab/referrers', static function () use ($boot, $page, $card, $btn, $db, $tbl, $badge, $tabs, $searchBar, $rowFilter, $sq): void {
    [$p, $L] = $boot('referrers');
    $kind = ($_GET['kind'] ?? 'doctor') === 'partner' ? 'partner' : 'doctor';
    $rows = [];
    foreach ($L->referrers($kind, false) as $r) {
        $T = $L->commissionTotals((int) $r['id']);
        $rows[] = ['<a href="' . e(url('portal/lab/referrers/' . $r['id'])) . '">' . e((string) $r['name']) . '</a>', e((string) ($r['speciality'] ?: $r['org'])), e((string) $r['phone']), e((string) $T['patients']), e((string) $T['tests']), e(money((float) $T['earned'])), e(money((float) $T['outstanding'])), $r['active'] ? 'Active' : $badge('Inactive')];
    }
    $rows = $rowFilter($rows, $sq());
    $page($p, $L, 'referrers', 'Referrers and partners', $tabs(['doctor' => ['Doctors', url('portal/lab/referrers')], 'partner' => ['Partner laboratories', url('portal/lab/referrers', ['kind' => 'partner'])]], $kind) . $searchBar('portal/lab/referrers', 'Search by name, phone or organisation', ['kind' => $kind]) . $tbl([['Name'], ['Speciality or organisation'], ['Phone'], ['Patients', 'R'], ['Tests', 'R'], ['Earned', 'R'], ['Outstanding', 'R'], ['Status']], $rows, 'No ' . ($kind === 'doctor' ? 'doctors' : 'partner laboratories') . ' yet.', 'users'), 'Clinic doctors appear here by themselves. Add outside doctors and partner labs.', $btn($kind === 'doctor' ? 'Add outside doctor' : 'Add partner lab', url('portal/lab/referrers/new', ['kind' => $kind])));
});
$app->router->get('/portal/lab/referrers/new', static function () use ($boot, $page, $card, $form, $fld): void {
    [$p, $L] = $boot('referrers');
    $kind = ($_GET['kind'] ?? 'doctor') === 'partner' ? 'partner' : 'doctor';
    $page($p, $L, 'referrers', $kind === 'doctor' ? 'Add outside doctor' : 'Add partner laboratory', $card('', $form(url('portal/lab/referrers/save'), '<input type="hidden" name="kind" value="' . $kind . '"><div class="d-form-grid">' . $fld('name', ['label' => 'Name', 'required' => true, 'max' => 160]) . $fld('org', ['label' => 'Organisation', 'max' => 160]) . $fld('speciality', ['label' => 'Speciality', 'max' => 120]) . $fld('contact_person', ['label' => 'Contact person', 'max' => 120]) . $fld('phone', ['label' => 'Phone', 'max' => 60]) . $fld('email', ['label' => 'Email', 'max' => 190]) . $fld('cnic', ['label' => 'CNIC', 'max' => 40]) . $fld('address', ['label' => 'Address', 'max' => 255, 'wide' => true]) . $fld('agreement', ['label' => 'Commission agreement note', 'type' => 'textarea', 'rows' => 2, 'max' => 500]) . '</div>', 'Save')));
});
$app->router->post('/portal/lab/referrers/save', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('referrers');
    $post($p);
    $id = (int) ($_POST['id'] ?? 0);
    $d = [];
    foreach (['name' => 160, 'org' => 160, 'speciality' => 120, 'contact_person' => 120, 'phone' => 60, 'email' => 190, 'cnic' => 40, 'address' => 255, 'agreement' => 500, 'notes' => 500] as $k => $m) {
        if (isset($_POST[$k])) {
            $d[$k] = mb_substr(trim((string) $_POST[$k]), 0, $m) ?: null;
        }
    }
    if (empty($d['name'])) {
        flash('Enter a name.', 'bad');
        redirect(url('portal/lab/referrers'));
    }
    $d['updated_at'] = date('Y-m-d H:i:s');
    if ($id) {
        $db->update('sc_referrers', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
    } else {
        $id = $db->insert('sc_referrers', $d + ['tenant_id' => $p->tenantId, 'kind' => ($_POST['kind'] ?? '') === 'partner' ? 'partner' : 'doctor', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $L->log('referrer', $id, 'created', null, ['name' => $d['name']], null, $L->mainId());
    }
    flash('Saved.', 'ok');
    redirect(url('portal/lab/referrers/' . $id));
});
$app->router->get('/portal/lab/referrers/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $tbl, $badge, $form, $fld, $pf, $btn): void {
    [$p, $L] = $boot('referrers');
    $r = $db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r === null) {
        $app->error(404, 'Not found.');
    }
    $id = (int) $r['id'];
    $info = $form(url('portal/lab/referrers/save'), '<input type="hidden" name="id" value="' . $id . '"><div class="d-form-grid">' . $fld('name', ['label' => 'Name', 'required' => true, 'max' => 160], (string) $r['name']) . $fld('org', ['label' => 'Organisation', 'max' => 160], (string) $r['org']) . $fld('speciality', ['label' => 'Speciality', 'max' => 120], (string) $r['speciality']) . $fld('contact_person', ['label' => 'Contact person', 'max' => 120], (string) $r['contact_person']) . $fld('phone', ['label' => 'Phone', 'max' => 60], (string) $r['phone']) . $fld('email', ['label' => 'Email', 'max' => 190], (string) $r['email']) . $fld('agreement', ['label' => 'Agreement note', 'type' => 'textarea', 'rows' => 2, 'max' => 500], (string) $r['agreement']) . '</div>', 'Save');
    $rules = [];
    $depts = [];
    foreach ($db->all('SELECT id, name FROM sc_lab_departments WHERE tenant_id = ? ORDER BY name', [$p->tenantId]) as $d) {
        $depts[(int) $d['id']] = $d['name'];
    }
    foreach ($db->all('SELECT c.*, t.name tn FROM sc_comm_rules c LEFT JOIN sc_lab_tests t ON t.id = c.test_id WHERE c.tenant_id = ? AND c.referrer_id = ? AND c.active = 1 ORDER BY c.scope, c.id', [$p->tenantId, $id]) as $c) {
        $rules[] = [e(['default' => 'Everything', 'dept' => 'Department: ' . ($depts[(int) $c['dept_id']] ?? ''), 'test' => 'Test: ' . ($c['tn'] ?? '')][$c['scope']] ?? ''), e($c['basis'] === 'patient' ? 'Per patient' : 'Per test'), e($c['vtype'] === 'pct' ? rtrim(rtrim((string) $c['value'], '0'), '.') . ' percent' : money((float) $c['value'], \Selfcare\Currency::forTenant($L->t)) . ' fixed'), $pf(url('portal/lab/referrers/' . $id . '/rule/' . $c['id'] . '/off'), 'Remove', 'd-btn-soft', 'Remove this commission rule?')];
    }
    $testOpts = [];
    foreach ($db->all('SELECT id, name FROM sc_lab_tests WHERE tenant_id = ? AND active = 1 ORDER BY name', [$p->tenantId]) as $t) {
        $testOpts[(string) $t['id']] = $t['name'];
    }
    $rf = $form(url('portal/lab/referrers/' . $id . '/rule'), '<div class="d-form-grid">' . $fld('scope', ['label' => 'Applies to', 'type' => 'select', 'options' => ['default' => 'Everything', 'dept' => 'One department', 'test' => 'One test'], 'required' => true], 'default') . $fld('dept_id', ['label' => 'Department (if one department)', 'type' => 'select', 'options' => array_map('strval', $depts)], '') . $fld('test_id', ['label' => 'Test (if one test)', 'type' => 'select', 'options' => $testOpts], '') . $fld('basis', ['label' => 'Counted', 'type' => 'select', 'options' => ['test' => 'Per test', 'patient' => 'Per patient (whole bill)'], 'required' => true], 'test') . $fld('vtype', ['label' => 'Type', 'type' => 'select', 'options' => ['pct' => 'Percentage', 'fixed' => 'Fixed amount'], 'required' => true], 'pct') . $fld('value', ['label' => 'Value', 'type' => 'number', 'step' => '0.01', 'required' => true], '') . '</div>', 'Add rule');
    $T = $L->commissionTotals($id);
    $stats = '<div class="d-stats">' . stat2('Patients referred', (string) $T['patients']) . stat2('Tests referred', (string) $T['tests']) . stat2('Earned', money((float) $T['earned'])) . stat2('Paid', money((float) $T['paid'])) . stat2('Outstanding', money((float) $T['outstanding'])) . '</div>';
    $page($p, $L, 'referrers', $r['name'], $card('Details', $info) . $card('Commission rules', $tbl([['Applies to'], ['Counted'], ['Value'], [' ']], $rules, 'No rule yet. No commission is earned until a rule exists.') . $rf) . $card('Totals', $stats . '<p>' . $btn('Statement and payments', url('portal/lab/commission/' . $id)) . ' ' . $pf(url('portal/lab/referrers/' . $id . '/active'), $r['active'] ? 'Deactivate' : 'Activate') . '</p>'), ucfirst((string) $r['kind']));
});
function stat2(string $l, string $v): string { return '<div class="d-stat"><span>' . e($l) . '</span><b>' . e($v) . '</b></div>'; }
$app->router->post('/portal/lab/referrers/{id}/{act}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('referrers');
    $post($p);
    $id = (int) $a['id'];
    $r = $db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ?', [$id, $p->tenantId]);
    if ($r === null) {
        redirect(url('portal/lab/referrers'));
    }
    if ($a['act'] === 'active') {
        $db->run('UPDATE sc_referrers SET active = 1 - active WHERE id = ?', [$id]);
        $L->log('referrer', $id, $r['active'] ? 'deactivated' : 'restored', null, null, null, $L->mainId());
    } elseif ($a['act'] === 'rule') {
        $scope = in_array($_POST['scope'] ?? '', ['default', 'dept', 'test'], true) ? (string) $_POST['scope'] : 'default';
        $v = round((float) ($_POST['value'] ?? 0), 4);
        $vt = ($_POST['vtype'] ?? '') === 'fixed' ? 'fixed' : 'pct';
        if ($v <= 0 || ($vt === 'pct' && $v > 100) || ($scope === 'dept' && !(int) ($_POST['dept_id'] ?? 0)) || ($scope === 'test' && !(int) ($_POST['test_id'] ?? 0))) {
            flash('Check the rule: choose the department or test, and enter a value above zero (a percentage cannot pass 100).', 'bad');
        } else {
            $db->insert('sc_comm_rules', ['tenant_id' => $p->tenantId, 'referrer_id' => $id, 'scope' => $scope, 'dept_id' => $scope === 'dept' ? (int) $_POST['dept_id'] : null, 'test_id' => $scope === 'test' ? (int) $_POST['test_id'] : null, 'basis' => ($_POST['basis'] ?? '') === 'patient' && $scope === 'default' ? 'patient' : 'test', 'vtype' => $vt, 'value' => $v, 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $L->log('referrer', $id, 'edited', null, ['rule' => $scope, 'value' => $v, 'type' => $vt], 'Commission rule added', $L->mainId());
            flash('Rule added. It applies to new orders and when an order changes.', 'ok');
        }
    }
    redirect(url('portal/lab/referrers/' . $id));
});
$app->router->post('/portal/lab/referrers/{id}/rule/{rid}/off', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('referrers');
    $post($p);
    $db->run('UPDATE sc_comm_rules SET active = 0 WHERE id = ? AND referrer_id = ? AND tenant_id = ?', [(int) $a['rid'], (int) $a['id'], $p->tenantId]);
    $L->log('referrer', (int) $a['id'], 'edited', null, ['rule' => 'removed'], null, $L->mainId());
    redirect(url('portal/lab/referrers/' . (int) $a['id']));
});

// commission statements and payments
$app->router->get('/portal/lab/commission', static function () use ($boot, $page, $db, $tbl, $periodBar, $btn): void {
    [$p, $L] = $boot('commission');
    [$f, $t, $bar] = $periodBar($L, 'portal/lab/commission');
    $rows = [];
    $tot = ['earned' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
    foreach ($L->referrers(null, false) as $r) {
        $T = $L->commissionTotals((int) $r['id'], $f, $t);
        $all = $L->commissionTotals((int) $r['id']);
        if (!$T['orders'] && !$T['earned'] && !$all['outstanding']) {
            continue;
        }
        $tot['earned'] += $T['earned'];
        $tot['paid'] += $T['paid'];
        $tot['outstanding'] += $all['outstanding'];
        $rows[] = ['<a href="' . e(url('portal/lab/commission/' . $r['id'], ['from' => $f, 'to' => $t, 'period' => 'custom'])) . '">' . e((string) $r['name']) . '</a>', e(ucfirst((string) $r['kind'])), e((string) $T['patients']), e((string) $T['tests']), e(money((float) $T['earned'])), e(money((float) $T['paid'])), e(money((float) $all['outstanding']))];
    }
    $rows[] = ['<b>Total</b>', '', '', '', '<b>' . e(money($tot['earned'])) . '</b>', '<b>' . e(money($tot['paid'])) . '</b>', '<b>' . e(money($tot['outstanding'])) . '</b>'];
    $page($p, $L, 'commission', 'Commission', $bar . $tbl([['Referrer'], ['Type'], ['Patients', 'R'], ['Tests', 'R'], ['Earned', 'R'], ['Paid', 'R'], ['Outstanding (all time)', 'R']], count($rows) > 1 ? $rows : [], 'No commission in this period.', 'card'), 'Earned already includes adjustments for refunds, discounts and cancellations.');
});
$app->router->get('/portal/lab/commission/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $tbl, $periodBar, $btn, $form, $fld, $pf): void {
    [$p, $L] = $boot('commission');
    $r = $db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r === null) {
        $app->error(404, 'Not found.');
    }
    [$f, $t, $bar] = $periodBar($L, 'portal/lab/commission/' . $r['id']);
    $T = $L->commissionTotals((int) $r['id'], $f, $t);
    $all = $L->commissionTotals((int) $r['id']);
    $rows = [];
    foreach ($db->all('SELECT c.*, o.order_no FROM sc_commissions c JOIN sc_lab_orders o ON o.id = c.order_id WHERE c.tenant_id = ? AND c.referrer_id = ? AND DATE(c.created_at) BETWEEN ? AND ? AND ' . $L->scope('c.branch_id') . ' ORDER BY c.id DESC LIMIT 200', [$p->tenantId, (int) $r['id'], $f, $t]) as $c) {
        $rows[] = [e(dt((string) $c['created_at'], 'd M H:i')), '<a href="' . e(url('portal/lab/order/' . $c['order_id'])) . '">' . e($c['order_no']) . '</a>', e(ucfirst((string) $c['kind'])), e((string) ($c['note'] ?: $c['rule'])), e(money((float) $c['base'])), e(money((float) $c['amount']))];
    }
    $pay = $form(url('portal/lab/commission/' . $r['id'] . '/pay'), '<div class="d-form-grid">' . $fld('amount', ['label' => 'Pay now (outstanding ' . money((float) $all['outstanding']) . ')', 'type' => 'number', 'step' => '0.01', 'required' => true], (string) max(0, $all['outstanding'])) . $fld('method', ['label' => 'Paid by', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . $fld('reference', ['label' => 'Reference', 'max' => 120]) . '</div>', 'Record payment', '', 'Record this commission payment? It goes into the cash book as an expense.');
    $stats = '<div class="d-stats">' . stat2('Patients referred', (string) $T['patients']) . stat2('Tests referred', (string) $T['tests']) . stat2('Earned in period', money((float) $T['earned'])) . stat2('Paid in period', money((float) $T['paid'])) . stat2('Outstanding', money((float) $all['outstanding'])) . '</div>';
    $page($p, $L, 'commission', $r['name'] . ' commission', $bar . $stats . $card('Earned and adjustments', $tbl([['When'], ['Order'], ['Type'], ['Basis'], ['Bill share', 'R'], ['Amount', 'R']], $rows, 'Nothing in this period.')) . ($p->canDo('lab_commission', 'add') && $p->writable() ? $card('Pay commission', $pay) : '') . '<p>' . $btn('Statement PDF', url('portal/lab/commission/' . $r['id'] . '/pdf', ['from' => $f, 'to' => $t])) . '</p>', ucfirst((string) $r['kind']));
});
$app->router->post('/portal/lab/commission/{id}/pay', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('commission');
    $post($p);
    $r = $db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    $amt = round((float) ($_POST['amount'] ?? 0), 2);
    if ($r === null || !$p->canDo('lab_commission', 'add')) {
        redirect(url('portal/lab/commission'));
    }
    $out = $L->commissionTotals((int) $r['id'])['outstanding'];
    if ($amt <= 0 || $amt > $out + 0.001) {
        flash('Enter an amount above zero and not more than the outstanding ' . money((float) $out) . '.', 'bad');
        redirect(url('portal/lab/commission/' . $r['id']));
    }
    $cat = $r['kind'] === 'partner' ? 'Partner laboratory commissions' : 'Doctor commissions';
    $L->postExpense($cat, $amt, date('Y-m-d'), (string) $r['name'], 'Commission paid', in_array($_POST['method'] ?? '', Lab::METHODS, true) ? (string) $_POST['method'] : 'Cash', 'paid', $L->writeBranch(0), 'commission', null, (int) $r['id'], null, trim((string) ($_POST['reference'] ?? '')) ?: null);
    $L->log('referrer', (int) $r['id'], 'edited', null, ['commission_paid' => $amt], null, $L->writeBranch(0));
    flash('Commission payment recorded in the cash book.', 'ok');
    redirect(url('portal/lab/commission/' . $r['id']));
});
$app->router->get('/portal/lab/commission/{id}/pdf', static function (array $a) use ($app, $boot, $send, $db, $dateIn): void {
    [$p, $L] = $boot('commission');
    $r = $db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r === null || !$p->canDo('lab_commission', 'print')) {
        $app->error(403, 'Not allowed.', 'No access');
    }
    $f = $dateIn((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-01');
    $t = $dateIn((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
    $D = new LabDocs($L);
    $send($D->commissionStatement($r, $f, $t, $D->mark('commission', (int) $r['id'], $L->mainId())), 'commission-' . $r['id'] . '-' . $t);
});

// ================================================================ finance
$finTabs = static fn (string $on): string => $tabs(['dash' => ['Dashboard', url('portal/lab/finance')], 'tx' => ['Transactions', url('portal/lab/finance/transactions')], 'exp' => ['Expenses', url('portal/lab/expenses')], 'cats' => ['Expense categories', url('portal/lab/expenses', ['tab' => 'cats'])]], $on);
$app->router->get('/portal/lab/finance', static function () use ($boot, $page, $card, $btn, $tbl, $periodBar, $finTabs): void {
    [$p, $L] = $boot('finance');
    [$f, $t, $bar] = $periodBar($L, 'portal/lab/finance');
    $F = $L->finance($f, $t);
    $q = ['from' => $f, 'to' => $t];
    $lnk = static fn (string $txt, string $kind) => '<a href="' . e(url('portal/lab/finance/transactions', $q + ['kind' => $kind])) . '">' . e($txt) . '</a>';
    $row = static fn (string $l, string $v, string $note = '') => [$l, '<b>' . e($v) . '</b>', e($note)];
    $rows = [
        $row('Test income', money($F['tests']), 'Money received on lab bills'), $row('Other bill income', money($F['bills']), 'Other invoices and receipts'), $row('Other income', money($F['other']), 'Entered in accounts'),
        ['<b>Total income</b>', '<b>' . e(money($F['income'])) . '</b>', ''],
        $row('Less refunds', '- ' . money($F['refunds']), 'Paid back to patients'),
    ];
    foreach ($F['expenses'] as $c => $v) {
        $rows[] = [$lnk('Less ' . $c, $c), '<b>- ' . e(money($v)) . '</b>', ''];
    }
    $rows[] = ['<b>Net income</b>', '<b>' . e(money($F['net'])) . '</b>', 'Income, less refunds, less expenses paid'];
    $stats = '<div class="d-stats"><div class="d-stat"><span>Income</span><b>' . e(money($F['income'])) . '</b></div><div class="d-stat"><span>Expenses</span><b>' . e(money($F['expense_total'])) . '</b></div><div class="d-stat"><span>Net income</span><b>' . e(money($F['net'])) . '</b></div><div class="d-stat"><span>Refunds</span><b>' . e(money($F['refunds'])) . '</b></div><div class="d-stat"><span>Discounts given</span><b>' . e(money($F['discounts'])) . '</b><small>shown apart, not in net</small></div></div>';
    $split = '<div class="d-stats"><div class="d-stat"><span>Doctor commission paid</span><b>' . e(money($F['doctor_comm'])) . '</b></div><div class="d-stat"><span>Partner commission paid</span><b>' . e(money($F['partner_comm'])) . '</b></div><div class="d-stat"><span>Salary paid</span><b>' . e(money($F['salary'])) . '</b></div><div class="d-stat"><span>Stock purchases</span><b>' . e(money($F['stock'])) . '</b></div><div class="d-stat"><span>Home collection charges</span><b>' . e(money($F['home'])) . '</b></div></div>';
    $pay = '<div class="d-stats"><div class="d-stat"><span>Commission still to pay</span><b>' . e(money($F['payable_commission'])) . '</b></div><div class="d-stat"><span>Salary still to pay</span><b>' . e(money($F['payable_salary'])) . '</b></div><div class="d-stat"><span>Bills to pay later</span><b>' . e(money($F['payable_bills'])) . '</b></div><div class="d-stat"><span>Patients owe</span><b>' . e(money($F['receivable'])) . '</b></div></div>';
    $exp = '<p class="d-tools" style="gap:8px;flex-wrap:wrap">' . $btn('Excel', url('portal/lab/reports/export', ['type' => 'finance', 'from' => $f, 'to' => $t])) . ' ' . $btn('PDF', url('portal/lab/reports/pdf', ['type' => 'finance', 'from' => $f, 'to' => $t]), 'd-btn-soft') . '</p>';
    $curNote = '<p class="d-help">' . e($L->current() > 0 ? 'Amounts are in the currency of ' . $L->branchName($L->current()) . ': ' . $L->reportCurrency() . '.' : 'Amounts are in ' . $L->reportCurrency() . ($L->mixedCurrencies() ? ', converted at the rates set under Bills and legal details. The table below keeps each currency apart.' : '.')) . '</p>';
    $perCur = '';
    if ($L->mixedCurrencies()) {
        $cr = [];
        foreach ($L->currencyTotals($f, $t) as $cc => $v) {
            $cr[] = [e($cc), e(money($v['income'], $cc)), e(money($v['refunds'], $cc)), e(money($v['expenses'], $cc)), '<b>' . e(money($v['net'], $cc)) . '</b>'];
        }
        $perCur = $card('Totals by currency (never mixed)', $tbl([['Currency'], ['Income', 'R'], ['Refunds', 'R'], ['Expenses', 'R'], ['Net', 'R']], $cr, 'No figures.', 'chart'));
    }
    $page($p, $L, 'finance', 'Finance', $finTabs('dash') . $bar . $curNote . $stats . $perCur . $card('How net income is worked out', $tbl([['Line'], ['Amount', 'R'], ['Note']], $rows, 'No figures.', 'chart')) . $card('Paid out to', $split) . $card('Still to pay and to collect', $pay . '<p class="d-help">Unpaid commission and salary are shown here. They count as an expense only when paid, so nothing is counted twice.</p>') . $exp, $f === $t ? dt($f) : dt($f) . ' to ' . dt($t));
});
$app->router->get('/portal/lab/finance/transactions', static function () use ($boot, $page, $tbl, $periodBar, $finTabs, $btn): void {
    [$p, $L] = $boot('finance');
    [$f, $t, $bar] = $periodBar($L, 'portal/lab/finance/transactions', ['kind' => (string) ($_GET['kind'] ?? '')]);
    $kind = (string) ($_GET['kind'] ?? '');
    $rows = [];
    $in = 0.0;
    $out = 0.0;
    foreach ($L->transactions($f, $t, $kind) as $m) {
        $m['dir'] === 'in' ? $in += (float) $m['amount'] : $out += (float) $m['amount'];
        $rows[] = [e(dt((string) $m['d'], 'd M')), e((string) $m['label']), e((string) $m['party']), e((string) $m['src']), e((string) $m['method']), $m['dir'] === 'in' ? e(money((float) $m['amount'])) : '', $m['dir'] === 'out' ? e(money((float) $m['amount'])) : ''];
    }
    $page($p, $L, 'finance', 'Transactions', $finTabs('tx') . $bar . $tbl([['Date'], ['What'], ['Party'], ['Type'], ['Method'], ['In', 'R'], ['Out', 'R']], $rows, 'No transactions in this period.', 'card') . '<p class="d-info">In ' . e(money($in)) . ' · Out ' . e(money($out)) . ($kind ? ' · filtered by ' . e($kind) . ' ' : '') . '</p>', 'Every figure on the dashboard comes from these lines.');
});
$app->router->get('/portal/lab/expenses', static function () use ($boot, $page, $card, $db, $tbl, $finTabs, $fld, $form, $pf, $badge): void {
    [$p, $L] = $boot('expenses');
    $p->need('lab_finance');
    if (($_GET['tab'] ?? '') === 'cats') {
        $c = $L->expenseCats();
        $rows = [];
        foreach ($L->expenseList(true) as $n) {
            $sys = in_array($n, Lab::SYSTEM_EXPENSE, true);
            $rows[] = [e($n), $sys ? 'Used by salary, commission or stock' : (in_array($n, $c['extra'], true) ? 'Added by you' : 'Standard'), in_array($n, $c['off'], true) ? $badge('Off') : 'On', $sys ? '' : $pf(url('portal/lab/expenses/cat/off'), in_array($n, $c['off'], true) ? 'Switch on' : 'Switch off', 'd-btn-soft', '', hid2('name', $n))];
        }
        $page($p, $L, 'finance', 'Expense categories', $finTabs('cats') . '<section class="d-card">' . $form(url('portal/lab/expenses/cat'), $fld('name', ['label' => 'New category', 'required' => true, 'max' => 60]), 'Add category') . '</section>' . $tbl([['Category'], ['Kind'], ['Status'], [' ']], $rows, 'None.'));
    }
    $opts = array_combine($L->expenseList(), $L->expenseList());
    $bo = [];
    foreach (array_intersect_key($L->branches(true), array_flip($L->allowed())) as $b) {
        $bo[(string) $b['id']] = $b['name'];
    }
    $nf = $p->canDo('lab_finance', 'add') && $p->writable() ? '<section class="d-card">' . $form(url('portal/lab/expenses/save'), '<div class="d-form-grid">' . $fld('category', ['label' => 'Category', 'type' => 'select', 'options' => $opts, 'required' => true]) . $fld('amount', ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('entry_date', ['label' => 'Date', 'type' => 'date', 'required' => true], date('Y-m-d')) . $fld('party', ['label' => 'Paid to', 'max' => 190]) . $fld('description', ['label' => 'Note', 'max' => 255]) . $fld('status', ['label' => 'Status', 'type' => 'select', 'options' => ['paid' => 'Paid now', 'due' => 'Pay later'], 'required' => true], 'paid') . $fld('method', ['label' => 'Method', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . $fld('branch_id', ['label' => 'Branch', 'type' => 'select', 'options' => $bo, 'required' => true], (string) $L->writeBranch(0)) . '</div>', 'Save expense') . '</section>' : '';
    $rows = [];
    foreach ($db->all("SELECT * FROM sc_ledger WHERE tenant_id = ? AND kind = 'expense' AND " . $L->scope('branch_id') . ' ORDER BY (deleted_at IS NOT NULL), id DESC LIMIT 100', [$p->tenantId]) as $x) {
        $act = '';
        if (!$x['deleted_at'] && $p->canDo('lab_finance', 'delete') && $p->writable()) {
            $act .= '<form class="d-inline" method="post" action="' . e(url('portal/lab/expenses/' . $x['id'] . '/void')) . '" data-confirm="Void this expense? It stays in the history.">' . csrf_field() . '<input name="reason" placeholder="Reason" required maxlength="190" style="width:9rem"><button class="d-btn d-btn-soft" type="submit">Void</button></form>';
        }
        if (!$x['deleted_at'] && $x['status'] === 'due' && $p->canDo('lab_finance', 'edit') && $p->writable()) {
            $act .= $pf(url('portal/lab/expenses/' . $x['id'] . '/pay'), 'Mark paid', 'd-btn-primary');
        }
        $rows[] = [e((string) $x['code']), e(dt((string) $x['entry_date'], 'd M')), e((string) $x['category']), e((string) $x['party']), e($L->branchName((int) $x['branch_id'])), e(money((float) $x['amount'], $x['currency'] ?? null)), $x['deleted_at'] ? $badge('Voided: ' . $x['void_reason'], 'bad') : ($x['status'] === 'due' ? $badge('Pay later', 'warn') : $badge('Paid', 'ok')), $act];
    }
    $page($p, $L, 'finance', 'Expenses', $finTabs('exp') . $nf . $tbl([['No'], ['Date'], ['Category'], ['Paid to'], ['Branch'], ['Amount', 'R'], ['Status'], [' ']], $rows, 'No expenses yet.', 'card'), 'The same cash book as Accounts. Salary, commission and stock purchases post here by themselves.');
});
function hid2(string $n, string $v): string { return '<input type="hidden" name="' . htmlspecialchars($n) . '" value="' . htmlspecialchars($v) . '">'; }
$app->router->post('/portal/lab/expenses/save', static function () use ($boot, $post): void {
    [$p, $L] = $boot('expenses');
    $post($p);
    $cat = (string) ($_POST['category'] ?? '');
    $amt = round((float) ($_POST['amount'] ?? 0), 2);
    $date = (string) ($_POST['entry_date'] ?? '');
    if (!$p->canDo('lab_finance', 'add') || !in_array($cat, $L->expenseList(), true) || $amt <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash('Choose a category, a date and an amount above zero.', 'bad');
        redirect(url('portal/lab/expenses'));
    }
    $b = $L->writeBranch((int) ($_POST['branch_id'] ?? 0));
    $id = $L->postExpense($cat, $amt, $date, trim((string) ($_POST['party'] ?? '')), trim((string) ($_POST['description'] ?? '')), in_array($_POST['method'] ?? '', Lab::METHODS, true) ? (string) $_POST['method'] : 'Cash', ($_POST['status'] ?? '') === 'due' ? 'due' : 'paid', $b);
    $L->log('expense', $id, 'created', null, ['category' => $cat, 'amount' => $amt], null, $b);
    flash('Expense saved.', 'ok');
    redirect(url('portal/lab/expenses'));
});
$app->router->post('/portal/lab/expenses/cat', static function () use ($boot, $post): void {
    [$p, $L] = $boot('expenses');
    $post($p);
    $n = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
    $c = $L->expenseCats();
    if ($n !== '' && !in_array($n, $L->expenseList(true), true)) {
        $c['extra'][] = $n;
        $L->setSetting('expense_cats', json_encode($c));
        flash('Category added.', 'ok');
    }
    redirect(url('portal/lab/expenses', ['tab' => 'cats']));
});
$app->router->post('/portal/lab/expenses/cat/off', static function () use ($boot, $post): void {
    [$p, $L] = $boot('expenses');
    $post($p);
    $n = (string) ($_POST['name'] ?? '');
    $c = $L->expenseCats();
    if ($n !== '' && !in_array($n, Lab::SYSTEM_EXPENSE, true)) {
        $c['off'] = in_array($n, $c['off'], true) ? array_values(array_diff($c['off'], [$n])) : array_merge($c['off'], [$n]);
        $L->setSetting('expense_cats', json_encode($c));
    }
    redirect(url('portal/lab/expenses', ['tab' => 'cats']));
});
$app->router->post('/portal/lab/expenses/{id}/{act}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('expenses');
    $post($p);
    $x = $db->one("SELECT * FROM sc_ledger WHERE id = ? AND tenant_id = ? AND kind = 'expense'", [(int) $a['id'], $p->tenantId]);
    if ($x === null || !$L->may((int) ($x['branch_id'] ?: $L->mainId()))) {
        redirect(url('portal/lab/expenses'));
    }
    if ($a['act'] === 'void' && $p->canDo('lab_finance', 'delete') && !$x['deleted_at']) {
        $r = trim((string) ($_POST['reason'] ?? ''));
        if ($r === '') {
            flash('Write the reason for voiding.', 'bad');
        } else {
            $db->run('UPDATE sc_ledger SET deleted_at = NOW(), void_reason = ? WHERE id = ?', [mb_substr($r, 0, 255), (int) $x['id']]);
            $L->log('expense', (int) $x['id'], 'voided', ['amount' => $x['amount']], null, $r, (int) ($x['branch_id'] ?: $L->mainId()));
            flash('Voided. It is kept in the history.', 'ok');
        }
    } elseif ($a['act'] === 'pay' && $p->canDo('lab_finance', 'edit') && $x['status'] === 'due') {
        $db->run("UPDATE sc_ledger SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ?", [(int) $x['id']]);
        $L->log('expense', (int) $x['id'], 'edited', ['status' => 'due'], ['status' => 'paid'], null, (int) ($x['branch_id'] ?: $L->mainId()));
        flash('Marked paid and added to the cash book.', 'ok');
    }
    redirect(url('portal/lab/expenses'));
});

// ================================================================ inventory (the clinic stock tables, per branch)
$invTabs = static fn (string $on): string => $tabs(['items' => ['Stock', url('portal/lab/inventory')], 'alerts' => ['Alerts', url('portal/lab/inventory', ['tab' => 'alerts'])], 'moves' => ['Movements', url('portal/lab/inventory', ['tab' => 'moves'])]], $on);
$stockMove = static function (Lab $L, array $it, float $delta, string $reason, string $note, ?float $cost = null, ?string $supplier = null) use ($db): void {
    $db->run('UPDATE sc_stock_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [$delta, (int) $it['id'], $L->t]);
    $bal = $db->val('SELECT quantity FROM sc_stock_items WHERE id = ?', [(int) $it['id']]);
    $db->insert('sc_stock_moves', ['tenant_id' => $L->t, 'item_id' => (int) $it['id'], 'change_qty' => $delta, 'balance' => $bal, 'reason' => $reason, 'note' => mb_substr($note, 0, 255) ?: null, 'user_id' => (int) $L->p->user['id'], 'created_at' => date('Y-m-d H:i:s'), 'branch_id' => (int) ($it['branch_id'] ?: $L->mainId()), 'unit_cost' => $cost, 'supplier' => $supplier]);
};
$app->router->get('/portal/lab/inventory', static function () use ($boot, $page, $card, $db, $tbl, $invTabs, $fld, $form, $badge, $btn, $searchBar, $sq): void {
    [$p, $L] = $boot('inventory');
    $tab = (string) ($_GET['tab'] ?? 'items');
    $w = 'tenant_id = ? AND ' . $L->scope('branch_id');
    if ($tab === 'moves') {
        $rows = [];
        foreach ($db->all("SELECT m.*, i.name FROM sc_stock_moves m JOIN sc_stock_items i ON i.id = m.item_id WHERE m.tenant_id = ? AND " . $L->scope('m.branch_id') . ' ORDER BY m.id DESC LIMIT 200', [$p->tenantId]) as $m) {
            $rows[] = [e(dt((string) $m['created_at'], 'd M H:i')), e((string) $m['name']), e(ucfirst(str_replace('_', ' ', (string) $m['reason']))), e(($m['change_qty'] > 0 ? '+' : '') . rtrim(rtrim((string) $m['change_qty'], '0'), '.')), e(rtrim(rtrim((string) $m['balance'], '0'), '.')), e($L->branchName((int) $m['branch_id'])), e((string) $m['note'])];
        }
        $page($p, $L, 'inventory', 'Stock movements', $invTabs('moves') . $tbl([['When'], ['Item'], ['Why'], ['Change', 'R'], ['Balance', 'R'], ['Branch'], ['Note']], $rows, 'No movements yet.'));
    }
    $alertDays = (int) $L->setting('expiry_days', '60');
    $rows = [];
    $value = 0.0;
    $iq = $sq();
    $iargs = [$p->tenantId];
    if ($iq !== '') {
        $w .= ' AND (name LIKE ? OR sku LIKE ? OR batch_no LIKE ?)';
        array_push($iargs, "%$iq%", "%$iq%", "%$iq%");
    }
    $items = $db->all("SELECT * FROM sc_stock_items WHERE $w ORDER BY name LIMIT 300", $iargs);
    foreach ($items as $it) {
        $low = (float) $it['reorder_level'] > 0 && (float) $it['quantity'] <= (float) $it['reorder_level'];
        $exp = $it['expiry_date'] && strtotime((string) $it['expiry_date']) <= strtotime('+' . $alertDays . ' days');
        if ($tab === 'alerts' && !$low && !$exp) {
            continue;
        }
        $value += \Selfcare\Currency::convert((float) $it['quantity'] * (float) $it['cost_price'], (string) ($it['currency'] ?: $L->branchCurrency((int) $it['branch_id'])), $L->reportCurrency());
        $rows[] = ['<a href="' . e(url('portal/lab/inventory/' . $it['id'])) . '">' . e((string) $it['name']) . '</a>', e((string) $it['batch_no']), e($L->branchName((int) $it['branch_id'])), e(rtrim(rtrim((string) $it['quantity'], '0'), '.') . ' ' . $it['unit']), e((string) $it['supplier']), e($it['expiry_date'] ? dt((string) $it['expiry_date']) : ''), e(money((float) $it['cost_price'], $it['currency'] ?? null)), ($low ? $badge('Low stock', 'bad') . ' ' : '') . ($exp ? $badge(strtotime((string) $it['expiry_date']) < time() ? 'Expired' : 'Near expiry', 'warn') : '')];
    }
    $bo = [];
    foreach (array_intersect_key($L->branches(true), array_flip($L->allowed())) as $b) {
        $bo[(string) $b['id']] = $b['name'];
    }
    $new = $tab === 'items' && $p->canDo('lab_inventory', 'add') && $p->writable() ? $card('Add item', $form(url('portal/lab/inventory/save'), '<div class="d-form-grid">' . $fld('name', ['label' => 'Item', 'required' => true, 'max' => 190]) . $fld('category', ['label' => 'Category (reagent, consumable)', 'max' => 80]) . $fld('unit', ['label' => 'Unit', 'max' => 20], 'pcs') . $fld('reorder_level', ['label' => 'Alert when stock is at or below', 'type' => 'number', 'step' => '0.01'], '0') . $fld('supplier', ['label' => 'Usual supplier', 'max' => 160]) . $fld('branch_id', ['label' => 'Branch', 'type' => 'select', 'options' => $bo, 'required' => true], (string) $L->writeBranch(0)) . '</div>', 'Add item')) : '';
    $page($p, $L, 'inventory', $tab === 'alerts' ? 'Stock alerts' : 'Lab inventory', $invTabs($tab === 'alerts' ? 'alerts' : 'items') . $searchBar('portal/lab/inventory', 'Search by item, code or batch', ['tab' => $tab]) . $new . $tbl([['Item'], ['Batch'], ['Branch'], ['In stock', 'R'], ['Supplier'], ['Expiry'], ['Cost', 'R'], ['Alert']], $rows, $tab === 'alerts' ? 'No alerts. Stock and expiry dates are fine.' : 'No stock items yet.', 'box') . '<p class="d-info">Stock value shown: <b>' . e(money($value)) . '</b></p>', 'Reagents and consumables, per branch. Purchases post to Accounts.');
});
$app->router->post('/portal/lab/inventory/save', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('inventory');
    $post($p);
    $n = trim((string) ($_POST['name'] ?? ''));
    if ($n === '' || !$p->canDo('lab_inventory', 'add')) {
        redirect(url('portal/lab/inventory'));
    }
    $b = $L->writeBranch((int) ($_POST['branch_id'] ?? 0));
    $id = $db->insert('sc_stock_items', Selfcare\Currency::stamp('sc_stock_items', ['tenant_id' => $p->tenantId, 'name' => mb_substr($n, 0, 190), 'category' => mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 80) ?: null, 'unit' => mb_substr(trim((string) ($_POST['unit'] ?? '')), 0, 20) ?: null, 'reorder_level' => max(0, (float) ($_POST['reorder_level'] ?? 0)), 'supplier' => mb_substr(trim((string) ($_POST['supplier'] ?? '')), 0, 160) ?: null, 'quantity' => 0, 'branch_id' => $b, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]));
    $L->log('stock', $id, 'created', null, ['name' => $n], null, $b);
    flash('Item added. Now record the stock in.', 'ok');
    redirect(url('portal/lab/inventory/' . $id));
});
$app->router->get('/portal/lab/inventory/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $tbl, $form, $fld): void {
    [$p, $L] = $boot('inventory');
    $it = $db->one('SELECT * FROM sc_stock_items WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($it === null || !$L->may((int) ($it['branch_id'] ?: $L->mainId()))) {
        $app->error(404, 'Item not found.');
    }
    $id = (int) $it['id'];
    $bo = [];
    foreach (array_intersect_key($L->branches(true), array_flip($L->allowed())) as $b) {
        if ((int) $b['id'] !== (int) ($it['branch_id'] ?: $L->mainId())) {
            $bo[(string) $b['id']] = $b['name'];
        }
    }
    $w = $p->writable();
    $recv = $w && $p->canDo('lab_inventory', 'add') ? $form(url('portal/lab/inventory/' . $id . '/in'), '<div class="d-form-grid">' . $fld('qty', ['label' => 'Quantity received', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('cost', ['label' => 'Cost per unit', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('supplier', ['label' => 'Supplier', 'max' => 160], (string) $it['supplier']) . $fld('batch_no', ['label' => 'Batch no', 'max' => 60], (string) $it['batch_no']) . $fld('expiry_date', ['label' => 'Expiry date', 'type' => 'date'], (string) $it['expiry_date']) . $fld('pay', ['label' => 'Payment', 'type' => 'select', 'options' => ['paid' => 'Paid now', 'due' => 'Pay the supplier later'], 'required' => true], 'paid') . '</div>', 'Receive stock') : '';
    $out = $w && $p->canDo('lab_inventory', 'edit') ? $form(url('portal/lab/inventory/' . $id . '/out'), '<div class="d-form-grid">' . $fld('qty', ['label' => 'Quantity', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('why', ['label' => 'What happened', 'type' => 'select', 'options' => ['issue' => 'Used in the lab (issue)', 'return' => 'Returned to the supplier', 'wastage' => 'Wastage, spoiled or expired'], 'required' => true], 'issue') . $fld('note', ['label' => 'Note', 'max' => 190]) . '</div>', 'Record') : '';
    $tr = $w && $bo && $p->canDo('lab_inventory', 'edit') ? $form(url('portal/lab/inventory/' . $id . '/transfer'), '<div class="d-form-grid">' . $fld('qty', ['label' => 'Quantity to send', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('to', ['label' => 'To branch', 'type' => 'select', 'options' => $bo, 'required' => true]) . '</div>', 'Transfer') : '';
    $rows = [];
    foreach ($db->all('SELECT * FROM sc_stock_moves WHERE item_id = ? ORDER BY id DESC LIMIT 50', [$id]) as $m) {
        $rows[] = [e(dt((string) $m['created_at'], 'd M H:i')), e(ucfirst(str_replace('_', ' ', (string) $m['reason']))), e(($m['change_qty'] > 0 ? '+' : '') . rtrim(rtrim((string) $m['change_qty'], '0'), '.')), e(rtrim(rtrim((string) $m['balance'], '0'), '.')), e((string) $m['note'])];
    }
    $page($p, $L, 'inventory', $it['name'], $card('In stock', '<p><b>' . e(rtrim(rtrim((string) $it['quantity'], '0'), '.') . ' ' . $it['unit']) . '</b> in ' . e($L->branchName((int) $it['branch_id'])) . ' · value ' . e(money((float) $it['quantity'] * (float) $it['cost_price'], $it['currency'] ?? null)) . '</p>') . ($recv ? $card('Stock in', $recv) : '') . ($out ? $card('Stock out, return or wastage', $out) : '') . ($tr ? $card('Transfer to another branch', $tr) : '') . $card('History', $tbl([['When'], ['Why'], ['Change', 'R'], ['Balance', 'R'], ['Note']], $rows, 'No movements.')));
});
$app->router->post('/portal/lab/inventory/{id}/{act}', static function (array $a) use ($boot, $post, $db, $stockMove): void {
    [$p, $L] = $boot('inventory');
    $post($p);
    $it = $db->one('SELECT * FROM sc_stock_items WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    $back = url('portal/lab/inventory/' . (int) $a['id']);
    if ($it === null || !$L->may((int) ($it['branch_id'] ?: $L->mainId()))) {
        redirect(url('portal/lab/inventory'));
    }
    $qty = round((float) ($_POST['qty'] ?? 0), 2);
    $b = (int) ($it['branch_id'] ?: $L->mainId());
    if ($qty <= 0) {
        flash('Enter a quantity above zero.', 'bad');
        redirect($back);
    }
    $db->begin();
    try {
        if ($a['act'] === 'in' && $p->canDo('lab_inventory', 'add')) {
            $cost = max(0, round((float) ($_POST['cost'] ?? 0), 2));
            $sup = mb_substr(trim((string) ($_POST['supplier'] ?? '')), 0, 160);
            $db->run('UPDATE sc_stock_items SET cost_price = ?, supplier = ?, batch_no = COALESCE(NULLIF(?, \'\'), batch_no), expiry_date = COALESCE(NULLIF(?, \'\'), expiry_date) WHERE id = ?', [$cost, $sup ?: null, trim((string) ($_POST['batch_no'] ?? '')), (string) ($_POST['expiry_date'] ?? ''), (int) $it['id']]);
            $stockMove($L, $it, $qty, 'purchase', 'Received ' . $qty . ' at ' . $cost, $cost, $sup);
            if ($cost * $qty > 0) {
                $L->postExpense('Stock purchases', $cost * $qty, date('Y-m-d'), $sup ?: 'Supplier', $it['name'] . ' x ' . $qty, 'Cash', ($_POST['pay'] ?? '') === 'due' ? 'due' : 'paid', $b, 'stock', (int) $it['id']);
            }
            $L->log('stock', (int) $it['id'], 'edited', null, ['received' => $qty], null, $b);
            flash('Stock received. The purchase was posted to Accounts.', 'ok');
        } elseif ($a['act'] === 'out' && $p->canDo('lab_inventory', 'edit')) {
            if ($qty > (float) $it['quantity']) {
                throw new \RuntimeException('Only ' . rtrim(rtrim((string) $it['quantity'], '0'), '.') . ' in stock.');
            }
            $why = in_array($_POST['why'] ?? '', ['issue', 'return', 'wastage'], true) ? (string) $_POST['why'] : 'issue';
            $stockMove($L, $it, -$qty, $why, (string) ($_POST['note'] ?? ''));
            $L->log('stock', (int) $it['id'], 'edited', null, [$why => $qty], null, $b);
            flash('Recorded.', 'ok');
        } elseif ($a['act'] === 'transfer' && $p->canDo('lab_inventory', 'edit')) {
            $to = (int) ($_POST['to'] ?? 0);
            if (!$L->may($to) || $to === $b || $qty > (float) $it['quantity']) {
                throw new \RuntimeException('Choose another branch you may open, and a quantity that is in stock.');
            }
            $dest = $db->one('SELECT * FROM sc_stock_items WHERE tenant_id = ? AND name = ? AND COALESCE(batch_no, \'\') = ? AND branch_id = ?', [$p->tenantId, $it['name'], (string) $it['batch_no'], $to]);
            if ($dest === null) {
                $did = $db->insert('sc_stock_items', Selfcare\Currency::stamp('sc_stock_items', ['tenant_id' => $p->tenantId, 'name' => $it['name'], 'sku' => $it['sku'], 'batch_no' => $it['batch_no'], 'unit' => $it['unit'], 'expiry_date' => $it['expiry_date'], 'quantity' => 0, 'reorder_level' => $it['reorder_level'], 'cost_price' => $it['cost_price'], 'category' => $it['category'], 'supplier' => $it['supplier'], 'branch_id' => $to, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]));
                $dest = $db->one('SELECT * FROM sc_stock_items WHERE id = ?', [$did]);
            }
            $stockMove($L, $it, -$qty, 'transfer_out', 'To ' . $L->branchName($to));
            $stockMove($L, $dest, $qty, 'transfer_in', 'From ' . $L->branchName($b));
            $L->log('stock', (int) $it['id'], 'edited', null, ['transfer' => $qty, 'to' => $L->branchName($to)], null, $b);
            flash('Transferred to ' . $L->branchName($to) . '.', 'ok');
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash($e instanceof \RuntimeException ? $e->getMessage() : 'Could not save.', 'bad');
    }
    redirect($back);
});

// ================================================================ employees (users of the clinic plus an HR profile)
$staffSql = "u.tenant_id = ? AND u.deleted_at IS NULL AND COALESCE(u.is_super_admin_default, 0) = 0 AND COALESCE(d.name, '') NOT IN ('Patient', 'Family Member', 'Referring Doctor', 'B2B Customer')";
$staffRows = static function (Lab $L, string $extra = '', array $args = []) use ($db, $staffSql): array {
    return $db->all("SELECT u.id, u.first_name, u.last_name, u.status, u.phone, u.email, d.name role, sp.staff_no, sp.designation, sp.joining_date, sp.branch_id, sp.salary, sp.salary_terms, sp.pin_hash, sp.cnic, sp.address FROM users u LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = 'App\\\\Models\\\\User' LEFT JOIN departments d ON d.id = m.role_id LEFT JOIN sc_staff_profiles sp ON sp.user_id = u.id WHERE $staffSql AND " . $L->scope('sp.branch_id') . " $extra ORDER BY u.first_name", array_merge([$L->t], $args));
};
$profileEnsure = static function (Lab $L, int $uid) use ($db): void {
    if ((int) $db->val('SELECT COUNT(*) FROM sc_staff_profiles WHERE user_id = ?', [$uid]) === 0) {
        $db->insert('sc_staff_profiles', ['user_id' => $uid, 'tenant_id' => $L->t, 'staff_no' => 'EMP-' . str_pad((string) $L->seq('lab_emp'), 4, '0', STR_PAD_LEFT), 'branch_id' => $L->mainId(), 'salary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }
};
$app->router->get('/portal/lab/employees', static function () use ($boot, $page, $btn, $db, $tbl, $badge, $staffRows, $profileEnsure, $staffSql, $searchBar, $rowFilter, $sq): void {
    [$p, $L] = $boot('employees');
    foreach ($db->all("SELECT u.id FROM users u LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = 'App\\\\Models\\\\User' LEFT JOIN departments d ON d.id = m.role_id WHERE $staffSql", [$p->tenantId]) as $u) {
        $profileEnsure($L, (int) $u['id']);
    }
    $rows = [];
    foreach ($staffRows($L) as $s) {
        $rows[] = [e((string) $s['staff_no']), '<a href="' . e(url('portal/lab/employees/' . $s['id'])) . '">' . e(trim($s['first_name'] . ' ' . $s['last_name'])) . '</a>', e((string) ($s['designation'] ?: $s['role'])), e($L->branchName((int) $s['branch_id'])), e(money((float) $s['salary'], $s['currency'] ?? null)), $s['pin_hash'] ? 'PIN set' : '<span class="d-mute">No PIN</span>', $s['status'] ? $badge('Active', 'ok') : $badge('Inactive')];
    }
    $rows = $rowFilter($rows, $sq());
    $page($p, $L, 'employees', 'Employees', $searchBar('portal/lab/employees', 'Search by staff number, name, designation or branch') . $tbl([['Staff no'], ['Name'], ['Designation'], ['Branch'], ['Salary', 'R'], ['Attendance PIN'], ['Status']], $rows, 'No staff yet.', 'user'), 'The staff accounts of this clinic with their HR details. New people are added under Staff accounts.', $btn('Add staff account', url('portal/staff/new')));
});
$app->router->get('/portal/lab/employees/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $tbl, $form, $fld, $staffRows, $profileEnsure, $pf): void {
    [$p, $L] = $boot('employees');
    $profileEnsure($L, (int) $a['id']);
    $s = $staffRows($L, ' AND u.id = ?', [(int) $a['id']])[0] ?? null;
    if ($s === null) {
        $app->error(404, 'Employee not found.');
    }
    $bo = [];
    foreach ($L->branches(true) as $b) {
        $bo[(string) $b['id']] = $b['name'];
    }
    $id = (int) $s['id'];
    $edit = $form(url('portal/lab/employees/' . $id . '/save'), '<div class="d-form-grid">' . $fld('designation', ['label' => 'Designation', 'max' => 80], (string) $s['designation']) . $fld('cnic', ['label' => 'CNIC', 'max' => 40], (string) $s['cnic']) . $fld('joining_date', ['label' => 'Joining date', 'type' => 'date'], (string) $s['joining_date']) . $fld('salary', ['label' => 'Monthly salary', 'type' => 'number', 'step' => '0.01'], (string) $s['salary']) . $fld('salary_terms', ['label' => 'Salary terms (overtime, allowances)', 'max' => 255, 'wide' => true], (string) $s['salary_terms']) . $fld('address', ['label' => 'Address', 'max' => 255, 'wide' => true], (string) $s['address']) . '</div>', 'Save profile');
    $tr = $form(url('portal/lab/employees/' . $id . '/transfer'), '<div class="d-form-grid">' . $fld('branch_id', ['label' => 'Move to branch', 'type' => 'select', 'options' => $bo, 'required' => true], (string) ($s['branch_id'] ?: $L->mainId())) . $fld('reason', ['label' => 'Reason', 'required' => true, 'max' => 190]) . '</div>', 'Transfer');
    $pin = $form(url('portal/lab/employees/' . $id . '/pin'), '<div class="d-form-grid">' . $fld('pin', ['label' => 'New attendance PIN (4 to 8 digits)', 'required' => true, 'max' => 8]) . '</div><p class="d-help">The PIN is stored hashed. Nobody can read it back.</p>', $s['pin_hash'] ? 'Change PIN' : 'Set PIN');
    $off = $form(url('portal/lab/employees/' . $id . '/active'), $fld('reason', ['label' => $s['status'] ? 'Reason for deactivating' : 'Reason for reactivating', 'required' => true, 'max' => 190]), $s['status'] ? 'Deactivate' : 'Reactivate', '', $s['status'] ? 'Deactivate this employee? They can no longer sign in. Their records stay.' : '');
    $hist = [];
    foreach ($db->all("SELECT h.*, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) un FROM sc_record_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.tenant_id = ? AND h.entity = 'employee' AND h.entity_id = ? ORDER BY h.id DESC LIMIT 20", [$p->tenantId, $id]) as $h) {
        $hist[] = [e(dt((string) $h['created_at'], 'd M Y H:i')), e((string) $h['action']), e((string) $h['un']), e((string) $h['reason'])];
    }
    $page($p, $L, 'employees', trim($s['first_name'] . ' ' . $s['last_name']), $card('HR profile', '<p>Staff no <b>' . e((string) $s['staff_no']) . '</b> · Role ' . e((string) $s['role']) . ' · Branch ' . e($L->branchName((int) $s['branch_id'])) . '</p>' . $edit) . $card('Transfer to another branch', $tr) . $card('Attendance PIN', $pin) . ($p->canDo('lab_staff', 'delete') ? $card($s['status'] ? 'Deactivate' : 'Reactivate', $off) : '') . $card('History', $tbl([['When'], ['What'], ['Who'], ['Reason']], $hist, 'No changes yet.')), (string) $s['staff_no']);
});
$app->router->post('/portal/lab/employees/{id}/{act}', static function (array $a) use ($boot, $post, $db, $profileEnsure, $staffRows): void {
    [$p, $L] = $boot('employees');
    $post($p);
    $id = (int) $a['id'];
    $profileEnsure($L, $id);
    $s = $staffRows($L, ' AND u.id = ?', [$id])[0] ?? null;
    $back = url('portal/lab/employees/' . $id);
    if ($s === null || !$p->canDo('lab_staff', 'edit')) {
        redirect(url('portal/lab/employees'));
    }
    $b = (int) ($s['branch_id'] ?: $L->mainId());
    switch ((string) $a['act']) {
        case 'save':
            $d = ['designation' => mb_substr(trim((string) ($_POST['designation'] ?? '')), 0, 80) ?: null, 'cnic' => mb_substr(trim((string) ($_POST['cnic'] ?? '')), 0, 40) ?: null, 'joining_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['joining_date'] ?? '')) ? $_POST['joining_date'] : null, 'salary' => max(0, round((float) ($_POST['salary'] ?? 0), 2)), 'salary_terms' => mb_substr(trim((string) ($_POST['salary_terms'] ?? '')), 0, 255) ?: null, 'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255) ?: null, 'updated_at' => date('Y-m-d H:i:s')];
            [$bf, $af] = Lab::diff($s, $d);
            $db->update('sc_staff_profiles', $d, 'user_id = ?', [$id]);
            $L->log('employee', $id, 'edited', $bf, $af, null, $b);
            flash('Profile saved.', 'ok');
            break;
        case 'transfer':
            $to = (int) ($_POST['branch_id'] ?? 0);
            $r = trim((string) ($_POST['reason'] ?? ''));
            if (!$L->branch($to) || $r === '') {
                flash('Choose a branch and write the reason.', 'bad');
                break;
            }
            $db->run('UPDATE sc_staff_profiles SET branch_id = ?, updated_at = NOW() WHERE user_id = ?', [$to, $id]);
            $db->run('DELETE FROM sc_user_branches WHERE tenant_id = ? AND user_id = ? AND branch_id <> 0', [$p->tenantId, $id]);
            $db->run('INSERT IGNORE INTO sc_user_branches (tenant_id, user_id, branch_id) VALUES (?, ?, ?)', [$p->tenantId, $id, $to]);
            $L->log('employee', $id, 'edited', ['branch' => $L->branchName($b)], ['branch' => $L->branchName($to)], $r, $to);
            flash('Transferred to ' . $L->branchName($to) . '.', 'ok');
            break;
        case 'pin':
            $pin = (string) ($_POST['pin'] ?? '');
            if (!preg_match('/^\d{4,8}$/', $pin)) {
                flash('The PIN must be 4 to 8 digits.', 'bad');
                break;
            }
            $db->run('UPDATE sc_staff_profiles SET pin_hash = ? WHERE user_id = ?', [password_hash($pin, PASSWORD_BCRYPT), $id]);
            $L->log('employee', $id, 'edited', null, ['pin' => 'changed'], null, $b);
            flash('PIN saved (hashed).', 'ok');
            break;
        case 'active':
            $r = trim((string) ($_POST['reason'] ?? ''));
            if ($r === '' || !$p->canDo('lab_staff', 'delete') || $id === (int) $p->user['id']) {
                flash('Write the reason. You cannot deactivate yourself.', 'bad');
                break;
            }
            $db->run('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [$s['status'] ? 0 : 1, $id, $p->tenantId]);
            $L->log('employee', $id, $s['status'] ? 'deactivated' : 'restored', null, null, $r, $b);
            flash($s['status'] ? 'Deactivated. Records stay.' : 'Reactivated.', 'ok');
            break;
    }
    redirect($back);
});

// ================================================================ attendance
$attTabs = static fn (string $on): string => $tabs(['day' => ['Daily register', url('portal/lab/attendance')], 'month' => ['Monthly summary', url('portal/lab/attendance', ['view' => 'month'])], 'kiosk' => ['PIN clock in', url('portal/lab/attendance/kiosk')]], $on);
$app->router->get('/portal/lab/attendance', static function () use ($boot, $page, $card, $db, $tbl, $badge, $attTabs, $staffRows, $fld, $dateIn): void {
    [$p, $L] = $boot('attendance');
    if (($_GET['view'] ?? '') === 'month') {
        $m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
        $rows = [];
        foreach ($staffRows($L) as $s) {
            $c = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];
            foreach ($db->all("SELECT status, COUNT(*) n FROM sc_attendance WHERE tenant_id = ? AND user_id = ? AND DATE_FORMAT(att_date, '%Y-%m') = ? GROUP BY status", [$p->tenantId, (int) $s['id'], $m]) as $r) {
                $c[$r['status']] = (int) $r['n'];
            }
            $rows[] = [e(trim($s['first_name'] . ' ' . $s['last_name'])), e($L->branchName((int) $s['branch_id'])), e((string) $c['present']), e((string) $c['late']), e((string) $c['absent']), e((string) $c['leave'])];
        }
        $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/attendance')) . '"><input type="hidden" name="view" value="month"><label class="d-field" style="margin:0"><span class="d-help">Month</span><input type="month" name="month" value="' . e($m) . '" onchange="this.form.submit()"></label></form>';
        $page($p, $L, 'attendance', 'Monthly attendance', $attTabs('month') . $f . $tbl([['Employee'], ['Branch'], ['Present', 'R'], ['Late', 'R'], ['Absent', 'R'], ['Leave', 'R']], $rows, 'No staff.'));
    }
    $d = $dateIn((string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : date('Y-m-d');
    $rows = [];
    $cnt = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'none' => 0];
    foreach ($staffRows($L, ' AND u.status = 1') as $s) {
        $r = $db->one('SELECT * FROM sc_attendance WHERE tenant_id = ? AND user_id = ? AND att_date = ?', [$p->tenantId, (int) $s['id'], $d]);
        $r ? $cnt[$r['status']]++ : $cnt['none']++;
        $mark = '';
        if ($p->canDo('lab_attendance', 'edit') && $p->writable()) {
            $mark = '<form class="d-inline" method="post" action="' . e(url('portal/lab/attendance/mark')) . '">' . csrf_field() . '<input type="hidden" name="user_id" value="' . (int) $s['id'] . '"><input type="hidden" name="date" value="' . e($d) . '"><select name="status"><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="leave">Leave</option></select> <input name="reason" placeholder="Reason for the change" ' . ($r ? 'required ' : '') . 'maxlength="190" style="width:11rem"><button class="d-btn d-btn-soft" type="submit">Save</button></form>';
        }
        $rows[] = [e(trim($s['first_name'] . ' ' . $s['last_name'])), e($L->branchName((int) $s['branch_id'])), $r ? $badge(ucfirst((string) $r['status']), $r['status'] === 'present' ? 'ok' : ($r['status'] === 'late' ? 'warn' : 'bad')) : '<span class="d-mute">Not marked</span>', e($r && $r['check_in'] ? dt((string) $r['check_in'], 'H:i') : ''), e($r && $r['check_out'] ? dt((string) $r['check_out'], 'H:i') : ''), e($r ? (string) $r['note'] : ''), $mark];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/attendance')) . '"><label class="d-field" style="margin:0"><span class="d-help">Date</span><input type="date" name="date" value="' . e($d) . '" onchange="this.form.submit()"></label></form>';
    $sum = '<p class="d-info">Present ' . $cnt['present'] . ' · Late ' . $cnt['late'] . ' · Absent ' . $cnt['absent'] . ' · Leave ' . $cnt['leave'] . ' · Not marked ' . $cnt['none'] . '</p>';
    $page($p, $L, 'attendance', 'Attendance', $attTabs('day') . $f . $sum . $tbl([['Employee'], ['Branch'], ['Status'], ['In'], ['Out'], ['Note'], ['Correct']], $rows, 'No staff.', 'clock'), 'Staff clock in with their own PIN. Any change made here needs a reason and is kept in the history.');
});
$app->router->post('/portal/lab/attendance/mark', static function () use ($boot, $post, $db, $dateIn, $staffRows): void {
    [$p, $L] = $boot('attendance');
    $post($p);
    $uid = (int) ($_POST['user_id'] ?? 0);
    $d = (string) ($_POST['date'] ?? '');
    $st = (string) ($_POST['status'] ?? '');
    $s = $staffRows($L, ' AND u.id = ?', [$uid])[0] ?? null;
    if ($s === null || !$dateIn($d) || !in_array($st, ['present', 'late', 'absent', 'leave'], true) || !$p->canDo('lab_attendance', 'edit') || $d > date('Y-m-d')) {
        flash('Choose a status for a past or current day.', 'bad');
        redirect(url('portal/lab/attendance'));
    }
    $old = $db->one('SELECT * FROM sc_attendance WHERE tenant_id = ? AND user_id = ? AND att_date = ?', [$p->tenantId, $uid, $d]);
    $r = trim((string) ($_POST['reason'] ?? ''));
    if ($old && $r === '') {
        flash('A correction needs a reason.', 'bad');
        redirect(url('portal/lab/attendance', ['date' => $d]));
    }
    $b = (int) ($s['branch_id'] ?: $L->mainId());
    if ($old) {
        $db->run('UPDATE sc_attendance SET status = ?, note = ?, source = ? WHERE id = ?', [$st, mb_substr($r, 0, 255), 'manual', (int) $old['id']]);
        $L->log('attendance', (int) $old['id'], 'edited', ['status' => $old['status']], ['status' => $st], $r, $b);
    } else {
        $id = $db->insert('sc_attendance', ['tenant_id' => $p->tenantId, 'branch_id' => $b, 'user_id' => $uid, 'att_date' => $d, 'status' => $st, 'note' => mb_substr($r, 0, 255) ?: null, 'source' => 'manual']);
        $L->log('attendance', $id, 'created', null, ['status' => $st], $r ?: null, $b);
    }
    flash('Attendance saved.', 'ok');
    redirect(url('portal/lab/attendance', ['date' => $d]));
});
$app->router->get('/portal/lab/attendance/kiosk', static function () use ($boot, $page, $card, $attTabs, $fld): void {
    [$p, $L] = $boot('attendance');
    $h = '<form class="d-form" method="post" action="' . e(url('portal/lab/attendance/kiosk')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">' . $fld('staff_no', ['label' => 'Staff number (for example EMP-0004)', 'required' => true, 'max' => 30]) . '<div class="d-field"><label><span>PIN <span class="d-req">*</span></span><input type="password" name="pin" inputmode="numeric" maxlength="8" required autocomplete="off"></label></div></div><p class="d-tools" style="gap:8px"><button class="d-btn d-btn-primary" type="submit" name="act" value="in">Clock in</button><button class="d-btn d-btn-soft" type="submit" name="act" value="out">Clock out</button></p></form>';
    $page($p, $L, 'attendance', 'PIN clock in', $attTabs('kiosk') . $card('Staff clock in or out', $h . '<p class="d-help">Each person uses their own PIN. One clock in and one clock out per day. Too many wrong PINs lock the screen for ten minutes.</p>'));
});
$app->router->post('/portal/lab/attendance/kiosk', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('attendance');
    $post($p);
    $k = 'lab_pin_try';
    $tries = array_filter((array) ($_SESSION[$k] ?? []), static fn ($t) => $t > time() - 600);
    if (count($tries) >= 6) {
        flash('Too many wrong PINs. Try again in ten minutes.', 'bad');
        redirect(url('portal/lab/attendance/kiosk'));
    }
    $sp = $db->one('SELECT sp.*, u.first_name, u.status FROM sc_staff_profiles sp JOIN users u ON u.id = sp.user_id WHERE sp.tenant_id = ? AND sp.staff_no = ?', [$p->tenantId, strtoupper(trim((string) ($_POST['staff_no'] ?? '')))]);
    if ($sp === null || !$sp['status'] || !$sp['pin_hash'] || !password_verify((string) ($_POST['pin'] ?? ''), (string) $sp['pin_hash']) || !$L->may((int) ($sp['branch_id'] ?: $L->mainId()))) {
        $tries[] = time();
        $_SESSION[$k] = $tries;
        flash('Staff number or PIN is not right.', 'bad');
        redirect(url('portal/lab/attendance/kiosk'));
    }
    $_SESSION[$k] = [];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $row = $db->one('SELECT * FROM sc_attendance WHERE tenant_id = ? AND user_id = ? AND att_date = ?', [$p->tenantId, (int) $sp['user_id'], $today]);
    $b = (int) ($sp['branch_id'] ?: $L->mainId());
    if (($_POST['act'] ?? '') === 'out') {
        if ($row === null || !$row['check_in']) {
            flash('Clock in first.', 'bad');
        } elseif ($row['check_out']) {
            flash('Already clocked out today.', 'bad');
        } else {
            $db->run('UPDATE sc_attendance SET check_out = ? WHERE id = ?', [$now, (int) $row['id']]);
            flash($sp['first_name'] . ' clocked out at ' . date('H:i') . '.', 'ok');
        }
    } else {
        if ($row !== null && $row['check_in']) {
            flash('Already clocked in today at ' . date('H:i', strtotime((string) $row['check_in'])) . '.', 'bad');
        } else {
            $late = date('H:i') > date('H:i', strtotime($L->setting('shift_start', '09:00') . ' +' . (int) $L->setting('grace_min', '15') . ' minutes'));
            if ($row) {
                $db->run('UPDATE sc_attendance SET check_in = ?, status = ?, source = ? WHERE id = ?', [$now, $late ? 'late' : 'present', 'pin', (int) $row['id']]);
            } else {
                $db->insert('sc_attendance', ['tenant_id' => $p->tenantId, 'branch_id' => $b, 'user_id' => (int) $sp['user_id'], 'att_date' => $today, 'status' => $late ? 'late' : 'present', 'check_in' => $now, 'source' => 'pin']);
            }
            flash($sp['first_name'] . ' clocked in at ' . date('H:i') . ($late ? ' (late).' : '.'), 'ok');
        }
    }
    redirect(url('portal/lab/attendance/kiosk'));
});

// ================================================================ salary
$app->router->get('/portal/lab/salary', static function () use ($boot, $page, $card, $btn, $db, $tbl, $badge, $fld, $form, $pf): void {
    [$p, $L] = $boot('salary');
    $per = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['period'] ?? '')) ? (string) $_GET['period'] : date('Y-m');
    $rows = [];
    $tot = ['net' => 0.0, 'paid' => 0.0];
    foreach ($db->all("SELECT s.*, u.first_name, u.last_name, sp.staff_no FROM sc_salary s JOIN users u ON u.id = s.user_id LEFT JOIN sc_staff_profiles sp ON sp.user_id = s.user_id WHERE s.tenant_id = ? AND s.period = ? AND " . $L->scope('s.branch_id') . ' ORDER BY u.first_name', [$p->tenantId, $per]) as $s) {
        $sc = (string) ($s['currency'] ?: $L->branchCurrency((int) $s['branch_id']));
        $tot['net'] += \Selfcare\Currency::convert((float) $s['net'], $sc, $L->reportCurrency());
        $tot['paid'] += \Selfcare\Currency::convert((float) $s['paid'], $sc, $L->reportCurrency());
        $due = round((float) $s['net'] - (float) $s['paid'], 2);
        $act = '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/salary/' . $s['id'])) . '">Open</a>';
        $rows[] = [e((string) $s['staff_no']), e(trim($s['first_name'] . ' ' . $s['last_name'])), e($L->branchName((int) $s['branch_id'])), e(money((float) $s['base'], $s['currency'] ?? null)), e(money((float) $s['net'], $s['currency'] ?? null)), e(money((float) $s['paid'], $s['currency'] ?? null)), e(money($due)), $badge(ucfirst((string) $s['status']), $s['status'] === 'paid' ? 'ok' : ($s['status'] === 'partial' ? 'warn' : 'bad')), $act];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/salary')) . '"><label class="d-field" style="margin:0"><span class="d-help">Month</span><input type="month" name="period" value="' . e($per) . '" onchange="this.form.submit()"></label></form>';
    $gen = $p->canDo('lab_salary', 'add') && $p->writable() ? '<p>' . $pf(url('portal/lab/salary/generate'), 'Prepare ' . $per . ' salary sheet', 'd-btn-primary', 'Create the salary lines for ' . $per . ' for every active employee with a salary? Existing lines stay as they are.', hid2('period', $per)) . '</p>' : '';
    $page($p, $L, 'salary', 'Salary', $f . $gen . $tbl([['Staff no'], ['Employee'], ['Branch'], ['Basic', 'R'], ['Net', 'R'], ['Paid', 'R'], ['Due', 'R'], ['Status'], [' ']], $rows, 'No salary lines for this month. Prepare the sheet.', 'card') . '<p class="d-info">Net total ' . e(money($tot['net'])) . ' · Paid ' . e(money($tot['paid'])) . ' · Still due ' . e(money($tot['net'] - $tot['paid'])) . '</p>', 'Paid, partial or due. Payments post to Accounts as salaries and wages.');
});
$app->router->post('/portal/lab/salary/generate', static function () use ($boot, $post, $db, $staffRows, $profileEnsure): void {
    [$p, $L] = $boot('salary');
    $post($p);
    $per = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? '')) ? (string) $_POST['period'] : date('Y-m');
    if (!$p->canDo('lab_salary', 'add')) {
        redirect(url('portal/lab/salary'));
    }
    $n = 0;
    foreach ($staffRows($L, ' AND u.status = 1') as $s) {
        if ((float) $s['salary'] > 0 && (int) $db->val('SELECT COUNT(*) FROM sc_salary WHERE tenant_id = ? AND user_id = ? AND period = ?', [$p->tenantId, (int) $s['id'], $per]) === 0) {
            $db->insert('sc_salary', ['tenant_id' => $p->tenantId, 'branch_id' => (int) ($s['branch_id'] ?: $L->mainId()), 'user_id' => (int) $s['id'], 'period' => $per, 'base' => $s['salary'], 'net' => $s['salary'], 'status' => 'due', 'created_at' => date('Y-m-d H:i:s')]);
            $n++;
        }
    }
    $L->log('salary', 0, 'created', null, ['period' => $per, 'lines' => $n], null, $L->writeBranch(0));
    flash($n . ' salary line' . ($n === 1 ? '' : 's') . ' prepared for ' . $per . '.', 'ok');
    redirect(url('portal/lab/salary', ['period' => $per]));
});
$salaryOf = static function (Portal $p, Lab $L, int $id) use ($app, $db): array {
    $s = $db->one('SELECT s.*, u.first_name, u.last_name FROM sc_salary s JOIN users u ON u.id = s.user_id WHERE s.id = ? AND s.tenant_id = ?', [$id, $p->tenantId]);
    if ($s === null || !$L->may((int) $s['branch_id'])) {
        $app->error(404, 'Salary line not found.');
    }
    return $s;
};
$salaryNet = static function (int $id) use ($db): void {
    $s = $db->one('SELECT base FROM sc_salary WHERE id = ?', [$id]);
    $sum = ['bonus' => 0.0, 'deduction' => 0.0, 'advance' => 0.0];
    foreach ($db->all('SELECT kind, SUM(amount) a FROM sc_salary_adj WHERE salary_id = ? GROUP BY kind', [$id]) as $r) {
        $sum[$r['kind']] = (float) $r['a'];
    }
    $net = round((float) $s['base'] + $sum['bonus'] - $sum['deduction'] - $sum['advance'], 2);
    $paid = (float) $db->val('SELECT paid FROM sc_salary WHERE id = ?', [$id]);
    $db->run('UPDATE sc_salary SET bonus = ?, deductions = ?, advances = ?, net = ?, status = ? WHERE id = ?', [$sum['bonus'], $sum['deduction'], $sum['advance'], $net, $paid <= 0 ? 'due' : ($paid + 0.004 >= $net ? 'paid' : 'partial'), $id]);
};
$app->router->get('/portal/lab/salary/{id}', static function (array $a) use ($boot, $salaryOf, $page, $card, $db, $tbl, $badge, $form, $fld, $btn): void {
    [$p, $L] = $boot('salary');
    $s = $salaryOf($p, $L, (int) $a['id']);
    $id = (int) $s['id'];
    $adj = [];
    foreach ($db->all('SELECT * FROM sc_salary_adj WHERE salary_id = ? ORDER BY id', [$id]) as $x) {
        $adj[] = [e(dt((string) $x['created_at'], 'd M')), e(ucfirst((string) $x['kind'])), e(money((float) $x['amount'], $x['currency'] ?? null)), e((string) $x['reason'])];
    }
    $pays = [];
    foreach ($db->all("SELECT * FROM sc_ledger WHERE tenant_id = ? AND source = 'salary' AND source_id = ? AND deleted_at IS NULL ORDER BY id", [$p->tenantId, $id]) as $x) {
        $pays[] = [e(dt((string) $x['entry_date'], 'd M Y')), e((string) $x['code']), e((string) $x['description']), e((string) $x['method']), e(money((float) $x['amount'], $x['currency'] ?? null))];
    }
    $due = round((float) $s['net'] - (float) $s['paid'], 2);
    $w = $p->writable();
    $af = $w && $p->canDo('lab_salary', 'edit') ? $form(url('portal/lab/salary/' . $id . '/adjust'), '<div class="d-form-grid">' . $fld('kind', ['label' => 'Type', 'type' => 'select', 'options' => ['bonus' => 'Bonus or allowance', 'deduction' => 'Deduction', 'advance' => 'Advance paid now'], 'required' => true], 'deduction') . $fld('amount', ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true]) . $fld('reason', ['label' => 'Reason', 'required' => true, 'max' => 190]) . $fld('method', ['label' => 'If advance: paid by', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS)], 'Cash') . '</div>', 'Add') : '';
    $pf1 = $w && $due > 0 && $p->canDo('lab_salary', 'add') ? $form(url('portal/lab/salary/' . $id . '/pay'), '<div class="d-form-grid">' . $fld('amount', ['label' => 'Pay now (due ' . money($due) . ')', 'type' => 'number', 'step' => '0.01', 'required' => true], (string) $due) . $fld('method', ['label' => 'Paid by', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . '</div>', 'Pay salary', '', 'Pay this salary? It goes into the cash book.') : '';
    $name = trim($s['first_name'] . ' ' . $s['last_name']);
    $page($p, $L, 'salary', $name . ' · ' . $s['period'], '<div class="d-stats"><div class="d-stat"><span>Basic</span><b>' . e(money((float) $s['base'], $s['currency'] ?? null)) . '</b></div><div class="d-stat"><span>Net</span><b>' . e(money((float) $s['net'], $s['currency'] ?? null)) . '</b></div><div class="d-stat"><span>Paid</span><b>' . e(money((float) $s['paid'], $s['currency'] ?? null)) . '</b></div><div class="d-stat"><span>Due</span><b>' . e(money($due)) . '</b></div></div>' . $card('Bonus, deductions and advances', $tbl([['Date'], ['Type'], ['Amount', 'R'], ['Reason']], $adj, 'None.') . $af) . $card('Payments', $tbl([['Date'], ['No'], ['Note'], ['Method'], ['Amount', 'R']], $pays, 'Nothing paid yet.') . $pf1) . '<p>' . $btn('Salary slip PDF', url('portal/lab/salary/' . $id . '/slip')) . '</p>', ucfirst((string) $s['status']));
});
$app->router->post('/portal/lab/salary/{id}/{act}', static function (array $a) use ($boot, $post, $salaryOf, $db, $salaryNet): void {
    [$p, $L] = $boot('salary');
    $post($p);
    $s = $salaryOf($p, $L, (int) $a['id']);
    $id = (int) $s['id'];
    $amt = round((float) ($_POST['amount'] ?? 0), 2);
    $b = (int) $s['branch_id'];
    $method = in_array($_POST['method'] ?? '', Lab::METHODS, true) ? (string) $_POST['method'] : 'Cash';
    if ($amt <= 0) {
        flash('Enter an amount above zero.', 'bad');
    } elseif ($a['act'] === 'adjust' && $p->canDo('lab_salary', 'edit')) {
        $k = (string) ($_POST['kind'] ?? '');
        $r = trim((string) ($_POST['reason'] ?? ''));
        if (!in_array($k, ['bonus', 'deduction', 'advance'], true) || $r === '') {
            flash('Choose the type and write the reason.', 'bad');
        } else {
            $db->insert('sc_salary_adj', ['tenant_id' => $p->tenantId, 'salary_id' => $id, 'kind' => $k, 'amount' => $amt, 'reason' => mb_substr($r, 0, 255), 'approved_by' => (int) $p->user['id'], 'created_at' => date('Y-m-d H:i:s')]);
            if ($k === 'advance') {
                $L->postExpense('Salaries and wages', $amt, date('Y-m-d'), trim($s['first_name'] . ' ' . $s['last_name']), 'Advance for ' . $s['period'], $method, 'paid', $b, 'salary', $id, null, (int) $s['user_id']);
            }
            $salaryNet($id);
            $L->log('salary', $id, 'edited', null, [$k => $amt], $r, $b);
            flash('Added. The net salary was recalculated.', 'ok');
        }
    } elseif ($a['act'] === 'pay' && $p->canDo('lab_salary', 'add')) {
        $due = round((float) $s['net'] - (float) $s['paid'], 2);
        if ($amt > $due + 0.001) {
            flash('That is more than the ' . money($due) . ' still due.', 'bad');
        } else {
            $L->postExpense('Salaries and wages', $amt, date('Y-m-d'), trim($s['first_name'] . ' ' . $s['last_name']), 'Salary ' . $s['period'], $method, 'paid', $b, 'salary', $id, null, (int) $s['user_id']);
            $db->run('UPDATE sc_salary SET paid = paid + ? WHERE id = ?', [$amt, $id]);
            $salaryNet($id);
            $L->log('salary', $id, 'edited', null, ['paid' => $amt], null, $b);
            flash('Salary payment recorded in the cash book.', 'ok');
        }
    }
    redirect(url('portal/lab/salary/' . $id));
});
$app->router->get('/portal/lab/salary/{id}/slip', static function (array $a) use ($app, $boot, $salaryOf, $send, $db): void {
    [$p, $L] = $boot('salary');
    $s = $salaryOf($p, $L, (int) $a['id']);
    if (!$p->canDo('lab_salary', 'print')) {
        $app->error(403, 'Not allowed to print.', 'No access');
    }
    $u = $db->one('SELECT u.first_name, u.last_name, sp.staff_no, sp.designation FROM users u LEFT JOIN sc_staff_profiles sp ON sp.user_id = u.id WHERE u.id = ?', [(int) $s['user_id']]);
    $D = new LabDocs($L);
    $send($D->salarySlip($s, $u, $db->all('SELECT * FROM sc_salary_adj WHERE salary_id = ? ORDER BY id', [(int) $s['id']]), $D->mark('salary', (int) $s['id'], (int) $s['branch_id'])), 'salary-' . $s['period'] . '-' . $s['id']);
});

// ================================================================ reports
$repTabs = static function (string $on, string $f, string $t) use ($tabs): string {
    $items = [];
    foreach (Lab::REPORTS as $k => $l) {
        $items[$k] = [$l, url('portal/lab/reports', ['type' => $k, 'period' => 'custom', 'from' => $f, 'to' => $t])];
    }
    return $tabs($items, $on);
};
$app->router->get('/portal/lab/reports', static function () use ($boot, $page, $tbl, $periodBar, $repTabs, $btn): void {
    [$p, $L] = $boot('reports');
    $type = isset(Lab::REPORTS[$_GET['type'] ?? '']) ? (string) $_GET['type'] : 'summary';
    [$f, $t, $bar] = $periodBar($L, 'portal/lab/reports', ['type' => $type]);
    $d = $L->reportData($type, $f, $t);
    $rows = array_map(static fn ($r) => array_map(static fn ($c) => e((string) $c), $r), $d['rows']);
    $cols = array_map(static fn ($c) => [$c[0], $c[2] ?? 'L'], $d['cols']);
    $tot = '';
    foreach ($d['totals'] as $x) {
        $tot .= '<p class="d-info"><b>' . e($x[0]) . '</b> ' . e($x[1]) . '</p>';
    }
    $ex = '<p class="d-tools" style="gap:8px;flex-wrap:wrap">' . ($p->canDo('lab_reports', 'export') ? $btn('Excel', url('portal/lab/reports/export', ['type' => $type, 'from' => $f, 'to' => $t])) : '') . ' ' . ($p->canDo('lab_reports', 'print') ? $btn('PDF', url('portal/lab/reports/pdf', ['type' => $type, 'from' => $f, 'to' => $t]), 'd-btn-soft') : '') . '</p>';
    $page($p, $L, 'reports', 'Lab reports', $repTabs($type, $f, $t) . $bar . $tbl($cols, $rows, 'Nothing to show for this period.', 'chart') . $tot . $ex, ($L->current() === 0 ? 'All branches combined' : $L->branchName($L->current())) . ' · ' . dt($f) . ' to ' . dt($t));
});
$app->router->get('/portal/lab/reports/export', static function () use ($app, $boot, $dateIn, $xlsxSend): void {
    [$p, $L] = $boot('reports');
    if (!$p->canDo('lab_reports', 'export')) {
        $app->error(403, 'Not allowed to export.', 'No access');
    }
    $f = $dateIn((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-01');
    $t = $dateIn((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
    $type = (string) ($_GET['type'] ?? 'summary');
    $L->log('report', 0, 'exported', null, ['type' => $type, 'from' => $f, 'to' => $t], null, $L->current());
    if ($type === 'finance' || !isset(Lab::REPORTS[$type])) {
        $type = 'summary';
    }
    if (($_GET['all'] ?? '') === '1') {
        $b = $L->workbook($f, $t);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="lab-report-' . $f . '-' . $t . '.xlsx"');
        echo $b;
        exit;
    }
    $d = $L->reportData($type, $f, $t);
    $xlsxSend([$d['title'] => array_merge([array_map(static fn ($c) => $c[0], $d['cols'])], $d['rows'], array_map(static fn ($x) => [$x[0], $x[1]], $d['totals']))], 'lab-' . $type . '-' . $f . '-' . $t);
});
$app->router->get('/portal/lab/reports/pdf', static function () use ($app, $boot, $dateIn, $send): void {
    [$p, $L] = $boot('reports');
    if (!$p->canDo('lab_reports', 'print')) {
        $app->error(403, 'Not allowed to print.', 'No access');
    }
    $f = $dateIn((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-01');
    $t = $dateIn((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
    $type = (string) ($_GET['type'] ?? 'summary');
    $type = isset(Lab::REPORTS[$type]) ? $type : 'summary';
    $d = $L->reportData($type, $f, $t);
    $D = new LabDocs($L);
    $send($D->table($d['title'], strtoupper(substr($type, 0, 3)) . '-' . str_replace('-', '', $t), [['Period', dt($f) . ' to ' . dt($t)], ['Branch', $L->current() === 0 ? 'All branches combined' : $L->branchName($L->current())]], $d['cols'], $d['rows'], $d['totals'], $D->mark('report_' . $type, 0, $L->current() ?: $L->mainId())), 'lab-' . $type . '-' . $t);
});

// ================================================================ record history
$app->router->get('/portal/lab/history', static function () use ($boot, $page, $db, $tbl, $badge): void {
    [$p, $L] = $boot('history');
    $ent = trim((string) ($_GET['entity'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));
    $w = 'h.tenant_id = ? AND ' . $L->scope('h.branch_id');
    $a = [$p->tenantId];
    if ($ent !== '') {
        $w .= ' AND h.entity = ?';
        $a[] = $ent;
    }
    if ($q !== '') {
        $w .= ' AND (h.action LIKE ? OR h.reason LIKE ? OR h.after_json LIKE ?)';
        array_push($a, "%$q%", "%$q%", "%$q%");
    }
    $rows = [];
    foreach ($db->all("SELECT h.*, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) un FROM sc_record_history h LEFT JOIN users u ON u.id = h.user_id WHERE $w ORDER BY h.id DESC LIMIT 200", $a) as $h) {
        $rows[] = [e(dt((string) $h['created_at'], 'd M Y H:i')), e((string) $h['un']), e($L->branchName((int) $h['branch_id'])), e((string) $h['entity'] . ' ' . ($h['entity_id'] ?: '')), $badge((string) $h['action'], in_array($h['action'], ['voided', 'deactivated'], true) ? 'bad' : 'info'), e((string) $h['reason']), e(mb_substr((string) $h['after_json'], 0, 110))];
    }
    $ents = $db->all('SELECT DISTINCT entity FROM sc_record_history WHERE tenant_id = ? ORDER BY entity', [$p->tenantId]);
    $o = '<option value="">All records</option>';
    foreach ($ents as $x) {
        $o .= '<option value="' . e($x['entity']) . '"' . ($ent === $x['entity'] ? ' selected' : '') . '>' . e(ucfirst($x['entity'])) . '</option>';
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/history')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Search action, reason or details</span><input type="search" name="q" value="' . e($q) . '"></label><label class="d-field" style="margin:0"><span class="d-help">Record</span><select name="entity" onchange="this.form.submit()">' . $o . '</select></label><button class="d-btn d-btn-primary" type="submit">Find</button></form>';
    $page($p, $L, 'history', 'Record history', $f . $tbl([['When'], ['Who'], ['Branch'], ['Record'], ['Action'], ['Reason'], ['Details']], $rows, 'No history yet.', 'clock'), 'Created, edited, voided, deactivated, restored, printed, shared, exported and branch views. Nothing here can be changed.');
});

// ================================================================ branches, user access, settings, backups
$adminTabs = static fn (string $on): string => $tabs(['branches' => ['Branches', url('portal/lab/branches')], 'access' => ['User access', url('portal/lab/access')], 'settings' => ['Settings', url('portal/lab/settings')], 'backups' => ['Backups', url('portal/lab/backups')]], $on);
$brMoney = static function (\Selfcare\Lab $L, ?array $b = null) use ($fld): string {
    $vc = \Selfcare\Currency::forTenant($L->t);
    $locked = $b !== null && $L->branchHasMoney((int) $b['id']);
    $co = \Selfcare\Currency::options((string) ($b['currency'] ?? ''));
    $zo = array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers());
    $f = $fld('currency', ['label' => 'Branch currency', 'type' => 'select', 'options' => $co, 'blank_label' => 'Same as the clinic (' . $vc . ')', 'help' => $locked ? 'Fixed, because this branch already has bills or records in ' . ($b['currency'] ?: $vc) . '. Open a new branch to use another currency.' : 'Bills, receipts, PDFs, emails and reports of this branch use it.', 'disabled' => $locked], (string) ($b['currency'] ?? ''));
    return $f . $fld('timezone', ['label' => 'Branch time zone', 'type' => 'select', 'options' => $zo, 'blank_label' => 'Same as the clinic', 'help' => 'Dates and times for this branch. Empty uses the clinic zone.'], (string) ($b['timezone'] ?? ''));
};
$app->router->get('/portal/lab/branches', static function () use ($brMoney, $boot, $page, $card, $db, $tbl, $badge, $form, $fld, $pf, $adminTabs): void {
    [$p, $L] = $boot('branches');
    $rows = [];
    foreach ($L->branches() as $b) {
        $n = (int) $db->val("SELECT COUNT(*) FROM sc_lab_orders WHERE tenant_id = ? AND branch_id = ?", [$p->tenantId, (int) $b['id']]);
        $edit = '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/branches/' . $b['id'])) . '">Edit</a>';
        if (!$b['is_main']) {
            $edit .= $pf(url('portal/lab/branches/' . $b['id'] . '/toggle'), $b['active'] ? 'Close' : 'Reopen', 'd-btn-soft', $b['active'] ? 'Close this branch? Its records stay and it can be reopened.' : '');
        }
        $rows[] = [e((string) $b['code']), e((string) $b['name']) . ($b['is_main'] ? ' <small>(main)</small>' : ''), e((string) $b['phone']), e((string) $b['report_email']), e((string) $n), $b['active'] ? $badge('Open', 'ok') : $badge('Closed'), $edit];
    }
    $add = $p->writable() ? $card('Add a branch', $form(url('portal/lab/branches/save'), '<div class="d-form-grid">' . $fld('name', ['label' => 'Branch name', 'required' => true, 'max' => 120]) . $fld('code', ['label' => 'Short code (for numbers like ORD-BR5-000001)', 'max' => 12]) . $fld('phone', ['label' => 'Phone', 'max' => 60]) . $fld('email', ['label' => 'Email', 'max' => 190]) . $fld('report_email', ['label' => 'Daily report email for this branch', 'max' => 190]) . $fld('address', ['label' => 'Address', 'max' => 255, 'wide' => true]) . $brMoney($L) . '</div>', 'Add branch')) : '';
    $page($p, $L, 'branches', 'Branches', $adminTabs('branches') . $tbl([['Code'], ['Branch'], ['Phone'], ['Report email'], ['Orders', 'R'], ['Status'], [' ']], $rows, 'No branches.', 'globe') . $add, 'Add, rename, close or reopen branches at any time. There is no limit.');
});
$app->router->get('/portal/lab/branches/{id}', static function (array $a) use ($brMoney, $app, $boot, $page, $card, $form, $fld, $adminTabs): void {
    [$p, $L] = $boot('branches');
    $b = $L->branch((int) $a['id']);
    if ($b === null) {
        $app->error(404, 'Branch not found.');
    }
    $page($p, $L, 'branches', $b['name'], $card('Branch', $form(url('portal/lab/branches/save'), '<input type="hidden" name="id" value="' . (int) $b['id'] . '"><div class="d-form-grid">' . $fld('name', ['label' => 'Branch name', 'required' => true, 'max' => 120], (string) $b['name']) . $fld('code', ['label' => 'Short code', 'max' => 12], (string) $b['code']) . $fld('phone', ['label' => 'Phone', 'max' => 60], (string) $b['phone']) . $fld('email', ['label' => 'Email', 'max' => 190], (string) $b['email']) . $fld('report_email', ['label' => 'Daily report email', 'max' => 190], (string) $b['report_email']) . $fld('address', ['label' => 'Address', 'max' => 255, 'wide' => true], (string) $b['address']) . $brMoney($L, $b) . '</div>', 'Save branch')));
});
// A branch administrator looks after the details of the branch they work in: address, contact, time zone and currency (only while it has no bills).
$app->router->get('/portal/lab/my-branch', static function () use ($brMoney, $boot, $page, $card, $form, $fld): void {
    [$p, $L] = $boot('dashboard');
    $bid = $L->current() ?: ($L->allowed()[0] ?? 0);
    $b = $L->branch($bid);
    if ($b === null || !$L->may($bid)) {
        redirect(url('portal/lab'));
    }
    $page($p, $L, 'dashboard', 'My branch: ' . $b['name'], $card('Branch details', $form(url('portal/lab/my-branch'), '<input type="hidden" name="id" value="' . (int) $b['id'] . '"><div class="d-form-grid">' . $fld('phone', ['label' => 'Phone', 'max' => 60], (string) $b['phone']) . $fld('email', ['label' => 'Email', 'max' => 190], (string) $b['email']) . $fld('address', ['label' => 'Address', 'max' => 255, 'wide' => true], (string) $b['address']) . $brMoney($L, $b) . '</div>', 'Save branch details')));
});
$app->router->post('/portal/lab/my-branch', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('dashboard');
    $post($p);
    $id = (int) ($_POST['id'] ?? 0);
    $old = $L->branch($id);
    if ($old === null || !$L->may($id)) {
        redirect(url('portal/lab'));
    }
    $d = ['phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 60) ?: null, 'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190) ?: null, 'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255) ?: null, 'updated_at' => date('Y-m-d H:i:s')];
    $tz = trim((string) ($_POST['timezone'] ?? ''));
    $cu = strtoupper(trim((string) ($_POST['currency'] ?? '')));
    if (($tz !== '' && !in_array($tz, \DateTimeZone::listIdentifiers(), true)) || ($cu !== '' && !isset(\Selfcare\Currency::all(true)[$cu]))) {
        flash('Choose a time zone and a currency from the lists.', 'bad');
        redirect(url('portal/lab/my-branch'));
    }
    $d['timezone'] = $tz !== '' ? $tz : null;
    $newCur = $cu !== '' && $cu !== \Selfcare\Currency::forTenant($p->tenantId) ? $cu : null;
    if ((string) ($old['currency'] ?? '') !== (string) ($newCur ?? '')) {
        if ($L->branchHasMoney($id)) {
            flash('The currency was not changed: this branch already has bills or records. The other details are saved.', 'info');
        } else {
            $d['currency'] = $newCur;
        }
    }
    [$b, $a] = Lab::diff($old, $d);
    $db->update('sc_branches', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
    $L->log('branch', $id, 'edited', $b, $a, null, $id);
    $L->flushBranches();
    flash('Branch details saved.', 'ok');
    redirect(url('portal/lab/my-branch'));
});
$app->router->post('/portal/lab/branches/save', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('branches');
    $post($p);
    if (!$p->canDo('lab_branches', 'add') && !$p->canDo('lab_branches', 'edit')) {
        redirect(url('portal/lab/branches'));
    }
    $id = (int) ($_POST['id'] ?? 0);
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')) ?: '');
    if ($name === '') {
        flash('Enter the branch name.', 'bad');
        redirect(url('portal/lab/branches'));
    }
    if ($code === '') {
        $code = 'BR' . ((int) $db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ?', [$p->tenantId]));
    }
    $code = substr($code, 0, 12);
    if ($id === 0 && ($limit = \Selfcare\app()->plans->limitError($p->tenantId, 'branches')) !== null) {
        flash($limit, 'bad');
        redirect(url('portal/lab/branches'));
    }
    if ((int) $db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ? AND code = ? AND id <> ?', [$p->tenantId, $code, $id])) {
        flash('That short code is used by another branch.', 'bad');
        redirect(url('portal/lab/branches' . ($id ? '/' . $id : '')));
    }
    $d = ['name' => $name, 'code' => $code, 'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 60) ?: null, 'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190) ?: null, 'report_email' => mb_substr(trim((string) ($_POST['report_email'] ?? '')), 0, 190) ?: null, 'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255) ?: null, 'updated_at' => date('Y-m-d H:i:s')];
    $reqCur = strtoupper(trim((string) ($_POST['currency'] ?? '')));
    $reqTz = trim((string) ($_POST['timezone'] ?? ''));
    if ($reqCur !== '' && !isset(\Selfcare\Currency::all(true)[$reqCur])) {
        flash('Choose one of the listed currencies.', 'bad');
        redirect(url('portal/lab/branches' . ($id ? '/' . $id : '')));
    }
    if ($reqTz !== '' && !in_array($reqTz, \DateTimeZone::listIdentifiers(), true)) {
        flash('Choose a time zone from the list.', 'bad');
        redirect(url('portal/lab/branches' . ($id ? '/' . $id : '')));
    }
    $d['timezone'] = $reqTz !== '' ? $reqTz : null;
    $d['currency'] = $reqCur !== '' && $reqCur !== \Selfcare\Currency::forTenant($p->tenantId) ? $reqCur : null;
    if ($id) {
        $old = $L->branch($id);
        if ($old === null) {
            redirect(url('portal/lab/branches'));
        }
        if ((string) ($old['currency'] ?? '') !== (string) ($d['currency'] ?? '') && $L->branchHasMoney($id)) {
            unset($d['currency']);
            flash('The currency was not changed: this branch already has bills or records in ' . ($old['currency'] ?: \Selfcare\Currency::forTenant($p->tenantId)) . '. The other changes are saved.', 'info');
        }
        [$b, $a] = Lab::diff($old, $d);
        $db->update('sc_branches', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        $L->log('branch', $id, 'edited', $b, $a, null, $id);
    } else {
        $id = $db->insert('sc_branches', $d + ['tenant_id' => $p->tenantId, 'is_main' => 0, 'active' => 1, 'sort_order' => (int) $db->val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM sc_branches WHERE tenant_id = ?', [$p->tenantId]), 'created_at' => date('Y-m-d H:i:s')]);
        $L->log('branch', $id, 'created', null, ['name' => $name], null, $id);
    }
    $L->flushBranches();
    flash('Branch saved.', 'ok');
    redirect(url('portal/lab/branches'));
});
$app->router->post('/portal/lab/branches/{id}/toggle', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('branches');
    $post($p);
    $b = $L->branch((int) $a['id']);
    if ($b !== null && !$b['is_main'] && $p->canDo('lab_branches', 'delete')) {
        $db->run('UPDATE sc_branches SET active = 1 - active, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [(int) $b['id'], $p->tenantId]);
        $L->log('branch', (int) $b['id'], $b['active'] ? 'deactivated' : 'restored', null, null, null, (int) $b['id']);
        $L->flushBranches();
        flash($b['active'] ? 'Branch closed. Its records are kept.' : 'Branch reopened.', 'ok');
    }
    redirect(url('portal/lab/branches'));
});
// user access: branches and per person permission overrides
$app->router->get('/portal/lab/access', static function () use ($boot, $page, $card, $db, $tbl, $badge, $adminTabs, $staffRows): void {
    [$p, $L] = $boot('access');
    $rows = [];
    foreach ($staffRows($L) as $s) {
        $br = array_column($db->all('SELECT branch_id FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, (int) $s['id']]), 'branch_id');
        $names = in_array('0', array_map('strval', $br), true) ? 'All branches' : implode(', ', array_map(fn ($b) => $L->branchName((int) $b), $br));
        $ov = (int) $db->val('SELECT COUNT(*) FROM sc_user_perms WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, (int) $s['id']]);
        $rows[] = ['<a href="' . e(url('portal/lab/access/' . $s['id'])) . '">' . e(trim($s['first_name'] . ' ' . $s['last_name'])) . '</a>', e((string) $s['role']), e($names ?: 'Main branch only'), $ov ? $badge($ov . ' personal rule' . ($ov > 1 ? 's' : ''), 'info') : '<span class="d-mute">Role only</span>'];
    }
    $page($p, $L, 'access', 'User access', $adminTabs('access') . $tbl([['Person'], ['Role'], ['Branches'], ['Personal permissions']], $rows, 'No staff.', 'shield'), 'Roles give the base access. Here you add branches and personal grants or blocks for one person.');
});
$app->router->get('/portal/lab/access/{id}', static function (array $a) use ($app, $boot, $page, $card, $db, $adminTabs, $staffRows): void {
    [$p, $L] = $boot('access');
    $s = $staffRows($L, ' AND u.id = ?', [(int) $a['id']])[0] ?? null;
    if ($s === null) {
        $app->error(404, 'Person not found.');
    }
    $uid = (int) $s['id'];
    $cur = [];
    foreach ($db->all('SELECT * FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]) as $r) {
        $cur[(int) $r['branch_id']] = true;
    }
    $f = '<form class="d-form" method="post" action="' . e(url('portal/lab/access/' . $uid)) . '">' . csrf_field() . '<h3>Branches this person may open</h3><div class="d-checks"><label class="d-check"><input type="checkbox" name="br[]" value="0"' . (isset($cur[0]) ? ' checked' : '') . '><span>All branches (central staff)</span></label>';
    foreach ($L->branches(true) as $b) {
        $f .= '<label class="d-check"><input type="checkbox" name="br[]" value="' . (int) $b['id'] . '"' . (isset($cur[(int) $b['id']]) ? ' checked' : '') . '><span>' . e($b['name']) . '</span></label>';
    }
    $f .= '</div><h3>Personal permissions</h3><p class="d-help">Leave on "Same as role" to follow the role. A grant replaces the role actions for that module. Block removes the module for this person.</p><div class="d-table-wrap"><table class="d-table"><thead><tr><th>Module</th><th>Setting</th><th>Actions when granted</th></tr></thead><tbody>';
    $ov = [];
    foreach ($db->all('SELECT * FROM sc_user_perms WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]) as $r) {
        $ov[$r['perm']] = $r;
    }
    $cfgPerms = $app->access->permissions();
    foreach (Lab::PERM_LIST as $perm) {
        $o = $ov[$perm] ?? null;
        $acts = $o ? array_filter(explode(',', (string) $o['actions'])) : [];
        $f .= '<tr><td data-label="Module">' . e($cfgPerms[$perm][0]) . '</td><td data-label="Setting"><select name="mode[' . $perm . ']"><option value="">Same as role</option><option value="grant"' . ($o && $o['mode'] === 'grant' ? ' selected' : '') . '>Grant</option><option value="deny"' . ($o && $o['mode'] === 'deny' ? ' selected' : '') . '>Block</option></select></td><td data-label="Actions"><div class="d-checks">';
        foreach (['add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'print' => 'Print', 'export' => 'Export', 'full' => 'Full'] as $k => $l) {
            $f .= '<label class="d-check"><input type="checkbox" name="act[' . $perm . '][]" value="' . $k . '"' . (in_array($k, $acts, true) ? ' checked' : '') . '><span>' . $l . '</span></label>';
        }
        $f .= '</div></td></tr>';
    }
    $f .= '</tbody></table></div><div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e(url('portal/lab/access')) . '">Back</a><button class="d-btn d-btn-primary" type="submit">Save access</button></div></form>';
    $page($p, $L, 'access', trim($s['first_name'] . ' ' . $s['last_name']), $card('Role: ' . $s['role'], $f), 'View is always included with a grant.');
});
$app->router->post('/portal/lab/access/{id}', static function (array $a) use ($boot, $post, $db, $staffRows): void {
    [$p, $L] = $boot('access');
    $post($p);
    $s = $staffRows($L, ' AND u.id = ?', [(int) $a['id']])[0] ?? null;
    if ($s === null || !$p->canDo('lab_branches', 'edit')) {
        redirect(url('portal/lab/access'));
    }
    $uid = (int) $s['id'];
    $before = ['branches' => array_column($db->all('SELECT branch_id FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]), 'branch_id'), 'perms' => array_column($db->all('SELECT perm, mode FROM sc_user_perms WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]), 'mode', 'perm')];
    $db->run('DELETE FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]);
    foreach (array_unique(array_map('intval', (array) ($_POST['br'] ?? []))) as $b) {
        if ($b === 0 || $L->branch($b)) {
            $db->run('INSERT IGNORE INTO sc_user_branches (tenant_id, user_id, branch_id) VALUES (?, ?, ?)', [$p->tenantId, $uid, $b]);
        }
    }
    $db->run('DELETE FROM sc_user_perms WHERE tenant_id = ? AND user_id = ?', [$p->tenantId, $uid]);
    $after = [];
    foreach (Lab::PERM_LIST as $perm) {
        $m = (string) (($_POST['mode'] ?? [])[$perm] ?? '');
        if (in_array($m, ['grant', 'deny'], true)) {
            $acts = array_values(array_intersect((array) (($_POST['act'] ?? [])[$perm] ?? []), ['add', 'edit', 'delete', 'print', 'export', 'full']));
            $db->insert('sc_user_perms', ['tenant_id' => $p->tenantId, 'user_id' => $uid, 'perm' => $perm, 'mode' => $m, 'actions' => implode(',', $acts), 'updated_by' => (int) $p->user['id'], 'updated_at' => date('Y-m-d H:i:s')]);
            $after[$perm] = $m . ':' . implode('+', $acts);
        }
    }
    $L->log('access', $uid, 'edited', $before, ['branches' => array_map('intval', (array) ($_POST['br'] ?? [])), 'perms' => $after], 'Access changed', $L->mainId());
    flash('Access saved.', 'ok');
    redirect(url('portal/lab/access/' . $uid));
});
// settings
$app->router->get('/portal/lab/settings', static function () use ($boot, $page, $card, $db, $tbl, $form, $fld, $adminTabs, $pf): void {
    [$p, $L] = $boot('settings');
    $s = static fn (string $k, string $d = '') => $L->setting($k, $d);
    $general = $form(url('portal/lab/settings/save'), '<input type="hidden" name="group" value="general"><div class="d-form-grid">'
        . $fld('report_title', ['label' => 'Name printed on reports and receipts', 'max' => 160], $s('report_title')) . $fld('mr_prefix', ['label' => 'MR number prefix', 'max' => 8], $s('mr_prefix', $L->mrPrefix()))
        . $fld('footer', ['label' => 'Footer text on documents', 'max' => 160], $s('footer', 'Generated by Self Care')) . $fld('link_days', ['label' => 'Days a shared link works', 'type' => 'number'], $s('link_days', '7'))
        . $fld('home_charge', ['label' => 'Default home collection charge', 'type' => 'number', 'step' => '0.01'], $s('home_charge', '0')) . $fld('shift_start', ['label' => 'Shift starts at', 'type' => 'time'], $s('shift_start', '09:00')) . $fld('grace_min', ['label' => 'Minutes of grace before Late', 'type' => 'number'], $s('grace_min', '15'))
        . $fld('expiry_days', ['label' => 'Warn this many days before expiry', 'type' => 'number'], $s('expiry_days', '60')) . $fld('self_approve', ['label' => 'Allow the person who entered results to approve them', 'type' => 'toggle'], $s('self_approve', '0')) . $fld('public_tests', ['label' => 'Show the test list and prices on the public website', 'type' => 'toggle', 'help' => 'Off by default. Turn it on once the prices are confirmed.'], $s('public_tests', '0')) . $fld('public_home', ['label' => 'Let visitors request a home collection on the website', 'type' => 'toggle'], $s('public_home', '1'))
        . $fld('wa_text', ['label' => 'WhatsApp message wording. Use {name} {doc} {no} {lab} {link} {days}', 'type' => 'textarea', 'rows' => 3, 'max' => 500], $s('wa_text', 'Dear {name}, your {doc} {no} from {lab} is ready. Open it here: {link} . The link works for {days} days.')) . '</div>', 'Save settings');
    $mail = $form(url('portal/lab/settings/save'), '<input type="hidden" name="group" value="mail"><div class="d-form-grid">'
        . $fld('smtp_host', ['label' => 'SMTP server (leave empty to use the Self Care mail)', 'max' => 190], $s('smtp_host')) . $fld('smtp_port', ['label' => 'Port', 'type' => 'number'], $s('smtp_port', '587')) . $fld('smtp_enc', ['label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'None'], 'blank' => false], $s('smtp_enc', 'tls'))
        . $fld('smtp_user', ['label' => 'Username', 'max' => 190], $s('smtp_user')) . '<div class="d-field"><label><span>Password</span><input type="password" name="smtp_password" autocomplete="new-password" placeholder="' . ($L->secret('smtp_password') !== '' ? 'Saved. Type to replace' : '') . '"></label></div>'
        . $fld('from_address', ['label' => 'From address', 'max' => 190], $s('from_address')) . $fld('from_name', ['label' => 'From name', 'max' => 120], $s('from_name', (string) $p->vendor['name'])) . '</div>', 'Save mail settings');
    $sched = $form(url('portal/lab/settings/save'), '<input type="hidden" name="group" value="schedule"><div class="d-form-grid">'
        . $fld('report_on', ['label' => 'Send the daily report by email', 'type' => 'toggle'], $s('report_on', '0')) . $fld('report_to', ['label' => 'Send to (comma separated)', 'max' => 300], $s('report_to', 'haiderlab09@gmail.com')) . $fld('report_time', ['label' => 'Send at', 'type' => 'time'], $s('report_time', '21:00'))
        . $fld('backup_on', ['label' => 'Scheduled daily backup', 'type' => 'toggle'], $s('backup_on', '0')) . $fld('backup_keep', ['label' => 'Keep the newest backups', 'type' => 'number'], $s('backup_keep', '14')) . $fld('backup_copy_dir', ['label' => 'Second copy folder on the server (optional)', 'max' => 200], $s('backup_copy_dir')) . '</div>', 'Save schedule');
    $sg = [];
    foreach ($db->all('SELECT * FROM sc_lab_signatories WHERE tenant_id = ? ORDER BY is_default DESC, id', [$p->tenantId]) as $x) {
        $sg[] = [e((string) $x['name']), e((string) $x['title']), e((string) $x['reg_no']), $x['is_default'] ? 'Default' : '', $x['active'] ? 'Active' : 'Off', $pf(url('portal/lab/settings/signatory/' . $x['id'] . '/default'), 'Make default') . $pf(url('portal/lab/settings/signatory/' . $x['id'] . '/toggle'), $x['active'] ? 'Switch off' : 'Switch on')];
    }
    $sgf = $form(url('portal/lab/settings/signatory'), '<div class="d-form-grid">' . $fld('name', ['label' => 'Name', 'required' => true, 'max' => 120]) . $fld('title', ['label' => 'Title', 'max' => 160]) . $fld('reg_no', ['label' => 'Registration no', 'max' => 60]) . '</div>', 'Add signatory');
    $runs = [];
    foreach ($db->all('SELECT * FROM sc_lab_report_runs WHERE tenant_id = ? ORDER BY id DESC LIMIT 15', [$p->tenantId]) as $r) {
        $runs[] = [e(dt((string) $r['created_at'], 'd M H:i')), e((string) $r['report_date']), e((string) $r['recipient']), e((string) $r['status']), e((string) $r['error']), e((string) $r['tries']), $r['status'] !== 'sent' ? $pf(url('portal/lab/settings/report-retry/' . $r['id']), 'Retry') : ''];
    }
    $send = $p->canDo('lab_branches', 'edit') ? $pf(url('portal/lab/settings/report-now'), 'Send today\'s report now', 'd-btn-primary', 'Email the report for today to the recipients above?') : '';
    $page($p, $L, 'settings', 'Lab settings', $adminTabs('settings') . $card('General', $general) . $card('Email (SMTP)', $mail) . $card('Schedules and backups', $sched) . $card('Report signatories', $tbl([['Name'], ['Title'], ['Registration'], ['Default'], ['Status'], [' ']], $sg, 'None.') . $sgf) . $card('Daily report delivery log', $send . $tbl([['When'], ['Report date'], ['To'], ['Status'], ['Error'], ['Tries', 'R'], [' ']], $runs, 'No reports sent yet.', 'mail')), 'Everything here belongs to this clinic only.');
});
$app->router->post('/portal/lab/settings/save', static function () use ($boot, $post): void {
    [$p, $L] = $boot('settings');
    $post($p);
    if (!$p->canDo('lab_branches', 'edit')) {
        redirect(url('portal/lab/settings'));
    }
    $groups = ['general' => ['report_title', 'mr_prefix', 'footer', 'link_days', 'home_charge', 'shift_start', 'grace_min', 'expiry_days', 'self_approve', 'public_tests', 'public_home', 'wa_text'], 'mail' => ['smtp_host', 'smtp_port', 'smtp_enc', 'smtp_user', 'from_address', 'from_name'], 'schedule' => ['report_on', 'report_to', 'report_time', 'backup_on', 'backup_keep', 'backup_copy_dir']];
    $g = (string) ($_POST['group'] ?? '');
    $changed = [];
    foreach ($groups[$g] ?? [] as $k) {
        if (array_key_exists($k, $_POST)) {
            $v = mb_substr(trim((string) $_POST[$k]), 0, 500);
            if ($k === 'report_to') {
                $v = implode(',', array_filter(array_map('trim', explode(',', $v)), static fn ($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
            }
            if ($v !== $L->setting($k)) {
                $L->setSetting($k, $v);
                $changed[] = $k;
            }
        }
    }
    if ($g === 'mail' && trim((string) ($_POST['smtp_password'] ?? '')) !== '') {
        $L->setSecret('smtp_password', (string) $_POST['smtp_password']);
        $changed[] = 'smtp_password';
    }
    $L->log('settings', 0, 'edited', null, ['changed' => $changed], null, $L->mainId());
    flash('Saved.', 'ok');
    redirect(url('portal/lab/settings'));
});
$app->router->post('/portal/lab/settings/signatory', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('settings');
    $post($p);
    $n = trim((string) ($_POST['name'] ?? ''));
    if ($n !== '' && $p->canDo('lab_branches', 'add')) {
        $db->insert('sc_lab_signatories', ['tenant_id' => $p->tenantId, 'name' => mb_substr($n, 0, 120), 'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160) ?: null, 'reg_no' => mb_substr(trim((string) ($_POST['reg_no'] ?? '')), 0, 60) ?: null, 'is_default' => 0, 'active' => 1]);
    }
    redirect(url('portal/lab/settings'));
});
$app->router->post('/portal/lab/settings/signatory/{id}/{act}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('settings');
    $post($p);
    if ($p->canDo('lab_branches', 'edit')) {
        if ($a['act'] === 'default') {
            $db->run('UPDATE sc_lab_signatories SET is_default = 0 WHERE tenant_id = ?', [$p->tenantId]);
            $db->run('UPDATE sc_lab_signatories SET is_default = 1, active = 1 WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
        } elseif ($a['act'] === 'toggle') {
            $db->run('UPDATE sc_lab_signatories SET active = 1 - active WHERE id = ? AND tenant_id = ? AND is_default = 0', [(int) $a['id'], $p->tenantId]);
        }
    }
    redirect(url('portal/lab/settings'));
});
$app->router->post('/portal/lab/settings/report-now', static function () use ($boot, $post): void {
    [$p, $L] = $boot('settings');
    $post($p);
    if ($p->canDo('lab_branches', 'edit')) {
        $r = $L->sendDailyReport(date('Y-m-d'));
        flash($r ?? 'Report sent.', $r ? 'bad' : 'ok');
    }
    redirect(url('portal/lab/settings'));
});
$app->router->post('/portal/lab/settings/report-retry/{id}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('settings');
    $post($p);
    $r = $db->one('SELECT * FROM sc_lab_report_runs WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r && $p->canDo('lab_branches', 'edit')) {
        $e = $L->sendDailyReport((string) $r['report_date'], (int) $r['id']);
        flash($e ?? 'Report sent.', $e ? 'bad' : 'ok');
    }
    redirect(url('portal/lab/settings'));
});
// backups
$app->router->get('/portal/lab/backups', static function () use ($boot, $page, $card, $db, $tbl, $badge, $pf, $adminTabs): void {
    [$p, $L] = $boot('backups');
    $rows = [];
    foreach ($db->all('SELECT * FROM sc_lab_backups WHERE tenant_id = ? ORDER BY id DESC LIMIT 40', [$p->tenantId]) as $b) {
        $act = '';
        if ($b['action'] === 'backup') {
            $act = '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/backups/' . $b['id'] . '/download')) . '">Download</a>' . $pf(url('portal/lab/backups/' . $b['id'] . '/verify'), 'Check integrity');
            if ($p->canDo('lab_branches', 'delete')) {
                $act .= '<form class="d-inline" method="post" action="' . e(url('portal/lab/backups/' . $b['id'] . '/restore')) . '" data-confirm="Restore the laboratory data from this backup? Records made after it will be replaced. A safety backup is made first.">' . csrf_field() . '<input name="word" placeholder="Type RESTORE" required style="width:8rem"><button class="d-btn d-btn-danger" type="submit">Restore</button></form>';
            }
        }
        $rows[] = [e(dt((string) $b['created_at'], 'd M Y H:i')), e(ucfirst((string) $b['action'])), e((string) $b['kind']), e((string) $b['file']), e(number_format((int) $b['size'] / 1024, 1) . ' KB'), $b['verified'] ? $badge('Verified', 'ok') : $badge('Not checked'), $act];
    }
    $mk = $p->canDo('lab_branches', 'add') && $p->writable() ? '<p>' . $pf(url('portal/lab/backups/make'), 'Make a backup now', 'd-btn-primary') . '</p>' : '';
    $page($p, $L, 'backups', 'Backups', $adminTabs('backups') . $mk . $tbl([['When'], ['Action'], ['Kind'], ['File'], ['Size', 'R'], ['Integrity'], [' ']], $rows, 'No backups yet.', 'shield'), 'Backups hold this clinic\'s laboratory data. Platform level backups run separately on the server.');
});
$app->router->post('/portal/lab/backups/make', static function () use ($boot, $post): void {
    [$p, $L] = $boot('backups');
    $post($p);
    $r = $p->canDo('lab_branches', 'add') ? $L->makeBackup('manual') : ['ok' => false, 'msg' => 'Not allowed.'];
    flash($r['msg'], $r['ok'] ? 'ok' : 'bad');
    redirect(url('portal/lab/backups'));
});
$app->router->post('/portal/lab/backups/{id}/{act}', static function (array $a) use ($boot, $post): void {
    [$p, $L] = $boot('backups');
    $post($p);
    $id = (int) $a['id'];
    if ($a['act'] === 'verify') {
        $e = $L->verifyBackup($id);
        flash($e ?? 'The backup is complete and unchanged.', $e ? 'bad' : 'ok');
    } elseif ($a['act'] === 'restore' && $p->canDo('lab_branches', 'delete')) {
        if (($_POST['word'] ?? '') !== 'RESTORE') {
            flash('Type RESTORE to confirm.', 'bad');
        } else {
            $e = $L->restoreBackup($id);
            $L->log('backup', $id, 'restored', null, null, $e, $L->mainId());
            flash($e ?? 'Restored. A safety backup of the previous state was kept.', $e ? 'bad' : 'ok');
        }
    }
    redirect(url('portal/lab/backups'));
});
$app->router->get('/portal/lab/backups/{id}/download', static function (array $a) use ($app, $boot, $db): void {
    [$p, $L] = $boot('backups');
    $b = $db->one("SELECT * FROM sc_lab_backups WHERE id = ? AND tenant_id = ? AND action = 'backup'", [(int) $a['id'], $p->tenantId]);
    $path = $b ? $L->backupDir() . '/' . $b['file'] : '';
    if ($b === null || !is_file($path) || !$p->canDo('lab_branches', 'export')) {
        $app->error(404, 'Backup not found.');
    }
    $L->log('backup', (int) $b['id'], 'exported', null, null, null, $L->mainId());
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $b['file'] . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});
