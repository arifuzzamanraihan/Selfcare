<?php
// Selfcare original routes: sign in, sign out, handoff, forgot / reset password, register.
declare(strict_types=1);

use Selfcare\Vendors;
use Selfcare\VendorTypes;
use Selfcare\Tenant;
use function Selfcare\{clear_old, flash, hop, keep_old, redirect, slugify, url};

$authPage = static function (string $tpl, string $title, array $extra = []) use ($app): void {
    $app->view->page($tpl, ['title' => $title, 'user' => $app->auth->user(), 'layout' => 'layout', 'bare' => true] + $extra);
};

/** Vendor row of a user id, or null for the super admin. @return array<string,mixed>|null */
$vendorOfUser = static function (int $uid) use ($app): ?array {
    $row = $app->db->one('SELECT t.tenant_username FROM users u JOIN tenants t ON t.id = u.tenant_id WHERE u.id = ?', [$uid]);
    return $row === null ? null : $app->tenant->bySlug((string) $row['tenant_username']);
};

$app->router->get('/login', static function () use ($app, $authPage): void {
    $u = $app->auth->user();
    if ($u !== null) {
        redirect($app->home($u));
    }
    $authPage('login', 'Sign in', ['vendor' => $app->hostVendor]);
});

$app->router->post('/login', static function () use ($app, $authPage, $vendorOfUser): void {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pw = (string) ($_POST['password'] ?? '');
    $fail = static function (string $m, int $code = 401) use ($authPage, $app, $email): void {
        http_response_code($code);
        keep_old(['email' => $email]);
        $authPage('login', 'Sign in', ['vendor' => $app->hostVendor, 'error' => $m]);
        clear_old();
    };
    $who = $app->ip() . '|' . $email;
    if ($app->throttle->tooMany('login', $who, 8, 900)) {
        $fail('Too many tries. Please wait 15 minutes and try again.', 429);
        return;
    }
    $tenantId = $app->hostVendor['id'] ?? null;
    $uid = $app->auth->verify($email, $pw, $tenantId === null ? null : (string) $tenantId);
    if ($uid === null) {
        $app->throttle->hit('login', $who);
        $fail('Email or password is not correct.');
        return;
    }
    $app->throttle->clear('login', $who);
    $vendor = $vendorOfUser($uid);
    if ($vendor !== null) {
        if ($vendor['status'] === 'deleted') {
            $fail('Email or password is not correct.');
            return;
        }
        if ($vendor['status'] === 'suspended') {
            $fail('This account is suspended. Please contact Self Care.');
            return;
        }
        // main site in sub domain mode: send the user to their own address
        if ($app->tenant->suffix() !== '' && $app->hostSlug === null) {
            hop($app->vendorPortalUrl($vendor, 'auth/handoff') . '?token=' . $app->auth->makeHandoff($uid));
        }
        $row = $app->db->one('SELECT m.role_id, d.name AS role FROM model_has_roles m JOIN departments d ON d.id = m.role_id WHERE m.model_id = ? AND m.model_type = ? LIMIT 1', [$uid, Selfcare\Auth::ROLE_MODEL]);
        $block = $app->access->timeBlock((string) $vendor['id'], $uid, (string) ($row['role'] ?? 'Patient'));
        if ($block !== null) {
            $fail($block);
            return;
        }
    } elseif ($app->hostVendor !== null) {
        $fail('Email or password is not correct.');
        return;
    }
    $app->auth->login($uid);
    hop($app->home($app->auth->user() ?? []));
});

// Main site -> vendor site: one-time token signs the user in on the vendor address.
$app->router->get('/auth/handoff', static function () use ($app, $vendorOfUser): void {
    $token = (string) ($_GET['token'] ?? '');
    if ($app->hostVendor === null || $token === '') {
        redirect(url('login'));
    }
    $uid = $app->auth->useHandoff($token, (string) $app->hostVendor['id']);
    if ($uid === null) {
        flash('That sign in link expired. Please sign in again.', 'bad');
        redirect(url('login'));
    }
    $imp = $app->auth->handoffImpersonator;
    $app->auth->login($uid, $imp === null);
    if ($imp !== null) {
        $_SESSION['sc_impersonator'] = $imp; // set after login(), which clears it
    }
    redirect(url('portal'));
});

