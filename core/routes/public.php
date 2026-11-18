<?php
// Selfcare original routes: main website. $app is provided by bootstrap.php.
declare(strict_types=1);

use Selfcare\Vendors;
use Selfcare\VendorTypes;
use function Selfcare\{csrf_ok, flash, keep_old, redirect, url};

/** Data every main-site page needs. */
$mainPage = static function (string $title, array $extra = []) use ($app): array {
    return ['title' => $title, 'user' => $app->auth->user()] + $extra;
};

$app->router->get('/', static function () use ($app, $mainPage): void {
    if ($app->hostVendor !== null) {
        ($app->siteHome)($app->hostVendor, '');
        return;
    }
    $db = $app->db;
    $types = VendorTypes::all($db);
    $counts = $db->all("SELECT type_slug, COUNT(*) n FROM sc_vendors WHERE status = 'active' AND is_listed = 1 GROUP BY type_slug");
    $app->view->page('home', $mainPage($app->site->get(null, 'seo_title'), [
        'types' => $types,
        'typeCounts' => array_column($counts, 'n', 'type_slug'),
        'vendors' => Vendors::listed($db, null, '', 6),
        'free' => Vendors::freeServices($db, null, '', 6),
        'plans' => $app->plans->all(true),
        'stats' => ['vendors' => (int) $db->val("SELECT COUNT(*) FROM sc_vendors WHERE status = 'active'"), 'types' => count($types), 'doctors' => (int) $db->val('SELECT COUNT(*) FROM doctors'), 'free' => (int) $db->val('SELECT COUNT(*) FROM free_services WHERE is_active = 1')],
        'seo' => true,
    ]));
});

$app->router->get('/directory', static function () use ($app, $mainPage): void {
    $type = (string) ($_GET['type'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));
    $types = VendorTypes::all($app->db);
    $slugs = array_column($types, 'slug');
    if ($type !== '' && !in_array($type, $slugs, true)) {
        $type = '';
    }
    $app->view->page('directory', $mainPage('Provider directory', ['types' => $types, 'type' => $type, 'q' => $q, 'vendors' => Vendors::listed($app->db, $type, $q, 120)]));
});

$app->router->get('/types/{slug}', static function (array $p) use ($app, $mainPage): void {
    $type = VendorTypes::find($app->db, (string) $p['slug']);
    if ($type === null || (int) $type['is_enabled'] !== 1) {
        $app->error(404, 'We could not find that kind of provider.');
    }
    $tpl = VendorTypes::template($type);
    $app->view->page('type', $mainPage((string) $type['name'] . ' websites and portal', [
        'type' => $type, 'tpl' => $tpl, 'vendors' => Vendors::listed($app->db, (string) $type['slug'], '', 12),
        'others' => array_values(array_filter(VendorTypes::all($app->db), static fn ($t) => $t['slug'] !== $type['slug'])),
        'plans' => array_values(array_filter($app->plans->all(true), fn ($pl) => $app->plans->fitsType($pl, (string) $type['slug']))),
    ]));
});

$app->router->get('/free-services', static function () use ($app, $mainPage): void {
    $type = (string) ($_GET['type'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));
    $app->view->page('free_services', $mainPage('Free services', ['types' => VendorTypes::all($app->db), 'type' => $type, 'q' => $q, 'items' => Vendors::freeServices($app->db, $type, $q, 120)]));
});

$app->router->get('/pricing', static function (): void {
    redirect(url('') . '#pricing');
});

foreach (['terms' => 'Terms of use', 'privacy' => 'Privacy policy'] as $slug => $label) {
    $app->router->get('/' . $slug, static function () use ($app, $mainPage, $slug, $label): void {
        $app->view->page('page', $mainPage($label, ['heading' => $label, 'slug' => $slug]));
    });
}

$app->router->get('/health', static function () use ($app): void {
    header('Content-Type: application/json');
    echo json_encode(['app' => 'Selfcare', 'database' => $app->db->healthy()]);
});

// Contact form and newsletter on the main site
$app->router->post('/contact', static function () use ($app): void {
    if ($app->hostVendor !== null) {
        ($app->siteContact)($app->hostVendor);
        return;
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $msg = trim((string) ($_POST['message'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $back = url('') . '#contact';
    if (($_POST['website'] ?? '') !== '') { // honeypot
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
    $app->db->insert('sc_messages', ['tenant_id' => null, 'name' => mb_substr($name, 0, 160), 'email' => $email ?: null, 'phone' => mb_substr((string) ($_POST['phone'] ?? ''), 0, 40) ?: null, 'message' => mb_substr($msg, 0, 3000)]);
    flash('Thank you. Your message was sent.', 'ok');
    redirect($back);
});

$app->router->post('/newsletter', static function () use ($app): void {
    $email = trim((string) ($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !$app->throttle->tooMany('news', $app->ip(), 5, 3600)) {
        $app->throttle->hit('news', $app->ip());
        $app->db->insert('sc_messages', ['tenant_id' => null, 'name' => 'Newsletter signup', 'email' => $email, 'message' => 'Please add me to the newsletter.']);
        flash('You are on the list. Thank you.', 'ok');
    } else {
        flash('Please enter a valid email.', 'bad');
    }
    redirect($_SERVER['HTTP_REFERER'] ?? url(''));
});
