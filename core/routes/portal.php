<?php
// Selfcare original routes: the vendor portal (overview, people, website, access, plan, account).
// Every route starts with Portal::boot (signed in, right host, not suspended, inside shift) and then need() for the permission.
declare(strict_types=1);

use Selfcare\{Crud, Listing, People, Portal, VendorTypes};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

/** Renders a dashboard page. $p null = super admin shell (pass nav etc. in $o). */
$app->dash = static function (?Portal $p, string $active, string $title, string $html, array $o = []) use ($app): void {
    $d = ['layout' => 'dash/layout', 'html' => $html, 'title' => $title, 'active' => $active, 'sub' => $o['sub'] ?? '', 'actions' => $o['actions'] ?? ''];
    if ($p !== null) {
        $banner = '';
        if (!$p->writable()) {
            $banner = 'Your plan has ended, so this account is read-only. You can still look at everything and download PDFs.' . ($p->can('view_plan') ? ' <a href="' . e(url('portal/plan')) . '">See your plan</a>.' : ' Ask your administrator to renew the plan.');
        } elseif (!empty($p->sub['ends_at']) && strtotime((string) $p->sub['ends_at']) < time() + 7 * 86400) {
            $banner = 'Your plan ends on ' . e(dt((string) $p->sub['ends_at'])) . '. Ask Self Care to renew it so nothing stops.';
        }
        $imp = $app->auth->impersonator();
        if ($imp !== null) {
            $d['impBanner'] = '<span>You are viewing <b>' . e((string) $p->vendor['name']) . '</b> as super admin. Everything you do here is recorded.</span><form method="post" action="' . e(url('auth/return')) . '">' . csrf_field() . '<button class="d-btn d-btn-soft" type="submit">Return to super admin</button></form>';
        }
        $d += [
            'nav' => $p->nav(), 'orgName' => $p->vendor['name'], 'orgSub' => $p->type['name'], 'user' => $p->user, 'roleLabel' => $app->access->roleLabel($p->role, $p->family),
            'banner' => $banner, 'siteLink' => $app->tenant->siteUrl((string) $p->vendor['slug']), 'orgLogo' => $app->site->get($p->tenantId, 'logo'), 'navHome' => 'portal',
        ];
    }
    $app->view->page('dash/raw', $o + $d);
    exit;
};
$dash = $app->dash;

$card = static fn (string $title, string $html, string $extra = ''): string => '<section class="d-card"' . $extra . '>' . ($title !== '' ? '<h3>' . e($title) . '</h3>' : '') . $html . '</section>';
$btn = static fn (string $label, string $href, string $cls = 'd-btn-primary'): string => '<a class="d-btn ' . $cls . '" href="' . e($href) . '">' . e($label) . '</a>';
$stat = static fn (string $label, string $value, string $ic, string $note = ''): string => '<div class="d-stat"><span class="ic">' . icon($ic, 22) . '</span><span>' . e($label) . '</span><b' . (mb_strlen($value) > 11 ? ' style="font-size:1.25rem"' : '') . '>' . e($value) . '</b>' . ($note !== '' ? '<small>' . e($note) . '</small>' : '') . '</div>';
$app->ui = ['card' => $card, 'btn' => $btn, 'stat' => $stat];

$posted = static function (string $okMsg, string $to, ?string $err = null): void {
    if ($err !== null) {
        flash($err, 'bad');
        redirect($_SERVER['HTTP_REFERER'] ?? $to);
    }
    flash($okMsg, 'ok');
    redirect($to);
};

