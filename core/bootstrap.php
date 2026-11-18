<?php
// Selfcare original front bootstrap. No framework, only PHP itself.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi'] as $f) {
    if (is_file(__DIR__ . '/' . $f . '.php')) {
        require_once __DIR__ . '/' . $f . '.php';
    }
}

use Selfcare\App;

// Safe session cookie and standard security headers.
session_name('SELFCARESESSID');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Selfcare\is_https()]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
if (Selfcare\is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}

$root = dirname(__DIR__);
try {
    $app = new App($root);
} catch (\Throwable $e) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Unavailable</title><p style="font-family:sans-serif;padding:40px">Self Care is starting up. Please try again in a minute.</p>';
    exit;
}
if ($app->db->val('SELECT COUNT(*) FROM sc_vendor_types') == 0) {
    Selfcare\VendorTypes::seed($app->db);
}
Selfcare\Plans::seedDefaults($app->db);

foreach (['public', 'auth', 'site', 'portal', 'admin', 'documents', 'ops', 'accounts', 'rx', 'roletpl'] as $r) {
    $file = __DIR__ . '/routes/' . $r . '.php';
    if (is_file($file)) {
        (static function (App $app, string $file): void {
            require $file;
        })($app, $file);
    }
}

$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$bp = Selfcare\base_path();
$path = ($bp !== '' && str_starts_with($uri, $bp)) ? substr($uri, strlen($bp)) : $uri;
$path = rtrim($path === '' ? '/' : $path, '/') ?: '/';
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST' && !Selfcare\csrf_ok()) {
    $app->error(419, 'Your session timed out. Go back, reload the page and try again.', 'Please try again');
}
if ($app->hostSlug === '' || ($app->hostSlug !== null && $app->hostVendor === null)) {
    $app->error(404, 'There is no provider at this address.', 'Address not found');
}
$app->router->dispatch($method, $path);
