<?php
// Selfcare original routes: super admin (main host only). Vendors and subscriptions, types, plans, website content, platform billing and reports.
declare(strict_types=1);

use Selfcare\{Billing, Crud, Documents, Listing, Reports, Portal, VendorTypes, Vendors};
use function Selfcare\{app, csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, slugify, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$billing = new Billing($app->db);

/** Super admin only. Returns the user. */
$adminUser = static function () use ($app): array {
    $u = $app->auth->user();
    if ($u === null) {
        flash('Please sign in to continue.', 'info');
        redirect(url('login'));
    }
    if ($u['role'] !== 'Super Admin') {
        $app->error(403, 'This area is for Self Care administrators.', 'No access');
    }
    if ($app->hostVendor !== null) {
        redirect($app->tenant->mainUrl('admin'));
    }
    return $u;
};
$adminNav = static function () use ($app): array {
    $i = static fn (string $k, string $l, string $ic, string $p, int $badge = 0): array => ['key' => $k, 'label' => $l, 'icon' => $ic, 'url' => url($p), 'badge' => $badge];
    $pending = (int) $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE status = 'pending'");
    return [
        ['group' => 'Platform', 'items' => [$i('overview', 'Overview', 'home', 'admin'), $i('vendors', 'Vendors', 'building', 'admin/vendors'), $i('requests', 'Registration requests', 'clock', 'admin/requests', $pending), $i('types', 'Vendor types', 'layers', 'admin/types'), $i('plans', 'Plans', 'card', 'admin/plans')]],
        ['group' => 'Money', 'items' => [$i('billing', 'Subscription invoices', 'file', 'admin/billing'), $i('currencies', 'Currencies', 'card', 'admin/currencies'), $i('reports', 'Reports', 'chart', 'admin/reports')]],
        ['group' => 'Website', 'items' => [$i('content', 'Site content and footer', 'globe', 'admin/content'), $i('c-features', 'Features', 'star', 'admin/c/features'), $i('c-steps', 'How it works', 'arrow', 'admin/c/steps'), $i('c-faq', 'Questions', 'help', 'admin/c/faq'), $i('c-testimonials', 'Testimonials', 'heart', 'admin/c/testimonials'), $i('messages', 'Messages', 'mail', 'admin/messages'), $i('free', 'Free services', 'heart', 'admin/free-services')]],
        ['group' => 'Account', 'items' => [$i('account', 'My account', 'user', 'admin/account')]],
    ];
};
$page = static function (array $u, string $active, string $title, string $html, array $o = []) use ($app, $dash, $adminNav): void {
    $dash(null, $active, $title, $html, $o + ['nav' => $adminNav(), 'orgName' => 'Self Care', 'orgSub' => 'Super admin', 'user' => $u, 'roleLabel' => 'Super admin', 'banner' => '', 'navHome' => 'admin', 'siteLink' => $app->tenant->mainUrl('')]);
};
$go = static function (string $ok, string $to, ?string $err = null): void {
    if ($err !== null) {
        flash($err, 'bad');
        redirect($_SERVER['HTTP_REFERER'] ?? $to);
    }
    flash($ok, 'ok');
    redirect($to);
};

// ------------------------------------------------------------------ overview
$app->router->get('/admin', static function () use ($app, $adminUser, $page, $card, $stat, $billing): void {
    $u = $adminUser();
    $db = $app->db;
    $plat = \Selfcare\Currency::platform();
    $mrr = 0.0;
    $byPlan = [];
    foreach ($db->all("SELECT p.*, COUNT(*) AS n FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW()) AND s.tenant_id IN (SELECT tenant_id FROM sc_vendors WHERE status <> 'deleted') GROUP BY p.id") as $r) {
        [$amt] = \Selfcare\Plans::price($r, $plat);
        $per = (int) ($r['duration_days'] ?? 0) >= 360 ? $amt / 12 : $amt;
        $mrr += $per * (int) $r['n'];
        $byPlan[] = [(string) $r['name'], $per * (int) $r['n'], null, money($per * (int) $r['n'], $plat) . ' · ' . (int) $r['n'] . ' vendor' . ((int) $r['n'] === 1 ? '' : 's')];
    }
    usort($byPlan, static fn ($a, $b) => $b[1] <=> $a[1]);
    $live = "v.status <> 'deleted'";
    $pending = (int) $db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE status = 'pending'");
    $h = '<div class="d-stats">'
        . $stat('Vendors', (string) $db->val("SELECT COUNT(*) FROM sc_vendors v WHERE $live"), 'building', (int) $db->val("SELECT COUNT(*) FROM sc_vendors WHERE status = 'suspended'") . ' suspended')
        . $stat('Waiting for approval', (string) $pending, 'clock', $pending ? 'open the queue' : 'all clear')
        . $stat('Active subscriptions', (string) $db->val("SELECT COUNT(*) FROM sc_subscriptions WHERE status = 'active' AND (ends_at IS NULL OR ends_at > NOW())"), 'card')
        . $stat('Monthly recurring revenue', money($mrr, $plat), 'chart')
        . $stat('Ending in 30 days', (string) $db->val("SELECT COUNT(*) FROM sc_subscriptions WHERE status = 'active' AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)"), 'clock')
        . $stat('Unread messages', (string) $db->val('SELECT COUNT(*) FROM sc_messages WHERE tenant_id IS NULL AND is_read = 0'), 'mail') . '</div>';

    // one card per vendor type
    $mStart = date('Y-m-01');
    $pStart = date('Y-m-01', strtotime('-1 month'));
    $rows = $db->all("SELECT ty.slug, ty.name, ty.icon, ty.family, ty.is_enabled,
        COUNT(v.tenant_id) AS total, SUM(v.status = 'active') AS active, SUM(v.status = 'suspended') AS suspended,
        SUM(t.created_at >= ?) AS this_m, SUM(t.created_at >= ? AND t.created_at < ?) AS last_m,
        (SELECT COUNT(*) FROM sc_registration_requests r WHERE r.type_slug = ty.slug AND r.status = 'pending') AS pend
        FROM sc_vendor_types ty LEFT JOIN sc_vendors v ON v.type_slug = ty.slug AND v.status <> 'deleted' LEFT JOIN tenants t ON t.id = v.tenant_id
        GROUP BY ty.id, ty.slug, ty.name, ty.icon, ty.family, ty.is_enabled ORDER BY ty.sort_order, ty.name", [$mStart, $pStart, $mStart]);
    $cards = '';
    $bars = [];
    $fam = ['care' => ['Care providers', 0, 0], 'diag' => ['Diagnostics and blood', 0, 0], 'supply' => ['Pharmacy and supply', 0, 0], 'service' => ['Services', 0, 0]];
    $famOf = ['clinical' => 'care', 'residential' => 'care', 'rehab' => 'care', 'diagnostics' => 'diag', 'bloodbank' => 'diag', 'pharmacy' => 'supply', 'equipment' => 'supply', 'ambulance' => 'service', 'telemedicine' => 'service', 'homecare' => 'service'];
    foreach ($rows as $r) {
        $href = url('admin/vendors', ['kind' => $r['slug']]);
        $new = (int) $r['this_m'];
        $trend = $new > 0 ? '<span class="d-trend is-up">' . icon('arrow', 12) . ' +' . $new . ' this month</span>' : ((int) $r['last_m'] > 0 ? '<span class="d-trend">0 this month · ' . (int) $r['last_m'] . ' last</span>' : '<span class="d-trend is-flat">No new this month</span>');
        $cards .= '<a class="d-typecard' . ((int) $r['total'] === 0 ? ' is-empty' : '') . '" href="' . e($href) . '"><span class="ic">' . icon((string) $r['icon'], 20) . '</span><span class="nm">' . e($r['name']) . '</span><b class="n">' . (int) $r['total'] . '</b>'
            . '<span class="mini"><span><i class="dot ok"></i>' . (int) $r['active'] . ' active</span><span><i class="dot warn"></i>' . (int) $r['pend'] . ' pending</span><span><i class="dot bad"></i>' . (int) $r['suspended'] . ' suspended</span></span>' . $trend . '</a>';
        $bars[] = [(string) $r['name'], (int) $r['total'], $href, (string) (int) $r['total']];
        $k = $famOf[$r['family']] ?? 'service';
        $fam[$k][1] += (int) $r['total'];
        $fam[$k][2] += $new;
    }
    usort($bars, static fn ($a, $b) => $b[1] <=> $a[1]);
    $famBars = [];
    foreach ($fam as [$lab, $n, $new]) {
        $famBars[] = [$lab, $n, null, $n . ($new ? ' (+' . $new . ' this month)' : '')];
    }
    $h .= $card('Vendors by kind', '<div class="d-typegrid">' . $cards . '</div>');

    // sign ups per month (12 months) and revenue by plan
    $months = \Selfcare\Charts::months(12);
    $got = array_column($db->all("SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS m, COUNT(*) AS n FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.created_at >= ? GROUP BY m", [date('Y-m-01', strtotime('-11 month'))]), 'n', 'm');
    $cols = array_map(static fn ($m) => [$m[0], (int) ($got[$m[1]] ?? 0)], $months);
    $h .= '<div class="d-grid d-g2">' . $card('Vendors per kind', \Selfcare\Charts::bars($bars)) . '<div class="d-stack">' . $card('By group', \Selfcare\Charts::bars($famBars)) . $card('Monthly revenue by plan (' . $plat . ')', \Selfcare\Charts::bars($byPlan, 'No active subscriptions yet.')) . '</div></div>';
    $h .= $card('New vendors per month', \Selfcare\Charts::columns($cols, 'No sign ups in the last 12 months yet.'));
    $page($u, 'overview', 'Overview', $h, ['sub' => 'The whole platform at a glance. Click a kind to open its vendors.']);
});

// ------------------------------------------------------------------ vendors
$app->router->get('/admin/vendors', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    if (isset($_GET['kind']) && !isset($_GET['f_type'])) {
        $_GET['f_type'] = (string) $_GET['kind'];
    }
    $types = array_column(VendorTypes::all($app->db, false), 'name', 'slug');
    $html = Listing::render($app->db, [
        'where' => ($_GET['f_status'] ?? '') === 'deleted' ? [] : ["v.status <> 'deleted'"],
        'id' => 'vendors', 'select' => "t.id, t.tenant_username, t.hospital_name, v.type_slug, v.status, v.city, t.created_at, (SELECT CONCAT(p.name, '|', COALESCE(s.ends_at, '')) FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.tenant_id = t.id AND s.status = 'active' ORDER BY s.id DESC LIMIT 1) AS sub",
        'from' => 'tenants t JOIN sc_vendors v ON v.tenant_id = t.id', 'search' => ['t.hospital_name', 't.tenant_username', 'v.city'], 'search_hint' => 'Search by name, address or city',
        'filters' => ['type' => ['label' => 'Kind', 'sql' => 'v.type_slug = ?', 'options' => $types], 'status' => ['label' => 'State', 'sql' => 'v.status = ?', 'options' => ['active' => 'Active', 'suspended' => 'Suspended', 'deleted' => 'Deleted']]],
        'columns' => ['hospital_name' => ['label' => 'Vendor', 'sort' => 't.hospital_name', 'render' => fn ($r) => '<a href="' . e(url('admin/vendors/' . $r['id'])) . '"><b>' . e($r['hospital_name']) . '</b></a><br><small class="d-mute">' . e($r['tenant_username']) . '</small>'], 'type_slug' => ['label' => 'Kind', 'sort' => 'v.type_slug', 'render' => fn ($r) => e($types[$r['type_slug']] ?? $r['type_slug'])], 'city' => ['label' => 'City', 'sort' => 'v.city'],
            'sub' => ['label' => 'Plan', 'render' => function ($r) { [$n, $end] = array_pad(explode('|', (string) $r['sub']), 2, ''); return $n === '' ? '<span class="d-badge is-bad">No plan</span>' : e($n) . ' <small class="d-mute">' . ($end === '' ? 'never ends' : ($end < date('Y-m-d H:i:s') ? 'ended ' : 'ends ') . e(dt($end))) . '</small>'; }],
            'status' => ['label' => 'State', 'sort' => 'v.status', 'render' => fn ($r) => '<span class="d-badge is-' . ($r['status'] === 'active' ? 'ok' : ($r['status'] === 'deleted' ? 'mute' : 'bad')) . '">' . e(ucfirst((string) $r['status'])) . '</span>'], 'created_at' => ['label' => 'Joined', 'sort' => 't.created_at', 'type' => 'date']],
        'sort' => 'created_at', 'dir' => 'desc', 'actions' => [['label' => 'Profile', 'icon' => 'eye', 'url' => fn ($r) => url('admin/vendors/' . $r['id'])],
            ['label' => 'Open portal', 'icon' => 'home', 'method' => 'post', 'url' => fn ($r) => url('admin/vendors/' . $r['id'] . '/open-portal'), 'if' => fn ($r) => $r['status'] !== 'deleted'],
            ['label' => 'Website', 'icon' => 'globe', 'url' => fn ($r) => app()->tenant->siteUrl((string) $r['tenant_username']), 'if' => fn ($r) => $r['status'] !== 'deleted'],
            ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'url' => fn ($r) => url('admin/vendors/' . $r['id'] . '/delete'), 'if' => fn ($r) => $r['status'] !== 'deleted'],
            ['label' => 'Restore', 'icon' => 'check', 'method' => 'post', 'url' => fn ($r) => url('admin/vendors/' . $r['id'] . '/restore'), 'if' => fn ($r) => $r['status'] === 'deleted', 'confirm' => 'Restore this vendor?']],
        'empty' => ['title' => 'No vendors yet', 'text' => 'Vendors appear here when they register.'],
    ]);
    $page($u, 'vendors', 'Vendors', $card('', $html), ['sub' => 'Every registered organisation. Open one to change its plan.']);
});
// ------------------------------------------------------------------ add vendor, edit vendor, open portal, delete / restore / purge
$planOptions = static function () use ($app): array {
    $o = [];
    foreach ($app->plans->all() as $p) {
        $o[(string) $p['id']] = $p['name'] . ($p['price_label'] ? ' (' . $p['price_label'] . ')' : ((float) $p['price'] == 0 ? ' (free)' : ' (' . \Selfcare\Plans::priceText($p) . '/mo)')) . ($p['is_active'] ? '' : ' - hidden');
    }
    return $o;
};
$typeOptions = static fn (bool $enabledOnly = true): array => array_column(VendorTypes::all($app->db, $enabledOnly), 'name', 'slug');
$vendorFields = static function (bool $new) use ($typeOptions, $planOptions): array {
    $f = ['type' => ['label' => 'Kind of vendor', 'type' => 'select', 'options' => $typeOptions(!$new), 'required' => true], 'name' => ['label' => 'Organisation name', 'required' => true, 'max' => 120]];
    if ($new) {
        $f['slug'] = ['label' => 'Web address (sub domain)', 'required' => true, 'max' => 40, 'help' => 'Letters, numbers and dashes. It becomes name.' . ltrim(app()->tenant->suffix(), '.-') . '. Cannot be changed later.'];
    }
    $f += ['city' => ['label' => 'City', 'max' => 120], 'address' => ['label' => 'Street address', 'max' => 190], 'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40], 'email' => ['label' => 'Public contact email', 'type' => 'email', 'max' => 191], 'logo' => ['label' => 'Logo', 'type' => 'image']];
    if ($new) {
        $f += ['admin_first' => ['label' => 'Vendor admin first name', 'required' => true, 'max' => 120], 'admin_last' => ['label' => 'Vendor admin last name', 'max' => 120], 'admin_email' => ['label' => 'Vendor admin email (they sign in with it)', 'type' => 'email', 'required' => true, 'max' => 191],
            'plan_id' => ['label' => 'Plan', 'type' => 'select', 'options' => $planOptions(), 'required' => true], 'starts_at' => ['label' => 'Plan starts', 'type' => 'date'], 'ends_at' => ['label' => 'Plan ends', 'type' => 'date', 'help' => 'Leave empty with "Unlimited" on for a plan that never ends.'], 'unlimited' => ['label' => 'Unlimited (no end date)', 'type' => 'toggle']];
    }
    $f += ['status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'Active', 'suspended' => 'Suspended'], 'required' => true], 'is_listed' => ['label' => 'Show in the public directory', 'type' => 'toggle'], 'currency' => ['label' => 'Currency on the vendor\'s bills', 'type' => 'select', 'options' => \Selfcare\Currency::options(), 'blank' => true, 'blank_label' => 'Platform default']];
    return $f;
};
$setLink = static function (string $slug, int $uid, string $first, string $email, bool $mail = true) use ($app): string {
    $token = bin2hex(random_bytes(32));
    $app->db->run('UPDATE sc_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$uid]);
    $app->db->insert('sc_password_resets', ['user_id' => $uid, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400)]);
    $link = $app->tenant->siteUrl($slug, 'reset') . '?token=' . $token;
    $sent = $mail && $app->mailer->send($email, 'Set your Self Care password', "Hello $first,\n\nUse this link to choose your Self Care password. It works once and expires in 7 days:\n\n$link\n\nYour website: " . $app->tenant->siteUrl($slug) . "\n\nSelf Care");
    if (!$sent) {
        $_SESSION['sc_setlink'] = ['slug' => $slug, 'link' => $link, 'email' => $email];
    }
    return $link;
};