// ------------------------------------------------------------------ overview
$app->router->get('/portal', static function () use ($app, $dash, $card, $btn, $stat): void {
    $p = Portal::boot($app);
    $db = $app->db;
    $t = $p->tenantId;
    if ($p->isSide()) {
        $mine = $db->all('SELECT * FROM sc_appointment_requests WHERE tenant_id = ? AND email = ? ORDER BY id DESC LIMIT 5', [$t, $p->user['email']]);
        $h = $card('Welcome, ' . $p->user['first_name'], '<p>This is your page at ' . e($p->vendor['name']) . '. You can see your requests and update your details.</p>' . $btn('My requests', url('portal/mine')) . ' ' . $btn('My account', url('portal/account'), 'd-btn-soft'));
        $dash($p, 'overview', 'Overview', $h);
    }
    $q = static fn (string $sql, array $a = []) => (int) $db->val($sql, $a);
    $month = date('Y-m-01');
    $h = '<div class="d-stats">';
    if ($p->can('manage_appointments')) {
        $h .= $stat('New ' . strtolower($p->label('requests', 'requests')), (string) $q("SELECT COUNT(*) FROM sc_appointment_requests WHERE tenant_id = ? AND status = 'new'", [$t]), 'cal', 'waiting for a reply');
    }
    if ($p->can('manage_doctors') && $p->module('doctors') && $p->hasKind('team')) {
        $h .= $stat($p->label('team', 'Team'), (string) $q('SELECT COUNT(*) FROM doctors WHERE tenant_id = ?', [$t]), 'stethoscope');
    }
    if ($p->can('manage_patients') && $p->label('people') !== '') {
        $h .= $stat($p->label('people', 'People'), (string) $q('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$t]), 'users');
    }
    if ($p->can('manage_invoices') && $p->module('billing')) {
        $h .= $stat('Collected this month', money((float) $db->val("SELECT COALESCE(SUM(p.amount),0) FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND " . (new \Selfcare\Billing($db))->notCreditSql('i') . " AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%') AND p.paid_at >= ?", [$t, $month])), 'card');
        $h .= $stat('Unpaid', money((float) $db->val("SELECT COALESCE(SUM(balance),0) FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND status = 'issued' AND balance > 0", [$t])), 'clock', 'across all invoices');
    }
    if ($p->can('manage_accounts') && $p->module('billing')) {
        $acc = new \Selfcare\Accounts($db, new \Selfcare\Billing($db));
        $tdy = $acc->totals($t, date('Y-m-d'), date('Y-m-d'));
        $mth = $acc->totals($t, $month, date('Y-m-d'));
        $h .= $stat('Cash in today', money($tdy['in']), 'plus', 'out: ' . money($tdy['out']));
        $h .= $stat('Expenses this month', money($mth['out']), 'minus', 'see Accounts');
    }
    if ($p->can('view_messages') && $p->module('messages')) {
        $h .= $stat('Unread messages', (string) $q('SELECT COUNT(*) FROM sc_messages WHERE tenant_id = ? AND is_read = 0', [$t]), 'mail');
    }
    [$opsTiles, $opsCharts] = \Selfcare\Ops::overview($p, $stat, $card);
    $h .= $opsTiles;
    $h .= $stat('Plan', (string) $p->sub['plan_name'], 'layers', $app->plans->label($p->sub));
    $h .= '</div>';
    $months = \Selfcare\Charts::months(6);
    $since = date('Y-m-01', strtotime('-5 month'));
    $lab = $p->label('requests', 'Requests');
    $charts = $opsCharts;
    if ($p->can('manage_appointments')) {
        $got = array_column($db->all("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) n FROM sc_appointment_requests WHERE tenant_id = ? AND created_at >= ? GROUP BY m", [$t, $since]), 'n', 'm');
        $charts .= $card($lab . ' per month', \Selfcare\Charts::columns(array_map(static fn ($m) => [$m[0], (int) ($got[$m[1]] ?? 0)], $months), 'No ' . strtolower($lab) . ' yet. Share your website address to get the first one.'));
        $st = array_column($db->all('SELECT status, COUNT(*) n FROM sc_appointment_requests WHERE tenant_id = ? GROUP BY status', [$t]), 'n', 'status');
        $names = ['new' => 'Waiting', 'confirmed' => 'Approved', 'done' => 'Done', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
        $bars = [];
        foreach ($names as $k => $n) {
            $bars[] = [$n, (int) ($st[$k] ?? 0), url('portal/requests', ['f_status' => $k])];
        }
        $charts .= $card($lab . ' by state', \Selfcare\Charts::bars($bars));
    }
    if ($p->can('manage_invoices') && $p->module('billing')) {
        $got = array_column($db->all("SELECT DATE_FORMAT(p.paid_at, '%Y-%m') m, SUM(p.amount) n FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND p.paid_at >= ? GROUP BY m", [$t, $since]), 'n', 'm');
        $charts .= $card('Money collected per month', \Selfcare\Charts::columns(array_map(static fn ($m) => [$m[0], (float) ($got[$m[1]] ?? 0), money((float) ($got[$m[1]] ?? 0))], $months), 'No payments recorded yet.'));
        $inv = array_column($db->all("SELECT status, COUNT(*) n FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' GROUP BY status", [$t]), 'n', 'status');
        $bars = [];
        foreach (['issued' => 'Open', 'paid' => 'Paid', 'void' => 'Void'] as $k => $n) {
            $bars[] = [$n, (int) ($inv[$k] ?? 0), url('portal/billing')];
        }
        $charts .= $card('Invoices by state', \Selfcare\Charts::bars($bars));
    }
    if ($p->can('manage_users') && $p->module('staff')) {
        $roles = $db->all("SELECT d.name, COUNT(*) n FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND u.deleted_at IS NULL AND d.name NOT IN ('Patient', 'Family Member', 'Referring Doctor', 'B2B Customer') GROUP BY d.name ORDER BY n DESC", [\Selfcare\Auth::ROLE_MODEL, $t]);
        $charts .= $card('Team by role', \Selfcare\Charts::bars(array_map(fn ($r) => [$app->access->roleLabel((string) $r['name'], $p->family), (int) $r['n'], url('portal/staff')], $roles)));
    }
    if ($charts !== '') {
        $h .= '<div class="d-grid d-g2">' . $charts . '</div>';
    }
    if ($p->can('manage_appointments')) {
        $rows = $db->all('SELECT * FROM sc_appointment_requests WHERE tenant_id = ? ORDER BY id DESC LIMIT 6', [$t]);
        $r = '';
        foreach ($rows as $x) {
            $r .= '<tr><td data-label="Name"><a href="' . e(url('portal/requests/' . $x['id'])) . '">' . e($x['name']) . '</a></td><td data-label="Phone">' . e($x['phone']) . '</td><td data-label="Date">' . e(dt($x['preferred_date'])) . '</td><td data-label="Status"><span class="d-badge is-' . (['new' => 'warn', 'confirmed' => 'info', 'done' => 'ok', 'cancelled' => 'mute', 'rejected' => 'bad'][$x['status']] ?? 'mute') . '">' . e(ucfirst($x['status'])) . '</span></td></tr>';
        }
        $h .= $card('Latest ' . strtolower($lab), $rows ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Name</th><th>Phone</th><th>Date</th><th>Status</th></tr></thead><tbody>' . $r . '</tbody></table></div><p style="margin-top:12px">' . $btn('See all', url('portal/requests'), 'd-btn-soft') . '</p>' : '<p class="d-mute">Nothing yet.</p>');
    }
    $quick = '';
    foreach ([['manage_invoices', 'billing', 'portal/billing', 'Make an invoice', 'card'], ['manage_reports', 'reports', 'portal/reports', 'Open reports', 'chart'], ['manage_settings', 'website', 'portal/website', 'Edit my website', 'globe'], ['manage_users', 'staff', 'portal/staff', 'Add staff', 'user']] as [$perm, $mod, $path, $label, $ic]) {
        if ($p->can($perm) && $p->module($mod)) {
            $quick .= '<a class="d-btn d-btn-soft" href="' . e(url($path)) . '">' . icon($ic, 16) . ' ' . e($label) . '</a> ';
        }
    }
    if ($quick !== '') {
        $h .= $card('Quick actions', $quick);
    }
    $dash($p, 'overview', 'Overview', $h, ['sub' => 'A short look at your day.']);
});

$app->router->get('/portal/mine', static function () use ($app, $dash, $card): void {
    $p = Portal::boot($app);
    $p->need('self');
    $rows = $app->db->all('SELECT * FROM sc_appointment_requests WHERE tenant_id = ? AND email = ? ORDER BY id DESC', [$p->tenantId, $p->user['email']]);
    $h = '';
    foreach ($rows as $x) {
        $h .= '<tr><td data-label="Date">' . e(dt($x['preferred_date'])) . '</td><td data-label="Note">' . e((string) $x['message']) . '</td><td data-label="Status">' . e(ucfirst($x['status'])) . '</td></tr>';
    }
    $dash($p, 'mine', 'My requests', $card('', $rows ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Date</th><th>Note</th><th>Status</th></tr></thead><tbody>' . $h . '</tbody></table></div>' : '<div class="d-empty"><h3>No requests yet</h3><p>Requests you send from the website with your email appear here.</p></div>'));
});

// ------------------------------------------------------------------ requests (appointments, bookings, orders, enquiries)
$reqTone = ['new' => 'warn', 'confirmed' => 'info', 'done' => 'ok', 'cancelled' => 'mute', 'rejected' => 'bad'];
$app->router->get('/portal/requests', static function () use ($app, $dash, $card, $reqTone, $btn): void {
    $p = Portal::boot($app);
    $p->need('manage_appointments');
    if (!isset($_GET['f_status'])) {
        $_GET['f_status'] = 'new';
    }
    $lab = strtolower($p->label('requests', 'requests'));
    $act = static fn (string $st, string $label, string $ic, string $ok, string $confirm = '') => ['label' => $label, 'icon' => $ic, 'method' => 'post', 'url' => fn ($r) => url('portal/requests/' . $r['id'] . '/' . $st), 'if' => fn ($r) => $p->writable() && in_array($r['status'], $ok === 'open' ? ['new'] : ($ok === 'live' ? ['new', 'confirmed'] : ['confirmed']), true), 'confirm' => $confirm, 'danger' => $st === 'cancel'];
    $html = Listing::render($app->db, [
        'id' => 'requests', 'select' => 'r.*, u.first_name AS dfn, u.last_name AS dln', 'from' => 'sc_appointment_requests r LEFT JOIN doctors d ON d.id = r.doctor_id LEFT JOIN users u ON u.id = d.user_id',
        'where' => ['r.tenant_id = ?'], 'params' => [$p->tenantId], 'search' => ['r.name', 'r.phone', 'r.email', 'r.message'], 'search_hint' => 'Search by name, phone or note',
        'filters' => ['status' => ['label' => 'Status', 'sql' => 'r.status = ?', 'options' => ['new' => 'Waiting', 'confirmed' => 'Approved', 'done' => 'Done', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']]],
        'columns' => ['name' => ['label' => 'Name', 'sort' => 'r.name', 'render' => fn ($r) => '<a href="' . e(url('portal/requests/' . $r['id'])) . '"><b>' . e($r['name']) . '</b></a>'], 'phone' => ['label' => 'Phone'], 'preferred_date' => ['label' => 'Date', 'sort' => 'r.preferred_date', 'type' => 'date'],
            'doctor' => ['label' => 'With', 'render' => fn ($r) => $r['dfn'] ? e(trim($r['dfn'] . ' ' . $r['dln'])) : '<span class="d-mute">Any</span>'], 'message' => ['label' => 'Note', 'render' => fn ($r) => e(mb_strimwidth((string) $r['message'], 0, 70, '…'))], 'status' => ['label' => 'Status', 'sort' => 'r.status', 'type' => 'badge'], 'created_at' => ['label' => 'Received', 'sort' => 'r.created_at', 'type' => 'date']],
        'badges' => ['status' => $reqTone], 'sort' => 'created_at', 'dir' => 'desc',
        'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => fn ($r) => url('portal/requests/' . $r['id'])], $act('approve', 'Approve', 'check', 'open', 'Approve this request?'), $act('done', 'Done', 'check', 'confirmed'), $act('cancel', 'Cancel', 'x', 'live', 'Cancel this request?')],
        'empty' => ['title' => 'No ' . $lab . ' here', 'text' => 'People can send one from your website. Share your website address to get the first one.'],
    ]);
    $dash($p, 'requests', $p->label('requests', 'Requests'), $card('', $html), ['sub' => 'Sent from your website. Open one to approve, reject with a reason or cancel.', 'actions' => $p->writable() ? $btn('Add ' . rtrim($lab, 's'), url('portal/requests/new')) : '']);
});
// walk-in or phone request typed in by staff
$reqFields = static fn (): array => [
    'name' => ['label' => 'Name', 'required' => true, 'max' => 160], 'phone' => ['label' => 'Phone', 'required' => true, 'max' => 40, 'type' => 'tel'],
    'email' => ['label' => 'Email', 'type' => 'email', 'max' => 191], 'preferred_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')],
    'message' => ['label' => 'Note', 'type' => 'textarea', 'max' => 1000, 'rows' => 3],
];
$app->router->get('/portal/requests/new', static function () use ($app, $dash, $card, $reqFields): void {
    $p = Portal::boot($app);
    $p->need('manage_appointments');
    $p->needWritable();
    $h = '<form class="d-form" method="post" action="' . e(url('portal/requests/new')) . '">' . csrf_field() . '<div class="d-form-grid">';
    foreach ($reqFields() as $n => $f) {
        $h .= Crud::field($n, $f, old($n, (string) ($f['default'] ?? '')));
    }
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save</button><a class="d-btn d-btn-soft" href="' . e(url('portal/requests')) . '">Cancel</a></div></form>';
    clear_old();
    $dash($p, 'requests', 'Add ' . rtrim(strtolower($p->label('requests', 'request')), 's'), $card('', $h), ['sub' => 'For people who phone or walk in. It joins the same list as website requests.']);
});
$app->router->post('/portal/requests/new', static function () use ($app, $reqFields): void {
    $p = Portal::boot($app);
    $p->need('manage_appointments');
    $p->needWritable();
    [$d, $err] = Crud::collect($reqFields());
    if ($err !== null) {
        keep_old($_POST);
        flash($err, 'bad');
        redirect(url('portal/requests/new'));
    }
    $id = $app->db->insert('sc_appointment_requests', $d + ['tenant_id' => $p->tenantId, 'status' => 'new', 'created_at' => date('Y-m-d H:i:s')]);
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'request_added', null, ['id' => $id]);
    flash('Saved.', 'ok');
    redirect(url('portal/requests/' . $id));
});
$app->router->get('/portal/requests/{id}', static function (array $a) use ($app, $dash, $card, $reqTone, $btn): void {
    $p = Portal::boot($app);
    $p->need('manage_appointments');
    $r = $app->db->one('SELECT r.*, u.first_name AS dfn, u.last_name AS dln FROM sc_appointment_requests r LEFT JOIN doctors d ON d.id = r.doctor_id LEFT JOIN users u ON u.id = d.user_id WHERE r.id = ? AND r.tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($r === null) {
        $app->error(404, 'We could not find that request.');
    }
    $id = (int) $r['id'];
    $w = $p->writable();
    $form = static fn (string $path, string $label, string $cls, string $confirm = '') => '<form class="d-inline" method="post" action="' . e(url('portal/requests/' . $id . '/' . $path)) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<button class="d-btn ' . $cls . '" type="submit">' . e($label) . '</button></form> ';
    $by = $r['decided_by'] ? $app->db->one('SELECT first_name, last_name FROM users WHERE id = ?', [(int) $r['decided_by']]) : null;
    $dl = '<dl class="d-kv"><dt>Status</dt><dd><span class="d-badge is-' . $reqTone[$r['status']] . '">' . e(['new' => 'Waiting', 'confirmed' => 'Approved', 'done' => 'Done', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'][$r['status']] ?? $r['status']) . '</span></dd><dt>Name</dt><dd><b>' . e($r['name']) . '</b></dd><dt>Phone</dt><dd><a href="tel:' . e((string) $r['phone']) . '">' . e($r['phone']) . '</a></dd><dt>Email</dt><dd>' . e((string) $r['email']) . '</dd><dt>Preferred date</dt><dd>' . e(dt((string) $r['preferred_date'])) . '</dd>'
        . ($r['dfn'] ? '<dt>With</dt><dd>' . e(trim($r['dfn'] . ' ' . $r['dln'])) . '</dd>' : '') . '<dt>Note</dt><dd>' . e((string) $r['message']) . '</dd><dt>Received</dt><dd>' . e(dt((string) $r['created_at'], 'd M Y H:i')) . '</dd>'
        . ($r['reason'] ? '<dt>Reason</dt><dd>' . e((string) $r['reason']) . '</dd>' : '') . ($r['decided_at'] ? '<dt>Decided</dt><dd>' . e(dt((string) $r['decided_at'], 'd M Y H:i')) . ($by ? ' by ' . e(trim($by['first_name'] . ' ' . $by['last_name'])) : '') . '</dd>' : '') . '</dl>';
    $acts = '';
    if ($w) {
        if ($r['status'] === 'new') {
            $acts .= $form('approve', 'Approve', 'd-btn-primary', 'Approve this request?');
        }
        if ($r['status'] === 'confirmed') {
            $acts .= $form('done', 'Mark done', 'd-btn-primary');
        }
        if (in_array($r['status'], ['new', 'confirmed'], true)) {
            foreach (\Selfcare\Ops::forPortal($p) as $ok => $om) {
                if (!empty($om['intake'])) {
                    $acts .= '<a class="d-btn d-btn-soft" href="' . e(\Selfcare\Ops::url($ok, 'new', array_intersect_key(['title' => (string) $r['name'], 'phone' => (string) $r['phone'], 'date1' => (string) $r['preferred_date'], 'notes' => (string) $r['message']], array_flip($om['intake'] === true ? ['title', 'phone', 'date1', 'notes'] : (array) $om['intake'])))) . '">Make ' . e((string) $om['singular']) . '</a> ';
                    break;
                }
            }
            $acts .= $form('cancel', 'Cancel', 'd-btn-soft', 'Cancel this request?');
        }
        if (in_array($r['status'], ['rejected', 'cancelled', 'done'], true)) {
            $acts .= $form('reopen', 'Reopen', 'd-btn-soft');
        }
    }
    $h = '<div class="d-grid d-g2">' . $card('Request #' . $id, $dl . ($acts ? '<p style="margin-top:14px">' . $acts . '</p>' : ''));
    if ($w && $r['status'] === 'new') {
        $h .= $card('Reject with a reason', '<p class="d-help">The reason is saved and emailed to ' . e($r['name']) . ' when they gave an email.</p><form class="d-form" method="post" action="' . e(url('portal/requests/' . $id . '/reject')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('reason', ['label' => 'Reason', 'type' => 'textarea', 'required' => true, 'max' => 240, 'rows' => 3], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-danger" type="submit">Reject</button></div></form>');
    }
    $h .= '</div>';
    $dash($p, 'requests', 'Request from ' . $r['name'], $h, ['actions' => $btn('Back to the list', url('portal/requests'), 'd-btn-soft')]);
});
$reqDo = static function (string $path, string $to, string $ok, bool $needReason = false) use ($app): void {
    $app->router->post('/portal/requests/{id}/' . $path, static function (array $a) use ($app, $to, $ok, $needReason, $path): void {
        $p = Portal::boot($app);
        $p->need('manage_appointments');
        $p->needWritable();
        $id = (int) $a['id'];
        $r = $app->db->one('SELECT * FROM sc_appointment_requests WHERE id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        if ($r === null) {
            $app->error(404, 'We could not find that request.');
        }
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 240);
        $allowed = ['approve' => ['new'], 'done' => ['confirmed'], 'cancel' => ['new', 'confirmed'], 'reject' => ['new'], 'reopen' => ['rejected', 'cancelled', 'done']][$path];
        if ($needReason && $reason === '') {
            flash('Please write the reason.', 'bad');
            redirect(url('portal/requests/' . $id));
        }
        if (!in_array($r['status'], $allowed, true)) {
            flash('This request is already ' . $r['status'] . '.', 'bad');
            redirect(url('portal/requests/' . $id));
        }
        $app->db->run('UPDATE sc_appointment_requests SET status = ?, reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ?', [$to, $needReason ? $reason : ($to === 'new' ? null : $r['reason']), (int) $p->user['id'], $id]);
        $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'request_' . $path, $r['status'], $to, 'Request #' . $id . ($reason !== '' ? ': ' . $reason : ''));
        if ($r['email'] && in_array($path, ['approve', 'reject', 'cancel'], true)) {
            $word = ['approve' => 'approved', 'reject' => 'not accepted', 'cancel' => 'cancelled'][$path];
            $app->mailer->send((string) $r['email'], 'Your request to ' . $p->vendor['name'] . ' is ' . $word, 'Hello ' . $r['name'] . ",\n\nYour request for " . dt((string) $r['preferred_date']) . ' was ' . $word . ' by ' . $p->vendor['name'] . '.' . ($reason !== '' ? "\n\nReason: " . $reason : '') . "\n\n" . $p->vendor['name']);
        }
        flash($ok, 'ok');
        redirect($_SERVER['HTTP_REFERER'] ?? url('portal/requests'));
    });
};
$reqDo('approve', 'confirmed', 'Approved.');
$reqDo('done', 'done', 'Marked done.');
$reqDo('cancel', 'cancelled', 'Cancelled.');
$reqDo('reject', 'rejected', 'Rejected. The reason was saved.', true);
$reqDo('reopen', 'new', 'Reopened.');

// ------------------------------------------------------------------ people (doctors / patients / staff)
$peopleCfg = ['doctors' => ['manage_doctors', 'doctors', 'team', 'Add'], 'patients' => ['manage_patients', 'patients', 'people', 'Register'], 'staff' => ['manage_users', 'staff', null, 'Add']];
foreach ($peopleCfg as $kind => [$perm, $mod, $labelKey, $verb]) {
    $title = static fn (Portal $p): string => $labelKey ? $p->label($labelKey, ucfirst($kind)) : 'Staff accounts';
    $guard = static function (Portal $p) use ($perm, $mod, $labelKey): void {
        $p->need($perm, $mod);
        if (($labelKey === 'people' && $p->label('people') === '') || ($labelKey === 'team' && !$p->hasKind('team'))) {
            flash('That list is not used by your kind of organisation.', 'info');
            redirect(url('portal'));
        }
    };
    $app->router->get('/portal/' . $kind, static function () use ($app, $dash, $card, $btn, $kind, $title, $guard, $verb): void {
        $p = Portal::boot($app);
        $guard($p);
        $people = new People($app, $p);
        $dash($p, $kind, $title($p), $card('', $people->listing($kind)), ['actions' => $p->writable() ? $btn($verb . ' ' . ($kind === 'staff' ? 'a staff member' : 'one'), url('portal/' . $kind . '/new')) : '', 'sub' => $kind === 'staff' ? 'People who sign in to this portal. Each role sees only what it needs.' : '']);
    });
    $form = static function (array $a) use ($app, $dash, $card, $kind, $title, $guard): void {
        $p = Portal::boot($app);
        $guard($p);
        $people = new People($app, $p);
        $id = isset($a['id']) ? (int) $a['id'] : null;
        if ($id !== null && $people->find($kind, $id) === null) {
            $app->error(404, 'We could not find that person.');
        }
        $dash($p, $kind, ($id ? 'Edit ' : 'New ') . ($kind === 'staff' ? 'staff member' : strtolower(rtrim($title($p), 's'))), $card('', $people->form($kind, $id)));
    };
    $app->router->get('/portal/' . $kind . '/{id}/view', static function (array $a) use ($app, $dash, $card, $kind, $title, $guard, $btn): void {
        $p = Portal::boot($app);
        $guard($p);
        $people = new People($app, $p);
        $dash($p, $kind, 'Profile', $people->profile($kind, (int) $a['id']), ['actions' => $btn('Back to ' . strtolower($title($p)), url('portal/' . $kind), 'd-btn-soft')]);
    });
    $app->router->get('/portal/' . $kind . '/{id}/delete', static function (array $a) use ($app, $dash, $card, $kind, $guard): void {
        $p = Portal::boot($app);
        $guard($p);
        $p->needWritable();
        $people = new People($app, $p);
        if ($people->find($kind, (int) $a['id']) === null) {
            $app->error(404, 'We could not find that person.');
        }
        $dash($p, $kind, 'Delete', $card('', $people->deleteForm($kind, (int) $a['id'])));
    });
    $mutate = static function (string $path, callable $do, string $ok) use ($app, $kind, $guard, $posted): void {
        $app->router->post('/portal/' . $kind . '/{id}/' . $path, static function (array $a) use ($app, $kind, $guard, $posted, $do, $ok): void {
            $p = Portal::boot($app);
            $guard($p);
            $p->needWritable();
            $err = $do(new People($app, $p), $kind, (int) $a['id']);
            $posted($ok, url('portal/' . $kind . '/' . $a['id'] . '/view'), $err);
        });
    };
    $mutate('suspend', static fn (People $pe, string $k, int $id) => $pe->setState($k, $id, 0), 'Account suspended.');
    $mutate('activate', static fn (People $pe, string $k, int $id) => $pe->setState($k, $id, 1), 'Account activated.');
    $mutate('restore', static fn (People $pe, string $k, int $id) => $pe->restore($k, $id), 'Restored. The account is suspended until you activate it.');
    $mutate('reset', static fn (People $pe, string $k, int $id) => $pe->resetLink($k, $id, ($_POST['mode'] ?? 'email') !== 'link'), 'Done. If no email went out, the one-time link is shown on this page.');
    $app->router->post('/portal/' . $kind . '/{id}/delete', static function (array $a) use ($app, $kind, $guard, $posted): void {
        $p = Portal::boot($app);
        $guard($p);
        $p->needWritable();
        $err = (new People($app, $p))->softDelete($kind, (int) $a['id'], (string) ($_POST['confirm'] ?? ''));
        if ($err !== null) {
            flash($err, 'bad');
            redirect(url('portal/' . $kind . '/' . $a['id'] . '/delete'));
        }
        flash('Deleted. You can restore from the list, State = Deleted.', 'ok');
        redirect(url('portal/' . $kind));
    });
    $app->router->get('/portal/' . $kind . '/new', $form);
    $app->router->get('/portal/' . $kind . '/{id}', $form);
    $app->router->post('/portal/' . $kind . '/save', static function () use ($app, $kind, $guard, $posted): void {
        $p = Portal::boot($app);
        $guard($p);
        $p->needWritable();
        $err = (new People($app, $p))->save($kind);
        if ($err === null) {
            clear_old();
        }
        $posted('Saved.', url('portal/' . $kind), $err);
    });
}

// ------------------------------------------------------------------ website profile and hours
$profileFields = static function (Portal $p): array {
    $f = [
        'tagline' => ['label' => 'One line about you', 'max' => 190, 'wide' => true],
        'about' => ['label' => 'About us', 'type' => 'textarea', 'max' => 3000, 'rows' => 5],
        'address' => ['label' => 'Street address', 'max' => 190],
        'city' => ['label' => 'City', 'max' => 120],
        'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
        'email' => ['label' => 'Public email', 'type' => 'email', 'max' => 191],
        'emergency_phone' => ['label' => (string) ($p->tpl['profile']['emergency_phone'] ?? 'Emergency phone'), 'type' => 'tel', 'max' => 40],
        'license_no' => ['label' => 'Registration / licence number', 'max' => 80, 'help' => 'Printed on invoices and forms.'],
        'logo' => ['label' => 'Logo', 'type' => 'image'],
        'cover_image' => ['label' => 'Cover photo', 'type' => 'image'],
        'social_facebook' => ['label' => 'Facebook link', 'type' => 'url', 'max' => 190], 'social_x' => ['label' => 'X link', 'type' => 'url', 'max' => 190],
        'social_instagram' => ['label' => 'Instagram link', 'type' => 'url', 'max' => 190], 'social_youtube' => ['label' => 'YouTube link', 'type' => 'url', 'max' => 190], 'social_linkedin' => ['label' => 'LinkedIn link', 'type' => 'url', 'max' => 190],
    ];
    foreach (VendorTypes::textSections($p->tpl) as $s) {
        $f[(string) $s['field']] = ['label' => (string) $s['label'], 'type' => 'textarea', 'max' => 2000, 'rows' => 3, 'help' => (string) ($s['help'] ?? '')];
    }
    return $f;
};
$app->router->get('/portal/website', static function () use ($app, $dash, $card, $profileFields): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $t = $p->tenantId;
    $h = '<form class="d-form" method="post" action="' . e(url('portal/website')) . '" enctype="multipart/form-data">' . csrf_field() . '<div class="d-form-grid">';
    foreach ($profileFields($p) as $k => $f) {
        $cur = $k === 'city' ? (string) ($p->vendor['city'] ?? '') : ($k === 'phone' ? $app->site->get($t, 'phone', (string) ($p->vendor['phone'] ?? '')) : $app->site->get($t, $k));
        $h .= Crud::field($k, $f, old($k, $cur));
    }
    $listed = (int) $app->db->val('SELECT is_listed FROM sc_vendors WHERE tenant_id = ?', [$t]);
    $h .= Crud::field('is_listed', ['label' => 'Show me in the public directory', 'type' => 'toggle'], old('is_listed', (string) $listed));
    $h .= '</div><h3 style="margin-top:26px">Opening hours</h3><p class="d-help">Write the hours or "Closed". Leave empty to hide a day.</p><div class="d-form-grid">';
    foreach (['sat' => 'Saturday', 'sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday'] as $k => $lab) {
        $h .= Crud::field('hours_' . $k, ['label' => $lab, 'max' => 60, 'placeholder' => '09:00 - 17:00'], old('hours_' . $k, $app->site->get($t, 'hours_' . $k)));
    }
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit"' . ($p->writable() ? '' : ' disabled') . '>Save changes</button><a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e($app->tenant->siteUrl((string) $p->vendor['slug'])) . '">View my website</a></div></form>';
    clear_old();
    $dash($p, 'website', 'Profile and hours', $card('', $h), ['sub' => 'What visitors see at the top of your website.']);
});
$app->router->post('/portal/website', static function () use ($app, $profileFields, $posted): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $p->needWritable();
    $t = $p->tenantId;
    $fields = $profileFields($p);
    $fields += array_combine(array_map(fn ($k) => 'hours_' . $k, ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']), array_fill(0, 7, ['max' => 60, 'label' => 'Hours']));
    $fields['is_listed'] = ['type' => 'toggle'];
    [$d, $err] = Crud::collect($fields, 'uploads/v/' . $t);
    if ($err !== null) {
        keep_old($_POST);
        $posted('', url('portal/website'), $err);
    }
    foreach ($d as $k => $v) {
        if ($k === 'is_listed') {
            $app->db->run('UPDATE sc_vendors SET is_listed = ? WHERE tenant_id = ?', [(int) $v, $t]);
        } elseif ($k === 'city') {
            $app->db->run('UPDATE sc_vendors SET city = ? WHERE tenant_id = ?', [$v ?: null, $t]);
        } elseif (($fields[$k]['type'] ?? '') === 'image') {
            if ($v !== null) {
                $app->site->set($t, $k, (string) $v);
            }
        } else {
            $app->site->set($t, $k, (string) $v);
            if ($k === 'phone') {
                $app->db->run('UPDATE sc_vendors SET phone = ? WHERE tenant_id = ?', [$v ?: null, $t]);
            }
        }
    }
    $posted('Saved. Your website is updated.', url('portal/website'));
});

// ------------------------------------------------------------------ web addresses (custom domains + SSL state)
$app->router->get('/portal/domains', static function () use ($app, $dash, $card): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $dash($p, 'domains', 'Web addresses', $card('', $app->domains->panel($p->tenantId, 'portal/domains')), ['sub' => 'Use your own domain for your website. We set up the HTTPS lock for you.']);
});
$app->router->post('/portal/domains/add', static function () use ($app, $posted): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $p->needWritable();
    $err = $app->domains->add($p->tenantId, (string) ($_POST['host'] ?? ''), (int) $p->user['id']);
    if ($err === null) {
        $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'domain_added', null, (string) ($_POST['host'] ?? ''));
    }
    $posted('Domain added. Now set the DNS records shown below.', url('portal/domains'), $err);
});
$app->router->post('/portal/domains/verify/{id}', static function (array $a) use ($app): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $row = $app->db->one('SELECT * FROM sc_domains WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    if ($row !== null) {
        [$ok, $msg] = $app->domains->verify($row);
        flash($ok ? 'DNS is right. We are getting the HTTPS certificate now, this takes a few minutes.' : $msg, $ok ? 'ok' : 'bad');
    }
    redirect(url('portal/domains'));
});
$app->router->post('/portal/domains/remove/{id}', static function (array $a) use ($app): void {
    $p = Portal::boot($app);
    $p->need('manage_settings', 'website');
    $p->needWritable();
    if ($app->domains->remove($p->tenantId, (int) $a['id'])) {
        $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'domain_removed', (int) $a['id'], null);
        flash('Domain removed.', 'ok');
    }
    redirect(url('portal/domains'));
});

// ------------------------------------------------------------------ website content lists (Crud modules)
$moduleFor = static function (Portal $p, string $key) use ($app): ?array {
    $t = $p->tenantId;
    $common = ['url' => 'portal/c/' . $key, 'upload_dir' => 'v/' . $t];
    if ($key === 'free-services') {
        $icons = array_combine(['heart', 'pill', 'flask', 'truck', 'stethoscope', 'drop', 'siren', 'users', 'home', 'star'], ['Heart / care', 'Medicine', 'Test', 'Delivery', 'Check-up', 'Blood', 'Emergency', 'Camp / community', 'Home visit', 'Special']);
        return $common + [
            'key' => 'free-services', 'title' => 'Free services', 'singular' => 'Free service', 'table' => 'free_services', 'scope' => ['tenant_id' => $t],
            'fields' => ['title' => ['label' => 'Name of the free service', 'required' => true, 'max' => 190, 'wide' => true, 'help' => 'For example: Free eye camp, Free home delivery over 500.'], 'description' => ['label' => 'What is included', 'type' => 'textarea', 'max' => 1500], 'schedule' => ['label' => 'When', 'max' => 190, 'placeholder' => 'Every Friday 9am to 1pm'], 'eligibility' => ['label' => 'Who can use it', 'max' => 190, 'placeholder' => 'Senior citizens'], 'icon' => ['label' => 'Icon', 'type' => 'select', 'options' => $icons], 'image' => ['label' => 'Photo', 'type' => 'image'], 'sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 0], 'is_active' => ['label' => 'Show on my website and the main site', 'type' => 'toggle', 'default' => 1]],
            'columns' => ['title' => ['label' => 'Service'], 'schedule' => ['label' => 'When'], 'eligibility' => ['label' => 'For'], 'is_active' => ['label' => 'Shown', 'type' => 'bool']],
            'search' => ['t.title', 't.description'], 'sort' => 'title', 'empty' => 'Add a free camp, free delivery or any service you give at no cost. It shows on your website and in the main free services list.',
            'before_save' => function (array $d, ?array $old) { $d[$old ? 'updated_at' : 'created_at'] = date('Y-m-d H:i:s'); $d['updated_at'] = date('Y-m-d H:i:s'); return $d; },
        ];
    }
    if ($key === 'departments') {
        if (!$p->hasKind('departments')) {
            return null; // this kind of organisation has no departments list
        }
        return $common + ['key' => 'departments', 'title' => 'Departments', 'singular' => 'Department', 'table' => 'doctor_departments', 'scope' => ['tenant_id' => $t],
            'fields' => ['title' => ['label' => 'Department name', 'required' => true, 'max' => 160], 'description' => ['label' => 'About the department', 'type' => 'textarea', 'max' => 1000]],
            'columns' => ['title' => ['label' => 'Department'], 'description' => ['label' => 'About']], 'search' => ['t.title'], 'sort' => 'title', 'empty' => 'Add the departments that doctors belong to.',
            'before_save' => function (array $d, ?array $old) { $d['updated_at'] = date('Y-m-d H:i:s'); if (!$old) { $d['created_at'] = $d['updated_at']; } return $d; },
            'before_delete' => fn (array $r) => \Selfcare\app()->db->val('SELECT COUNT(*) FROM doctors WHERE doctor_department_id = ?', [(int) $r['id']]) ? 'This department still has doctors. Move them first.' : null];
    }
    $sec = $p->section($key);
    if ($sec === null || !in_array($sec['kind'], ['cards', 'faq', 'testimonials', 'gallery', 'news'], true)) {
        return null;
    }
    $f = ['title' => ['label' => $sec['kind'] === 'faq' ? 'Question' : ($sec['kind'] === 'testimonials' ? 'Name of the person' : 'Title'), 'required' => $sec['kind'] !== 'gallery', 'max' => 190, 'wide' => true]];
    if ($sec['kind'] !== 'gallery') {
        $f['body'] = ['label' => $sec['kind'] === 'faq' ? 'Answer' : ($sec['kind'] === 'testimonials' ? 'What they said' : 'Description'), 'type' => 'textarea', 'max' => 1500];
    }
    if (!empty($sec['meta'])) {
        $f['meta'] = ['label' => (string) $sec['meta'], 'max' => 190, 'help' => (string) ($sec['metaHelp'] ?? '')];
    }
    if (in_array($sec['kind'], ['cards', 'gallery', 'news'], true)) {
        $f['image'] = ['label' => 'Photo', 'type' => 'image'];
    }
    if ($sec['kind'] === 'news') {
        $f['published_at'] = ['label' => 'Date', 'type' => 'date', 'null' => true];
    }
    $f += ['sort_order' => ['label' => 'Order (small numbers first)', 'type' => 'number', 'default' => 0], 'is_active' => ['label' => 'Show on my website', 'type' => 'toggle', 'default' => 1]];
    $cols = ['title' => ['label' => 'Title']];
    if (!empty($sec['meta'])) {
        $cols['meta'] = ['label' => (string) $sec['meta']];
    }
    $cols += ['sort_order' => ['label' => 'Order'], 'is_active' => ['label' => 'Shown', 'type' => 'bool']];
    return $common + ['key' => $key, 'title' => (string) $sec['label'], 'singular' => (string) ($sec['item'] ?? 'Item'), 'table' => 'sc_items', 'scope' => ['tenant_id' => $t, 'section' => $key], 'fields' => $f, 'columns' => $cols, 'search' => ['t.title', 't.body'], 'sort' => 'sort_order', 'empty' => 'Add the first one and it shows on your website.'];
};
$contentPerm = static function (Portal $p, string $key): array {
    if ($key === 'free-services') {
        return ['manage_free_services', 'free_services'];
    }
    if ($key === 'departments') {
        return ['manage_doctor_departments', 'website'];
    }
    $s = $p->section($key);
    return match ($s['kind'] ?? '') { 'cards' => ['manage_catalog', 'catalog'], 'news' => ['manage_notice_board', 'website'], default => ['manage_settings', 'website'] };
};
$contentPage = static function (callable $body) use ($app, $moduleFor, $contentPerm): callable {
    return static function (array $a) use ($app, $moduleFor, $contentPerm, $body): void {
        $p = Portal::boot($app);
        $key = (string) $a['key'];
        $m = $moduleFor($p, $key);
        if ($m === null) {
            $app->error(404, 'We could not find that list.');
        }
        [$perm, $mod] = $contentPerm($p, $key);
        $p->need($perm, $mod);
        $body($p, new Crud($app->db, $m), $m, $a);
    };
};
$app->router->get('/portal/c/{key}', $contentPage(static function (Portal $p, Crud $c, array $m) use ($app, $dash, $card, $btn): void {
    $dash($p, $m['key'] === 'free-services' ? 'c-free-services' : 'c-' . $m['key'], $m['title'], $card('', $c->list()), ['actions' => $p->writable() ? $btn('Add ' . strtolower($m['singular']), $c->url('new')) : '', 'sub' => $m['key'] === 'free-services' ? 'Services you give for free. People see them on your website and on the main Free services page.' : '']);
}));
$formPage = static function (Portal $p, Crud $c, array $m, array $a) use ($dash, $card): void {
    $id = isset($a['id']) ? (int) $a['id'] : null;
    if ($id !== null && $c->find($id) === null) {
        \Selfcare\app()->error(404, 'We could not find that item.');
    }
    $dash($p, $m['key'] === 'free-services' ? 'c-free-services' : 'c-' . $m['key'], ($id ? 'Edit ' : 'Add ') . strtolower($m['singular']), $card('', $c->form($id)));
};
$app->router->get('/portal/c/{key}/new', $contentPage($formPage));
$app->router->post('/portal/c/{key}/save', $contentPage(static function (Portal $p, Crud $c, array $m) use ($posted): void {
    $p->needWritable();
    $err = $c->save();
    if ($err === null) {
        clear_old();
    }
    $posted('Saved.', url($m['url']), $err);
}));
$app->router->get('/portal/c/{key}/{id}', $contentPage($formPage));
$app->router->post('/portal/c/{key}/{id}/delete', $contentPage(static function (Portal $p, Crud $c, array $m, array $a) use ($posted): void {
    $p->needWritable();
    if ($c->delete((int) $a['id'])) {
        flash('Deleted.', 'ok');
    }
    redirect(url($m['url']));
}));

// ------------------------------------------------------------------ messages
$app->router->get('/portal/messages', static function () use ($app, $dash, $card): void {
    $p = Portal::boot($app);
    $p->need('view_messages', 'messages');
    $html = Listing::render($app->db, [
        'id' => 'messages', 'select' => 'm.*', 'from' => 'sc_messages m', 'where' => ['m.tenant_id = ?'], 'params' => [$p->tenantId], 'search' => ['m.name', 'm.email', 'm.message'], 'search_hint' => 'Search messages',
        'filters' => ['read' => ['label' => 'Show', 'sql' => 'm.is_read = ?', 'options' => ['0' => 'Unread', '1' => 'Read']]],
        'columns' => ['name' => ['label' => 'From', 'sort' => 'm.name', 'render' => fn ($r) => '<b>' . e($r['name']) . '</b><br><small class="d-mute">' . e((string) ($r['email'] ?: $r['phone'])) . '</small>'], 'message' => ['label' => 'Message'], 'is_read' => ['label' => 'State', 'sort' => 'm.is_read', 'render' => fn ($r) => $r['is_read'] ? '<span class="d-badge is-mute">Read</span>' : '<span class="d-badge is-warn">New</span>'], 'created_at' => ['label' => 'Received', 'sort' => 'm.id', 'type' => 'datetime']],
        'sort' => 'created_at', 'dir' => 'desc',
        'actions' => [['label' => 'Mark read', 'icon' => 'check', 'method' => 'post', 'url' => fn ($r) => url('portal/messages/' . $r['id'] . '/read'), 'if' => fn ($r) => !$r['is_read']], ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this message?', 'url' => fn ($r) => url('portal/messages/' . $r['id'] . '/delete')]],
        'empty' => ['title' => 'No messages', 'text' => 'Messages from the contact form on your website show up here.'],
    ]);
    $dash($p, 'messages', 'Messages', $card('', $html));
});
$app->router->post('/portal/messages/{id}/{do}', static function (array $a) use ($app): void {
    $p = Portal::boot($app);
    $p->need('view_messages', 'messages');
    if ($a['do'] === 'read') {
        $app->db->run('UPDATE sc_messages SET is_read = 1 WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    } elseif ($a['do'] === 'delete') {
        $app->db->run('DELETE FROM sc_messages WHERE id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    }
    redirect(url('portal/messages'));
});

// ------------------------------------------------------------------ role access
$permModule = ['manage_doctors' => 'doctors', 'manage_patients' => 'patients', 'manage_users' => 'staff', 'manage_settings' => 'website', 'manage_catalog' => 'catalog', 'manage_free_services' => 'free_services', 'view_messages' => 'messages', 'manage_access' => 'access', 'manage_invoices' => 'billing', 'manage_prescriptions' => 'documents', 'manage_medicines' => 'documents', 'manage_blood_bank' => 'documents', 'manage_reports' => 'reports', 'manage_accounts' => 'billing'];
$app->router->get('/portal/access', static function () use ($app, $dash, $card, $permModule): void {
    $p = Portal::boot($app);
    $p->need('manage_access', 'access');
    $a = $app->access;
    $t = $p->tenantId;
    $offered = $a->offeredRoles((string) $p->type['slug']);
    $enabled = $a->enabledRoles($t, (string) $p->type['slug']);
    $sel = (string) ($_GET['role'] ?? '');
    if (!isset($offered[$sel])) {
        $sel = '';
    }
    $h = '<p class="d-help">Pick a role to change what it can do. Switch roles on only if your organisation uses them. Admin always has full access.</p><div class="d-table-wrap"><table class="d-table"><thead><tr><th>Role</th><th>What it is for</th><th>State</th><th>Actions</th></tr></thead><tbody>';
    foreach ($offered as $role => $ship) {
        $on = in_array($role, $enabled, true);
        $full = $a->full($role);
        $users = (int) $app->db->val("SELECT COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE d.name = ? AND u.tenant_id = ?", [\Selfcare\Auth::ROLE_MODEL, $role, $t]);
        $h .= '<tr' . ($sel === $role ? ' style="background:var(--soft)"' : '') . '><td data-label="Role"><b>' . e($a->roleLabel($role, $p->family)) . '</b><br><small class="d-mute">' . $users . ' ' . ($users === 1 ? 'person' : 'people') . '</small></td><td data-label="For">' . e($a->roleHelp($role)) . '</td><td data-label="State"><span class="d-badge is-' . ($on ? 'ok' : 'mute') . '">' . ($on ? 'In use' : 'Off') . '</span></td><td class="d-actions" data-label="Actions">';
        if (!$full) {
            $h .= '<a class="d-act" href="' . e(url('portal/access', ['role' => $role])) . '#matrix">' . icon('edit', 14) . '<span>Permissions</span></a>';
            if ($p->writable()) {
                $h .= '<form class="d-inline" method="post" action="' . e(url('portal/access/role')) . '">' . csrf_field() . '<input type="hidden" name="role" value="' . e($role) . '"><input type="hidden" name="on" value="' . ($on ? 0 : 1) . '"><button class="d-act" type="submit">' . icon($on ? 'x' : 'check', 14) . '<span>' . ($on ? 'Switch off' : 'Switch on') . '</span></button></form>';
            }
        } else {
            $h .= '<span class="d-mute">Always full access</span>';
        }
        $h .= '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    $out = $card('Roles', $h);
    if ($sel !== '' && !$a->full($sel)) {
        $cur = $a->effective($t, $sel, $p->family);
        $m = '<form method="post" action="' . e(url('portal/access/save')) . '">' . csrf_field() . '<input type="hidden" name="role" value="' . e($sel) . '"><div class="d-table-wrap"><table class="d-matrix"><thead><tr><th>Can do</th><th>Group</th><th>Allowed</th></tr></thead><tbody>';
        foreach ($a->permissions() as $name => $info) {
            $mod = $permModule[$name] ?? null;
            $planOk = $mod === null || $p->module($mod);
            $mine = $p->can($name);
            $dis = !$planOk || !$mine || !$p->writable();
            $why = !$planOk ? 'Not in your plan' : (!$mine ? 'You do not have this yourself' : '');
            $m .= '<tr><td><b>' . e($info[0]) . '</b><br><small class="d-mute">' . e($info[2]) . ($why !== '' ? ' (' . e($why) . ')' : '') . '</small></td><td>' . e($info[1]) . '</td><td><input type="checkbox" name="perm[]" value="' . e($name) . '"' . (in_array($name, $cur, true) ? ' checked' : '') . ($dis ? ' disabled' : '') . ' aria-label="' . e($info[0]) . '"></td></tr>';
        }
        $m .= '</tbody></table></div><div class="d-field" style="margin-top:14px"><label for="reason">Why are you changing this? (kept in the log)</label><input id="reason" name="reason" maxlength="250"></div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit"' . ($p->writable() ? '' : ' disabled') . '>Save role</button></div></form>';
        $out .= '<div id="matrix">' . $card('What "' . $a->roleLabel($sel, $p->family) . '" can do', $m) . '</div>';
    }
    $log = $app->db->all('SELECT l.*, u.first_name, u.last_name FROM permission_change_log l LEFT JOIN users u ON u.id = l.actor_id WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 15', [$t]);
    $l = '';
    foreach ($log as $r) {
        $l .= '<li><b>' . e(str_replace('_', ' ', (string) $r['action'])) . '</b> ' . e((string) $r['role_name']) . '<small>' . e(trim((string) $r['first_name'] . ' ' . (string) $r['last_name'])) . ' · ' . e(dt((string) $r['created_at'], 'd M Y, H:i')) . ($r['reason'] ? ' · ' . e((string) $r['reason']) : '') . '</small></li>';
    }
    $out .= $card('Recent changes', $l !== '' ? '<ul class="d-tl">' . $l . '</ul>' : '<p class="d-mute">Nothing has been changed yet.</p>');
    $dash($p, 'access', 'Role access', $out, ['sub' => 'Choose which roles you use and what each can do. For time-limited access, edit the person under Staff accounts.']);
});
$app->router->post('/portal/access/role', static function () use ($app, $posted): void {
    $p = Portal::boot($app);
    $p->need('manage_access', 'access');
    $p->needWritable();
    $role = (string) ($_POST['role'] ?? '');
    $offered = $app->access->offeredRoles((string) $p->type['slug']);
    $on = ($_POST['on'] ?? '0') === '1';
    $err = null;
    if (!isset($offered[$role]) || $app->access->full($role)) {
        $err = 'That role cannot be changed.';
    } elseif (!$on && $role === $p->role) {
        $err = 'You cannot switch off your own role.';
    } elseif (!$on && (int) $app->db->val("SELECT COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE d.name = ? AND u.tenant_id = ? AND u.status = 1", [\Selfcare\Auth::ROLE_MODEL, $role, $p->tenantId]) > 0) {
        $err = 'People still use this role. Move or deactivate them first.';
    }
    if ($err === null) {
        $app->access->setRoleEnabled($p->tenantId, $role, $on);
        $app->access->log($p->tenantId, (int) $p->user['id'], null, $role, $on ? 'role_enabled' : 'role_disabled', !$on, $on);
    }
    $posted(($on ? 'Role switched on.' : 'Role switched off.'), url('portal/access'), $err);
});
$app->router->post('/portal/access/save', static function () use ($app, $posted, $permModule): void {
    $p = Portal::boot($app);
    $p->need('manage_access', 'access');
    $p->needWritable();
    $role = (string) ($_POST['role'] ?? '');
    $a = $app->access;
    $t = $p->tenantId;
    $back = url('portal/access', ['role' => $role]);
    if (!isset($a->offeredRoles((string) $p->type['slug'])[$role]) || $a->full($role)) {
        $posted('', $back, 'That role cannot be changed.');
    }
    $before = $a->effective($t, $role, $p->family);
    $want = array_values(array_intersect(array_keys($a->permissions()), array_map('strval', (array) ($_POST['perm'] ?? []))));
    // keep what the form could not change (disabled boxes): plan ceiling and grantor ceiling
    foreach ($a->permissions() as $name => $_) {
        $mod = $permModule[$name] ?? null;
        $locked = ($mod !== null && !$p->module($mod)) || !$p->can($name);
        if ($locked) {
            $want = array_values(array_diff($want, [$name]));
            if (in_array($name, $before, true)) {
                $want[] = $name;
            }
        }
    }
    if ($role === $p->role && in_array('manage_access', $before, true) && !in_array('manage_access', $want, true)) {
        $posted('', $back, 'You cannot remove your own access to this page.');
    }
    $a->save($t, $role, $want);
    $a->log($t, (int) $p->user['id'], null, $role, 'permissions_changed', $before, $want, (string) ($_POST['reason'] ?? ''));
    $posted('Role saved.', $back);
});

// ------------------------------------------------------------------ plan
$app->router->get('/portal/plan', static function () use ($app, $dash, $card): void {
    $p = Portal::boot($app);
    $p->need('view_plan');
    $t = $p->tenantId;
    $s = $p->sub;
    $use = ['Staff accounts' => [(int) $app->db->val('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 1', [$t]), $s['max_staff'] ?? null], 'Patient records' => [(int) $app->db->val('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$t]), $s['max_patients'] ?? null], 'Requests this month' => [(int) $app->db->val('SELECT COUNT(*) FROM sc_appointment_requests WHERE tenant_id = ? AND created_at >= ?', [$t, date('Y-m-01')]), $s['max_appointments'] ?? null]];
    $h = '<dl class="d-kv"><dt>Plan</dt><dd><b>' . e((string) $s['plan_name']) . '</b></dd><dt>State</dt><dd>' . e($app->plans->label($s)) . '</dd><dt>Started</dt><dd>' . e(dt((string) ($s['starts_at'] ?? ''))) . '</dd><dt>Ends</dt><dd>' . (empty($s['ends_at']) ? 'Never' : e(dt((string) $s['ends_at']))) . '</dd></dl>';
    $u = '<div class="d-stats">';
    foreach ($use as $lab => [$n, $max]) {
        $u .= '<div class="d-stat"><span>' . e($lab) . '</span><b>' . $n . '</b><small>' . ($max === null ? 'no limit' : 'of ' . number_format((float) $max)) . '</small></div>';
    }
    $u .= '</div>';
    $mods = '<div class="d-pills">';
    $allowed = array_filter(array_map('trim', explode(',', (string) ($s['modules'] ?? ''))));
    foreach (Portal::MODULES as $k => $lab) {
        $on = !$allowed || in_array($k, $allowed, true);
        $mods .= '<span class="d-pill' . ($on ? ' is-on' : '') . '">' . e($lab) . ($on ? '' : ' (not in plan)') . '</span>';
    }
    $mods .= '</div>';
    $dash($p, 'plan', 'Plan and expiry', $card('Your plan', $h . '<p style="margin-top:14px" class="d-mute">To renew or change your plan, write to Self Care from the contact form on the main site.</p>') . $card('What you use', $u) . $card('What your plan includes', $mods));
});

// ------------------------------------------------------------------ account
$app->router->get('/portal/account', static function () use ($app, $dash, $card): void {
    $p = Portal::boot($app);
    $p->need('self');
    $u = $p->user;
    $h = '<form class="d-form" method="post" action="' . e(url('portal/account')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('first_name', ['label' => 'First name', 'required' => true, 'max' => 160], old('first_name', (string) $u['first_name']))
        . Crud::field('last_name', ['label' => 'Last name', 'max' => 191], old('last_name', (string) $u['last_name']))
        . Crud::field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], old('phone', (string) ($u['phone'] ?? '')))
        . '<div class="d-field"><label>Email</label><input value="' . e((string) $u['email']) . '" disabled><small class="d-help">Ask your admin to change your sign in email.</small></div>'
        . '</div><h3 style="margin-top:24px">Change password</h3><div class="d-form-grid">'
        . Crud::field('current', ['label' => 'Current password', 'type' => 'password', 'autocomplete' => 'current-password'], '')
        . Crud::field('password', ['label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'help' => 'At least 8 characters. Leave empty to keep your password.'], '')
        . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save</button></div></form>';
    clear_old();
    $dash($p, 'account', 'My account', $card('', $h));
});
$app->router->post('/portal/account', static function () use ($app, $posted): void {
    $p = Portal::boot($app);
    $p->need('self');
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $err = null;
    if ($first === '') {
        $err = 'Please enter your first name.';
    }
    $pw = (string) ($_POST['password'] ?? '');
    if ($err === null && $pw !== '') {
        $row = $app->db->one('SELECT password FROM users WHERE id = ?', [(int) $p->user['id']]);
        if (strlen($pw) < 8) {
            $err = 'Use at least 8 characters for the new password.';
        } elseif ($row === null || !password_verify((string) ($_POST['current'] ?? ''), (string) $row['password'])) {
            $err = 'The current password is not correct.';
        }
    }
    if ($err === null) {
        $set = ['first_name' => mb_substr($first, 0, 160), 'last_name' => mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 191), 'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 40) ?: null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($pw !== '') {
            $set['password'] = password_hash($pw, PASSWORD_BCRYPT);
        }
        $app->db->update('users', $set, 'id = ?', [(int) $p->user['id']]);
    }
    $posted('Saved.', url('portal/account'), $err);
});