// "Viewing as <vendor>": return to the super admin. CSRF-checked POST like every other POST.
$app->router->post('/auth/return', static function () use ($app): void {
    $imp = $app->auth->impersonator();
    $me = $app->auth->user();
    if ($imp === null || $me === null) {
        redirect(url(''));
    }
    $slug = (string) ($me['tenant_id'] ?? '');
    $app->access->log($slug, $imp, (int) $me['id'], null, 'impersonate_end', null, null, 'Returned to super admin');
    $app->auth->logout();
    if ($app->tenant->suffix() === '') {
        $app->auth->login($imp, false); // one host: bring the super admin session back
    }
    flash('You are back as super admin.', 'ok');
    hop($app->tenant->suffix() === '' ? url('admin/vendors/' . $slug) : $app->tenant->mainUrl('admin/vendors/' . $slug));
});

$app->router->post('/logout', static function () use ($app): void {
    $app->auth->logout();
    redirect(url(''));
});

// ---------------------------------------------------------------- forgot / reset password
$app->router->get('/forgot', static function () use ($authPage, $app): void {
    $authPage('forgot', 'Forgot password', ['vendor' => $app->hostVendor]);
});

$app->router->post('/forgot', static function () use ($app, $authPage): void {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $done = static function () use ($authPage, $app): void {
        $authPage('forgot', 'Forgot password', ['vendor' => $app->hostVendor, 'sent' => true]);
    };
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $app->throttle->tooMany('forgot', $app->ip(), 5, 3600) || $app->throttle->tooMany('forgot_mail', $email, 3, 3600)) {
        $done();
        return;
    }
    $app->throttle->hit('forgot', $app->ip());
    $app->throttle->hit('forgot_mail', $email);
    $user = $app->db->one('SELECT id, first_name, tenant_id, status FROM users WHERE email = ? LIMIT 1', [$email]);
    $hostTenant = $app->hostVendor['id'] ?? null;
    if ($user !== null && (int) $user['status'] === 1 && ($hostTenant === null || (string) $user['tenant_id'] === (string) $hostTenant)) {
        $token = bin2hex(random_bytes(32));
        $app->db->run('UPDATE sc_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
        $app->db->insert('sc_password_resets', ['user_id' => (int) $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $vendor = $user['tenant_id'] !== null ? $app->tenant->bySlug((string) $user['tenant_id']) : null;
        $base = $vendor !== null && $app->tenant->suffix() !== '' ? $app->tenant->siteUrl((string) $vendor['slug'], 'reset') : $app->tenant->mainUrl('reset');
        $app->mailer->send($email, 'Reset your Self Care password', "Hello " . $user['first_name'] . ",\n\nUse this link to choose a new password. It works once and expires in 60 minutes:\n\n" . $base . '?token=' . $token . "\n\nIf you did not ask for this, you can ignore this email. Your password stays the same.\n\nSelf Care");
    }
    $done();
});

$tokenRow = static function (string $token) use ($app): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return $app->db->one('SELECT r.id, r.user_id FROM sc_password_resets r WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1', [hash('sha256', $token)]);
};

$app->router->get('/reset', static function () use ($authPage, $app, $tokenRow): void {
    $token = (string) ($_GET['token'] ?? '');
    $authPage('reset', 'Choose a new password', ['vendor' => $app->hostVendor, 'token' => $token, 'valid' => $tokenRow($token) !== null]);
});

$app->router->post('/reset', static function () use ($authPage, $app, $tokenRow): void {
    $token = (string) ($_POST['token'] ?? '');
    $row = $tokenRow($token);
    $pw = (string) ($_POST['password'] ?? '');
    $err = null;
    if ($row === null) {
        $authPage('reset', 'Choose a new password', ['vendor' => $app->hostVendor, 'token' => $token, 'valid' => false]);
        return;
    }
    if (strlen($pw) < 8) {
        $err = 'Use at least 8 characters.';
    } elseif ($pw !== (string) ($_POST['password2'] ?? '')) {
        $err = 'The two passwords do not match.';
    }
    if ($err !== null) {
        $authPage('reset', 'Choose a new password', ['vendor' => $app->hostVendor, 'token' => $token, 'valid' => true, 'error' => $err]);
        return;
    }
    $app->db->run('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [password_hash($pw, PASSWORD_BCRYPT), (int) $row['user_id']]);
    $app->db->run('UPDATE sc_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $row['user_id']]);
    flash('Your password was changed. Please sign in.', 'ok');
    redirect(url('login'));
});

// ---------------------------------------------------------------- register a vendor
$app->router->get('/register', static function () use ($app, $authPage): void {
    if ($app->hostVendor !== null) {
        redirect($app->tenant->mainUrl('register'));
    }
    $authPage('register', 'Register your organisation', ['types' => VendorTypes::all($app->db), 'pick' => (string) ($_GET['type'] ?? ''), 'suffix' => $app->tenant->suffix(), 'plans' => $app->plans->all(true)]);
});

$app->router->post('/register', static function () use ($app, $authPage): void {
    $types = VendorTypes::all($app->db);
    $again = static function (string $err) use ($authPage, $types, $app): void {
        http_response_code(422);
        $authPage('register', 'Register your organisation', ['types' => $types, 'pick' => (string) ($_POST['type'] ?? ''), 'suffix' => $app->tenant->suffix(), 'plans' => $app->plans->all(true), 'error' => $err]);
        clear_old();
    };
    keep_old($_POST);
    if (($_POST['website'] ?? '') !== '') {
        redirect(url(''));
    }
    if ($app->throttle->tooMany('register', $app->ip(), 5, 3600)) {
        $again('Too many sign ups from this network. Please try again later.');
        return;
    }
    $type = (string) ($_POST['type'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = slugify((string) ($_POST['slug'] ?? ''));
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pw = (string) ($_POST['password'] ?? '');
    if (!in_array($type, array_column($types, 'slug'), true)) {
        $again('Please choose what kind of organisation you are.');
        return;
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 120 || $first === '') {
        $again('Please enter your organisation name (at least 3 letters) and your name.');
        return;
    }
    if (($slugErr = Vendors::slugError($app, $slug)) !== null) {
        $again($slugErr);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $again('Please enter a valid email.');
        return;
    }
    if (strlen($pw) < 8 || $pw !== (string) ($_POST['password2'] ?? '')) {
        $again(strlen($pw) < 8 ? 'Use at least 8 characters for the password.' : 'The two passwords do not match.');
        return;
    }
    if ($app->db->val('SELECT COUNT(*) FROM tenants WHERE hospital_name = ?', [$name]) > 0 || $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE name = ? AND status = 'pending'", [$name]) > 0) {
        $again('That organisation name is already registered or waiting for approval.');
        return;
    }
    if ($app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0 || $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE email = ? AND status = 'pending'", [$email]) > 0) {
        $again('That email already has an account. Try signing in.');
        return;
    }
    $app->throttle->hit('register', $app->ip());
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $app->db->insert('sc_registration_requests', ['type_slug' => $type, 'name' => $name, 'slug' => $slug, 'city' => trim((string) ($_POST['city'] ?? '')) ?: null, 'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null, 'first_name' => mb_substr($first, 0, 120), 'last_name' => mb_substr($last, 0, 120), 'email' => $email,
        'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'plan_id' => $planId > 0 && $app->plans->find($planId) !== null ? $planId : null, 'status' => 'pending', 'ip' => $app->ip(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    clear_old();
    $authPage('register_done', 'Registration received', ['name' => $name]);
});
