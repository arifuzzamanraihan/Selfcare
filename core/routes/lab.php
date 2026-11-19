<?php
// Selfcare original routes: the laboratory suite inside the clinic portal (part 1).
// Dashboard, branch switch, patients, test booking, orders with payment, refund and cancel, samples, home collection, results, approval, report PDF.
// Everything sits on the clinic's own tables (patients, sc_invoices, sc_ledger) plus the sc_lab_* tables. Logic is in core/Lab.php.
// /portal/lab  /portal/lab/{book,orders,order/{id},patients,samples,home,results,approve,report/{id}}   Part 2 is in lab2.php.
declare(strict_types=1);

use Selfcare\{Billing, Crud, Documents, Lab, LabDocs, Listing, Portal};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$db = $app->db;

/** Signed in, a clinic with the lab suite, and the permission of the section. @return array{0:Portal,1:Lab} */
$boot = static function (string $section = 'dashboard') use ($app): array {
    $p = Portal::boot($app);
    if (!Lab::enabled($p)) {
        $app->error(404, 'The laboratory suite is not part of this kind of organisation.');
    }
    $perm = Lab::SECTION_PERM[$section] ?? 'lab_reports';
    if ($section === 'dashboard') {
        // the lab home is open to anyone who has at least one lab module
        $any = false;
        foreach (Lab::PERM_LIST as $pm) {
            $any = $any || $p->can($pm);
        }
        if (!$any) {
            $app->error(403, 'Your access does not include the laboratory. Ask your administrator.', 'No access');
        }
        return [$p, Lab::for($p)];
    }
    $p->need($perm, 'billing');
    return [$p, Lab::for($p)];
};
$post = static function (Portal $p): void {
    $p->needWritable();
};
$page = static function (Portal $p, Lab $L, string $key, string $title, string $html, string $sub = '', string $actions = '') use ($dash): void {
    $dash($p, 'lab-' . $key, $title, $L->switcher() . $html, ['sub' => $sub, 'actions' => $actions]);
};
$badge = static function (string $text, string $tone = 'mute'): string {
    return '<span class="d-badge d-badge-' . e($tone) . '">' . e($text) . '</span>';
};
$tone = ['pending' => 'warn', 'collected' => 'info', 'received' => 'info', 'processing' => 'info', 'completed' => 'ok', 'rejected' => 'bad', 'recollect' => 'bad', 'cancelled' => 'bad', 'draft' => 'warn', 'pending_review' => 'info', 'approved' => 'ok', 'delivered' => 'ok', 'booked' => 'info', 'none' => 'mute', 'scheduled' => 'warn', 'assigned' => 'info', 'sent' => 'ok', 'failed' => 'bad', 'queued' => 'warn'];
$label = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s === 'recollect' ? 'recollection required' : $s));
$tbl = static function (array $cols, array $rows, string $empty, string $ic = 'file'): string {
    if (!$rows) {
        return '<div class="d-empty"><div class="d-empty-art">' . icon($ic, 28) . '</div><h3>' . e($empty) . '</h3></div>';
    }
    $t = '<div class="d-table-wrap"><table class="d-table"><thead><tr>';
    foreach ($cols as $c) {
        $t .= '<th' . (($c[1] ?? '') === 'R' ? ' class="r"' : '') . '>' . e($c[0]) . '</th>';
    }
    $t .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $t .= '<tr>';
        foreach (array_values($r) as $i => $cell) {
            $t .= '<td data-label="' . e($cols[$i][0] ?? '') . '"' . (($cols[$i][1] ?? '') === 'R' ? ' class="r"' : '') . '>' . $cell . '</td>';
        }
        $t .= '</tr>';
    }
    return $t . '</tbody></table></div>';
};
/** A small form posting to one address. */
$pf = static function (string $action, string $btnLabel, string $cls = 'd-btn-soft', string $confirm = '', string $body = ''): string {
    return '<form class="d-inline" method="post" action="' . e($action) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $body . '<button class="d-btn ' . $cls . '" type="submit">' . e($btnLabel) . '</button></form> ';
};
$hid = static fn (string $n, string $v): string => '<input type="hidden" name="' . e($n) . '" value="' . e($v) . '">';
$tabs = static function (array $items, string $on): string {
    $h = '<p class="d-tools" style="gap:8px;flex-wrap:wrap">';
    foreach ($items as $k => [$lab, $href]) {
        $h .= '<a class="d-btn ' . ($k === $on ? 'd-btn-primary' : 'd-btn-soft') . '" href="' . e($href) . '">' . e($lab) . '</a>';
    }
    return $h . '</p>';
};
$dateIn = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$back = static fn (string $to): never => redirect($to);
/** Date filter row (period buttons and custom dates). @return array{0:string,1:string,2:string} from, to, html */
$periodBar = static function (Lab $L, string $path, array $keep = []) use ($btn): array {
    $k = (string) ($_GET['period'] ?? 'month');
    [$f, $t, $lab] = $L->period($k, $_GET['from'] ?? null, $_GET['to'] ?? null);
    $h = '<form class="d-queue-toolbar" method="get" action="' . e(url($path)) . '">';
    foreach ($keep as $kk => $vv) {
        $h .= '<input type="hidden" name="' . e($kk) . '" value="' . e($vv) . '">';
    }
    $h .= '<label class="d-field" style="margin:0"><span class="d-help">Period</span><select name="period" onchange="this.form.submit()">';
    foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'month' => 'This month', 'lastmonth' => 'Last month', 'custom' => 'Custom dates'] as $v => $l) {
        $h .= '<option value="' . $v . '"' . ($v === $k ? ' selected' : '') . '>' . $l . '</option>';
    }
    $h .= '</select></label>';
    if ($k === 'custom') {
        $h .= '<label class="d-field" style="margin:0"><span class="d-help">From</span><input type="date" name="from" value="' . e($f) . '"></label><label class="d-field" style="margin:0"><span class="d-help">To</span><input type="date" name="to" value="' . e($t) . '"></label><button class="d-btn d-btn-soft" type="submit">Show</button>';
    }
    return [$f, $t, $h . '</form>'];
};
$send = static function (array $model, string $name, bool $inline = true): never {
    Documents::send($model, preg_replace('/[^A-Za-z0-9_-]+/', '_', $name) . '.pdf', $inline);
};

$app->lab = compact('boot', 'post', 'page', 'badge', 'tone', 'label', 'tbl', 'pf', 'hid', 'tabs', 'dateIn', 'periodBar', 'send');

// ---------------------------------------------------------------- branch switcher
$app->router->post('/portal/lab/branch', static function () use ($app, $boot): void {
    [$p, $L] = $boot();
    $L->switchTo((int) ($_POST['branch'] ?? 0));
    redirect($_SERVER['HTTP_REFERER'] ?? url('portal/lab'));
});

// ---------------------------------------------------------------- dashboard
$app->router->get('/portal/lab', static function () use ($boot, $page, $stat, $card, $btn, $db, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot('dashboard');
    $t = $p->tenantId;
    $today = date('Y-m-d');
    $sc = fn (string $c) => $L->scope($c);
    $n = static fn (string $sql, array $a = []) => (int) $db->val($sql, array_merge([$t], $a));
    $orders = $n("SELECT COUNT(*) FROM sc_lab_orders WHERE tenant_id = ? AND DATE(order_date) = CURDATE() AND status <> 'cancelled' AND " . $sc('branch_id'));
    $pendS = $n("SELECT COUNT(*) FROM sc_lab_samples WHERE tenant_id = ? AND status IN ('pending','recollect') AND " . $sc('branch_id'));
    $toEnter = $n("SELECT COUNT(*) FROM sc_lab_order_items WHERE tenant_id = ? AND status IN ('received','processing') AND report_status IN ('none','draft') AND " . $sc('branch_id'));
    $toApprove = $n("SELECT COUNT(*) FROM sc_lab_order_items WHERE tenant_id = ? AND report_status = 'pending_review' AND " . $sc('branch_id'));
    $ready = $n("SELECT COUNT(*) FROM sc_lab_order_items WHERE tenant_id = ? AND report_status = 'approved' AND " . $sc('branch_id'));
    $fin = $p->can('lab_finance') ? $L->finance($today, $today) : null;
    $month = $p->can('lab_finance') ? $L->finance(date('Y-m-01'), $today) : null;
    $h = '<div class="d-stats">' . $stat('Orders today', (string) $orders, 'file') . $stat('Samples waiting', (string) $pendS, 'clock', 'to collect') . $stat('Results to enter', (string) $toEnter, 'edit') . $stat('Waiting for approval', (string) $toApprove, 'check') . $stat('Approved, not yet delivered', (string) $ready, 'mail') . '</div>';
    if ($fin) {
        $h .= '<div class="d-stats">' . $stat('Income today', money($fin['income']), 'card') . $stat('Expenses today', money($fin['expense_total']), 'card') . $stat('Net today', money($fin['net']), 'chart') . $stat('Net this month', money($month['net']), 'chart', 'income less refunds and expenses') . '</div>';
    }
    $acts = [];
    foreach ([['book', 'Book tests', 'lab_booking'], ['samples', 'Samples', 'lab_samples'], ['results', 'Enter results', 'lab_results'], ['approve', 'Approve reports', 'lab_approve'], ['finance', 'Finance', 'lab_finance'], ['reports', 'Reports', 'lab_reports']] as [$k, $l, $perm]) {
        if ($p->can($perm)) {
            $acts[] = $btn($l, url('portal/lab/' . $k), $k === 'book' ? 'd-btn-primary' : 'd-btn-soft');
        }
    }
    if (!$L->central()) {
        $acts[] = $btn('My branch', url('portal/lab/my-branch'), 'd-btn-soft');
    }
    $h .= '<p class="d-tools" style="gap:8px;flex-wrap:wrap">' . implode(' ', $acts) . '</p>';
    $rows = [];
    foreach ($db->all("SELECT o.id, o.order_no, o.order_date, o.total, o.currency, o.status, o.branch_id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) pn FROM sc_lab_orders o JOIN patients pt ON pt.id = o.patient_id JOIN users u ON u.id = pt.user_id WHERE o.tenant_id = ? AND " . $sc('o.branch_id') . ' ORDER BY o.id DESC LIMIT 8', [$t]) as $r) {
        $rows[] = ['<a href="' . e(url('portal/lab/order/' . $r['id'])) . '">' . e($r['order_no']) . '</a>', e($r['pn']), e($L->branchName((int) $r['branch_id'])), e(money((float) $r['total'], (string) ($r['currency'] ?: $L->branchCurrency((int) $r['branch_id'])))), $badge($label((string) $r['status']), $tone[$r['status']] ?? 'mute')];
    }
    $h .= $card('Latest orders', $tbl([['Order'], ['Patient'], ['Branch'], ['Total', 'R'], ['Status']], $rows, 'No orders yet. Book the first test.'));
    $page($p, $L, 'dashboard', 'Laboratory', $h, $L->current() === 0 ? 'All branches you may open, combined' : $L->branchName($L->current()));
});