$app->router->get('/admin/vendors/new', static function () use ($app, $adminUser, $page, $card, $vendorFields): void {
    $u = $adminUser();
    $h = '<form class="d-form" method="post" action="' . e(url('admin/vendors/new')) . '" enctype="multipart/form-data">' . csrf_field() . '<div class="d-form-grid">';
    foreach ($vendorFields(true) as $k => $f) {
        $def = $k === 'status' ? 'active' : ($k === 'is_listed' ? '1' : ($k === 'starts_at' ? date('Y-m-d') : ($k === 'unlimited' ? '0' : '')));
        $field = Crud::field($k, $f, old($k, $def));
        $h .= $k === 'slug' ? str_replace('name="slug"', 'name="slug" data-slug-from="f_name"', $field) : $field;
    }
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Create vendor</button><a class="d-btn d-btn-soft" href="' . e(url('admin/vendors')) . '">Cancel</a></div></form>';
    clear_old();
    $page($u, 'vendors', 'Add a vendor', $card('', $h), ['sub' => 'Creates the website, the portal, the roles and the vendor admin account. The admin gets an email to set a password.']);
});
$app->router->post('/admin/vendors/new', static function () use ($app, $adminUser, $vendorFields, $setLink): void {
    $u = $adminUser();
    $slug = slugify((string) ($_POST['slug'] ?? ''));
    $fail = static function (string $m) use ($slug): void {
        keep_old($_POST);
        flash($m, 'bad');
        redirect(url('admin/vendors/new'));
    };
    [$d, $err] = Crud::collect($vendorFields(true), 'uploads/v/' . ($slug !== '' ? $slug : 'new'));
    if ($err !== null) {
        $fail($err);
    }
    $email = strtolower((string) $d['admin_email']);
    $type = (string) $d['type'];
    if (($e = Vendors::slugError($app, $slug)) !== null) {
        $fail($e);
    }
    if ($app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0 || $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE email = ? AND status = 'pending'", [$email]) > 0) {
        $fail('That email already has an account or a waiting registration.');
    }
    if ($app->db->val('SELECT COUNT(*) FROM tenants WHERE hospital_name = ?', [$d['name']]) > 0) {
        $fail('A vendor with that name already exists.');
    }
    $plan = $app->plans->find((int) $d['plan_id']);
    if ($plan === null || !$app->plans->fitsType($plan, $type)) {
        $fail('Choose a plan that is offered to this kind of vendor.');
    }
    $start = (string) ($d['starts_at'] ?: date('Y-m-d'));
    $end = (int) $d['unlimited'] === 1 ? null : ((string) $d['ends_at'] ?: null);
    if ($end === null && (int) $d['unlimited'] !== 1 && $plan['duration_days'] !== null && (int) $plan['duration_days'] > 0) {
        $end = date('Y-m-d', strtotime($start) + (int) $plan['duration_days'] * 86400);
    }
    if ($end !== null && $end < $start) {
        $fail('The plan cannot end before it starts.');
    }
    try {
        Vendors::create($app, ['type' => $type, 'name' => $d['name'], 'slug' => $slug, 'city' => $d['city'] ?: null, 'phone' => $d['phone'] ?: null, 'first_name' => $d['admin_first'], 'last_name' => $d['admin_last'], 'email' => $email, 'password' => bin2hex(random_bytes(12)), 'plan_id' => (int) $plan['id'], 'status' => $d['status']]);
    } catch (\Throwable $e) {
        $fail('Could not create the vendor. Please check the details and try again.');
    }
    $app->plans->assignRange($slug, (int) $plan['id'], $start . ' 00:00:00', $end !== null ? $end . ' 23:59:59' : null, 'Given by Self Care when the vendor was added', (int) $u['id']);
    foreach (['address', 'email', 'phone'] as $k) {
        if ((string) $d[$k] !== '') {
            $app->site->set($slug, $k, (string) $d[$k]);
        }
    }
    if ($d['logo'] !== null) {
        $app->site->set($slug, 'logo', (string) $d['logo']);
    }
    $app->db->run('UPDATE sc_vendors SET is_listed = ? WHERE tenant_id = ?', [(int) $d['is_listed'], $slug]);
    if ($d['currency'] !== null && isset(\Selfcare\Currency::all(true)[(string) $d['currency']])) {
        (new \Selfcare\Billing($app->db))->setSetting($slug, 'currency', (string) $d['currency']);
    }
    $uid = (int) $app->db->val('SELECT id FROM users WHERE email = ?', [$email]);
    $setLink($slug, $uid, (string) $d['admin_first'], $email);
    $app->access->log($slug, (int) $u['id'], $uid, null, 'vendor_created', null, ['type' => $type, 'plan' => $plan['name'], 'by' => 'super admin']);
    clear_old();
    flash(isset($_SESSION['sc_setlink']) ? 'Vendor created. The email could not be sent, so copy the set-password link below and send it yourself.' : 'Vendor created. A set-password email was sent to ' . $email . '.', 'ok');
    redirect(url('admin/vendors/' . $slug));
});

