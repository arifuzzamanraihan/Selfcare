<?php
// Selfcare original routes: a vendor's own website (on its sub domain, or under /h/{slug}).
declare(strict_types=1);

use Selfcare\VendorTypes;
use function Selfcare\{flash, keep_old, redirect, url};

/** Everything the vendor website templates need. @param array<string,mixed> $vendor @return array<string,mixed> */
$siteData = static function (array $vendor) use ($app): array {
    $db = $app->db;
    $tid = (string) $vendor['id'];
    $type = $app->typeRow((string) $vendor['type_slug']);
    $tpl = VendorTypes::template($type);
    $s = static fn (string $k, string $d = '') => $app->site->get($tid, $k, $d);
    $data = [];
    foreach ($tpl['sections'] as $sec) {
        $k = $sec['key'];
        switch ($sec['kind']) {
            case 'cards': case 'faq': case 'testimonials': case 'gallery': case 'news':
                $data[$k] = $app->site->items($tid, $k);
                if ($sec['kind'] === 'news') {
                    usort($data[$k], static fn ($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
                }
                break;
            case 'departments':
                $data[$k] = $db->all('SELECT d.id, d.title, d.description, (SELECT COUNT(*) FROM doctors x WHERE x.doctor_department_id = d.id) AS doctors FROM doctor_departments d WHERE d.tenant_id = ? ORDER BY d.title', [$tid]);
                break;
            case 'team':
                $data[$k] = $db->all('SELECT d.id, d.specialist, d.description, d.appointment_charge, u.first_name, u.last_name, u.qualification, dd.title AS department FROM doctors d JOIN users u ON u.id = d.user_id AND u.status = 1 LEFT JOIN doctor_departments dd ON dd.id = d.doctor_department_id WHERE d.tenant_id = ? AND u.status = 1 AND u.deleted_at IS NULL ORDER BY u.first_name', [$tid]);
                break;
            case 'text':
                $data[$k] = $s((string) $sec['field']);
                break;
            case 'hours':
                $data[$k] = $app->site->hours($tid);
                break;
        }
    }
    return [
        'vendor' => $vendor, 'type' => $type, 'tpl' => $tpl, 'data' => $data, 'tid' => $tid,
        'profile' => [
            'tagline' => $s('tagline'), 'about' => $s('about'), 'address' => $s('address'), 'city' => (string) ($vendor['city'] ?? ''), 'phone' => $s('phone', (string) ($vendor['phone'] ?? '')),
            'email' => $s('email'), 'emergency' => $s('emergency_phone'), 'cover' => $s('cover_image'), 'logo' => $s('logo'), 'license' => $s('license_no'),
            'facebook' => $s('social_facebook'), 'x' => $s('social_x'), 'instagram' => $s('social_instagram'), 'youtube' => $s('social_youtube'), 'linkedin' => $s('social_linkedin'),
        ],
        'free' => $db->all('SELECT * FROM free_services WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, id DESC', [$tid]),
        'doctors' => $db->all('SELECT d.id, u.first_name, u.last_name, d.specialist FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? AND u.status = 1 ORDER BY u.first_name', [$tid]),
        'user' => $app->auth->user(),
        'portalUrl' => url('portal'),
        'suffixMode' => $app->tenant->suffix() !== '',
    ];
};

$siteUrlFor = static fn (array $vendor, string $path = ''): string => $app->tenant->siteUrl((string) $vendor['slug'], $path);

$app->siteHome = static function (array $vendor, string $prefix) use ($app, $siteData, $siteUrlFor): void {
    $d = $siteData($vendor);
    $d['base'] = static fn (string $p = ''): string => $siteUrlFor($vendor, $p);
    $d['title'] = $vendor['name'];
    $d['layout'] = 'site/layout';
    $app->view->page('site/home', $d);
};

$siteDoctor = static function (array $vendor, int $id) use ($app, $siteData, $siteUrlFor): void {
    $doc = $app->db->one('SELECT d.id, d.specialist, d.description, d.appointment_charge, u.first_name, u.last_name, u.qualification, dd.title AS department FROM doctors d JOIN users u ON u.id = d.user_id AND u.status = 1 LEFT JOIN doctor_departments dd ON dd.id = d.doctor_department_id WHERE d.id = ? AND d.tenant_id = ? AND u.status = 1 AND u.deleted_at IS NULL', [$id, (string) $vendor['id']]);
    if ($doc === null) {
        $app->error(404, 'We could not find that profile.');
    }
    $d = $siteData($vendor);
    $d['base'] = static fn (string $p = ''): string => $siteUrlFor($vendor, $p);
    $d += ['doc' => $doc, 'title' => 'Dr. ' . $doc['first_name'] . ' ' . $doc['last_name'] . ' - ' . $vendor['name'], 'layout' => 'site/layout'];
    $app->view->page('site/doctor', $d);
};

$siteRequest = static function (array $vendor) use ($app, $siteUrlFor): void {
    $back = $siteUrlFor($vendor, '') . '#request';
    $tid = (string) $vendor['id'];
    if (($_POST['website'] ?? '') !== '') {
        redirect($back);
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $date = (string) ($_POST['preferred_date'] ?? '');
    $doctor = (int) ($_POST['doctor_id'] ?? 0);
    if ($app->throttle->tooMany('request', $app->ip(), 8, 3600)) {
        flash('You sent several requests already. Please call us or try again later.', 'bad');
        redirect($back);
    }
    $ok = $name !== '' && mb_strlen($name) <= 160 && preg_match('/^[0-9+()\-\s]{6,25}$/', $phone) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date >= date('Y-m-d')
        && ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL));
    if (!$ok) {
        keep_old($_POST);
        flash('Please check your name, phone, a date from today onwards, and your email if you added one.', 'bad');
        redirect($back);
    }
    if ($doctor && !$app->db->val('SELECT COUNT(*) FROM doctors WHERE id = ? AND tenant_id = ?', [$doctor, $tid])) {
        $doctor = 0;
    }
    $app->throttle->hit('request', $app->ip());
    $app->db->insert('sc_appointment_requests', ['tenant_id' => $tid, 'name' => $name, 'phone' => $phone, 'email' => $email ?: null, 'doctor_id' => $doctor ?: null, 'preferred_date' => $date, 'message' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1000) ?: null]);
    $tpl = VendorTypes::template($app->typeRow((string) $vendor['type_slug']));
    flash((string) $tpl['request']['done'], 'ok');
    redirect($back);
};