// ---------------------------------------------------------------- patients
$patientFields = static function (array $v = []): string {
    $h = '<div class="d-form-grid">'
        . Crud::field('first_name', ['label' => 'Full name', 'required' => true, 'max' => 160], (string) ($v['first_name'] ?? '') . (isset($v['last_name']) && $v['last_name'] ? ' ' . $v['last_name'] : ''))
        . Crud::field('guardian', ['label' => 'Guardian (father, husband)', 'max' => 160], (string) ($v['guardian'] ?? ''))
        . Crud::field('gender', ['label' => 'Sex', 'type' => 'select', 'options' => ['0' => 'Male', '1' => 'Female', '2' => 'Other'], 'required' => true], (string) ($v['gender'] ?? '0'))
        . Crud::field('dob', ['label' => 'Date of birth', 'type' => 'date'], (string) ($v['dob'] ?? ''))
        . Crud::field('age_value', ['label' => 'Age (if no date of birth)', 'type' => 'number', 'step' => '0.1'], (string) ($v['age_value'] ?? ''))
        . Crud::field('age_unit', ['label' => 'Age unit', 'type' => 'select', 'options' => ['years' => 'Years', 'months' => 'Months', 'days' => 'Days'], 'required' => true], (string) ($v['age_unit'] ?? 'years'))
        . Crud::field('phone', ['label' => 'Phone', 'max' => 40], (string) ($v['phone'] ?? ''))
        . Crud::field('whatsapp', ['label' => 'WhatsApp number', 'max' => 40, 'help' => 'Leave empty to use the phone number.'], (string) ($v['whatsapp'] ?? ''))
        . Crud::field('email', ['label' => 'Email', 'max' => 190], ($v['email'] ?? '') && !str_ends_with((string) $v['email'], '@no-login.invalid') ? (string) $v['email'] : '')
        . Crud::field('cnic', ['label' => 'CNIC', 'max' => 40], (string) ($v['cnic'] ?? ''))
        . Crud::field('address', ['label' => 'Address', 'max' => 255, 'wide' => true], (string) ($v['address'] ?? ''))
        . Crud::field('reg_notes', ['label' => 'Notes', 'type' => 'textarea', 'rows' => 2, 'max' => 500], (string) ($v['reg_notes'] ?? ''))
        . '</div>';
    return $h;
};
$app->lab['patientFields'] = $patientFields;
$splitName = static function (string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? '');
    $pos = strpos($full, ' ');
    return $pos === false ? [$full, ''] : [substr($full, 0, $pos), substr($full, $pos + 1)];
};
$app->lab['splitName'] = $splitName;

$app->router->get('/portal/lab/patients', static function () use ($boot, $page, $btn, $db, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot('patients');
    $q = trim((string) ($_GET['q'] ?? ''));
    $w = "pt.tenant_id = ? AND u.deleted_at IS NULL AND " . $L->scope('pt.branch_id');
    $a = [$p->tenantId];
    if ($q !== '') {
        $w .= " AND (pt.patient_unique_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR pt.whatsapp LIKE ? OR pt.cnic LIKE ? OR CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) LIKE ?)";
        array_push($a, ...array_fill(0, 7, '%' . $q . '%'));
    }
    $rows = [];
    foreach ($db->all("SELECT pt.id, pt.patient_unique_id mr, pt.cnic, pt.branch_id, u.first_name, u.last_name, u.phone, u.gender, u.status FROM patients pt JOIN users u ON u.id = pt.user_id WHERE $w ORDER BY pt.id DESC LIMIT 100", $a) as $r) {
        $rows[] = ['<a href="' . e(url('portal/lab/patients/' . $r['id'])) . '">' . e($r['mr']) . '</a>', e(trim($r['first_name'] . ' ' . $r['last_name'])), e(Lab::sexName($r['gender'])), e((string) $r['phone']), e((string) $r['cnic']), e($L->branchName((int) $r['branch_id'])), '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/book', ['patient' => $r['id']])) . '">Book tests</a>'];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/patients')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Search by MR number, name, phone or CNIC</span><input type="search" name="q" value="' . e($q) . '" autocomplete="off"></label><button class="d-btn d-btn-primary" type="submit">Search</button></form>';
    $page($p, $L, 'patients', 'Lab patients', $f . $tbl([['MR number'], ['Name'], ['Sex'], ['Phone'], ['CNIC'], ['Branch'], [' ']], $rows, 'No patients found.', 'users'), 'The same patient list as the clinic. One MR number for every visit.', $btn('Register patient', url('portal/lab/patients/new')));
});
$patientForm = static function (Portal $p, Lab $L, ?array $pt) use ($patientFields, $page, $pf): void {
    $h = '<form class="d-form" method="post" action="' . e(url('portal/lab/patients/save')) . '" autocomplete="off">' . csrf_field() . ($pt ? '<input type="hidden" name="id" value="' . (int) $pt['id'] . '">' : '') . $patientFields($pt ?? []) . '<div class="d-field"><span class="d-help">Branch</span><select name="branch_id">';
    foreach (array_intersect_key($L->branches(true), array_flip($L->allowed())) as $b) {
        $h .= '<option value="' . (int) $b['id'] . '"' . ((int) ($pt['branch_id'] ?? $L->writeBranch(0)) === (int) $b['id'] ? ' selected' : '') . '>' . e($b['name']) . '</option>';
    }
    $h .= '</select></div>' . ($pt ? Crud::field('reason', ['label' => 'Reason for the correction', 'required' => true, 'max' => 190, 'help' => 'Kept in the patient history with the old and new values.'], '') : '') . '<div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e(url('portal/lab/patients')) . '">Back</a><button class="d-btn d-btn-primary" type="submit">Save patient</button></div></form>';
    $page($p, $L, 'patients', $pt ? 'Correct patient details' : 'Register patient', '<section class="d-card">' . $h . '</section>');
};
$app->router->get('/portal/lab/patients/new', static function () use ($boot, $patientForm): void {
    [$p, $L] = $boot('patients');
    $patientForm($p, $L, null);
});
$app->router->get('/portal/lab/patients/{id}', static function (array $a) use ($app, $boot, $page, $card, $btn, $db, $tbl, $badge, $tone, $label, $pf, $patientForm): void {
    [$p, $L] = $boot('patients');
    $pt = $L->patient((int) $a['id']);
    if ($pt === null || !$L->may((int) ($pt['branch_id'] ?: $L->mainId()))) {
        $app->error(404, 'Patient not found.');
    }
    if (($_GET['edit'] ?? '') === '1') {
        $patientForm($p, $L, $pt);
    }
    $t = $p->tenantId;
    $orders = [];
    foreach ($db->all('SELECT * FROM sc_lab_orders WHERE tenant_id = ? AND patient_id = ? ORDER BY id DESC', [$t, (int) $pt['id']]) as $o) {
        $m = $L->money($o);
        $orders[] = ['<a href="' . e(url('portal/lab/order/' . $o['id'])) . '">' . e($o['order_no']) . '</a>', e(dt((string) $o['order_date'])), e(money($m['total'], $m['cur'])), e(money($m['balance'], $m['cur'])), $badge($label((string) $o['status']), $tone[$o['status']] ?? 'mute')];
    }
    $reports = [];
    foreach ($db->all("SELECT i.*, o.order_no FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id WHERE i.tenant_id = ? AND o.patient_id = ? AND i.report_status IN ('approved','delivered') ORDER BY i.id DESC", [$t, (int) $pt['id']]) as $i) {
        $reports[] = [e((string) $i['test_name']), e((string) $i['report_no']), e(dt((string) $i['approved_at'])), '<a href="' . e(url('portal/lab/report/' . $i['id'] . '/pdf')) . '">Open report</a>'];
    }
    $hist = [];
    foreach ($db->all("SELECT h.*, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) un FROM sc_record_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.tenant_id = ? AND h.entity = 'patient' AND h.entity_id = ? ORDER BY h.id DESC LIMIT 20", [$t, (int) $pt['id']]) as $h) {
        $hist[] = [e(dt((string) $h['created_at'], 'd M Y H:i')), e((string) $h['action']), e((string) $h['un']), e((string) $h['reason']), e(mb_substr((string) $h['after_json'], 0, 120))];
    }
    $info = '<div class="d-kv"><p><b>MR number</b> ' . e($pt['mr']) . '</p><p><b>Name</b> ' . e($pt['name']) . '</p><p><b>Guardian</b> ' . e((string) $pt['guardian']) . '</p><p><b>Age and sex</b> ' . e(Lab::ageText($pt) . ' ' . Lab::sexName($pt['gender'])) . '</p><p><b>Phone</b> ' . e((string) $pt['phone']) . '</p><p><b>WhatsApp</b> ' . e((string) $pt['whatsapp']) . '</p><p><b>Email</b> ' . e(Lab::realEmail($pt)) . '</p><p><b>CNIC</b> ' . e((string) $pt['cnic']) . '</p><p><b>Address</b> ' . e((string) $pt['address']) . '</p><p><b>Notes</b> ' . e((string) $pt['reg_notes']) . '</p></div><p>' . $btn('Book tests', url('portal/lab/book', ['patient' => $pt['id']])) . ' ' . $btn('Correct details', url('portal/lab/patients/' . $pt['id'], ['edit' => 1]), 'd-btn-soft') . '</p>';
    $page($p, $L, 'patients', $pt['name'], $card('Patient', $info) . $card('Orders and bills', $tbl([['Order'], ['Date'], ['Total', 'R'], ['Balance', 'R'], ['Status']], $orders, 'No lab orders yet.')) . $card('Approved reports', $tbl([['Test'], ['Report'], ['Approved'], [' ']], $reports, 'No approved reports yet.')) . $card('Corrections and history', $tbl([['When'], ['What'], ['Who'], ['Reason'], ['Change']], $hist, 'No changes yet.')), 'MR ' . $pt['mr']);
});
$app->router->post('/portal/lab/patients/save', static function () use ($boot, $post, $splitName): void {
    [$p, $L] = $boot('patients');
    $post($p);
    $id = (int) ($_POST['id'] ?? 0);
    [$f, $l] = $splitName((string) ($_POST['first_name'] ?? ''));
    $d = $_POST;
    $d['first_name'] = $f;
    $d['last_name'] = $l;
    $branch = $L->writeBranch((int) ($_POST['branch_id'] ?? 0));
    if ($id === 0) {
        $r = $L->registerPatient($d, $branch);
        if (is_string($r)) {
            keep_old($_POST);
            flash($r, 'bad');
            redirect(url('portal/lab/patients/new'));
        }
        flash('Patient registered with MR number ' . $L->patient($r)['mr'] . '.', 'ok');
        redirect(url('portal/lab/patients/' . $r));
    }
    $old = $L->patient($id);
    if ($old === null || !$L->may((int) ($old['branch_id'] ?: $L->mainId()))) {
        redirect(url('portal/lab/patients'));
    }
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '' || $f === '') {
        flash('Please enter the name and the reason for the correction.', 'bad');
        redirect(url('portal/lab/patients/' . $id, ['edit' => 1]));
    }
    $db = app()->db;
    $new = ['first_name' => $f, 'last_name' => $l, 'gender' => (string) max(0, min(2, (int) ($_POST['gender'] ?? 0))), 'dob' => (string) ($_POST['dob'] ?? ''), 'phone' => trim((string) ($_POST['phone'] ?? ''))] + array_map('strval', $L->extraCols($d));
    [$b, $a] = Lab::diff($old, $new);
    $db->run('UPDATE users SET first_name = ?, last_name = ?, gender = ?, dob = ?, phone = ?, updated_at = NOW() WHERE id = ?', [$f, $l ?: null, (int) $new['gender'], $new['dob'] !== '' ? $new['dob'] : null, $new['phone'] ?: null, (int) $old['user_id']]);
    $x = $L->extraCols($d);
    $x['branch_id'] = $branch;
    $db->update('patients', $x, 'id = ?', [$id]);
    $em = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($em !== '' && $em !== (string) $old['email'] && filter_var($em, FILTER_VALIDATE_EMAIL) && (int) $db->val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$em, (int) $old['user_id']]) === 0) {
        $db->run('UPDATE users SET email = ? WHERE id = ?', [$em, (int) $old['user_id']]);
        $a['email'] = $em;
        $b['email'] = $old['email'];
    }
    $L->log('patient', $id, 'edited', $b, $a, $reason, $branch);
    flash('Patient updated. The correction is in the history.', 'ok');
    redirect(url('portal/lab/patients/' . $id));
});