$app->router->get('/admin/vendors/{id}', static function (array $a) use ($app, $adminUser, $page, $card, $stat, $btn, $vendorFields, $planOptions): void {
    $u = $adminUser();
    $v = $app->db->one('SELECT t.id, t.tenant_username, t.hospital_name, t.created_at, v.* FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [(string) $a['id']]);
    if ($v === null) {
        $app->error(404, 'We could not find that vendor.');
    }
    $t = (string) $v['id'];
    $deleted = $v['status'] === 'deleted';
    $sub = $app->plans->current($t);
    $plans = array_filter($app->plans->all(), fn ($p) => $app->plans->fitsType($p, (string) $v['type_slug']));
    $opts = '';
    foreach ($plans as $p) {
        $opts .= '<option value="' . (int) $p['id'] . '">' . e($p['name']) . ($p['price_label'] ? ' (' . e($p['price_label']) . ')' : ((float) $p['price'] == 0 ? ' (free)' : ' (' . e(\Selfcare\Plans::priceText($p)) . '/mo)')) . ($p['is_active'] ? '' : ' - hidden') . '</option>';
    }
    $form = static fn (string $path, string $label, string $body = '', string $cls = 'd-btn-soft', string $confirm = ''): string => '<form class="d-inline" method="post" action="' . e(url('admin/vendors/' . $_GET['_id'] . '/' . $path)) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $body . '<button class="d-btn ' . $cls . '" type="submit">' . e($label) . '</button></form> ';
    $_GET['_id'] = $t;
    $siteUrl = $app->tenant->siteUrl((string) $v['tenant_username']);
    $admins = $app->db->all("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.last_login_at, d.name AS role FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND d.name IN ('Admin', 'Owner') ORDER BY u.is_admin_default DESC, u.id", [\Selfcare\Auth::ROLE_MODEL, $t]);
    $count = static fn (string $sql, array $pr = []): int => (int) $app->db->val($sql, $pr);
    $stats = ['Admins' => count($admins), 'Staff accounts' => $count('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [$t]), 'Doctors' => $count('SELECT COUNT(*) FROM doctors WHERE tenant_id = ?', [$t]), 'Patients / customers' => $count('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$t]), 'Invoices issued' => $count("SELECT COUNT(*) FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor'", [$t])];
    $h = '';
    if (isset($_SESSION['sc_setlink']) && $_SESSION['sc_setlink']['slug'] === $t) {
        $sl = $_SESSION['sc_setlink'];
        unset($_SESSION['sc_setlink']);
        $h .= '<div class="d-alert is-info"><b>One-time set-password link for ' . e((string) ($sl['email'] ?? '')) . '</b><p style="margin:6px 0">Copy it and send it yourself. It works once and expires in 7 days.</p><input class="d-copy" type="text" readonly value="' . e($sl['link']) . '" onclick="this.select()"></div>';
    }
    if ($deleted) {
        $h .= '<div class="d-alert is-danger"><b>This vendor is deleted.</b> Its website and portal are switched off. ' . $form('restore', 'Restore', '', 'd-btn-primary') . '<a class="d-btn d-btn-danger" href="' . e(url('admin/vendors/' . $t . '/purge')) . '">Erase for good</a></div>';
    }
    $h .= '<div class="d-stats">';
    foreach ($stats as $l => $n) {
        $h .= $stat($l, (string) $n, 'users');
    }
    $h .= '</div>';
    $reg = $app->db->one("SELECT * FROM sc_registration_requests WHERE tenant_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1", [$t]);
    $created = $app->db->one("SELECT l.created_at, l.actor_id FROM permission_change_log l WHERE l.tenant_id = ? AND l.action = 'vendor_created' ORDER BY l.id LIMIT 1", [$t]);
    $regInfo = $reg ? 'Registered on the public sign up page on ' . e(dt((string) $reg['created_at'])) . ' and approved on ' . e(dt((string) $reg['decided_at'])) . ' (request #' . (int) $reg['id'] . ').' : ($created ? 'Added by Self Care on ' . e(dt((string) $created['created_at'])) . '.' : 'Created on ' . e(dt((string) $v['created_at'])) . '.');
    $h .= '<div class="d-grid d-g2">';
    $h .= $card('Vendor details', '<dl class="d-kv"><dt>Name</dt><dd><b>' . e($v['hospital_name']) . '</b></dd><dt>Kind</dt><dd>' . e($app->typeRow((string) $v['type_slug'])['name']) . '</dd><dt>Website</dt><dd><a href="' . e($siteUrl) . '">' . e($siteUrl) . '</a></dd><dt>City</dt><dd>' . e((string) $v['city']) . '</dd><dt>Phone</dt><dd>' . e((string) $v['phone']) . '</dd><dt>Address</dt><dd>' . e($app->site->get($t, 'address')) . '</dd><dt>Public email</dt><dd>' . e($app->site->get($t, 'email')) . '</dd><dt>State</dt><dd><span class="d-badge is-' . ($v['status'] === 'active' ? 'ok' : 'bad') . '">' . e(ucfirst((string) $v['status'])) . '</span></dd><dt>In directory</dt><dd>' . ((int) $v['is_listed'] ? 'Yes' : 'No') . '</dd><dt>Joined</dt><dd>' . e(dt((string) $v['created_at'])) . '</dd><dt>Registration</dt><dd>' . $regInfo . '</dd></dl>'
        . '<p style="margin-top:14px">' . ($deleted ? '' : $form('open-portal', 'Open portal', '', 'd-btn-primary') . '<a class="d-btn d-btn-soft" href="' . e($siteUrl) . '">Visit website</a><a class="d-btn d-btn-soft" href="#edit">Edit profile</a>'
        . ($v['status'] === 'active' ? $form('suspend', 'Suspend', '', 'd-btn-soft', 'Suspend this vendor? Its staff cannot sign in and its website is hidden.') : $form('activate', 'Activate', '', 'd-btn-soft'))
        . '<a class="d-btn d-btn-danger" href="' . e(url('admin/vendors/' . $t . '/delete')) . '">Delete</a>') . '</p>');
    $h .= $card('Subscription', '<dl class="d-kv"><dt>Plan</dt><dd><b>' . e((string) $sub['plan_name']) . '</b></dd><dt>State</dt><dd>' . e($app->plans->label($sub)) . '</dd><dt>Started</dt><dd>' . e(dt((string) ($sub['starts_at'] ?? ''))) . '</dd></dl><p style="margin-top:14px">'
        . $form('extend', 'Extend 30 days', '<input type="hidden" name="days" value="30">') . $form('extend', 'Extend 365 days', '<input type="hidden" name="days" value="365">') . $form('noexpiry', 'Never expires') . $form('revoke', 'Revoke plan', '', 'd-btn-danger', 'Remove the plan? The vendor becomes read-only until you give a new one.') . '</p>');
    $h .= '</div>';

    // admins
    $rows = '';
    foreach ($admins as $ad) {
        $on = (int) $ad['status'] === 1;
        $pf = static fn (string $path, string $label, string $body = '', string $cls = 'd-act', string $confirm = '') => '<form class="d-inline" method="post" action="' . e(url('admin/vendors/' . $_GET['_id'] . '/admins/' . $path)) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $body . '<button class="' . $cls . '" type="submit">' . e($label) . '</button></form> ';
        $x = (int) $ad['id'];
        $rows .= '<tr><td data-label="Name"><b>' . e(trim($ad['first_name'] . ' ' . $ad['last_name'])) . '</b></td><td data-label="Email">' . e($ad['email']) . '</td><td data-label="Phone">' . e((string) $ad['phone']) . '</td><td data-label="Role">' . e($ad['role']) . '</td><td data-label="State"><span class="d-badge is-' . ($on ? 'ok' : 'mute') . '">' . ($on ? 'Active' : 'Suspended') . '</span></td><td data-label="Last sign in">' . ($ad['last_login_at'] ? e(dt((string) $ad['last_login_at'], 'd M Y H:i')) : '<span class="d-mute">Never</span>') . '</td><td data-label="Actions" class="r">'
            . ($deleted ? '' : $pf($x . '/open', 'Open portal', '', 'd-act') . $pf($x . '/reset', 'Email reset link', '<input type="hidden" name="mode" value="email">') . $pf($x . '/reset', 'Show one-time link', '<input type="hidden" name="mode" value="link">') . ($on ? $pf($x . '/suspend', 'Suspend', '', 'd-act is-danger', 'Suspend this admin? They cannot sign in until you activate them.') : $pf($x . '/activate', 'Activate'))) . '</td></tr>';
    }
    $addAdmin = $deleted ? '' : '<h4 style="margin:18px 0 8px">Add another admin</h4><form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/admins/add')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('first_name', ['label' => 'First name', 'required' => true, 'max' => 120], '') . Crud::field('last_name', ['label' => 'Last name', 'max' => 120], '') . Crud::field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 191], '') . Crud::field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], '')
        . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Add admin and email a set-password link</button></div></form>';
    $h .= $card('Admin accounts', '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>State</th><th>Last sign in</th><th class="r">Actions</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' . $addAdmin);

    // plan + history + invoices
    $h .= '<div class="d-grid d-g2">';
    if (!$deleted) {
        $h .= $card('Give or change the plan', '<form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/assign')) . '">' . csrf_field() . '<div class="d-form-grid"><div class="d-field"><label for="pl">Plan</label><select id="pl" name="plan_id" required>' . $opts . '</select></div>'
            . Crud::field('days', ['label' => 'Length in days (0 = never expires, empty = the plan length)', 'type' => 'number', 'min' => 0, 'step' => '1'], '') . Crud::field('note', ['label' => 'Note', 'max' => 200, 'wide' => true], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save plan</button></div></form>');
    }
    $hist = $app->db->all('SELECT s.*, p.name FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 12', [$t]);
    $l = '';
    foreach ($hist as $r) {
        $l .= '<li><b>' . e($r['name']) . '</b> · ' . e($r['status']) . '<small>' . e(dt((string) $r['starts_at'])) . ' to ' . ($r['ends_at'] ? e(dt((string) $r['ends_at'])) : 'never') . ($r['note'] ? ' · ' . e((string) $r['note']) : '') . '</small></li>';
    }
    $h .= $card('Plan history', $l !== '' ? '<ul class="d-tl">' . $l . '</ul>' : '<p class="d-mute">No plans yet.</p>');
    $h .= '</div>';
    $inv = $app->db->all("SELECT id, invoice_no, invoice_date, total, balance, status, currency FROM sc_invoices WHERE tenant_id = ? AND issuer = 'platform' ORDER BY id DESC LIMIT 10", [$t]);
    $ir = '';
    foreach ($inv as $r) {
        $ir .= '<tr><td data-label="No"><a href="' . e(url('admin/billing/' . $r['id'])) . '">' . e($r['invoice_no']) . '</a></td><td data-label="Date">' . e(dt((string) $r['invoice_date'])) . '</td><td data-label="Total" class="r">' . e(money((float) $r['total'], (string) $r['currency'])) . '</td><td data-label="Due" class="r">' . e(money((float) $r['balance'], (string) $r['currency'])) . '</td><td data-label="State">' . e(ucfirst((string) $r['status'])) . '</td></tr>';
    }
    $h .= $card('Subscription invoices', ($ir !== '' ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>No</th><th>Date</th><th class="r">Total</th><th class="r">Due</th><th>State</th></tr></thead><tbody>' . $ir . '</tbody></table></div>' : '<p class="d-mute">No invoices yet.</p>') . '<p style="margin-top:12px">' . $btn('Make a subscription invoice', url('admin/billing/new', ['tenant' => $t]), 'd-btn-soft') . '</p>');
    if (!$deleted) {
        $h .= $card('Web addresses and HTTPS', $app->domains->panel($t, 'admin/vendors/' . $t . '/domains'));
        $f = '<form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/save')) . '" enctype="multipart/form-data">' . csrf_field() . '<div class="d-form-grid">';
        $cur = ['type' => (string) $v['type_slug'], 'name' => (string) $v['hospital_name'], 'city' => (string) $v['city'], 'address' => $app->site->get($t, 'address'), 'phone' => (string) $v['phone'], 'email' => $app->site->get($t, 'email'), 'logo' => $app->site->get($t, 'logo'), 'status' => (string) $v['status'], 'is_listed' => (string) (int) $v['is_listed'], 'currency' => (string) $app->db->val("SELECT `value` FROM settings WHERE tenant_id = ? AND `key` = 'currency' LIMIT 1", [$t])];
        foreach ($vendorFields(false) as $k => $fd) {
            $f .= Crud::field($k, $fd, old($k, $cur[$k] ?? ''));
        }
        clear_old();
        $h .= '<div id="edit">' . $card('Edit vendor profile', $f . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save changes</button></div></form>') . '</div>';
    }
    $page($u, 'vendors', (string) $v['hospital_name'], $h, ['sub' => $app->typeRow((string) $v['type_slug'])['name'] . ' · ' . $v['tenant_username'], 'actions' => $deleted ? '' : $form('open-portal', 'Open portal', '', 'd-btn-primary')]);
});

$vendorPost = static function (string $path, callable $do, string $ok) use ($app, $adminUser, $go): void {
    $app->router->post('/admin/vendors/{id}/' . $path, static function (array $a) use ($app, $adminUser, $do, $ok, $go): void {
        $u = $adminUser();
        $t = (string) $a['id'];
        if (!$app->db->val('SELECT COUNT(*) FROM sc_vendors WHERE tenant_id = ?', [$t])) {
            $app->error(404, 'We could not find that vendor.');
        }
        $err = null;
        try {
            $do($t, $u);
        } catch (\InvalidArgumentException $e) {
            $err = $e->getMessage();
        }
        $go($ok, url('admin/vendors/' . $t), $err);
    });
};
$vendorPost('state', static function (string $t, array $u) use ($app): void {
    $to = $_POST['to'] === 'suspended' ? 'suspended' : 'active';
    $app->db->run('UPDATE sc_vendors SET status = ? WHERE tenant_id = ?', [$to, $t]);
    $app->access->log($t, (int) $u['id'], null, null, 'vendor_' . $to, null, $to);
}, 'Updated.');
$vendorPost('listed', static function (string $t) use ($app): void {
    $app->db->run('UPDATE sc_vendors SET is_listed = ? WHERE tenant_id = ?', [(int) ($_POST['to'] ?? 1) === 1 ? 1 : 0, $t]);
}, 'Updated.');
$vendorPost('assign', static function (string $t, array $u) use ($app): void {
    $plan = $app->plans->find((int) ($_POST['plan_id'] ?? 0));
    $type = (string) $app->db->val('SELECT type_slug FROM sc_vendors WHERE tenant_id = ?', [$t]);
    if ($plan === null || !$app->plans->fitsType($plan, $type)) {
        throw new \InvalidArgumentException('Choose a plan that is offered to this kind of vendor.');
    }
    $d = trim((string) ($_POST['days'] ?? ''));
    $app->plans->assign($t, (int) $plan['id'], $d === '' ? null : max(0, (int) $d), (string) ($_POST['note'] ?? '') ?: 'Set by Self Care', (int) $u['id']);
    $app->access->log($t, (int) $u['id'], null, null, 'plan_assigned', null, $plan['name']);
}, 'Plan saved.');
$vendorPost('extend', static function (string $t, array $u) use ($app): void {
    if (!$app->plans->extend($t, max(1, (int) ($_POST['days'] ?? 30)), (int) $u['id'])) {
        throw new \InvalidArgumentException('Nothing to extend: the vendor has no plan or its plan never expires.');
    }
    $app->access->log($t, (int) $u['id'], null, null, 'plan_extended', null, (int) $_POST['days']);
}, 'Extended.');
$vendorPost('noexpiry', static function (string $t, array $u) use ($app): void {
    if (!$app->plans->setNoExpiry($t)) {
        throw new \InvalidArgumentException('This vendor has no plan.');
    }
    $app->access->log($t, (int) $u['id'], null, null, 'plan_no_expiry', null, null);
}, 'The plan never expires now.');
$vendorPost('revoke', static function (string $t, array $u) use ($app): void {
    $app->plans->revoke($t);
    $app->access->log($t, (int) $u['id'], null, null, 'plan_revoked', null, null);
}, 'Plan removed.');

$app->router->post('/admin/vendors/{id}/save', static function (array $a) use ($app, $adminUser, $vendorFields, $go): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT t.id, t.hospital_name, v.* FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$t]);
    if ($v === null) {
        $app->error(404, 'We could not find that vendor.');
    }
    $fields = $vendorFields(false);
    [$d, $err] = Crud::collect($fields, 'uploads/v/' . $t);
    if ($err === null && $d['type'] !== $v['type_slug']) {
        $oldFam = VendorTypes::template($app->typeRow((string) $v['type_slug']))['family'];
        $newFam = VendorTypes::template($app->typeRow((string) $d['type']))['family'];
        if ($oldFam !== $newFam) {
            $err = 'The new kind of vendor uses a different set of roles and pages. Pick a kind from the same group, or create a new vendor.';
        }
    }
    if ($err === null && $app->db->val('SELECT COUNT(*) FROM tenants WHERE hospital_name = ? AND id <> ?', [$d['name'], $t]) > 0) {
        $err = 'Another vendor already has that name.';
    }
    if ($err !== null) {
        keep_old($_POST);
        $go('', url('admin/vendors/' . $t), $err);
    }
    $before = ['name' => $v['hospital_name'], 'type' => $v['type_slug'], 'city' => $v['city'], 'phone' => $v['phone'], 'status' => $v['status'], 'listed' => (int) $v['is_listed']];
    $app->db->run('UPDATE tenants SET hospital_name = ?, updated_at = NOW() WHERE id = ?', [$d['name'], $t]);
    $app->db->run('UPDATE users SET hospital_name = ? WHERE tenant_id = ?', [$d['name'], $t]);
    $app->db->run('UPDATE sc_vendors SET type_slug = ?, city = ?, phone = ?, status = ?, is_listed = ? WHERE tenant_id = ? AND status <> ?', [$d['type'], $d['city'] ?: null, $d['phone'] ?: null, $d['status'], (int) $d['is_listed'], $t, 'deleted']);
    foreach (['address', 'email', 'phone'] as $k) {
        $app->site->set($t, $k, (string) $d[$k]);
    }
    if ($d['logo'] !== null) {
        $app->site->set($t, 'logo', (string) $d['logo']);
    }
    if ($d['currency'] !== null && isset(\Selfcare\Currency::all(true)[(string) $d['currency']])) {
        (new \Selfcare\Billing($app->db))->setSetting($t, 'currency', (string) $d['currency']);
    }
    $app->access->log($t, (int) $u['id'], null, null, 'vendor_edited', $before, ['name' => $d['name'], 'type' => $d['type'], 'city' => $d['city'], 'phone' => $d['phone'], 'status' => $d['status'], 'listed' => (int) $d['is_listed']]);
    clear_old();
    $go('Vendor details saved.', url('admin/vendors/' . $t));
});

