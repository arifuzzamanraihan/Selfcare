<?php
// Selfcare original routes: super admin (main host only). Vendors and subscriptions, types, plans, website content, platform billing and reports.
declare(strict_types=1);

use Selfcare\{Billing, Crud, Documents, Listing, Reports, Portal, VendorTypes};
use function Selfcare\{app, csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

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
$adminNav = static function (): array {
    $i = static fn (string $k, string $l, string $ic, string $p): array => ['key' => $k, 'label' => $l, 'icon' => $ic, 'url' => url($p)];
    return [
        ['group' => 'Platform', 'items' => [$i('overview', 'Overview', 'home', 'admin'), $i('vendors', 'Vendors', 'building', 'admin/vendors'), $i('types', 'Vendor types', 'layers', 'admin/types'), $i('plans', 'Plans', 'card', 'admin/plans')]],
        ['group' => 'Money', 'items' => [$i('billing', 'Subscription invoices', 'file', 'admin/billing'), $i('reports', 'Reports', 'chart', 'admin/reports')]],
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
    $mrr = 0.0;
    foreach ($db->all("SELECT p.price, p.duration_days FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW())") as $r) {
        $mrr += (int) ($r['duration_days'] ?? 0) >= 360 ? (float) $r['price'] / 12 : (float) $r['price'];
    }
    $h = '<div class="d-stats">'
        . $stat('Vendors', (string) $db->val('SELECT COUNT(*) FROM sc_vendors'), 'building', (int) $db->val("SELECT COUNT(*) FROM sc_vendors WHERE status = 'suspended'") . ' suspended')
        . $stat('Active subscriptions', (string) $db->val("SELECT COUNT(*) FROM sc_subscriptions WHERE status = 'active' AND (ends_at IS NULL OR ends_at > NOW())"), 'card')
        . $stat('Monthly recurring revenue', money($mrr), 'chart')
        . $stat('Ending in 30 days', (string) $db->val("SELECT COUNT(*) FROM sc_subscriptions WHERE status = 'active' AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)"), 'clock')
        . $stat('Unread messages', (string) $db->val('SELECT COUNT(*) FROM sc_messages WHERE tenant_id IS NULL AND is_read = 0'), 'mail') . '</div>';
    $by = $db->all('SELECT ty.name, COUNT(v.tenant_id) n FROM sc_vendor_types ty LEFT JOIN sc_vendors v ON v.type_slug = ty.slug GROUP BY ty.id, ty.name ORDER BY ty.sort_order, ty.name');
    $t = '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Kind of vendor</th><th class="r">Registered</th></tr></thead><tbody>';
    foreach ($by as $r) {
        $t .= '<tr><td data-label="Kind">' . e($r['name']) . '</td><td class="r" data-label="Registered">' . (int) $r['n'] . '</td></tr>';
    }
    $page($u, 'overview', 'Overview', $h . $card('Vendors by kind', $t . '</tbody></table></div>'), ['sub' => 'The whole platform at a glance.']);
});

// ------------------------------------------------------------------ vendors
$app->router->get('/admin/vendors', static function () use ($app, $adminUser, $page, $card): void {
    $u = $adminUser();
    $types = array_column(VendorTypes::all($app->db, false), 'name', 'slug');
    $html = Listing::render($app->db, [
        'id' => 'vendors', 'select' => "t.id, t.tenant_username, t.hospital_name, v.type_slug, v.status, v.city, t.created_at, (SELECT CONCAT(p.name, '|', COALESCE(s.ends_at, '')) FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.tenant_id = t.id AND s.status = 'active' ORDER BY s.id DESC LIMIT 1) AS sub",
        'from' => 'tenants t JOIN sc_vendors v ON v.tenant_id = t.id', 'search' => ['t.hospital_name', 't.tenant_username', 'v.city'], 'search_hint' => 'Search by name, address or city',
        'filters' => ['type' => ['label' => 'Kind', 'sql' => 'v.type_slug = ?', 'options' => $types], 'status' => ['label' => 'State', 'sql' => 'v.status = ?', 'options' => ['active' => 'Active', 'suspended' => 'Suspended']]],
        'columns' => ['hospital_name' => ['label' => 'Vendor', 'sort' => 't.hospital_name', 'render' => fn ($r) => '<a href="' . e(url('admin/vendors/' . $r['id'])) . '"><b>' . e($r['hospital_name']) . '</b></a><br><small class="d-mute">' . e($r['tenant_username']) . '</small>'], 'type_slug' => ['label' => 'Kind', 'sort' => 'v.type_slug', 'render' => fn ($r) => e($types[$r['type_slug']] ?? $r['type_slug'])], 'city' => ['label' => 'City', 'sort' => 'v.city'],
            'sub' => ['label' => 'Plan', 'render' => function ($r) { [$n, $end] = array_pad(explode('|', (string) $r['sub']), 2, ''); return $n === '' ? '<span class="d-badge is-bad">No plan</span>' : e($n) . ' <small class="d-mute">' . ($end === '' ? 'never ends' : ($end < date('Y-m-d H:i:s') ? 'ended ' : 'ends ') . e(dt($end))) . '</small>'; }],
            'status' => ['label' => 'State', 'sort' => 'v.status', 'render' => fn ($r) => '<span class="d-badge is-' . ($r['status'] === 'active' ? 'ok' : 'bad') . '">' . e(ucfirst((string) $r['status'])) . '</span>'], 'created_at' => ['label' => 'Joined', 'sort' => 't.created_at', 'type' => 'date']],
        'sort' => 'created_at', 'dir' => 'desc', 'actions' => [['label' => 'Manage', 'icon' => 'settings', 'url' => fn ($r) => url('admin/vendors/' . $r['id'])], ['label' => 'Website', 'icon' => 'globe', 'url' => fn ($r) => app()->tenant->siteUrl((string) $r['tenant_username'])]],
        'empty' => ['title' => 'No vendors yet', 'text' => 'Vendors appear here when they register.'],
    ]);
    $page($u, 'vendors', 'Vendors', $card('', $html), ['sub' => 'Every registered organisation. Open one to change its plan.']);
});
$app->router->get('/admin/vendors/{id}', static function (array $a) use ($app, $adminUser, $page, $card, $stat, $btn): void {
    $u = $adminUser();
    $v = $app->db->one('SELECT t.id, t.tenant_username, t.hospital_name, t.created_at, v.* FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [(string) $a['id']]);
    if ($v === null) {
        $app->error(404, 'We could not find that vendor.');
    }
    $t = (string) $v['id'];
    $sub = $app->plans->current($t);
    $plans = array_filter($app->plans->all(), fn ($p) => $app->plans->fitsType($p, (string) $v['type_slug']));
    $opts = '';
    foreach ($plans as $p) {
        $opts .= '<option value="' . (int) $p['id'] . '">' . e($p['name']) . ($p['price_label'] ? ' (' . e($p['price_label']) . ')' : ((float) $p['price'] == 0 ? ' (free)' : ' (' . e(money((float) $p['price'])) . '/mo)')) . ($p['is_active'] ? '' : ' - hidden') . '</option>';
    }
    $act = static fn (string $path, string $label, string $body = '', string $cls = 'd-btn-soft', string $confirm = ''): string => '<form class="d-inline" method="post" action="' . e(url('admin/vendors/' . $_GET['_id'] . '/' . $path)) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $body . '<button class="d-btn ' . $cls . '" type="submit">' . e($label) . '</button></form> ';
    $_GET['_id'] = $t;
    $counts = ['Staff' => (int) $app->db->val('SELECT COUNT(*) FROM users WHERE tenant_id = ?', [$t]), 'Doctors' => (int) $app->db->val('SELECT COUNT(*) FROM doctors WHERE tenant_id = ?', [$t]), 'Patients' => (int) $app->db->val('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$t]), 'Invoices' => (int) $app->db->val("SELECT COUNT(*) FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor'", [$t])];
    $h = '<div class="d-stats">';
    foreach ($counts as $l => $n) {
        $h .= $stat($l, (string) $n, 'users');
    }
    $h .= '</div><div class="d-grid d-g2">';
    $h .= $card('Vendor', '<dl class="d-kv"><dt>Name</dt><dd><b>' . e($v['hospital_name']) . '</b></dd><dt>Kind</dt><dd>' . e($app->typeRow((string) $v['type_slug'])['name']) . '</dd><dt>Address</dt><dd>' . e($app->tenant->hostFor((string) $v['tenant_username']) ?? url('h/' . $v['tenant_username'])) . '</dd><dt>City</dt><dd>' . e((string) $v['city']) . '</dd><dt>State</dt><dd><span class="d-badge is-' . ($v['status'] === 'active' ? 'ok' : 'bad') . '">' . e(ucfirst((string) $v['status'])) . '</span></dd><dt>Listed</dt><dd>' . ((int) $v['is_listed'] ? 'Yes' : 'No') . '</dd><dt>Joined</dt><dd>' . e(dt((string) $v['created_at'])) . '</dd></dl><p style="margin-top:14px">'
        . ($v['status'] === 'active' ? $act('state', 'Suspend', '<input type="hidden" name="to" value="suspended">', 'd-btn-danger', 'Suspend this vendor? Its staff cannot sign in and its website is hidden.') : $act('state', 'Activate', '<input type="hidden" name="to" value="active">', 'd-btn-primary'))
        . $act('listed', ((int) $v['is_listed'] ? 'Hide from directory' : 'Show in directory'), '<input type="hidden" name="to" value="' . ((int) $v['is_listed'] ? 0 : 1) . '">') . '<a class="d-btn d-btn-soft" href="' . e($app->tenant->siteUrl((string) $v['tenant_username'])) . '">View website</a></p>');
    $h .= $card('Subscription', '<dl class="d-kv"><dt>Plan</dt><dd><b>' . e((string) $sub['plan_name']) . '</b></dd><dt>State</dt><dd>' . e($app->plans->label($sub)) . '</dd><dt>Started</dt><dd>' . e(dt((string) ($sub['starts_at'] ?? ''))) . '</dd></dl><p style="margin-top:14px">'
        . $act('extend', 'Extend 30 days', '<input type="hidden" name="days" value="30">') . $act('extend', 'Extend 365 days', '<input type="hidden" name="days" value="365">') . $act('noexpiry', 'Never expires') . $act('revoke', 'Revoke plan', '', 'd-btn-danger', 'Remove the plan? The vendor becomes read-only until you give a new one.') . '</p>');
    $h .= '</div>';
    $h .= $card('Give or change the plan', '<form class="d-form" method="post" action="' . e(url('admin/vendors/' . $t . '/assign')) . '">' . csrf_field() . '<div class="d-form-grid"><div class="d-field"><label for="pl">Plan</label><select id="pl" name="plan_id" required>' . $opts . '</select></div>'
        . Crud::field('days', ['label' => 'Length in days (0 = never expires, empty = the plan length)', 'type' => 'number', 'min' => 0, 'step' => '1'], '') . Crud::field('note', ['label' => 'Note', 'max' => 200, 'wide' => true], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save plan</button></div></form>');
    $hist = $app->db->all('SELECT s.*, p.name FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 12', [$t]);
    $l = '';
    foreach ($hist as $r) {
        $l .= '<li><b>' . e($r['name']) . '</b> · ' . e($r['status']) . '<small>' . e(dt((string) $r['starts_at'])) . ' to ' . ($r['ends_at'] ? e(dt((string) $r['ends_at'])) : 'never') . ($r['note'] ? ' · ' . e((string) $r['note']) : '') . '</small></li>';
    }
    $h .= $card('History', '<ul class="d-tl">' . $l . '</ul>');
    $page($u, 'vendors', (string) $v['hospital_name'], $h, ['actions' => $btn('Make a subscription invoice', url('admin/billing/new', ['tenant' => $t]))]);
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
$mount = static function (string $base, callable $mod, string $active, string $singularLabel = '') use ($app, $page, $card, $btn, $crudPage, $go): void {
    $app->router->get($base, $crudPage($mod, $active, static function (array $u, Crud $c, array $m) use ($page, $card, $btn, $active): void {
        $page($u, $active, $m['title'], $card('', $c->list()), ['actions' => $btn('Add ' . strtolower($m['singular']), $c->url('new'))]);
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
$mount('/admin/types', $typeMod, 'types');

// ------------------------------------------------------------------ plans
$planMod = static function () use ($app): array {
    $n = static fn (string $l, string $h = '', string $step = '1'): array => ['label' => $l, 'type' => 'number', 'min' => 0, 'step' => $step, 'null' => true, 'help' => $h];
    return ['key' => 'plans', 'title' => 'Plans', 'singular' => 'Plan', 'url' => 'admin/plans', 'table' => 'sc_plans', 'scope' => [],
        'fields' => [
            'name' => ['label' => 'Plan name', 'required' => true, 'max' => 120], 'description' => ['label' => 'Short description', 'max' => 250, 'wide' => true],
            'currency' => ['label' => 'Currency', 'type' => 'select', 'options' => ['USD' => 'USD', 'BDT' => 'BDT', 'EUR' => 'EUR', 'GBP' => 'GBP', 'INR' => 'INR'], 'required' => true],
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
    $amount = trim((string) ($_POST['amount'] ?? '')) === '' ? (float) $plan['price'] : (float) $_POST['amount'];
    $period = mb_substr(trim((string) ($_POST['period'] ?? '')), 0, 60);
    $in = ['party_name' => $ten['hospital_name'], 'invoice_date' => date('Y-m-d'), 'x_plan_name' => $plan['name'], 'x_period' => $period, 'discount' => $_POST['discount'] ?? 0, 'paid_now' => $_POST['paid_now'] ?? 0, 'payment_method' => $_POST['payment_method'] ?? 'Bank transfer', 'payment_ref' => $_POST['reference'] ?? '', 'note' => $_POST['note'] ?? ''];
    try {
        $id = $billing->create($t, 'platform', $type, $in, [['item_name' => 'Self Care ' . $plan['name'] . ' plan, ' . $period, 'category' => (string) $plan['name'], 'qty' => 1, 'price' => $amount, 'tax_pct' => $_POST['tax_pct'] ?? 0]], (int) $app->auth->user()['id'], (string) $plan['currency']);
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