$app->siteContact = static function (array $vendor) use ($app, $siteUrlFor): void {
    $back = $siteUrlFor($vendor, '') . '#contact';
    $name = trim((string) ($_POST['name'] ?? ''));
    $msg = trim((string) ($_POST['message'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if (($_POST['website'] ?? '') !== '') {
        redirect($back);
    }
    if ($app->throttle->tooMany('contact', $app->ip(), 5, 3600)) {
        flash('You sent a few messages already. Please try again later.', 'bad');
        redirect($back);
    }
    if ($name === '' || $msg === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        keep_old($_POST);
        flash('Please add your name, a valid email and a message.', 'bad');
        redirect($back);
    }
    $app->throttle->hit('contact', $app->ip());
    $app->db->insert('sc_messages', ['tenant_id' => (string) $vendor['id'], 'name' => mb_substr($name, 0, 160), 'email' => $email ?: null, 'phone' => mb_substr((string) ($_POST['phone'] ?? ''), 0, 40) ?: null, 'message' => mb_substr($msg, 0, 3000)]);
    flash('Thank you. Your message was sent.', 'ok');
    redirect($back);
};

// On a vendor host
$app->router->get('/doctor/{id}', static function (array $p) use ($app, $siteDoctor): void {
    if ($app->hostVendor === null) {
        $app->error(404, 'We could not find that page.');
    }
    $siteDoctor($app->hostVendor, (int) $p['id']);
});
$app->router->post('/request', static function () use ($app, $siteRequest): void {
    if ($app->hostVendor === null) {
        $app->error(404, 'We could not find that page.');
    }
    $siteRequest($app->hostVendor);
});

// Fallback without sub domains: /h/{slug}
$vendorBySlug = static function (string $slug) use ($app): array {
    $v = $app->tenant->bySlug($slug);
    if ($v === null || $v['status'] !== 'active') {
        $app->error(404, 'We could not find that provider.');
    }
    return $v;
};
$app->router->get('/h/{slug}', static function (array $p) use ($app, $vendorBySlug): void {
    ($app->siteHome)($vendorBySlug((string) $p['slug']), '');
});
$app->router->get('/h/{slug}/doctor/{id}', static function (array $p) use ($vendorBySlug, $siteDoctor): void {
    $siteDoctor($vendorBySlug((string) $p['slug']), (int) $p['id']);
});
$app->router->post('/h/{slug}/request', static function (array $p) use ($vendorBySlug, $siteRequest): void {
    $siteRequest($vendorBySlug((string) $p['slug']));
});
$app->router->post('/h/{slug}/contact', static function (array $p) use ($app, $vendorBySlug): void {
    ($app->siteContact)($vendorBySlug((string) $p['slug']));
});