$openPortal = static function (array $a) use ($app, $adminUser): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->tenant->bySlug($t);
    if ($v === null || $v['status'] === 'deleted') {
        $app->error(404, 'We could not find that vendor.');
    }
    $uid = (int) $app->db->val('SELECT id FROM users WHERE tenant_id = ? AND status = 1 ORDER BY is_admin_default DESC, id LIMIT 1', [$t]);
    if (isset($a['uid'])) {
        $uid = (int) $app->db->val('SELECT id FROM users WHERE tenant_id = ? AND status = 1 AND id = ?', [$t, (int) $a['uid']]);
    }
    if ($uid === 0) {
        flash('This vendor has no active admin account to open.', 'bad');
        redirect(url('admin/vendors/' . $t));
    }
    $app->access->log($t, (int) $u['id'], $uid, null, 'impersonate_start', null, null, 'Opened the vendor portal as super admin');
    if ($app->tenant->suffix() !== '') {
        \Selfcare\hop($app->vendorPortalUrl($v, 'auth/handoff') . '?token=' . $app->auth->makeHandoff($uid, (int) $u['id']));
    }
    $app->auth->login($uid, false);
    $_SESSION['sc_impersonator'] = (int) $u['id'];
    redirect(url('portal'));
};
$app->router->post('/admin/vendors/{id}/open-portal', $openPortal);
$app->router->post('/admin/vendors/{id}/admins/{uid}/open', $openPortal);

// ------------------------------------------------------------------ vendor admin accounts
$adminOf = static function (array $a) use ($app): array {
    $row = $app->db->one("SELECT u.* FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.id = ? AND u.tenant_id = ? AND d.name IN ('Admin', 'Owner')", [\Selfcare\Auth::ROLE_MODEL, (int) $a['uid'], (string) $a['id']]);
    if ($row === null) {
        $app->error(404, 'We could not find that admin.');
    }
    return $row;
};
$app->router->post('/admin/vendors/{id}/admins/add', static function (array $a) use ($app, $adminUser, $go, $setLink): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    if ((string) $app->db->val('SELECT status FROM sc_vendors WHERE tenant_id = ?', [$t]) === '' || (string) $app->db->val('SELECT status FROM sc_vendors WHERE tenant_id = ?', [$t]) === 'deleted') {
        $app->error(404, 'We could not find that vendor.');
    }
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($first === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $go('', url('admin/vendors/' . $t), 'Enter a first name and a valid email.');
    }
    if ($app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        $go('', url('admin/vendors/' . $t), 'That email already has an account.');
    }
    $limit = $app->plans->limitError($t, 'staff');
    if ($limit !== null) {
        $go('', url('admin/vendors/' . $t), $limit);
    }
    $role = $app->access->roleId('Admin') ?? 1;
    $now = date('Y-m-d H:i:s');
    $name = (string) $app->db->val('SELECT hospital_name FROM tenants WHERE id = ?', [$t]);
    $uid = $app->db->insert('users', ['department_id' => $role, 'first_name' => mb_substr($first, 0, 120), 'last_name' => mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 120), 'email' => $email, 'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 40) ?: null, 'password' => password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT), 'is_super_admin_default' => 0, 'gender' => 0, 'is_admin_default' => 0, 'status' => 1, 'language' => 'en', 'hospital_name' => $name, 'tenant_id' => $t, 'created_at' => $now, 'updated_at' => $now]);
    $app->db->insert('model_has_roles', ['role_id' => $role, 'model_type' => \Selfcare\Auth::ROLE_MODEL, 'model_id' => $uid]);
    $setLink($t, $uid, $first, $email);
    $app->access->log($t, (int) $u['id'], $uid, 'Admin', 'admin_added', null, $email);
    $go(isset($_SESSION['sc_setlink']) ? 'Admin added. The email could not be sent, so copy the link below.' : 'Admin added. A set-password email was sent to ' . $email . '.', url('admin/vendors/' . $t));
});
$app->router->post('/admin/vendors/{id}/admins/{uid}/reset', static function (array $a) use ($app, $adminUser, $adminOf, $go, $setLink): void {
    $u = $adminUser();
    $row = $adminOf($a);
    $mode = ($_POST['mode'] ?? 'email') === 'link' ? 'link' : 'email';
    $setLink((string) $a['id'], (int) $row['id'], (string) $row['first_name'], (string) $row['email'], $mode === 'email');
    $app->access->log((string) $a['id'], (int) $u['id'], (int) $row['id'], null, 'password_reset_sent', null, $mode);
    $go($mode === 'link' ? 'One-time link made. Copy it below.' : (isset($_SESSION['sc_setlink']) ? 'The email could not be sent, so copy the link below.' : 'A reset link was emailed to ' . $row['email'] . '.'), url('admin/vendors/' . $a['id']));
});
foreach (['suspend' => 0, 'activate' => 1] as $do => $to) {
    $app->router->post('/admin/vendors/{id}/admins/{uid}/' . $do, static function (array $a) use ($app, $adminUser, $adminOf, $go, $to, $do): void {
        $u = $adminUser();
        $row = $adminOf($a);
        $app->db->run('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$to, (int) $row['id']]);
        $app->access->log((string) $a['id'], (int) $u['id'], (int) $row['id'], null, 'admin_' . $do, (int) $row['status'], $to);
        $go($to ? 'Admin activated.' : 'Admin suspended.', url('admin/vendors/' . $a['id']));
    });
}

$app->router->get('/admin/vendors/{id}/delete', static function (array $a) use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT t.id, t.tenant_username, t.hospital_name, v.status FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$t]);
    if ($v === null || $v['status'] === 'deleted') {
        $app->error(404, 'We could not find that vendor.');
    }
    $h = '<div class="d-alert is-danger"><b>Delete ' . e($v['hospital_name']) . '?</b><p style="margin:6px 0 0">Its website disappears, its staff cannot sign in and it is hidden from every list and the public directory. Nothing is erased: you can restore it later from Vendors, State = Deleted.</p></div>'
        . '<form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/delete')) . '" autocomplete="off">' . csrf_field()
        . '<div class="d-form-grid">' . Crud::field('confirm', ['label' => 'Type the web address ' . $v['tenant_username'] . ' to confirm', 'required' => true, 'max' => 60, 'wide' => true], '') . '</div>'
        . '<div class="d-form-actions"><button class="d-btn d-btn-danger" type="submit">Delete this vendor</button><a class="d-btn d-btn-soft" href="' . e(url('admin/vendors/' . $t)) . '">Keep it</a></div></form>';
    $page($u, 'vendors', 'Delete vendor', $card('', $h));
});
$app->router->post('/admin/vendors/{id}/delete', static function (array $a) use ($app, $adminUser): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT t.tenant_username, v.status FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$t]);
    if ($v === null || $v['status'] === 'deleted') {
        $app->error(404, 'We could not find that vendor.');
    }
    if (trim((string) ($_POST['confirm'] ?? '')) !== (string) $v['tenant_username']) {
        flash('The web address you typed does not match. Nothing was deleted.', 'bad');
        redirect(url('admin/vendors/' . $t . '/delete'));
    }
    $app->db->run("UPDATE sc_vendors SET prev_status = status, status = 'deleted', deleted_at = NOW(), deleted_by = ? WHERE tenant_id = ?", [(int) $u['id'], $t]);
    $app->access->log($t, (int) $u['id'], null, null, 'vendor_deleted', $v['status'], 'deleted');
    flash('Vendor deleted. You can restore it from Vendors, State = Deleted.', 'ok');
    redirect(url('admin/vendors'));
});
$app->router->post('/admin/vendors/{id}/restore', static function (array $a) use ($app, $adminUser): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT status, prev_status FROM sc_vendors WHERE tenant_id = ?', [$t]);
    if ($v === null || $v['status'] !== 'deleted') {
        $app->error(404, 'We could not find that deleted vendor.');
    }
    $to = in_array($v['prev_status'], ['active', 'suspended'], true) ? (string) $v['prev_status'] : 'active';
    $app->db->run('UPDATE sc_vendors SET status = ?, prev_status = NULL, deleted_at = NULL, deleted_by = NULL WHERE tenant_id = ?', [$to, $t]);
    $app->access->log($t, (int) $u['id'], null, null, 'vendor_restored', 'deleted', $to);
    flash('Vendor restored.', 'ok');
    redirect(url('admin/vendors/' . $t));
});
$app->router->get('/admin/vendors/{id}/purge', static function (array $a) use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT t.tenant_username, t.hospital_name, v.status FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$t]);
    if ($v === null || $v['status'] !== 'deleted') {
        $app->error(404, 'Only a deleted vendor can be erased for good.');
    }
    $h = '<div class="d-alert is-danger"><b>Erase ' . e($v['hospital_name']) . ' for good?</b><p style="margin:6px 0 0">This removes the vendor, its staff, patients, invoices, documents and website content from the database. It cannot be undone. Restore the vendor instead if you may need it again.</p></div>'
        . '<form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/purge')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('confirm', ['label' => 'Step 1: type the web address ' . $v['tenant_username'], 'required' => true, 'max' => 60, 'wide' => true], '')
        . Crud::field('confirm2', ['label' => 'Step 2: type ERASE in capital letters', 'required' => true, 'max' => 10, 'wide' => true], '')
        . '</div><div class="d-form-actions"><button class="d-btn d-btn-danger" type="submit">Erase for good</button><a class="d-btn d-btn-soft" href="' . e(url('admin/vendors/' . $t)) . '">Cancel</a></div></form>';
    $page($u, 'vendors', 'Erase vendor for good', $card('', $h));
});
$app->router->post('/admin/vendors/{id}/purge', static function (array $a) use ($app, $adminUser): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $v = $app->db->one('SELECT t.tenant_username, v.status FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$t]);
    if ($v === null || $v['status'] !== 'deleted') {
        $app->error(404, 'Only a deleted vendor can be erased for good.');
    }
    if (trim((string) ($_POST['confirm'] ?? '')) !== (string) $v['tenant_username'] || trim((string) ($_POST['confirm2'] ?? '')) !== 'ERASE') {
        flash('Both confirmations must match. Nothing was erased.', 'bad');
        redirect(url('admin/vendors/' . $t . '/purge'));
    }
    $app->access->log('_platform', (int) $u['id'], null, null, 'vendor_purged', $t, null);
    Vendors::purge($app->db, $t);
    flash('Vendor erased for good.', 'ok');
    redirect(url('admin/vendors'));
});
foreach (['suspend' => ['suspended', 'Vendor suspended.'], 'activate' => ['active', 'Vendor activated.']] as $path => [$to, $msg]) {
    $vendorPost($path, static function (string $t, array $u) use ($app, $to): void {
        if ((string) $app->db->val('SELECT status FROM sc_vendors WHERE tenant_id = ?', [$t]) === 'deleted') {
            throw new \InvalidArgumentException('Restore the vendor first.');
        }
        $app->db->run('UPDATE sc_vendors SET status = ? WHERE tenant_id = ?', [$to, $t]);
        $app->access->log($t, (int) $u['id'], null, null, 'vendor_' . $to, null, $to);
    }, $msg);
}

// domains of a vendor, managed by super admin on the vendor page
$app->router->post('/admin/vendors/{id}/domains/add', static function (array $a) use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $t = (string) $a['id'];
    $err = $app->domains->add($t, (string) ($_POST['host'] ?? ''), (int) $u['id']);
    if ($err === null) {
        $app->access->log($t, (int) $u['id'], null, null, 'domain_added', null, (string) ($_POST['host'] ?? ''));
    }
    $go('Domain added. The vendor must set the DNS records shown.', url('admin/vendors/' . $t), $err);
});
$app->router->post('/admin/vendors/{id}/domains/verify/{did}', static function (array $a) use ($app, $adminUser): void {
    $adminUser();
    $row = $app->db->one('SELECT * FROM sc_domains WHERE id = ? AND tenant_id = ?', [(int) $a['did'], (string) $a['id']]);
    if ($row !== null) {
        [$ok, $msg] = $app->domains->verify($row);
        flash($ok ? 'DNS is right. The certificate is being requested.' : $msg, $ok ? 'ok' : 'bad');
    }
    redirect(url('admin/vendors/' . $a['id']));
});
$app->router->post('/admin/vendors/{id}/domains/remove/{did}', static function (array $a) use ($app, $adminUser): void {
    $u = $adminUser();
    if ($app->domains->remove((string) $a['id'], (int) $a['did'])) {
        $app->access->log((string) $a['id'], (int) $u['id'], null, null, 'domain_removed', (int) $a['did'], null);
        flash('Domain removed.', 'ok');
    }
    redirect(url('admin/vendors/' . $a['id']));
});