// ---------------------------------------------------------------- booking
$app->router->get('/portal/lab/book', static function () use ($boot, $page, $card, $btn, $db, $patientFields, $tbl): void {
    [$p, $L] = $boot('book');
    if (isset($_GET['branch']) && $L->may((int) $_GET['branch'])) {
        $L->switchTo((int) $_GET['branch']);
    }
    $branch = $L->writeBranch(0);
    $pt = ($id = (int) ($_GET['patient'] ?? 0)) > 0 ? $L->patient($id) : null;
    $q = trim((string) ($_GET['q'] ?? ''));
    $h = '';
    if ($pt === null) {
        $h .= '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/book')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Find an existing patient: MR number, name, phone or CNIC</span><input type="search" name="q" value="' . e($q) . '" autocomplete="off"></label><button class="d-btn d-btn-primary" type="submit">Search</button></form>';
        if ($q !== '') {
            $rows = [];
            foreach ($db->all("SELECT pt.id, pt.patient_unique_id mr, pt.cnic, u.first_name, u.last_name, u.phone FROM patients pt JOIN users u ON u.id = pt.user_id WHERE pt.tenant_id = ? AND u.deleted_at IS NULL AND " . $L->scope('pt.branch_id') . " AND (pt.patient_unique_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR pt.cnic LIKE ? OR CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) LIKE ?) LIMIT 20", array_merge([$p->tenantId], array_fill(0, 6, '%' . $q . '%'))) as $r) {
                $rows[] = [e($r['mr']), e(trim($r['first_name'] . ' ' . $r['last_name'])), e((string) $r['phone']), e((string) $r['cnic']), '<a class="d-btn d-btn-primary" href="' . e(url('portal/lab/book', ['patient' => $r['id']])) . '">Book for this patient</a>'];
            }
            $h .= $tbl([['MR number'], ['Name'], ['Phone'], ['CNIC'], [' ']], $rows, 'Nobody found. Register the patient below.', 'users');
        }
    }
    $tests = $L->testsFor($branch);
    $hm = ($hid = (int) ($_GET['home'] ?? 0)) > 0 ? $db->one('SELECT * FROM sc_lab_home WHERE id = ? AND tenant_id = ? AND order_id IS NULL', [$hid, $p->tenantId]) : null;
    $f = '<form class="d-form" method="post" action="' . e(url('portal/lab/book')) . '" autocomplete="off" data-lab-book>' . csrf_field() . ($hm ? '<input type="hidden" name="home_id" value="' . (int) $hm['id'] . '">' : '');
    if ($pt) {
        $f .= '<input type="hidden" name="patient_id" value="' . (int) $pt['id'] . '"><p class="d-info"><b>' . e($pt['name']) . '</b> · MR ' . e($pt['mr']) . ' · ' . e(Lab::ageText($pt) . ' ' . Lab::sexName($pt['gender'])) . ' · ' . e((string) $pt['phone']) . ' <a href="' . e(url('portal/lab/book')) . '">Change patient</a></p>';
    } else {
        $f .= '<h3>New patient</h3>' . $patientFields($hm ? ['first_name' => $hm['name'], 'phone' => $hm['phone'], 'address' => $hm['address']] : []);
    }
    $f .= '<h3>Branch and tests</h3><div class="d-form-grid"><div class="d-field"><span class="d-help">Branch</span><select name="branch_id" onchange="location.href=\'' . e(url('portal/lab/book')) . '?' . ($pt ? 'patient=' . (int) $pt['id'] . '&' : '') . 'branch=\'+this.value">';
    foreach (array_intersect_key($L->branches(true), array_flip($L->allowed())) as $b) {
        $f .= '<option value="' . (int) $b['id'] . '"' . ($branch === (int) $b['id'] ? ' selected' : '') . '>' . e($b['name']) . '</option>';
    }
    $f .= '</select></div><label class="d-field"><span class="d-help">Filter the test list</span><input type="search" data-test-filter placeholder="Type a test name or code"></label></div><div class="lab-tests">';
    $dept = null;
    foreach ($tests as $t) {
        if ($t['dept'] !== $dept) {
            $dept = $t['dept'];
            $f .= '<h4 class="lab-dept">' . e((string) ($dept ?: 'Other')) . '</h4>';
        }
        $f .= '<label class="d-check lab-test" data-name="' . e(strtolower($t['name'] . ' ' . $t['code'])) . '"><input type="checkbox" name="test_ids[]" value="' . (int) $t['id'] . '" data-price="' . e((string) $t['eff_price']) . '"><span>' . e($t['name']) . ' <small>' . e((string) $t['code']) . ' · ' . e(money((float) $t['eff_price'])) . ($t['approved'] ? '' : ' · price pending approval') . '</small></span></label>';
    }
    if (!$tests) {
        $f .= '<div class="d-empty"><h3>No tests in the catalogue for this branch</h3></div>';
    }
    $f .= '</div>';
    $docs = $L->referrers('doctor');
    $partners = $L->referrers('partner');
    $ref = '<option value="none">Walk in</option><optgroup label="Doctors">';
    foreach ($docs as $r) {
        $ref .= '<option value="doctor:' . (int) $r['id'] . '">' . e($r['name']) . '</option>';
    }
    $ref .= '</optgroup><optgroup label="Partner laboratories">';
    foreach ($partners as $r) {
        $ref .= '<option value="partner:' . (int) $r['id'] . '">' . e($r['name']) . '</option>';
    }
    $f .= '<h3>Referral, collection and payment</h3><div class="d-form-grid"><label class="d-field"><span class="d-help">Referral source</span><select name="referral">' . $ref . '</optgroup></select></label>'
        . Crud::field('collection', ['label' => 'Sample collection', 'type' => 'select', 'options' => ['lab' => 'At the lab', 'home' => 'Home collection'], 'required' => true], $hm ? 'home' : 'lab')
        . Crud::field('home_charge', ['label' => 'Home collection charge', 'type' => 'number', 'step' => '0.01'], $hm ? (string) $hm['charge'] : '0')
        . Crud::field('discount', ['label' => 'Discount', 'type' => 'number', 'step' => '0.01'], '0')
        . Crud::field('discount_reason', ['label' => 'Discount reason', 'max' => 190], '')
        . Crud::field('paid_now', ['label' => 'Paid now', 'type' => 'number', 'step' => '0.01'], '0')
        . Crud::field('method', ['label' => 'Payment method', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash')
        . Crud::field('reference', ['label' => 'Reference (transaction id, cheque)', 'max' => 120], '')
        . Crud::field('clinical_note', ['label' => 'Clinical note', 'type' => 'textarea', 'rows' => 2, 'max' => 500], '')
        . '</div><p class="lab-total d-info">Tests selected: <b data-count>0</b> · Subtotal <b data-sub>0</b></p><div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e(url('portal/lab/orders')) . '">Cancel</a><button class="d-btn d-btn-primary" type="submit">Book and bill</button></div></form>'
        . '<script>(function(){var f=document.querySelector("[data-lab-book]");if(!f)return;var c=f.querySelectorAll("input[name=\'test_ids[]\']"),n=f.querySelector("[data-count]"),s=f.querySelector("[data-sub]");function u(){var k=0,t=0;c.forEach(function(i){if(i.checked){k++;t+=parseFloat(i.dataset.price||0)}});n.textContent=k;s.textContent=t.toFixed(2)}c.forEach(function(i){i.addEventListener("change",u)});var q=f.querySelector("[data-test-filter]");q.addEventListener("input",function(){var v=q.value.toLowerCase();f.querySelectorAll(".lab-test").forEach(function(l){l.style.display=l.dataset.name.indexOf(v)<0?"none":""})});})();</script>';
    $h .= $card('', $f);
    $page($p, $L, 'book', 'Book tests', $h, 'One booking creates the order, bill, samples and commission.');
});
$app->router->post('/portal/lab/book', static function () use ($boot, $post, $splitName, $db): void {
    [$p, $L] = $boot('book');
    $post($p);
    $branch = $L->writeBranch((int) ($_POST['branch_id'] ?? 0));
    $pid = (int) ($_POST['patient_id'] ?? 0);
    if ($pid === 0) {
        [$f, $l] = $splitName((string) ($_POST['first_name'] ?? ''));
        $d = $_POST;
        $d['first_name'] = $f;
        $d['last_name'] = $l;
        $r = $L->registerPatient($d, $branch);
        if (is_string($r)) {
            keep_old($_POST);
            flash($r, 'bad');
            redirect(url('portal/lab/book'));
        }
        $pid = $r;
    } elseif ($L->patient($pid) === null) {
        redirect(url('portal/lab/book'));
    }
    // duplicate billing guard: the same patient and the same tests within two minutes
    $ids = array_values(array_unique(array_map('intval', (array) ($_POST['test_ids'] ?? []))));
    sort($ids);
    $sig = md5($pid . '|' . implode(',', $ids));
    if (($_SESSION['lab_last_book'][$p->tenantId] ?? '') === $sig && time() - (int) ($_SESSION['lab_last_book_at'] ?? 0) < 120) {
        flash('This booking was just saved. To avoid billing twice it was not saved again.', 'bad');
        redirect(url('portal/lab/orders'));
    }
    [$kind, $rid] = array_pad(explode(':', (string) ($_POST['referral'] ?? 'none')), 2, '0');
    $in = $_POST + [];
    $in['patient_id'] = $pid;
    $in['branch_id'] = $branch;
    $in['referral_type'] = in_array($kind, ['doctor', 'partner'], true) ? $kind : 'none';
    $in['referrer_id'] = (int) $rid;
    $r = $L->createOrder($in + ['home_id' => (int) ($_POST['home_id'] ?? 0)]);
    if (is_string($r)) {
        keep_old($_POST);
        flash($r, 'bad');
        redirect(url('portal/lab/book', $pid ? ['patient' => $pid] : []));
    }
    if (!empty($_POST['home_id'])) {
        $hr = $db->one('SELECT * FROM sc_lab_home WHERE id = ? AND tenant_id = ? AND order_id IS NULL', [(int) $_POST['home_id'], $p->tenantId]);
        if ($hr) {
            $db->run('UPDATE sc_lab_home SET order_id = ?, patient_id = ? WHERE id = ?', [$r, $pid, (int) $hr['id']]);
            if ($hr['status'] === 'collected') {
                foreach ($db->all("SELECT * FROM sc_lab_samples WHERE order_id = ? AND status = 'pending'", [$r]) as $s) {
                    $L->sampleStep($s, 'collect');
                }
            }
        }
    }
    $_SESSION['lab_last_book'][$p->tenantId] = $sig;
    $_SESSION['lab_last_book_at'] = time();
    flash('Booked. Order ' . $L->order($r)['order_no'] . ' with its bill and samples is ready.', 'ok');
    redirect(url('portal/lab/order/' . $r));
});

// ---------------------------------------------------------------- orders
$app->router->get('/portal/lab/orders', static function () use ($boot, $page, $btn, $db, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot('orders');
    $q = trim((string) ($_GET['q'] ?? ''));
    $st = (string) ($_GET['status'] ?? '');
    $w = 'o.tenant_id = ? AND ' . $L->scope('o.branch_id');
    $a = [$p->tenantId];
    if ($q !== '') {
        $w .= " AND (o.order_no LIKE ? OR o.invoice_no LIKE ? OR pt.patient_unique_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
        array_push($a, ...array_fill(0, 6, '%' . $q . '%'));
    }
    if (in_array($st, ['booked', 'cancelled'], true)) {
        $w .= ' AND o.status = ?';
        $a[] = $st;
    }
    $rows = [];
    foreach ($db->all("SELECT o.*, pt.patient_unique_id mr, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) pn FROM sc_lab_orders o JOIN patients pt ON pt.id = o.patient_id JOIN users u ON u.id = pt.user_id WHERE $w ORDER BY o.id DESC LIMIT 100", $a) as $o) {
        $m = $L->money($o);
        $rows[] = ['<a href="' . e(url('portal/lab/order/' . $o['id'])) . '">' . e($o['order_no']) . '</a>', e(dt((string) $o['order_date'], 'd M H:i')), e($o['pn'] . ' · ' . $o['mr']), e($L->branchName((int) $o['branch_id'])), e(money($m['total'], $m['cur'])), e(money($m['balance'], $m['cur'])), $badge($label((string) $o['status']), $tone[$o['status']] ?? 'mute')];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/orders')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Order, invoice, MR number, name or phone</span><input type="search" name="q" value="' . e($q) . '"></label><label class="d-field" style="margin:0"><span class="d-help">Status</span><select name="status"><option value="">All</option><option value="booked"' . ($st === 'booked' ? ' selected' : '') . '>Booked</option><option value="cancelled"' . ($st === 'cancelled' ? ' selected' : '') . '>Cancelled</option></select></label><button class="d-btn d-btn-primary" type="submit">Filter</button></form>';
    $page($p, $L, 'orders', 'Lab orders', $f . $tbl([['Order'], ['Booked'], ['Patient'], ['Branch'], ['Total', 'R'], ['Due', 'R'], ['Status']], $rows, 'No orders match.'), '', $btn('Book tests', url('portal/lab/book')));
});

$loadOrder = static function (Portal $p, Lab $L, int $id) use ($app): array {
    $o = $L->orderFor($id);
    if ($o === null) {
        $app->error(404, 'Order not found.');
    }
    return $o;
};
$app->lab['loadOrder'] = $loadOrder;

$app->router->get('/portal/lab/order/{id}', static function (array $a) use ($boot, $loadOrder, $page, $card, $btn, $db, $tbl, $badge, $tone, $label, $pf, $hid): void {
    [$p, $L] = $boot('order');
    $o = $loadOrder($p, $L, (int) $a['id']);
    $pt = $L->patient((int) $o['patient_id']);
    $m = $L->money($o);
    \Selfcare\Currency::$context = $m['cur'];
    $id = (int) $o['id'];
    $base = 'portal/lab/order/' . $id;
    $items = [];
    foreach ($L->items($id) as $i) {
        $act = '';
        if ($i['report_status'] === 'approved' || $i['report_status'] === 'delivered') {
            $act = '<a href="' . e(url('portal/lab/report/' . $i['id'] . '/pdf')) . '">Report PDF</a>';
        }
        $items[] = [e((string) $i['test_name']), e((string) $i['specimen']), e(money((float) $i['net'])), $badge($label((string) $i['status']), $tone[$i['status']] ?? 'mute'), $badge($label((string) $i['report_status']), $tone[$i['report_status']] ?? 'mute'), $act];
    }
    $samples = [];
    foreach ($db->all('SELECT * FROM sc_lab_samples WHERE tenant_id = ? AND order_id = ?', [$p->tenantId, $id]) as $s) {
        $samples[] = [e((string) $s['barcode']), e((string) $s['specimen']), $badge($label((string) $s['status']), $tone[$s['status']] ?? 'mute'), e((string) $s['reason'])];
    }
    $pays = [];
    foreach ($db->all('SELECT * FROM sc_invoice_payments WHERE invoice_id = ? ORDER BY id', [(int) $o['invoice_id']]) as $y) {
        $pays[] = [e(dt((string) $y['paid_at'], 'd M Y H:i')), e((string) $y['receipt_no']), e((string) $y['method']), e(money((float) $y['amount'])), e((string) $y['note'])];
    }
    $ref = $o['referrer_id'] ? (string) $db->val('SELECT name FROM sc_referrers WHERE id = ?', [(int) $o['referrer_id']]) : 'Walk in';
    $head = '<div class="d-kv"><p><b>Patient</b> <a href="' . e(url('portal/lab/patients/' . $pt['id'])) . '">' . e($pt['name']) . '</a> · MR ' . e($pt['mr']) . '</p><p><b>Branch</b> ' . e($L->branchName((int) $o['branch_id'])) . '</p><p><b>Referred by</b> ' . e($ref) . '</p><p><b>Collection</b> ' . ($o['collection'] === 'home' ? 'Home' : 'At the lab') . '</p><p><b>Bill</b> ' . e((string) $o['invoice_no']) . '</p><p><b>Status</b> ' . $badge($label((string) $o['status']), $tone[$o['status']] ?? 'mute') . '</p></div>';
    $money = '<div class="d-stats"><div class="d-stat"><span>Total</span><b>' . e(money($m['total'])) . '</b></div><div class="d-stat"><span>Received</span><b>' . e(money($m['paid'])) . '</b></div><div class="d-stat"><span>Refunded</span><b>' . e(money($m['refunded'])) . '</b></div><div class="d-stat"><span>Due</span><b>' . e(money($m['balance'])) . '</b></div></div>';
    $print = '<p class="d-tools" style="gap:8px;flex-wrap:wrap"><a class="d-btn d-btn-soft" href="' . e(url($base . '/slip')) . '">Booking slip</a><a class="d-btn d-btn-soft" href="' . e(url($base . '/receipt')) . '">Receipt</a><a class="d-btn d-btn-soft" href="' . e(url($base . '/invoice')) . '">Invoice</a>';
    $anyApproved = (int) $db->val("SELECT COUNT(*) FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered')", [$id]) > 0;
    if ($anyApproved) {
        $print .= '<a class="d-btn d-btn-soft" href="' . e(url($base . '/report')) . '">Full report PDF</a>';
        if ($p->can('lab_share')) {
            $print .= '<a class="d-btn d-btn-primary" href="' . e(url('portal/lab/share/' . $id)) . '">Share report</a>';
        }
    }
    if ($p->can('lab_share')) {
        $print .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/share/' . $id, ['doc' => 'receipt'])) . '">Share receipt</a>';
    }
    $print .= '</p>';
    $act = '';
    if ($o['status'] !== 'cancelled' && $p->writable()) {
        if ($m['balance'] > 0) {
            $act .= '<form class="d-form" method="post" action="' . e(url($base . '/pay')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('amount', ['label' => 'Take payment', 'type' => 'number', 'step' => '0.01'], (string) $m['balance']) . Crud::field('method', ['label' => 'Method', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . Crud::field('reference', ['label' => 'Reference', 'max' => 120], '') . '</div><button class="d-btn d-btn-primary" type="submit">Receive payment</button></form>';
        }
        if ($m['paid'] - $m['refunded'] > 0 && $p->can('lab_booking') && $p->canDo('lab_booking', 'delete')) {
            $act .= '<form class="d-form" method="post" action="' . e(url($base . '/refund')) . '" data-confirm="Refund this amount to the patient?">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('amount', ['label' => 'Refund amount', 'type' => 'number', 'step' => '0.01'], '') . Crud::field('method', ['label' => 'Paid back by', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . Crud::field('reason', ['label' => 'Reason', 'required' => true, 'max' => 190], '') . '</div><button class="d-btn d-btn-soft" type="submit">Refund</button></form>';
        }
        if ($p->canDo('lab_booking', 'delete')) {
            $act .= '<form class="d-form" method="post" action="' . e(url($base . '/cancel')) . '" data-confirm="Cancel this order? Money received is refunded and commission is reversed.">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('reason', ['label' => 'Reason for cancelling', 'required' => true, 'max' => 190], '') . Crud::field('method', ['label' => 'Refund by', 'type' => 'select', 'options' => array_combine(Lab::METHODS, Lab::METHODS), 'required' => true], 'Cash') . '</div><button class="d-btn d-btn-danger" type="submit">Cancel order</button></form>';
        }
        if ($p->canDo('lab_booking', 'edit') && !(int) $db->val("SELECT COUNT(*) FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered')", [$id])) {
            $opts = '';
            foreach ($L->testsFor((int) $o['branch_id']) as $t) {
                $opts .= '<option value="' . (int) $t['id'] . '">' . e($t['name'] . ' · ' . money((float) $t['eff_price'])) . '</option>';
            }
            $act .= '<form class="d-form" method="post" action="' . e(url($base . '/add-test')) . '">' . csrf_field() . '<div class="d-form-grid"><label class="d-field"><span class="d-help">Add a test to this order</span><select name="test_id" data-searchable>' . $opts . '</select></label></div><button class="d-btn d-btn-soft" type="submit">Add test</button></form>';
        }
    }
    $page($p, $L, 'orders', 'Order ' . $o['order_no'], $card('Order', $head . $money . $print) . $card('Tests', $tbl([['Test'], ['Specimen'], ['Net', 'R'], ['Sample'], ['Report'], [' ']], $items, 'No tests.')) . $card('Samples', $tbl([['Barcode'], ['Specimen'], ['Status'], ['Note']], $samples, 'No samples.')) . $card('Payments', $tbl([['Date'], ['Receipt'], ['Method'], ['Amount', 'R'], ['Note']], $pays, 'Nothing received yet.')) . ($act !== '' ? $card('Actions', $act) : ''), $o['order_no'] . ' · ' . dt((string) $o['order_date'], 'd M Y H:i'));
});
$app->router->post('/portal/lab/order/{id}/pay', static function (array $a) use ($boot, $post, $loadOrder): void {
    [$p, $L] = $boot('order');
    $post($p);
    $o = $loadOrder($p, $L, (int) $a['id']);
    $err = $L->pay($o, (float) ($_POST['amount'] ?? 0), (string) ($_POST['method'] ?? 'Cash'), trim((string) ($_POST['reference'] ?? '')) ?: null);
    flash($err ?? 'Payment received.', $err ? 'bad' : 'ok');
    redirect(url('portal/lab/order/' . (int) $o['id']));
});
$app->router->post('/portal/lab/order/{id}/refund', static function (array $a) use ($boot, $post, $loadOrder): void {
    [$p, $L] = $boot('order');
    $post($p);
    if (!$p->canDo('lab_booking', 'delete')) {
        flash('Refunds need the delete permission.', 'bad');
        redirect(url('portal/lab/order/' . (int) $a['id']));
    }
    $o = $loadOrder($p, $L, (int) $a['id']);
    $err = $L->refund($o, (float) ($_POST['amount'] ?? 0), (string) ($_POST['method'] ?? 'Cash'), (string) ($_POST['reason'] ?? ''));
    flash($err ?? 'Refund recorded. Commission was adjusted.', $err ? 'bad' : 'ok');
    redirect(url('portal/lab/order/' . (int) $o['id']));
});
$app->router->post('/portal/lab/order/{id}/cancel', static function (array $a) use ($boot, $post, $loadOrder): void {
    [$p, $L] = $boot('order');
    $post($p);
    if (!$p->canDo('lab_booking', 'delete')) {
        flash('Cancelling needs the delete permission.', 'bad');
        redirect(url('portal/lab/order/' . (int) $a['id']));
    }
    $o = $loadOrder($p, $L, (int) $a['id']);
    $err = $L->cancelOrder($o, (string) ($_POST['reason'] ?? ''), (string) ($_POST['method'] ?? 'Cash'));
    flash($err ?? 'Order cancelled. Received money was refunded and commission reversed.', $err ? 'bad' : 'ok');
    redirect(url('portal/lab/order/' . (int) $o['id']));
});
$app->router->post('/portal/lab/order/{id}/add-test', static function (array $a) use ($boot, $post, $loadOrder, $db): void {
    [$p, $L] = $boot('order');
    $post($p);
    $o = $loadOrder($p, $L, (int) $a['id']);
    $t = $db->one('SELECT t.*, d.name dept FROM sc_lab_tests t LEFT JOIN sc_lab_departments d ON d.id = t.dept_id WHERE t.id = ? AND t.tenant_id = ? AND t.active = 1', [(int) ($_POST['test_id'] ?? 0), $p->tenantId]);
    if ($t === null || $o['status'] === 'cancelled' || !$p->canDo('lab_booking', 'edit')) {
        flash('That test cannot be added.', 'bad');
        redirect(url('portal/lab/order/' . (int) $o['id']));
    }
    $t['eff_price'] = $L->priceIn($t, (int) $o['branch_id'])['price'];
    $L->addItems((int) $o['id'], (int) $o['branch_id'], [$t], 0.0, 0.0, $o['collection'] === 'home');
    $L->resync((int) $o['id'], 'Test added');
    $L->log('order', (int) $o['id'], 'edited', null, ['added' => $t['name']], null, (int) $o['branch_id']);
    flash('Test added. The bill and commission were updated.', 'ok');
    redirect(url('portal/lab/order/' . (int) $o['id']));
});
foreach (['slip', 'receipt', 'invoice', 'report'] as $doc) {
    $app->router->get('/portal/lab/order/{id}/' . $doc, static function (array $a) use ($app, $boot, $loadOrder, $send, $db, $doc): void {
        [$p, $L] = $boot('order');
        $o = $loadOrder($p, $L, (int) $a['id']);
        if (!$p->canDo('lab_booking', 'print')) {
            $app->error(403, 'Your role is not allowed to print.', 'No access');
        }
        $D = new LabDocs($L);
        $mark = $D->mark('doc_' . $doc, (int) $o['id'], (int) $o['branch_id']);
        if ($doc === 'slip') {
            $send($D->slip($o, $mark), $o['order_no'] . '-slip');
        }
        if ($doc === 'receipt') {
            $send($D->receipt($o, $L->lastReceipt($o), $mark), $o['order_no'] . '-receipt');
        }
        if ($doc === 'invoice') {
            $inv = $db->one('SELECT * FROM sc_invoices WHERE id = ? AND tenant_id = ?', [(int) $o['invoice_id'], $p->tenantId]);
            $m = (new Billing($db))->invoiceModel($app, $inv, $p->user);
            $m['status'] = $mark;
            Documents::send($m, preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $o['invoice_no']) . '.pdf', true);
        }
        if (!$p->can('lab_results') && !$p->can('lab_approve')) {
            $app->error(403, 'Your role cannot open reports.', 'No access');
        }
        $items = $db->all("SELECT * FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered') ORDER BY id", [(int) $o['id']]);
        if (!$items) {
            $app->error(404, 'No approved report on this order yet.');
        }
        $send($D->report($o, $items, $mark), $o['order_no'] . '-report');
    });
}

// ---------------------------------------------------------------- samples
$app->router->get('/portal/lab/samples', static function () use ($boot, $page, $db, $tbl, $badge, $tone, $label, $pf, $hid): void {
    [$p, $L] = $boot('samples');
    $st = (string) ($_GET['status'] ?? 'open');
    $w = 's.tenant_id = ? AND ' . $L->scope('s.branch_id');
    $w .= match ($st) { 'all' => '', 'open' => " AND s.status IN ('pending','collected','received','processing','recollect')", default => ' AND s.status = ' . $db->quote($st) };
    $q = trim((string) ($_GET['q'] ?? ''));
    $a = [$p->tenantId];
    if ($q !== '') {
        $w .= " AND (s.barcode LIKE ? OR o.order_no LIKE ? OR u.first_name LIKE ? OR pt.patient_unique_id LIKE ?)";
        array_push($a, ...array_fill(0, 4, '%' . $q . '%'));
    }
    $rows = [];
    foreach ($db->all("SELECT s.*, o.order_no, o.patient_id, pt.patient_unique_id mr, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) pn FROM sc_lab_samples s JOIN sc_lab_orders o ON o.id = s.order_id JOIN patients pt ON pt.id = o.patient_id JOIN users u ON u.id = pt.user_id WHERE $w AND o.status <> 'cancelled' ORDER BY s.id DESC LIMIT 150", $a) as $s) {
        $act = '';
        $sid = (int) $s['id'];
        $go = static fn (string $step, string $lab, string $cls = 'd-btn-soft') => $pf(url('portal/lab/samples/' . $sid . '/' . $step), $lab, $cls);
        $stt = (string) $s['status'];
        if ($stt === 'pending' || $stt === 'recollect') {
            $act .= $go('collect', 'Collected', 'd-btn-primary');
        } elseif ($stt === 'collected') {
            $act .= $go('receive', 'Receive in lab', 'd-btn-primary');
        } elseif ($stt === 'received') {
            $act .= $go('process', 'Start processing', 'd-btn-primary');
        }
        if (!in_array($stt, ['completed', 'rejected'], true)) {
            $act .= '<form class="d-inline" method="post" action="' . e(url('portal/lab/samples/' . $sid . '/reject')) . '">' . csrf_field() . '<input name="reason" placeholder="Reason" required maxlength="190" style="width:9rem"><button class="d-btn d-btn-soft" type="submit">Reject</button><button class="d-btn d-btn-soft" type="submit" formaction="' . e(url('portal/lab/samples/' . $sid . '/recollect')) . '">Recollect</button></form>';
        }
        $act .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/samples/' . $sid . '/label')) . '">Label</a>';
        $rows[] = [e((string) $s['barcode']), e($s['pn'] . ' · ' . $s['mr']), '<a href="' . e(url('portal/lab/order/' . $s['order_id'])) . '">' . e($s['order_no']) . '</a>', e((string) $s['specimen']), e($s['collection'] === 'home' ? 'Home' : 'Lab'), e($L->branchName((int) $s['branch_id'])), $badge($label($stt), $tone[$stt] ?? 'mute') . ($s['reason'] ? '<br><small>' . e((string) $s['reason']) . '</small>' : ''), $act];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/samples')) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Barcode, order, name or MR</span><input type="search" name="q" value="' . e($q) . '"></label><label class="d-field" style="margin:0"><span class="d-help">Show</span><select name="status" onchange="this.form.submit()">';
    foreach (['open' => 'Open samples', 'pending' => 'Pending', 'collected' => 'Collected', 'received' => 'Received', 'processing' => 'Processing', 'completed' => 'Completed', 'rejected' => 'Rejected', 'recollect' => 'Recollection required', 'all' => 'All'] as $k => $l) {
        $f .= '<option value="' . $k . '"' . ($k === $st ? ' selected' : '') . '>' . $l . '</option>';
    }
    $f .= '</select></label><button class="d-btn d-btn-primary" type="submit">Find</button></form>';
    $page($p, $L, 'samples', 'Samples', $f . $tbl([['Barcode'], ['Patient'], ['Order'], ['Specimen'], ['Where'], ['Branch'], ['Status'], [' ']], $rows, 'No samples here.', 'clock'), 'Pending, collected, received, processing, completed, rejected, recollection required.');
});
$app->router->post('/portal/lab/samples/{id}/{act}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('samples');
    $post($p);
    $s = $db->one('SELECT * FROM sc_lab_samples WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($s === null || !$L->may((int) $s['branch_id'])) {
        redirect(url('portal/lab/samples'));
    }
    $err = $L->sampleStep($s, (string) $a['act'], (string) ($_POST['reason'] ?? ''));
    flash($err ?? 'Sample updated.', $err ? 'bad' : 'ok');
    redirect($_SERVER['HTTP_REFERER'] ?? url('portal/lab/samples'));
});
$app->router->get('/portal/lab/samples/{id}/label', static function (array $a) use ($app, $boot, $send, $db): void {
    [$p, $L] = $boot('samples');
    $s = $db->one('SELECT * FROM sc_lab_samples WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($s === null || !$L->may((int) $s['branch_id'])) {
        $app->error(404, 'Sample not found.');
    }
    $send((new LabDocs($L))->sampleLabel($s, $L->order((int) $s['order_id'])), (string) $s['barcode']);
});

// ---------------------------------------------------------------- home collection
$app->router->get('/portal/lab/home', static function () use ($boot, $page, $card, $btn, $db, $tbl, $badge, $tone, $label, $pf, $patientFields): void {
    [$p, $L] = $boot('home');
    $t = $p->tenantId;
    $collectors = [];
    foreach ($db->all("SELECT u.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n FROM users u WHERE u.tenant_id = ? AND u.deleted_at IS NULL AND u.status = 1 AND u.department_id IN (SELECT id FROM departments WHERE (tenant_id = ? OR tenant_id IS NULL) AND name IN ('Phlebotomist', 'Sample Collector', 'Lab Technician', 'Attendant'))", [$t, $t]) as $c) {
        $collectors[(int) $c['id']] = $c['n'];
    }
    $rows = [];
    foreach ($db->all('SELECT h.* FROM sc_lab_home h WHERE h.tenant_id = ? AND ' . $L->scope('h.branch_id') . ' ORDER BY FIELD(h.status, \'scheduled\', \'assigned\', \'collected\'), h.collect_at DESC LIMIT 100', [$t]) as $h) {
        $act = '';
        $hid = (int) $h['id'];
        if ($h['status'] === 'scheduled' || $h['status'] === 'assigned') {
            $opts = '<option value="">Choose collector</option>';
            foreach ($collectors as $id => $n) {
                $opts .= '<option value="' . $id . '"' . ((int) $h['collector_id'] === $id ? ' selected' : '') . '>' . e($n) . '</option>';
            }
            $act .= '<form class="d-inline" method="post" action="' . e(url('portal/lab/home/' . $hid . '/assign')) . '">' . csrf_field() . '<select name="collector_id" style="width:9rem">' . $opts . '</select><button class="d-btn d-btn-soft" type="submit">Assign</button></form>';
            if ($h['status'] === 'assigned') {
                $act .= $pf(url('portal/lab/home/' . $hid . '/collected'), 'Mark collected', 'd-btn-primary');
            }
        }
        if (!$h['order_id'] && $h['status'] !== 'cancelled' && $p->can('lab_booking')) {
            $act .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/home/' . $hid . '/convert')) . '">Make order</a>';
        }
        $rows[] = [e((string) $h['req_no']), e((string) $h['name']), e((string) $h['phone']), e((string) $h['address']), e(dt((string) $h['collect_at'], 'd M H:i')), e($h['collector_id'] ? ($collectors[(int) $h['collector_id']] ?? 'Staff') : ''), e(money((float) $h['charge'])), $badge($label((string) $h['status']), $tone[$h['status']] ?? 'mute'), $h['order_id'] ? '<a href="' . e(url('portal/lab/order/' . $h['order_id'])) . '">Order</a>' : '', $act];
    }
    $f = '<form class="d-form" method="post" action="' . e(url('portal/lab/home/save')) . '">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('name', ['label' => 'Patient name', 'required' => true, 'max' => 160], '') . Crud::field('phone', ['label' => 'Phone', 'required' => true, 'max' => 60], '')
        . Crud::field('address', ['label' => 'Home address', 'required' => true, 'max' => 255, 'wide' => true], '') . Crud::field('location_note', ['label' => 'Location note (landmark, map link)', 'max' => 255, 'wide' => true], '')
        . Crud::field('collect_at', ['label' => 'Collection time', 'type' => 'datetime-local', 'required' => true], date('Y-m-d\TH:i', strtotime('+2 hours'))) . Crud::field('charge', ['label' => 'Home collection charge', 'type' => 'number', 'step' => '0.01'], (string) $L->setting('home_charge', '0'))
        . '</div><button class="d-btn d-btn-primary" type="submit">Schedule request</button></form>';
    $page($p, $L, 'home', 'Home collection', ($p->writable() ? $card('New request', $f) : '') . $card('Requests', $tbl([['No'], ['Patient'], ['Phone'], ['Address'], ['When'], ['Collector'], ['Charge', 'R'], ['Status'], [' '], [' ']], $rows, 'No home collection requests.', 'home')), 'Scheduled, assigned, collected. A request turns into an order without retyping.');
});
$app->router->post('/portal/lab/home/save', static function () use ($boot, $post, $db): void {
    [$p, $L] = $boot('home');
    $post($p);
    $name = trim((string) ($_POST['name'] ?? ''));
    $addr = trim((string) ($_POST['address'] ?? ''));
    $at = str_replace('T', ' ', (string) ($_POST['collect_at'] ?? '')) . ':00';
    if ($name === '' || $addr === '' || strtotime($at) === false) {
        flash('Please enter the name, address and collection time.', 'bad');
        redirect(url('portal/lab/home'));
    }
    $b = $L->writeBranch(0);
    $id = $db->insert('sc_lab_home', ['tenant_id' => $p->tenantId, 'branch_id' => $b, 'req_no' => $L->docNo('HC', $b), 'name' => mb_substr($name, 0, 160), 'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 60), 'address' => mb_substr($addr, 0, 255), 'location_note' => mb_substr(trim((string) ($_POST['location_note'] ?? '')), 0, 255) ?: null, 'collect_at' => date('Y-m-d H:i:s', strtotime($at)), 'charge' => max(0, (float) ($_POST['charge'] ?? 0)), 'status' => 'scheduled', 'created_by' => (int) $p->user['id'], 'created_at' => date('Y-m-d H:i:s')]);
    $L->log('home', $id, 'created', null, ['name' => $name], null, $b);
    flash('Home collection scheduled.', 'ok');
    redirect(url('portal/lab/home'));
});
$app->router->post('/portal/lab/home/{id}/{act}', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('home');
    $post($p);
    $h = $db->one('SELECT * FROM sc_lab_home WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($h === null || !$L->may((int) $h['branch_id'])) {
        redirect(url('portal/lab/home'));
    }
    if ($a['act'] === 'assign' && (int) ($_POST['collector_id'] ?? 0) > 0) {
        $db->run("UPDATE sc_lab_home SET collector_id = ?, status = 'assigned' WHERE id = ?", [(int) $_POST['collector_id'], (int) $h['id']]);
        flash('Collector assigned.', 'ok');
    } elseif ($a['act'] === 'collected' && $h['status'] === 'assigned') {
        $db->run("UPDATE sc_lab_home SET status = 'collected' WHERE id = ?", [(int) $h['id']]);
        if ($h['order_id']) {
            foreach ($db->all("SELECT * FROM sc_lab_samples WHERE order_id = ? AND status = 'pending'", [(int) $h['order_id']]) as $s) {
                $L->sampleStep($s, 'collect');
            }
        }
        flash('Marked collected. Bring the samples to the lab to receive them.', 'ok');
    }
    redirect(url('portal/lab/home'));
});
$app->router->get('/portal/lab/home/{id}/convert', static function (array $a) use ($boot, $db): void {
    [$p, $L] = $boot('book');
    $h = $db->one('SELECT * FROM sc_lab_home WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($h === null || !$L->may((int) $h['branch_id'])) {
        redirect(url('portal/lab/home'));
    }
    $pt = $db->one("SELECT pt.id FROM patients pt JOIN users u ON u.id = pt.user_id WHERE pt.tenant_id = ? AND u.phone = ? AND u.deleted_at IS NULL LIMIT 1", [$p->tenantId, $h['phone']]);
    $_SESSION['lab_home_book'] = (int) $h['id'];
    if ($pt) {
        redirect(url('portal/lab/book', ['patient' => $pt['id'], 'home' => $h['id']]));
    }
    redirect(url('portal/lab/book', ['home' => $h['id']]));
});

// ---------------------------------------------------------------- results
$itemOf = static function (Portal $p, Lab $L, int $id) use ($app, $db): array {
    $i = $db->one('SELECT i.*, o.order_no, o.patient_id, o.status order_status FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id WHERE i.id = ? AND i.tenant_id = ?', [$id, $p->tenantId]);
    if ($i === null || !$L->may((int) $i['branch_id'])) {
        $app->error(404, 'Test not found.');
    }
    return $i;
};
$app->lab['itemOf'] = $itemOf;
$worklist = static function (string $key, string $title, string $sub, string $where, string $actLabel, string $path) use ($boot, $page, $db, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot($key);
    $q = trim((string) ($_GET['q'] ?? ''));
    $a = [$p->tenantId];
    $w = $where;
    if ($q !== '') {
        $w .= ' AND (i.test_name LIKE ? OR o.order_no LIKE ? OR pt.patient_unique_id LIKE ? OR u.first_name LIKE ?)';
        array_push($a, ...array_fill(0, 4, '%' . $q . '%'));
    }
    $rows = [];
    foreach ($db->all("SELECT i.*, o.order_no, pt.patient_unique_id mr, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) pn FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id JOIN patients pt ON pt.id = o.patient_id JOIN users u ON u.id = pt.user_id WHERE i.tenant_id = ? AND " . $L->scope('i.branch_id') . " AND o.status <> 'cancelled' AND $w ORDER BY i.id LIMIT 150", $a) as $i) {
        $rows[] = [e((string) $i['test_name']), e($i['pn'] . ' · ' . $i['mr']), e((string) $i['order_no']), e($L->branchName((int) $i['branch_id'])), $badge($label((string) $i['status']), $tone[$i['status']] ?? 'mute'), $badge($label((string) $i['report_status']), $tone[$i['report_status']] ?? 'mute'), '<a class="d-btn d-btn-primary" href="' . e(url($path . '/' . $i['id'])) . '">' . e($actLabel) . '</a>'];
    }
    $f = '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/lab/' . $key)) . '"><label class="d-field" style="margin:0;flex:1"><span class="d-help">Test, order, name or MR</span><input type="search" name="q" value="' . e($q) . '"></label><button class="d-btn d-btn-primary" type="submit">Find</button></form>';
    $page($p, $L, $key, $title, $f . $tbl([['Test'], ['Patient'], ['Order'], ['Branch'], ['Sample'], ['Report'], [' ']], $rows, 'Nothing waiting here.', 'check'), $sub);
};
$app->router->get('/portal/lab/results', static fn () => $worklist('results', 'Enter results', 'Samples received in the lab. Enter values, then send for review.', "i.status IN ('received','processing','completed') AND i.report_status IN ('none','draft')", 'Enter results', 'portal/lab/results'));
$app->router->get('/portal/lab/approve', static fn () => $worklist('approve', 'Approve reports', 'Reports sent for review. Approve, or return with a reason.', "i.report_status = 'pending_review'", 'Review', 'portal/lab/report'));

$app->router->get('/portal/lab/results/{id}', static function (array $a) use ($boot, $itemOf, $page, $card, $btn, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot('results');
    $i = $itemOf($p, $L, (int) $a['id']);
    $pt = $L->patient((int) $i['patient_id']);
    $locked = !in_array($i['report_status'], ['none', 'draft'], true);
    $rows = $L->resultRows($i);
    $f = '<p class="d-info">' . e($pt['name']) . ' · MR ' . e($pt['mr']) . ' · ' . e(Lab::ageText($pt) . ' ' . Lab::sexName($pt['gender'])) . ' · Order ' . e((string) $i['order_no']) . ' · ' . $badge($label((string) $i['report_status']), $tone[$i['report_status']] ?? 'mute') . '</p>';
    if (!$rows) {
        $f .= '<div class="d-empty"><h3>This test has no result fields yet</h3><p>Ask the catalogue owner to add parameters to ' . e((string) $i['test_name']) . '.</p></div>';
    } else {
        $f .= '<form class="d-form" method="post" action="' . e(url('portal/lab/results/' . $i['id'])) . '" autocomplete="off">' . csrf_field() . '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference range</th><th>Flag</th><th>Comment</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $f .= '<tr><td data-label="Parameter">' . e($r['name']) . '</td><td data-label="Result"><input name="v[' . $r['param_id'] . ']" value="' . e((string) $r['value']) . '" maxlength="255"' . ($locked ? ' disabled' : '') . '></td><td data-label="Unit">' . e((string) $r['unit']) . '</td><td data-label="Reference range">' . e((string) $r['ref']) . '</td><td data-label="Flag">' . ($r['flag'] === 'H' ? '<b style="color:#c0392b">High</b>' : ($r['flag'] === 'L' ? '<b style="color:#c0392b">Low</b>' : e((string) $r['flag']))) . '</td><td data-label="Comment"><input name="c[' . $r['param_id'] . ']" value="' . e((string) $r['comment']) . '" maxlength="255"' . ($locked ? ' disabled' : '') . '></td></tr>';
        }
        $f .= '</tbody></table></div>';
        if (!$locked) {
            $f .= '<div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e(url('portal/lab/results')) . '">Back</a><button class="d-btn d-btn-soft" type="submit" name="go" value="save">Save draft</button><button class="d-btn d-btn-primary" type="submit" name="go" value="submit">Save and send for review</button></div>';
        }
        $f .= '</form>';
    }
    $page($p, $L, 'results', $i['test_name'], $card('', $f), 'Flags are worked out from the range for this patient age and sex.');
});
$app->router->post('/portal/lab/results/{id}', static function (array $a) use ($boot, $post, $itemOf): void {
    [$p, $L] = $boot('results');
    $post($p);
    $i = $itemOf($p, $L, (int) $a['id']);
    $err = $L->saveResults($i, (array) ($_POST['v'] ?? []), (array) ($_POST['c'] ?? []));
    if ($err === null && ($_POST['go'] ?? '') === 'submit') {
        $i = $itemOf($p, $L, (int) $a['id']);
        $err = $L->reportStep($i, 'submit');
        flash($err ?? 'Sent for review.', $err ? 'bad' : 'ok');
        redirect(url($err ? 'portal/lab/results/' . $i['id'] : 'portal/lab/results'));
    }
    flash($err ?? 'Draft saved.', $err ? 'bad' : 'ok');
    redirect(url('portal/lab/results/' . (int) $a['id']));
});

// ---------------------------------------------------------------- review, approve, amend, report
$app->router->get('/portal/lab/report/{id}', static function (array $a) use ($boot, $itemOf, $page, $card, $btn, $db, $tbl, $badge, $tone, $label, $pf, $hid): void {
    [$p, $L] = $boot('approve');
    $i = $itemOf($p, $L, (int) $a['id']);
    $pt = $L->patient((int) $i['patient_id']);
    $rows = [];
    foreach ($L->results((int) $i['id']) as $r) {
        $rows[] = [e((string) $r['param_name']), '<b>' . e((string) $r['value']) . '</b>', $r['flag'] === 'H' ? 'High' : ($r['flag'] === 'L' ? 'Low' : ''), e((string) $r['unit']), e((string) $r['ref_text']), e((string) $r['comment'])];
    }
    $log = [];
    foreach ($db->all("SELECT l.*, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) un FROM sc_lab_report_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.tenant_id = ? AND l.item_id = ? ORDER BY l.id DESC", [$p->tenantId, (int) $i['id']]) as $l) {
        $log[] = [e(dt((string) $l['created_at'], 'd M Y H:i')), e('v' . $l['version'] . ' ' . $l['action']), e((string) $l['un']), e((string) $l['reason'])];
    }
    $st = (string) $i['report_status'];
    $x = '<div class="d-kv"><p><b>Patient</b> ' . e($pt['name']) . ' · MR ' . e($pt['mr']) . '</p><p><b>Test</b> ' . e((string) $i['test_name']) . '</p><p><b>Status</b> ' . $badge($label($st), $tone[$st] ?? 'mute') . ' · version ' . (int) $i['version'] . '</p></div>' . $tbl([['Parameter'], ['Result'], ['Flag'], ['Unit'], ['Range'], ['Comment']], $rows, 'No results saved.');
    $act = '';
    $self = (int) $i['entered_by'] === (int) $p->user['id'];
    if ($st === 'pending_review' && $p->canDo('lab_approve', 'edit') && $p->writable()) {
        $sg = '';
        foreach ($db->all('SELECT id, name, title FROM sc_lab_signatories WHERE tenant_id = ? AND active = 1 ORDER BY is_default DESC, id', [$p->tenantId]) as $s) {
            $sg .= '<option value="' . (int) $s['id'] . '">' . e($s['name'] . ' · ' . $s['title']) . '</option>';
        }
        $act .= '<form class="d-form" method="post" action="' . e(url('portal/lab/report/' . $i['id'] . '/approve')) . '">' . csrf_field() . '<label class="d-field"><span class="d-help">Signatory on the report</span><select name="signatory">' . $sg . '</select></label><button class="d-btn d-btn-primary" type="submit">Approve report</button></form>'
            . '<form class="d-form" method="post" action="' . e(url('portal/lab/report/' . $i['id'] . '/return')) . '">' . csrf_field() . Crud::field('reason', ['label' => 'Return to the technician: what must be corrected', 'required' => true, 'max' => 190], '') . '<button class="d-btn d-btn-soft" type="submit">Return for correction</button></form>';
        if ($self) {
            $act .= '<p class="d-help">You entered these results, so another approver has to approve them.</p>';
        }
    }
    if (in_array($st, ['approved', 'delivered'], true)) {
        $act .= '<p><a class="d-btn d-btn-primary" href="' . e(url('portal/lab/report/' . $i['id'] . '/pdf')) . '">Report PDF</a> ' . ($p->can('lab_share') ? '<a class="d-btn d-btn-soft" href="' . e(url('portal/lab/share/' . $i['order_id'])) . '">Share</a> ' : '') . ($st === 'approved' && $p->writable() ? $pf(url('portal/lab/report/' . $i['id'] . '/deliver'), 'Mark delivered (handed over)') : '') . '</p>';
        if ($p->canDo('lab_approve', 'edit') && $p->writable()) {
            $act .= '<form class="d-form" method="post" action="' . e(url('portal/lab/report/' . $i['id'] . '/amend')) . '" data-confirm="Open this approved report for correction? The old version stays in the history.">' . csrf_field() . Crud::field('reason', ['label' => 'Reason for the amendment', 'required' => true, 'max' => 190], '') . '<button class="d-btn d-btn-soft" type="submit">Amend report</button></form>';
        }
    }
    $page($p, $L, 'approve', 'Report ' . ($i['report_no'] ?: $i['test_name']), $card('Report', $x) . ($act !== '' ? $card('Actions', $act) : '') . $card('Version history', $tbl([['When'], ['Step'], ['Who'], ['Reason']], $log, 'No steps yet.')), $i['order_no']);
});
$app->router->post('/portal/lab/report/{id}/{act}', static function (array $a) use ($boot, $post, $itemOf): void {
    [$p, $L] = $boot('approve');
    $post($p);
    $i = $itemOf($p, $L, (int) $a['id']);
    $act = (string) $a['act'];
    if (in_array($act, ['approve', 'return', 'amend'], true) && !$p->canDo('lab_approve', 'edit')) {
        flash('Your role cannot do this step.', 'bad');
        redirect(url('portal/lab/report/' . $i['id']));
    }
    $err = $act === 'amend' ? $L->amend($i, (string) ($_POST['reason'] ?? '')) : $L->reportStep($i, $act, (string) ($_POST['reason'] ?? ''), (int) ($_POST['signatory'] ?? 0));
    flash($err ?? ['approve' => 'Report approved and signed.', 'return' => 'Returned to the technician.', 'amend' => 'Report opened for correction.', 'deliver' => 'Marked delivered.'][$act] ?? 'Done.', $err ? 'bad' : 'ok');
    redirect(url($act === 'amend' ? 'portal/lab/results/' . $i['id'] : ($err ? 'portal/lab/report/' . $i['id'] : ($act === 'deliver' ? 'portal/lab/report/' . $i['id'] : 'portal/lab/approve'))));
});
$app->router->get('/portal/lab/report/{id}/pdf', static function (array $a) use ($app, $boot, $itemOf, $send, $db): void {
    [$p, $L] = $boot('results');
    $i = $itemOf($p, $L, (int) $a['id']);
    if (!in_array($i['report_status'], ['approved', 'delivered'], true)) {
        $app->error(403, 'Only approved reports can be printed. Draft sheets stay on screen.', 'Not approved');
    }
    if (!$p->canDo('lab_results', 'print') && !$p->canDo('lab_approve', 'print')) {
        $app->error(403, 'Your role is not allowed to print.', 'No access');
    }
    $o = $L->order((int) $i['order_id']);
    $D = new LabDocs($L);
    $send($D->report($o, [$i], $D->mark('report', (int) $i['id'], (int) $i['branch_id'])), (string) $i['report_no']);
});

// ---------------------------------------------------------------- sharing wizard and log
$app->router->get('/portal/lab/share/{id}', static function (array $a) use ($boot, $loadOrder, $page, $card, $db, $tbl, $badge, $tone, $label): void {
    [$p, $L] = $boot('share');
    $o = $loadOrder($p, $L, (int) $a['id']);
    $pt = $L->patient((int) $o['patient_id']);
    $doc = ($_GET['doc'] ?? 'report') === 'receipt' ? 'receipt' : 'report';
    if ($L->shareModel($o, $doc) === null) {
        flash('Only an approved report can be shared. Approve it first.', 'bad');
        redirect(url('portal/lab/order/' . (int) $o['id']));
    }
    $oid = (int) $o['id'];
    $wa = (string) ($pt['whatsapp'] ?: $pt['phone']);
    $f = '<p class="d-info"><b>Check the recipient before sending.</b> Patient: <b>' . e($pt['name']) . '</b> · MR <b>' . e($pt['mr']) . '</b> · ' . e(Lab::ageText($pt) . ' ' . Lab::sexName($pt['gender'])) . '</p>'
        . '<p><a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e(url('portal/lab/order/' . $oid . ($doc === 'receipt' ? '/receipt' : '/report'))) . '">Preview the ' . ($doc === 'receipt' ? 'receipt' : 'report') . ' PDF</a></p>'
        . '<form class="d-form" method="post" action="' . e(url('portal/lab/share/' . $oid)) . '">' . csrf_field() . '<input type="hidden" name="doc" value="' . $doc . '"><div class="d-form-grid">'
        . Crud::field('relation', ['label' => 'Sending to', 'type' => 'select', 'options' => ['patient' => 'The patient', 'guardian' => 'Guardian or family', 'doctor' => 'Referring doctor', 'partner' => 'Partner laboratory'], 'required' => true], 'patient')
        . Crud::field('name', ['label' => 'Recipient name', 'required' => true, 'max' => 160], $pt['name'])
        . Crud::field('whatsapp', ['label' => 'WhatsApp number (country code, for example 92300...)', 'max' => 40], $wa)
        . Crud::field('email', ['label' => 'Email address', 'max' => 190], Lab::realEmail($pt))
        . Crud::field('fix', ['label' => 'Save this contact on the patient record', 'type' => 'toggle'], '0')
        . Crud::field('confirm', ['label' => 'I checked the name, MR number and contact. They belong to this patient.', 'type' => 'toggle', 'required' => true], '0')
        . '</div><p class="d-tools" style="gap:8px;flex-wrap:wrap"><button class="d-btn d-btn-primary" type="submit" name="channel" value="whatsapp">Send by WhatsApp</button><button class="d-btn d-btn-soft" type="submit" name="channel" value="email">Send by email</button></p><p class="d-help">WhatsApp opens your WhatsApp with the message and a secure link. The link works for ' . (int) $L->setting('link_days', '7') . ' days. Email carries the PDF.</p></form>';
    $rows = [];
    foreach ($db->all('SELECT * FROM sc_lab_delivery_log WHERE tenant_id = ? AND order_id = ? ORDER BY id DESC', [$p->tenantId, $oid]) as $r) {
        $rows[] = [e(dt((string) $r['created_at'], 'd M H:i')), e(ucfirst((string) $r['channel'])), e((string) $r['recipient']), $badge($label((string) $r['status']), $tone[$r['status']] ?? 'mute'), e((string) $r['error'])];
    }
    $page($p, $L, 'share', 'Share ' . ($doc === 'receipt' ? 'receipt' : 'report') . ' ' . $o['order_no'], $card('Send', $f) . $card('Sent so far', $tbl([['When'], ['Channel'], ['To'], ['Status'], ['Note']], $rows, 'Nothing sent yet.', 'mail')));
});
$app->router->post('/portal/lab/share/{id}', static function (array $a) use ($boot, $post, $loadOrder, $page, $card): void {
    [$p, $L] = $boot('share');
    $post($p);
    $o = $loadOrder($p, $L, (int) $a['id']);
    $doc = ($_POST['doc'] ?? '') === 'receipt' ? 'receipt' : 'report';
    $ch = ($_POST['channel'] ?? '') === 'email' ? 'email' : 'whatsapp';
    $back = url('portal/lab/share/' . (int) $o['id'], $doc === 'receipt' ? ['doc' => 'receipt'] : []);
    if (($_POST['confirm'] ?? '0') !== '1') {
        flash('Tick the box to confirm you checked the recipient.', 'bad');
        redirect($back);
    }
    if (($_POST['fix'] ?? '0') === '1') {
        $L->updateContact((int) $o['patient_id'], ['whatsapp' => (string) ($_POST['whatsapp'] ?? ''), 'email' => (string) ($_POST['email'] ?? '')]);
    }
    $to = $ch === 'email' ? (string) ($_POST['email'] ?? '') : (string) ($_POST['whatsapp'] ?? '');
    $r = $L->share($o, $doc, $ch, $to, (string) ($_POST['name'] ?? ''), in_array($_POST['relation'] ?? '', ['patient', 'guardian', 'doctor', 'partner'], true) ? (string) $_POST['relation'] : 'patient');
    if ($ch === 'whatsapp' && $r['ok']) {
        $page($p, $L, 'share', 'WhatsApp is ready', $card('', '<p>The message is saved in the sharing log. Press the button to open WhatsApp and press send there.</p><p><a class="d-btn d-btn-primary" target="_blank" rel="noopener" href="' . e($r['link']) . '">Open WhatsApp</a> <a class="d-btn d-btn-soft" href="' . e(url('portal/lab/order/' . (int) $o['id'])) . '">Back to the order</a></p>'));
    }
    flash($r['ok'] ? $r['msg'] : ($r['id'] ? 'Email could not be sent now: ' . $r['msg'] . ' It stays in the log, use Retry.' : $r['msg']), $r['ok'] ? 'ok' : 'bad');
    redirect($r['id'] ? url('portal/lab/share') : $back);
});
$app->router->get('/portal/lab/share', static function () use ($boot, $page, $db, $tbl, $badge, $tone, $label, $pf): void {
    [$p, $L] = $boot('share');
    $rows = [];
    foreach ($db->all("SELECT d.*, o.order_no FROM sc_lab_delivery_log d JOIN sc_lab_orders o ON o.id = d.order_id WHERE d.tenant_id = ? AND " . $L->scope('d.branch_id') . ' ORDER BY d.id DESC LIMIT 150', [$p->tenantId]) as $r) {
        $retry = $r['status'] !== 'sent' && $r['channel'] === 'email' ? $pf(url('portal/lab/share/' . $r['id'] . '/retry'), 'Retry') : '';
        $rows[] = [e(dt((string) $r['created_at'], 'd M H:i')), '<a href="' . e(url('portal/lab/order/' . $r['order_id'])) . '">' . e($r['order_no']) . '</a>', e(ucfirst((string) $r['doc_type'])), e(ucfirst((string) $r['channel'])), e($r['recipient_name'] . ' · ' . $r['recipient']), $badge($label((string) $r['status']), $tone[$r['status']] ?? 'mute') . ($r['error'] ? '<br><small>' . e((string) $r['error']) . '</small>' : ''), e((string) $r['tries']), $retry];
    }
    $page($p, $L, 'share', 'Sharing log', $tbl([['When'], ['Order'], ['Document'], ['Channel'], ['Recipient'], ['Status'], ['Tries', 'R'], [' ']], $rows, 'Nothing shared yet.', 'mail'), 'Every WhatsApp and email share with who sent it and what happened.');
});
$app->router->post('/portal/lab/share/{id}/retry', static function (array $a) use ($boot, $post, $db): void {
    [$p, $L] = $boot('share');
    $post($p);
    $r = $db->one('SELECT * FROM sc_lab_delivery_log WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r && $L->may((int) $r['branch_id'])) {
        $err = $L->sendEmail((int) $r['id']);
        flash($err ?? 'Email sent.', $err ? 'bad' : 'ok');
    }
    redirect(url('portal/lab/share'));
});

// public secure link for a shared document (no sign in, expires)
$app->router->get('/lab-doc/{token}', static function (array $a) use ($app, $db): void {
    $r = preg_match('/^[a-f0-9]{40}$/', (string) $a['token']) ? $db->one('SELECT * FROM sc_lab_delivery_log WHERE token = ?', [(string) $a['token']]) : null;
    if ($r === null || strtotime((string) $r['expires_at']) < time()) {
        $app->error(404, 'This link has expired or is not valid. Ask the laboratory for a new one.');
    }
    $o = $db->one('SELECT * FROM sc_lab_orders WHERE id = ? AND tenant_id = ?', [(int) $r['order_id'], (string) $r['tenant_id']]);
    $port = Portal::forUser($app, (int) $r['user_id']);
    if ($o === null || $port === null) {
        $app->error(404, 'This document is not available.');
    }
    $L = Lab::for($port);
    $m = $L->shareModel($o, (string) $r['doc_type']);
    if ($m === null) {
        $app->error(404, 'This document is not available.');
    }
    $db->run('UPDATE sc_lab_delivery_log SET updated_at = NOW() WHERE id = ?', [(int) $r['id']]);
    Documents::send($m, preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $r['doc_ref']) . '.pdf', true);
});