// ------------------------------------------------------------------ registration requests
$reqStates = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
$reqBadge = static fn (string $s): string => '<span class="d-badge is-' . ['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'bad', 'cancelled' => 'mute'][$s] . '">' . e(['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'][$s] ?? $s) . '</span>';
$approveRequest = static function (int $id, array $u) use ($app): ?string {
    $r = $app->db->one('SELECT * FROM sc_registration_requests WHERE id = ?', [$id]);
    if ($r === null || $r['status'] !== 'pending') {
        return 'This request is not waiting any more.';
    }
    if (($e = Vendors::slugError($app, (string) $r['slug'], $id)) !== null) {
        return $e . ' Edit the request and choose another address.';
    }
    if ($app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$r['email']]) > 0) {
        return 'That email already has an account.';
    }
    if ($app->db->val('SELECT COUNT(*) FROM tenants WHERE hospital_name = ?', [$r['name']]) > 0) {
        return 'A vendor with that name already exists. Edit the request name first.';
    }
    try {
        Vendors::create($app, ['type' => $r['type_slug'], 'name' => $r['name'], 'slug' => $r['slug'], 'city' => $r['city'], 'phone' => $r['phone'], 'first_name' => $r['first_name'], 'last_name' => (string) $r['last_name'], 'email' => $r['email'], 'password_hash' => $r['password_hash'], 'plan_id' => (int) $r['plan_id']]);
    } catch (\Throwable $e) {
        return 'Could not create the vendor: ' . $e->getMessage();
    }
    if ((string) $r['address'] !== '') {
        $app->site->set((string) $r['slug'], 'address', (string) $r['address']);
    }
    $app->db->run("UPDATE sc_registration_requests SET status = 'approved', tenant_id = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW(), reason = NULL WHERE id = ?", [$r['slug'], (int) $u['id'], $id]);
    $app->access->log((string) $r['slug'], (int) $u['id'], null, null, 'registration_approved', 'pending', 'approved', 'Request #' . $id);
    $app->mailer->send((string) $r['email'], 'Your Self Care registration is approved', "Hello " . $r['first_name'] . ",\n\nGood news: " . $r['name'] . " is approved. Your website and your portal are ready.\n\nWebsite: " . $app->tenant->siteUrl((string) $r['slug']) . "\nSign in: " . $app->tenant->siteUrl((string) $r['slug'], 'login') . "\n\nUse the email and password you chose when you registered.\n\nSelf Care");
    return null;
};

$app->router->get('/admin/requests', static function () use ($app, $adminUser, $page, $card, $reqStates, $reqBadge): void {
    $u = $adminUser();
    if (!isset($_GET['f_status'])) {
        $_GET['f_status'] = 'pending';
    }
    $types = array_column(VendorTypes::all($app->db, false), 'name', 'slug');
    $html = Listing::render($app->db, [
        'id' => 'reqs', 'select' => 'r.*', 'from' => 'sc_registration_requests r', 'search' => ['r.name', 'r.slug', 'r.email', 'r.first_name', 'r.last_name'], 'search_hint' => 'Search by name, address or email',
        'filters' => ['status' => ['label' => 'State', 'sql' => 'r.status = ?', 'options' => $reqStates], 'type' => ['label' => 'Kind', 'sql' => 'r.type_slug = ?', 'options' => $types]],
        'columns' => ['name' => ['label' => 'Organisation', 'sort' => 'r.name', 'render' => fn ($r) => '<a href="' . e(url('admin/requests/' . $r['id'])) . '"><b>' . e($r['name']) . '</b></a><br><small class="d-mute">' . e($r['slug']) . '</small>'],
            'type_slug' => ['label' => 'Kind', 'sort' => 'r.type_slug', 'render' => fn ($r) => e($types[$r['type_slug']] ?? $r['type_slug'])], 'first_name' => ['label' => 'Contact', 'render' => fn ($r) => e(trim($r['first_name'] . ' ' . $r['last_name'])) . '<br><small class="d-mute">' . e($r['email']) . '</small>'],
            'status' => ['label' => 'State', 'sort' => 'r.status', 'render' => fn ($r) => $reqBadge((string) $r['status'])], 'created_at' => ['label' => 'Asked on', 'sort' => 'r.created_at', 'type' => 'date']],
        'sort' => 'created_at', 'dir' => 'desc',
        'actions' => [['label' => 'Details', 'icon' => 'eye', 'url' => fn ($r) => url('admin/requests/' . $r['id'])],
            ['label' => 'Approve', 'icon' => 'check', 'method' => 'post', 'url' => fn ($r) => url('admin/requests/' . $r['id'] . '/approve'), 'if' => fn ($r) => $r['status'] === 'pending', 'confirm' => 'Approve this registration? The website and portal are created and the vendor is emailed.'],
            ['label' => 'Cancel', 'icon' => 'x', 'method' => 'post', 'danger' => true, 'url' => fn ($r) => url('admin/requests/' . $r['id'] . '/cancel'), 'if' => fn ($r) => $r['status'] === 'pending', 'confirm' => 'Cancel this request?'],
            ['label' => 'Delete', 'icon' => 'trash', 'method' => 'post', 'danger' => true, 'url' => fn ($r) => url('admin/requests/' . $r['id'] . '/delete'), 'if' => fn ($r) => in_array($r['status'], ['rejected', 'cancelled'], true), 'confirm' => 'Delete this request for good?']],
        'empty' => ['title' => 'No requests here', 'text' => 'New registrations from the public sign up page wait here until you approve them.'],
    ]);
    $page($u, 'requests', 'Registration requests', $card('', $html), ['sub' => 'New organisations wait here. Nothing goes live and nobody can sign in until you approve.']);
});
$app->router->get('/admin/requests/{id}', static function (array $a) use ($app, $adminUser, $page, $card, $reqBadge, $planOptions, $typeOptions): void {
    $u = $adminUser();
    $r = $app->db->one('SELECT * FROM sc_registration_requests WHERE id = ?', [(int) $a['id']]);
    if ($r === null) {
        $app->error(404, 'We could not find that request.');
    }
    $id = (int) $r['id'];
    $by = $r['decided_by'] ? $app->db->one('SELECT first_name, last_name FROM users WHERE id = ?', [(int) $r['decided_by']]) : null;
    $dl = '<dl class="d-kv"><dt>State</dt><dd>' . $reqBadge((string) $r['status']) . '</dd><dt>Asked on</dt><dd>' . e(dt((string) $r['created_at'])) . '</dd>'
        . ($r['decided_at'] ? '<dt>Decided</dt><dd>' . e(dt((string) $r['decided_at'])) . ($by ? ' by ' . e(trim($by['first_name'] . ' ' . $by['last_name'])) : '') . '</dd>' : '')
        . ($r['reason'] ? '<dt>Reason</dt><dd>' . e((string) $r['reason']) . '</dd>' : '')
        . ($r['tenant_id'] ? '<dt>Vendor</dt><dd><a href="' . e(url('admin/vendors/' . $r['tenant_id'])) . '">' . e((string) $r['tenant_id']) . '</a></dd>' : '') . '<dt>From</dt><dd>' . e((string) $r['ip']) . '</dd></dl>';
    $h = '<div class="d-grid d-g2">' . $card('Request #' . $id, $dl);
    if ($r['status'] === 'pending') {
        $h .= $card('Decide', '<p class="d-help">Approving creates the tenant, the website, the roles, the plan and the vendor admin account, then emails the vendor.</p><p>'
            . '<form class="d-inline" method="post" action="' . e(url('admin/requests/' . $id . '/approve')) . '" data-confirm="Approve this registration?">' . csrf_field() . '<button class="d-btn d-btn-primary" type="submit">Approve</button></form> '
            . '<form class="d-inline" method="post" action="' . e(url('admin/requests/' . $id . '/cancel')) . '" data-confirm="Cancel this request?">' . csrf_field() . '<button class="d-btn d-btn-soft" type="submit">Cancel request</button></form></p>'
            . '<form class="d-form" method="post" action="' . e(url('admin/requests/' . $id . '/reject')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('reason', ['label' => 'Reject with a reason (sent to the vendor)', 'type' => 'textarea', 'required' => true, 'max' => 240, 'rows' => 3], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-danger" type="submit">Reject</button></div></form>');
    } elseif (in_array($r['status'], ['rejected', 'cancelled'], true)) {
        $h .= $card('Clean up', '<form class="d-inline" method="post" action="' . e(url('admin/requests/' . $id . '/delete')) . '" data-confirm="Delete this request for good?">' . csrf_field() . '<button class="d-btn d-btn-danger" type="submit">Delete request</button></form>');
    }
    $h .= '</div>';
    if ($r['status'] === 'pending') {
        $f = ['type_slug' => ['label' => 'Kind of vendor', 'type' => 'select', 'options' => $typeOptions(false), 'required' => true], 'name' => ['label' => 'Organisation name', 'required' => true, 'max' => 120], 'slug' => ['label' => 'Web address', 'required' => true, 'max' => 40], 'city' => ['label' => 'City', 'max' => 120], 'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40], 'address' => ['label' => 'Street address', 'max' => 190],
            'first_name' => ['label' => 'Contact first name', 'required' => true, 'max' => 120], 'last_name' => ['label' => 'Contact last name', 'max' => 120], 'email' => ['label' => 'Contact email', 'type' => 'email', 'required' => true, 'max' => 191], 'plan_id' => ['label' => 'Plan to give', 'type' => 'select', 'options' => $planOptions(), 'blank' => true, 'blank_label' => 'Default free plan'], 'admin_note' => ['label' => 'Private note', 'max' => 240, 'wide' => true]];
        $form = '<form class="d-form" method="post" action="' . e(url('admin/requests/' . $id . '/save')) . '">' . csrf_field() . '<div class="d-form-grid">';
        foreach ($f as $k => $fd) {
            $form .= Crud::field($k, $fd, old($k, (string) ($r[$k] ?? '')));
        }
        $form .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save changes</button></div></form>';
        clear_old();
        $h .= $card('Edit the details before approving', $form);
    } else {
        $h .= $card('Details', '<dl class="d-kv"><dt>Organisation</dt><dd>' . e($r['name']) . '</dd><dt>Web address</dt><dd>' . e($r['slug']) . '</dd><dt>Contact</dt><dd>' . e(trim($r['first_name'] . ' ' . $r['last_name'])) . ' · ' . e($r['email']) . '</dd><dt>City</dt><dd>' . e((string) $r['city']) . '</dd><dt>Phone</dt><dd>' . e((string) $r['phone']) . '</dd></dl>');
    }
    $page($u, 'requests', (string) $r['name'], $h, ['sub' => 'Registration request']);
});
$app->router->post('/admin/requests/{id}/save', static function (array $a) use ($app, $adminUser, $go, $planOptions, $typeOptions): void {
    $u = $adminUser();
    $id = (int) $a['id'];
    $r = $app->db->one('SELECT * FROM sc_registration_requests WHERE id = ?', [$id]);
    if ($r === null || $r['status'] !== 'pending') {
        $go('', url('admin/requests'), 'Only waiting requests can be edited.');
    }
    $f = ['type_slug' => ['label' => 'Kind of vendor', 'type' => 'select', 'options' => $typeOptions(false), 'required' => true], 'name' => ['label' => 'Organisation name', 'required' => true, 'max' => 120], 'slug' => ['label' => 'Web address', 'required' => true, 'max' => 40], 'city' => ['label' => 'City', 'max' => 120], 'phone' => ['label' => 'Phone', 'max' => 40], 'address' => ['label' => 'Street address', 'max' => 190],
        'first_name' => ['label' => 'Contact first name', 'required' => true, 'max' => 120], 'last_name' => ['label' => 'Contact last name', 'max' => 120], 'email' => ['label' => 'Contact email', 'type' => 'email', 'required' => true, 'max' => 191], 'plan_id' => ['label' => 'Plan', 'type' => 'select', 'options' => $planOptions()], 'admin_note' => ['label' => 'Note', 'max' => 240]];
    [$d, $err] = Crud::collect($f);
    if ($err === null) {
        $d['slug'] = slugify((string) $d['slug']);
        $err = Vendors::slugError($app, $d['slug'], $id);
        if ($err === null && $app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [strtolower((string) $d['email'])]) > 0) {
            $err = 'That email already has an account.';
        }
    }
    if ($err !== null) {
        keep_old($_POST);
        $go('', url('admin/requests/' . $id), $err);
    }
    $d['email'] = strtolower((string) $d['email']);
    $d['plan_id'] = $d['plan_id'] === null ? null : (int) $d['plan_id'];
    $d['updated_at'] = date('Y-m-d H:i:s');
    $sets = implode(', ', array_map(fn ($k) => "`$k` = ?", array_keys($d)));
    $app->db->run("UPDATE sc_registration_requests SET $sets WHERE id = ?", [...array_values($d), $id]);
    $app->access->log('_platform', (int) $u['id'], null, null, 'registration_edited', null, 'Request #' . $id);
    clear_old();
    $go('Request updated.', url('admin/requests/' . $id));
});
$app->router->post('/admin/requests/{id}/approve', static function (array $a) use ($app, $adminUser, $approveRequest, $go): void {
    $u = $adminUser();
    $err = $approveRequest((int) $a['id'], $u);
    $go('Approved. The vendor website and portal are ready and the vendor was emailed.', url('admin/requests'), $err);
});
$app->router->post('/admin/requests/{id}/reject', static function (array $a) use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $id = (int) $a['id'];
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $r = $app->db->one("SELECT * FROM sc_registration_requests WHERE id = ? AND status = 'pending'", [$id]);
    if ($r === null) {
        $go('', url('admin/requests'), 'This request is not waiting any more.');
    }
    if ($reason === '') {
        $go('', url('admin/requests/' . $id), 'Please write the reason for the rejection.');
    }
    $app->db->run("UPDATE sc_registration_requests SET status = 'rejected', reason = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW() WHERE id = ?", [mb_substr($reason, 0, 240), (int) $u['id'], $id]);
    $app->access->log('_platform', (int) $u['id'], null, null, 'registration_rejected', 'pending', 'rejected', 'Request #' . $id . ': ' . $reason);
    $app->mailer->send((string) $r['email'], 'About your Self Care registration', "Hello " . $r['first_name'] . ",\n\nThank you for asking to join Self Care. We could not approve the registration for " . $r['name'] . " this time.\n\nReason: " . $reason . "\n\nYou are welcome to register again after fixing this, or reply to this email.\n\nSelf Care");
    $go('Rejected. The vendor was emailed the reason.', url('admin/requests'));
});
$app->router->post('/admin/requests/{id}/cancel', static function (array $a) use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $id = (int) $a['id'];
    $n = $app->db->run("UPDATE sc_registration_requests SET status = 'cancelled', decided_by = ?, decided_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'", [(int) $u['id'], $id]);
    if ($n > 0) {
        $app->access->log('_platform', (int) $u['id'], null, null, 'registration_cancelled', 'pending', 'cancelled', 'Request #' . $id);
    }
    $go('Request cancelled.', url('admin/requests'), $n > 0 ? null : 'This request is not waiting any more.');
});
$app->router->post('/admin/requests/{id}/delete', static function (array $a) use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $id = (int) $a['id'];
    $n = $app->db->run("DELETE FROM sc_registration_requests WHERE id = ? AND status IN ('rejected', 'cancelled')", [$id]);
    if ($n > 0) {
        $app->access->log('_platform', (int) $u['id'], null, null, 'registration_deleted', null, 'Request #' . $id);
    }
    $go('Request deleted.', url('admin/requests'), $n > 0 ? null : 'Only rejected or cancelled requests can be deleted.');
});

// ------------------------------------------------------------------ vendor types
$typeMod = static function () use ($app): array {
    $icons = ['building', 'stethoscope', 'flask', 'pill', 'bed', 'drop', 'siren', 'heart', 'scan', 'brain', 'leaf', 'home2', 'box', 'truck', 'shield', 'users'];
    return ['key' => 'types', 'title' => 'Vendor types', 'singular' => 'Vendor type', 'url' => 'admin/types', 'table' => 'sc_vendor_types', 'scope' => [],
        'fields' => ['name' => ['label' => 'Name', 'required' => true, 'max' => 120], 'plural' => ['label' => 'Plural name', 'required' => true, 'max' => 120], 'slug' => ['label' => 'Short name for the address (cannot change later)', 'required' => true, 'max' => 60, 'help' => 'Letters and dashes, used in /types/short-name.'], 'family' => ['label' => 'Website and portal template', 'type' => 'select', 'options' => array_combine(VendorTypes::familyKeys(), VendorTypes::familyKeys()), 'required' => true], 'icon' => ['label' => 'Icon', 'type' => 'select', 'options' => array_combine($icons, $icons), 'required' => true], 'tagline' => ['label' => 'Short line', 'max' => 190, 'wide' => true], 'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 1500], 'serves' => ['label' => 'Who it is for (one per line)', 'type' => 'textarea', 'max' => 1000], 'sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 0], 'is_enabled' => ['label' => 'Offered to new vendors', 'type' => 'toggle', 'default' => 1]],
        'columns' => ['name' => ['label' => 'Type'], 'family' => ['label' => 'Template'], 'slug' => ['label' => 'Short name'], 'sort_order' => ['label' => 'Order'], 'is_enabled' => ['label' => 'On', 'type' => 'bool']],
        'search' => ['t.name', 't.slug'], 'sort' => 'sort_order', 'empty' => 'No types.',
        'before_save' => function (array $d, ?array $old) use ($app) { if ($old) { $d['slug'] = $old['slug']; } else { $d['slug'] = \Selfcare\slugify((string) $d['slug']); if ($d['slug'] === '' || $app->db->val('SELECT COUNT(*) FROM sc_vendor_types WHERE slug = ?', [$d['slug']])) { return 'That short name is empty or already used.'; } } return $d; },
        'before_delete' => fn (array $r) => $app->db->val('SELECT COUNT(*) FROM sc_vendors WHERE type_slug = ?', [$r['slug']]) ? 'Vendors use this type. Switch it off instead of deleting.' : null];
};
$crudPage = static function (callable $mod, string $active, callable $body) use ($app, $adminUser): callable {
    return static function (array $a = []) use ($app, $adminUser, $mod, $active, $body): void {
        $u = $adminUser();
        $m = $mod($a);
        $body($u, new Crud($app->db, $m), $m, $a, $active);
    };
};
$mount = static function (string $base, callable $mod, string $active, ?callable $after = null) use ($app, $page, $card, $btn, $crudPage, $go): void {
    $app->router->get($base, $crudPage($mod, $active, static function (array $u, Crud $c, array $m) use ($page, $card, $btn, $active, $after): void {
        $page($u, $active, $m['title'], $card('', $c->list()) . ($after ? $after() : ''), ['actions' => $btn('Add ' . strtolower($m['singular']), $c->url('new'))]);
    }));
    $form = $crudPage($mod, $active, static function (array $u, Crud $c, array $m, array $a) use ($page, $card, $active, $app): void {
        $id = isset($a['id']) ? (int) $a['id'] : null;
        if ($id !== null && $c->find($id) === null) {
            $app->error(404, 'We could not find that item.');
        }
        $page($u, $active, ($id ? 'Edit ' : 'Add ') . strtolower($m['singular']), $card('', $c->form($id)));
    });
    $app->router->get($base . '/new', $form);
    $app->router->post($base . '/save', $crudPage($mod, $active, static function (array $u, Crud $c, array $m) use ($go): void {
        $err = $c->save();
        if ($err === null) {
            clear_old();
        }
        $go('Saved.', url($m['url']), $err);
    }));
    $app->router->get($base . '/{id}', $form);
    $app->router->post($base . '/{id}/delete', $crudPage($mod, $active, static function (array $u, Crud $c, array $m, array $a): void {
        if ($c->delete((int) $a['id'])) {
            flash('Deleted.', 'ok');
        }
        redirect(url($m['url']));
    }));
};
// ------------------------------------------------------------------ currencies
$currencyMod = static function () use ($app): array {
    return ['key' => 'currencies', 'title' => 'Currencies', 'singular' => 'Currency', 'url' => 'admin/currencies', 'table' => 'sc_currencies', 'scope' => [],
        'fields' => [
            'code' => ['label' => 'Code (3 letters, cannot change later)', 'required' => true, 'max' => 8], 'name' => ['label' => 'Name', 'required' => true, 'max' => 80], 'symbol' => ['label' => 'Symbol', 'required' => true, 'max' => 12],
            'decimals' => ['label' => 'Decimal places (0 to 4)', 'type' => 'number', 'min' => 0, 'default' => 2], 'symbol_pos' => ['label' => 'Symbol position', 'type' => 'select', 'options' => ['before' => 'Before the number (Rs 1,000)', 'after' => 'After the number (1.000 €)'], 'required' => true],
            'thousand_sep' => ['label' => 'Thousands separator', 'max' => 2, 'default' => ','], 'decimal_sep' => ['label' => 'Decimal separator', 'max' => 2, 'default' => '.'],
            'grouping' => ['label' => 'Digit grouping', 'type' => 'select', 'options' => ['standard' => '1,234,567 (standard)', 'lakh' => '12,34,567 (lakh and crore)'], 'required' => true],
            'major_name' => ['label' => 'Main unit in words (Rupees)', 'required' => true, 'max' => 40], 'minor_name' => ['label' => 'Small unit in words (Paisa)', 'max' => 40],
            'is_enabled' => ['label' => 'Offer this currency to vendors', 'type' => 'toggle', 'default' => 1], 'sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 50]],
        'columns' => ['sort_order' => ['label' => 'Order'], 'code' => ['label' => 'Code'], 'name' => ['label' => 'Name'], 'symbol' => ['label' => 'Symbol'], 'decimals' => ['label' => 'Decimals'], 'grouping' => ['label' => 'Grouping'], 'is_enabled' => ['label' => 'On', 'type' => 'bool']],
        'search' => ['t.code', 't.name'], 'sort' => 'sort_order', 'empty' => 'No currencies.',
        'before_save' => function (array $d, ?array $old) use ($app) {
            $d['decimals'] = max(0, min(4, (int) $d['decimals']));
            if ($old) {
                $d['code'] = $old['code'];
            } else {
                $d['code'] = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $d['code']) ?? '');
                if (strlen($d['code']) !== 3 || $app->db->val('SELECT COUNT(*) FROM sc_currencies WHERE code = ?', [$d['code']])) {
                    return 'Use a new 3 letter code.';
                }
            }
            if ($old && (int) $d['is_enabled'] === 0 && \Selfcare\Currency::platform() === $old['code']) {
                return 'This is the platform currency. Pick another default first.';
            }
            return $d;
        },
        'after_save' => fn () => \Selfcare\Currency::reset(),
        'before_delete' => function (array $r) use ($app) {
            $c = (string) $r['code'];
            if (\Selfcare\Currency::platform() === $c || $app->db->val('SELECT COUNT(*) FROM sc_invoices WHERE currency = ?', [$c]) || $app->db->val('SELECT COUNT(*) FROM sc_plans WHERE currency = ?', [$c]) || $app->db->val("SELECT COUNT(*) FROM settings WHERE `key` = 'currency' AND `value` = ?", [$c])) {
                return 'This currency is in use. Switch it off instead of deleting.';
            }
            return null;
        }];
};
$app->router->post('/admin/currencies/default', static function () use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $c = (string) ($_POST['currency'] ?? '');
    $row = \Selfcare\Currency::all(true)[$c] ?? null;
    if ($row === null) {
        $go('', url('admin/currencies'), 'Choose one of the enabled currencies.');
    }
    $app->site->set(null, 'platform_currency', $c);
    $app->access->log('_platform', (int) $u['id'], null, null, 'platform_currency', null, $c);
    $go('Platform currency is now ' . $c . '. New plans and subscription invoices use it.', url('admin/currencies'));
});
$mount('/admin/types', $typeMod, 'types', static function () use ($app, $card): string {
    $by = [];
    foreach ($app->db->all("SELECT t.id, t.tenant_username, t.hospital_name, v.type_slug, v.status FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE v.status <> 'deleted' ORDER BY t.hospital_name") as $r) {
        $by[$r['type_slug']][] = $r;
    }
    $h = '';
    foreach (VendorTypes::all($app->db, false) as $ty) {
        $list = $by[$ty['slug']] ?? [];
        $links = '';
        foreach ($list as $r) {
            $links .= '<a class="d-chip" href="' . e(url('admin/vendors/' . $r['id'])) . '">' . e($r['hospital_name']) . ($r['status'] !== 'active' ? ' <small>(' . e($r['status']) . ')</small>' : '') . '</a>';
        }
        $h .= '<div class="d-typevendors"><div><b>' . e($ty['name']) . '</b> <small class="d-mute">' . count($list) . ' vendor' . (count($list) === 1 ? '' : 's') . '</small> <a class="d-act" href="' . e(url('admin/vendors', ['kind' => $ty['slug']])) . '">Open list</a></div><div class="d-chips">' . ($links ?: '<span class="d-mute">No vendors of this kind yet.</span>') . '</div></div>';
    }
    return $card('Vendors of each type', $h);
});

// ------------------------------------------------------------------ plans
$planMod = static function () use ($app): array {
    $n = static fn (string $l, string $h = '', string $step = '1'): array => ['label' => $l, 'type' => 'number', 'min' => 0, 'step' => $step, 'null' => true, 'help' => $h];
    return ['key' => 'plans', 'title' => 'Plans', 'singular' => 'Plan', 'url' => 'admin/plans', 'table' => 'sc_plans', 'scope' => [],
        'fields' => [
            'name' => ['label' => 'Plan name', 'required' => true, 'max' => 120], 'description' => ['label' => 'Short description', 'max' => 250, 'wide' => true],
            'currency' => ['label' => 'Currency of the price', 'type' => 'select', 'options' => \Selfcare\Currency::options(), 'required' => true, 'default' => \Selfcare\Currency::platform()], 'other_prices' => ['label' => 'Prices in other currencies', 'type' => 'textarea', 'rows' => 3, 'max' => 300, 'help' => 'One per line, like: PKR 8000. Shown to vendors who use that currency.'],
            'price' => ['label' => 'Price per month', 'type' => 'number', 'min' => 0, 'step' => '0.01', 'default' => 0], 'price_year' => $n('Price per year', 'Empty = no yearly price.', '0.01'), 'price_label' => ['label' => 'Show this instead of a price (for example Custom)', 'max' => 40, 'null' => true],
            'duration_days' => $n('Length in days', 'Empty = never expires.'), 'max_staff' => $n('Staff accounts', 'Empty = no limit.'), 'max_branches' => $n('Branches'), 'max_patients' => $n('Patient / client records'), 'max_appointments' => $n('Requests per month'),
            'storage_gb' => $n('Storage (GB)', '', '0.1'), 'sms_per_month' => $n('SMS per month'), 'email_per_month' => $n('Emails per month'), 'custom_domains' => $n('Custom domains'), 'custom_roles' => $n('Custom roles'), 'audit_days' => $n('Audit log kept (days)'),
            'support_level' => ['label' => 'Support', 'max' => 60, 'null' => true],
            'modules' => ['label' => 'Parts of the portal included (none ticked = all)', 'type' => 'checks', 'options' => Portal::MODULES], 'types' => ['label' => 'Offered to these kinds of vendor (none ticked = all)', 'type' => 'checks', 'options' => array_column(VendorTypes::all($app->db, false), 'name', 'slug')],
            'sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 0], 'is_featured' => ['label' => 'Mark as popular', 'type' => 'toggle'], 'is_active' => ['label' => 'Offered on the website and at registration', 'type' => 'toggle', 'default' => 1],
        ],
        'columns' => ['name' => ['label' => 'Plan'], 'price' => ['label' => 'Per month', 'type' => 'money', 'class' => 'r'], 'duration_days' => ['label' => 'Days', 'render' => fn ($r) => $r['duration_days'] === null ? 'Never ends' : e((string) $r['duration_days'])], 'max_staff' => ['label' => 'Staff', 'render' => fn ($r) => $r['max_staff'] === null ? 'No limit' : e((string) $r['max_staff'])], 'max_patients' => ['label' => 'Patients', 'render' => fn ($r) => $r['max_patients'] === null ? 'No limit' : e(number_format((float) $r['max_patients']))], 'sort_order' => ['label' => 'Order'], 'is_active' => ['label' => 'On', 'type' => 'bool']],
        'search' => ['t.name'], 'sort' => 'sort_order',
        'before_save' => function (array $d, ?array $old) { $d['updated_at'] = date('Y-m-d H:i:s'); return $d; },
        'before_delete' => fn (array $r) => $app->db->val('SELECT COUNT(*) FROM sc_subscriptions WHERE plan_id = ?', [(int) $r['id']]) ? 'Vendors have used this plan. Switch it off instead of deleting.' : null];
};
$mount('/admin/plans', $planMod, 'plans');

// ------------------------------------------------------------------ website content (main site)
$cfgSite = require dirname(__DIR__) . '/config/site.php';
$app->router->get('/admin/content', static function () use ($app, $adminUser, $page, $card, $cfgSite): void {
    $u = $adminUser();
    $h = '<form class="d-form" method="post" action="' . e(url('admin/content')) . '" enctype="multipart/form-data">' . csrf_field() . '<div class="d-form-grid">';
    foreach ($cfgSite['settings'] as $k => $def) {
        $cur = $app->site->get(null, $k);
        $label = ucfirst(str_replace('_', ' ', $k));
        if ($k === 'logo') {
            $h .= Crud::field($k, ['label' => 'Logo (leave empty for the Self Care mark)', 'type' => 'image'], old($k, $cur));
        } elseif ($k === 'default_plan_id') {
            $h .= Crud::field($k, ['label' => 'Plan given at registration', 'type' => 'select', 'options' => array_column($app->plans->all(true), 'name', 'id'), 'blank' => true, 'blank_label' => 'The free plan'], old($k, $cur));
        } else {
            $long = mb_strlen((string) $def) > 80 || str_contains($k, 'text') || str_contains($k, 'description');
            $h .= Crud::field($k, ['label' => $label, 'type' => $long ? 'textarea' : 'text', 'rows' => 2, 'max' => 400, 'wide' => $long], old($k, $cur));
        }
    }
    foreach (['terms_text' => 'Terms of use page (empty = built-in text)', 'privacy_text' => 'Privacy policy page (empty = built-in text)'] as $k => $l) {
        $h .= Crud::field($k, ['label' => $l, 'type' => 'textarea', 'rows' => 6, 'max' => 20000], old($k, $app->site->get(null, $k)));
    }
    $h .= Crud::field('footer_json', ['label' => 'Footer links (advanced, JSON). Empty = default columns from core/config/site.php', 'type' => 'textarea', 'rows' => 8, 'max' => 6000, 'help' => 'Example: {"Product":[["Features","/#features"],["Pricing","/#pricing"]]}'], old('footer_json', $app->site->get(null, 'footer_json')));
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save</button><a class="d-btn d-btn-soft" href="' . e($app->tenant->mainUrl('')) . '" target="_blank" rel="noopener">View the site</a></div></form>';
    clear_old();
    $page($u, 'content', 'Site content and footer', $card('', $h), ['sub' => 'Hero, headings, contact line, social links, logo, SEO, legal text and footer. Features, steps, questions and testimonials are in the Website menu.']);
});
$app->router->post('/admin/content', static function () use ($app, $adminUser, $cfgSite, $go): void {
    $adminUser();
    $fields = [];
    foreach ($cfgSite['settings'] as $k => $_) {
        $fields[$k] = $k === 'logo' ? ['type' => 'image'] : ['max' => 400];
    }
    $fields['default_plan_id'] = ['type' => 'select', 'options' => array_column($app->plans->all(), 'name', 'id')];
    $fields['terms_text'] = ['max' => 20000];
    $fields['privacy_text'] = ['max' => 20000];
    $fields['footer_json'] = ['max' => 6000];
    [$d, $err] = Crud::collect($fields, 'uploads/site');
    if ($err === null && ($d['footer_json'] ?? '') !== '') {
        $j = json_decode((string) $d['footer_json'], true);
        if (!is_array($j)) {
            $err = 'The footer links are not valid JSON.';
        }
    }
    if ($err !== null) {
        keep_old($_POST);
        $go('', url('admin/content'), $err);
    }
    foreach ($d as $k => $v) {
        if ($k === 'logo' && $v === null) {
            continue;
        }
        $app->site->set(null, $k, (string) $v);
    }
    $go('Saved. The main website is updated.', url('admin/content'));
});
$itemMod = static function (array $a) use ($app, $cfgSite): array {
    $key = (string) ($a['key'] ?? '');
    $labels = ['features' => ['Features', 'Feature', 'Icon name (globe, users, card, clock, heart, sun, shield, layers)'], 'steps' => ['How it works', 'Step', ''], 'faq' => ['Questions', 'Question', ''], 'testimonials' => ['Testimonials', 'Testimonial', 'Role or place']];
    if (!isset($labels[$key])) {
        $app->error(404, 'We could not find that list.');
    }
    if (!$app->db->val('SELECT COUNT(*) FROM sc_items WHERE tenant_id IS NULL AND section = ?', [$key])) { // copy the built-in text in once, so it can be edited
        foreach ($cfgSite[$key] as $i => $r) {
            $app->db->insert('sc_items', ['tenant_id' => null, 'section' => $key, 'title' => $r['title'], 'body' => $r['body'], 'meta' => $r['meta'] ?? null, 'sort_order' => $i, 'is_active' => 1]);
        }
    }
    [$title, $sing, $meta] = $labels[$key];
    $f = ['title' => ['label' => $key === 'faq' ? 'Question' : ($key === 'testimonials' ? 'Name' : 'Title'), 'required' => true, 'max' => 190, 'wide' => true], 'body' => ['label' => $key === 'faq' ? 'Answer' : 'Text', 'type' => 'textarea', 'max' => 1500]];
    if ($meta !== '') {
        $f['meta'] = ['label' => $meta, 'max' => 190];
    }
    $f += ['sort_order' => ['label' => 'Order', 'type' => 'number', 'default' => 0], 'is_active' => ['label' => 'Shown', 'type' => 'toggle', 'default' => 1]];
    return ['key' => $key, 'title' => $title, 'singular' => $sing, 'url' => 'admin/c/' . $key, 'upload_dir' => 'site', 'table' => 'sc_items', 'scope' => ['tenant_id' => null, 'section' => $key], 'fields' => $f, 'columns' => ['title' => ['label' => 'Title'], 'sort_order' => ['label' => 'Order'], 'is_active' => ['label' => 'Shown', 'type' => 'bool']], 'search' => ['t.title'], 'sort' => 'sort_order'];
};
$app->router->get('/admin/c/{key}', $crudPage($itemMod, 'c', static function (array $u, Crud $c, array $m) use ($page, $card, $btn): void {
    $page($u, 'c-' . $m['key'], $m['title'], $card('', $c->list()), ['actions' => $btn('Add ' . strtolower($m['singular']), $c->url('new'))]);
}));
$itemForm = $crudPage($itemMod, 'c', static function (array $u, Crud $c, array $m, array $a) use ($page, $card): void {
    $id = isset($a['id']) ? (int) $a['id'] : null;
    $page($u, 'c-' . $m['key'], ($id ? 'Edit ' : 'Add ') . strtolower($m['singular']), $card('', $c->form($id)));
});
$app->router->get('/admin/c/{key}/new', $itemForm);
$app->router->post('/admin/c/{key}/save', $crudPage($itemMod, 'c', static function (array $u, Crud $c, array $m) use ($go): void {
    $err = $c->save();
    if ($err === null) {
        clear_old();
    }
    $go('Saved.', url($m['url']), $err);
}));
$app->router->get('/admin/c/{key}/{id}', $itemForm);
$app->router->post('/admin/c/{key}/{id}/delete', $crudPage($itemMod, 'c', static function (array $u, Crud $c, array $m, array $a): void {
    $c->delete((int) $a['id']);
    redirect(url($m['url']));
}));

// ------------------------------------------------------------------ messages and free services
$app->router->get('/admin/messages', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $html = Listing::render($app->db, ['id' => 'amsg', 'select' => 'm.*', 'from' => 'sc_messages m', 'where' => ['m.tenant_id IS NULL'], 'search' => ['m.name', 'm.email', 'm.message'], 'search_hint' => 'Search messages',
        'columns' => ['name' => ['label' => 'From', 'sort' => 'm.name', 'render' => fn ($r) => '<b>' . e($r['name']) . '</b><br><small class="d-mute">' . e((string) ($r['email'] ?: $r['phone'])) . '</small>'], 'message' => ['label' => 'Message'], 'is_read' => ['label' => 'State', 'sort' => 'm.is_read', 'render' => fn ($r) => $r['is_read'] ? '<span class="d-badge is-mute">Read</span>' : '<span class="d-badge is-warn">New</span>'], 'created_at' => ['label' => 'Received', 'sort' => 'm.id', 'type' => 'datetime']], 'sort' => 'created_at', 'dir' => 'desc',
        'actions' => [['label' => 'Mark read', 'icon' => 'check', 'method' => 'post', 'url' => fn ($r) => url('admin/messages/' . $r['id'] . '/read'), 'if' => fn ($r) => !$r['is_read']], ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this message?', 'url' => fn ($r) => url('admin/messages/' . $r['id'] . '/delete')]],
        'empty' => ['title' => 'No messages', 'text' => 'Contact form and newsletter sign-ups from the main site show up here.']]);
    $page($u, 'messages', 'Messages', $card('', $html));
});
$app->router->post('/admin/messages/{id}/{do}', static function (array $a) use ($app, $adminUser): void {
    $adminUser();
    $app->db->run($a['do'] === 'delete' ? 'DELETE FROM sc_messages WHERE id = ? AND tenant_id IS NULL' : 'UPDATE sc_messages SET is_read = 1 WHERE id = ? AND tenant_id IS NULL', [(int) $a['id']]);
    redirect(url('admin/messages'));
});
$app->router->get('/admin/free-services', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $html = Listing::render($app->db, ['id' => 'afree', 'select' => 'f.*, t.hospital_name, t.tenant_username, v.type_slug', 'from' => 'free_services f JOIN tenants t ON t.id = f.tenant_id JOIN sc_vendors v ON v.tenant_id = t.id', 'search' => ['f.title', 't.hospital_name'], 'search_hint' => 'Search services or vendors',
        'filters' => ['active' => ['label' => 'Shown', 'sql' => 'f.is_active = ?', 'options' => ['1' => 'Yes', '0' => 'No']]],
        'columns' => ['title' => ['label' => 'Service', 'sort' => 'f.title'], 'hospital_name' => ['label' => 'Vendor', 'sort' => 't.hospital_name'], 'schedule' => ['label' => 'When'], 'is_active' => ['label' => 'Shown', 'type' => 'bool', 'sort' => 'f.is_active']], 'sort' => 'title',
        'actions' => [['label' => 'Hide / show', 'icon' => 'eye', 'method' => 'post', 'url' => fn ($r) => url('admin/free-services/' . $r['id'] . '/toggle')]],
        'empty' => ['title' => 'No free services yet', 'text' => 'Vendors add them from their portal.']]);
    $page($u, 'free', 'Free services across vendors', $card('', $html), ['sub' => 'These show on the main Free services page. You can hide one that does not follow the rules.']);
});
$app->router->post('/admin/free-services/{id}/toggle', static function (array $a) use ($app, $adminUser): void {
    $adminUser();
    $app->db->run('UPDATE free_services SET is_active = 1 - is_active WHERE id = ?', [(int) $a['id']]);
    redirect(url('admin/free-services'));
});

// ------------------------------------------------------------------ account
$app->router->get('/admin/account', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $h = '<form class="d-form" method="post" action="' . e(url('admin/account')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('first_name', ['label' => 'First name', 'required' => true], old('first_name', (string) $u['first_name'])) . Crud::field('last_name', ['label' => 'Last name'], old('last_name', (string) $u['last_name']))
        . Crud::field('current', ['label' => 'Current password', 'type' => 'password', 'autocomplete' => 'current-password'], '') . Crud::field('password', ['label' => 'New password (empty = keep)', 'type' => 'password', 'autocomplete' => 'new-password'], '')
        . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save</button></div></form>';
    clear_old();
    $page($u, 'account', 'My account', $card('', $h));
});
$app->router->post('/admin/account', static function () use ($app, $adminUser, $go): void {
    $u = $adminUser();
    $pw = (string) ($_POST['password'] ?? '');
    $err = null;
    if (trim((string) ($_POST['first_name'] ?? '')) === '') {
        $err = 'Please enter your first name.';
    } elseif ($pw !== '') {
        $row = $app->db->one('SELECT password FROM users WHERE id = ?', [(int) $u['id']]);
        $err = strlen($pw) < 8 ? 'Use at least 8 characters for the new password.' : (!password_verify((string) ($_POST['current'] ?? ''), (string) $row['password']) ? 'The current password is not correct.' : null);
    }
    if ($err === null) {
        $set = ['first_name' => mb_substr(trim((string) $_POST['first_name']), 0, 160), 'last_name' => mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 191), 'updated_at' => date('Y-m-d H:i:s')];
        if ($pw !== '') {
            $set['password'] = password_hash($pw, PASSWORD_BCRYPT);
        }
        $app->db->update('users', $set, 'id = ?', [(int) $u['id']]);
    }
    $go('Saved.', url('admin/account'), $err);
});

// ------------------------------------------------------------------ subscription invoices (platform billing)
$app->router->get('/admin/billing', static function () use ($app, $adminUser, $page, $card, $btn, $billing): void {
    $u = $adminUser();
    $html = Listing::render($app->db, ['id' => 'pinv', 'select' => 'i.*, t.hospital_name', 'from' => 'sc_invoices i JOIN tenants t ON t.id = i.tenant_id', 'where' => ["i.issuer = 'platform'"], 'search' => ['i.invoice_no', 't.hospital_name', 'i.party_name'], 'search_hint' => 'Search by number or vendor',
        'filters' => ['status' => ['label' => 'State', 'sql' => 'i.status = ?', 'options' => ['issued' => 'Unpaid', 'paid' => 'Paid', 'void' => 'Void']]],
        'columns' => ['invoice_no' => ['label' => 'Number', 'sort' => 'i.invoice_no', 'render' => fn ($r) => '<a href="' . e(url('admin/billing/' . $r['id'])) . '"><b>' . e($r['invoice_no']) . '</b></a>'], 'doc_type' => ['label' => 'Type', 'render' => fn ($r) => e((string) $billing->config()['invoices'][$r['doc_type']]['label'])], 'hospital_name' => ['label' => 'Vendor', 'sort' => 't.hospital_name'], 'invoice_date' => ['label' => 'Date', 'sort' => 'i.invoice_date', 'type' => 'date'], 'total' => ['label' => 'Total', 'sort' => 'i.total', 'type' => 'money', 'class' => 'r'], 'balance' => ['label' => 'Due', 'type' => 'money', 'class' => 'r', 'sort' => 'i.balance'], 'status' => ['label' => 'State', 'type' => 'badge', 'sort' => 'i.status']],
        'badges' => ['status' => ['paid' => 'ok', 'issued' => 'warn', 'void' => 'mute']], 'sort' => 'invoice_date', 'dir' => 'desc',
        'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => fn ($r) => url('admin/billing/' . $r['id'])], ['label' => 'PDF', 'icon' => 'file', 'url' => fn ($r) => url('admin/billing/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'No subscription invoices yet', 'text' => 'Make one from a vendor page or with the button above.']]);
    $page($u, 'billing', 'Subscription invoices', $card('', $html), ['actions' => $btn('New invoice or receipt', url('admin/billing/new'))]);
});
$app->router->get('/admin/billing/new', static function () use ($app, $adminUser, $page, $card, $billing): void {
    $u = $adminUser();
    $vend = array_column($app->db->all('SELECT id, hospital_name FROM tenants ORDER BY hospital_name'), 'hospital_name', 'id');
    $pl = $app->plans->all();
    $sel = (string) ($_GET['tenant'] ?? '');
    $h = '<form class="d-form" method="post" action="' . e(url('admin/billing/save')) . '">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('tenant_id', ['label' => 'Vendor', 'type' => 'select', 'options' => $vend, 'required' => true], old('tenant_id', $sel))
        . Crud::field('doc_type', ['label' => 'Document', 'type' => 'select', 'options' => ['subscription_invoice' => 'Subscription invoice', 'subscription_receipt' => 'Subscription payment receipt', 'platform_credit_note' => 'Credit note'], 'required' => true], old('doc_type', 'subscription_invoice'))
        . Crud::field('plan_id', ['label' => 'Plan', 'type' => 'select', 'options' => array_column($pl, 'name', 'id'), 'required' => true], old('plan_id'))
        . Crud::field('period', ['label' => 'Period', 'max' => 60, 'placeholder' => '1 month, or 1 Jan to 31 Dec 2026', 'required' => true], old('period', '1 month'))
        . Crud::field('amount', ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true, 'help' => 'Leave empty to use the monthly price of the plan.', 'null' => true], old('amount'))
        . Crud::field('tax_pct', ['label' => 'Tax %', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], old('tax_pct', '0')) . Crud::field('discount', ['label' => 'Discount amount', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], old('discount', '0'))
        . Crud::field('paid_now', ['label' => 'Paid now (invoices only)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], old('paid_now', '0')) . Crud::field('payment_method', ['label' => 'Payment method', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true], old('payment_method', 'Bank transfer'))
        . Crud::field('reference', ['label' => 'Reference', 'max' => 120], old('reference')) . Crud::field('note', ['label' => 'Note (printed)', 'max' => 200], old('note')) . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save and open</button><a class="d-btn d-btn-soft" href="' . e(url('admin/billing')) . '">Cancel</a></div></form>';
    clear_old();
    $page($u, 'billing', 'New subscription document', $card('', $h));
});
$app->router->post('/admin/billing/save', static function () use ($app, $adminUser, $billing, $go): void {
    $adminUser();
    $t = (string) ($_POST['tenant_id'] ?? '');
    $type = (string) ($_POST['doc_type'] ?? '');
    $ten = $app->db->one('SELECT id, hospital_name FROM tenants WHERE id = ?', [$t]);
    $plan = $app->plans->find((int) ($_POST['plan_id'] ?? 0));
    if ($ten === null || $plan === null || !in_array($type, ['subscription_invoice', 'subscription_receipt', 'platform_credit_note'], true)) {
        keep_old($_POST);
        $go('', url('admin/billing/new'), 'Choose a vendor, a document and a plan.');
    }
    [$planAmount, $planCur] = \Selfcare\Plans::price($plan);
    $amount = trim((string) ($_POST['amount'] ?? '')) === '' ? $planAmount : (float) $_POST['amount'];
    $period = mb_substr(trim((string) ($_POST['period'] ?? '')), 0, 60);
    $in = ['party_name' => $ten['hospital_name'], 'invoice_date' => date('Y-m-d'), 'x_plan_name' => $plan['name'], 'x_period' => $period, 'discount' => $_POST['discount'] ?? 0, 'paid_now' => $_POST['paid_now'] ?? 0, 'payment_method' => $_POST['payment_method'] ?? 'Bank transfer', 'payment_ref' => $_POST['reference'] ?? '', 'note' => $_POST['note'] ?? ''];
    try {
        $id = $billing->create($t, 'platform', $type, $in, [['item_name' => 'Self Care ' . $plan['name'] . ' plan, ' . $period, 'category' => (string) $plan['name'], 'qty' => 1, 'price' => $amount, 'tax_pct' => $_POST['tax_pct'] ?? 0]], (int) $app->auth->user()['id'], $planCur);
    } catch (\InvalidArgumentException $e) {
        keep_old($_POST);
        $go('', url('admin/billing/new'), $e->getMessage());
    }
    clear_old();
    $go('Saved.', url('admin/billing/' . $id));
});
$loadPlat = static function (array $a) use ($app): array {
    $inv = $app->db->one("SELECT * FROM sc_invoices WHERE id = ? AND issuer = 'platform'", [(int) $a['id']]);
    if ($inv === null) {
        $app->error(404, 'We could not find that document.');
    }
    return $inv;
};
$app->router->get('/admin/billing/{id}', static function (array $a) use ($app, $adminUser, $page, $billing, $loadPlat, $card): void {
    $u = $adminUser();
    $inv = $loadPlat($a);
    $bar = '<div class="dx-bar"><a class="d-btn d-btn-primary" href="' . e(url('admin/billing/' . $inv['id'] . '/pdf')) . '">' . icon('file', 16) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button><a class="d-btn d-btn-soft" href="' . e(url('admin/billing')) . '">Back</a></div>';
    $extra = '';
    $cfg = $billing->config()['invoices'][$inv['doc_type']];
    if ($inv['status'] !== 'void' && $cfg['kind'] === 'invoice' && (float) $inv['balance'] > 0) {
        $extra .= $card('Record a payment (due ' . Billing::fmt((float) $inv['balance'], (string) $inv['currency']) . ')', '<form class="d-form" method="post" action="' . e(url('admin/billing/' . $inv['id'] . '/pay')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('amount', ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true], (string) $inv['balance']) . Crud::field('method', ['label' => 'Method', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true], 'Bank transfer') . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save payment</button></div></form>');
    }
    $page($u, 'billing', (string) $inv['invoice_no'], $bar . Documents::html($billing->invoiceModel($app, $inv, $u)) . $extra);
});
$app->router->get('/admin/billing/{id}/pdf', static function (array $a) use ($app, $adminUser, $billing, $loadPlat): void {
    $u = $adminUser();
    $inv = $loadPlat($a);
    Documents::send($billing->invoiceModel($app, $inv, $u), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $inv['invoice_no']) . '.pdf');
});
$app->router->post('/admin/billing/{id}/pay', static function (array $a) use ($app, $adminUser, $billing, $loadPlat, $go): void {
    $adminUser();
    $inv = $loadPlat($a);
    $err = $billing->addPayment((string) $inv['tenant_id'], (int) $inv['id'], (float) ($_POST['amount'] ?? 0), (string) ($_POST['method'] ?? 'Bank transfer'), null, (int) $app->auth->user()['id']);
    $go('Payment saved.', url('admin/billing/' . $inv['id']), $err);
});

// ------------------------------------------------------------------ platform reports
$app->router->get('/admin/reports', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $h = '<div class="d-doclist">';
    foreach (Reports::platform() as $k) {
        $h .= '<a href="' . e(url('admin/reports/' . $k)) . '"><span class="ic">' . icon('chart', 22) . '</span><span><b>' . e(Reports::META[$k][0]) . '</b><small>' . e(Reports::META[$k][1]) . '</small></span></a>';
    }
    $page($u, 'reports', 'Reports', $card('Platform reports', $h . '</div>'));
});
$app->router->get('/admin/reports/{key}', static function (array $a) use ($app, $adminUser, $page, $card, $billing): void {
    $u = $adminUser();
    $key = (string) $a['key'];
    if (!in_array($key, Reports::platform(), true)) {
        $app->error(404, 'We could not find that report.');
    }
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-01-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-12-31');
    if (in_array($key, ['p_renewals'], true) && !isset($_GET['to'])) {
        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+120 days'));
    }
    $f = ['from' => $from, 'to' => $to, 'q' => ''];
    $rep = new Reports($app->db, $billing);
    $res = $rep->run($key, null, $f, 'USD');
    if (($_GET['format'] ?? '') === 'pdf') {
        Documents::send($rep->model($app, null, $key, Reports::META[$key][0], $res, $f, $u), $key . '-' . $from . '_' . $to . '.pdf');
    }
    $form = '<form class="d-tools" method="get" action="' . e(url('admin/reports/' . $key)) . '"><label class="d-filter"><span>From</span><input class="d-in" type="date" name="from" value="' . e($from) . '"></label><label class="d-filter"><span>To</span><input class="d-in" type="date" name="to" value="' . e($to) . '"></label><button class="d-btn d-btn-soft" type="submit">Show</button><span class="d-count"><a class="d-btn d-btn-primary" href="' . e(url('admin/reports/' . $key, ['from' => $from, 'to' => $to, 'format' => 'pdf'])) . '">' . icon('file', 14) . ' Download PDF</a> <button class="d-btn d-btn-soft" type="button" data-print>Print</button></span></form>';
    $sum = '<div class="d-stats">';
    foreach ($res['summary'] as $s) {
        $sum .= '<div class="d-stat"><span>' . e($s[0]) . '</span><b style="font-size:1.4rem">' . e($s[1]) . '</b></div>';
    }
    $t = '<div class="d-table-wrap"><table class="d-table"><thead><tr>';
    foreach ($res['cols'] as $c) {
        $t .= '<th' . (($c[2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . e($c[0]) . '</th>';
    }
    $t .= '</tr></thead><tbody>';
    foreach ($res['rows'] as $r) {
        $t .= '<tr>';
        foreach ($r as $i => $cell) {
            $t .= '<td data-label="' . e($res['cols'][$i][0]) . '"' . (($res['cols'][$i][2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . e((string) $cell) . '</td>';
        }
        $t .= '</tr>';
    }
    $page($u, 'reports', Reports::META[$key][0], $card('', $form) . $sum . '</div>' . $card('', $t . '</tbody></table></div>'), ['sub' => Reports::META[$key][1]]);
});

$mount('/admin/currencies', $currencyMod, 'currencies', static function () use ($app, $card): string {
    $o = '';
    foreach (\Selfcare\Currency::all(true) as $c => $r) {
        $o .= '<option value="' . e($c) . '"' . ($c === \Selfcare\Currency::platform() ? ' selected' : '') . '>' . e($r['name'] . ' (' . trim((string) $r['symbol']) . ')') . '</option>';
    }
    return $card('Platform currency', '<p class="d-help">Used for the plans on the website and for subscription invoices. Each vendor picks its own currency for its own bills under Bills and legal details. Example: ' . e(\Selfcare\Currency::fmt(1250000.5)) . ' and in words: ' . e(\Selfcare\Currency::words(1250.5)) . '.</p><form class="d-form" method="post" action="' . e(url('admin/currencies/default')) . '">' . csrf_field() . '<div class="d-form-grid"><div class="d-field"><label for="pc">Default currency</label><select id="pc" name="currency">' . $o . '</select></div></div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save default</button></div></form>');
});
