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

/** A place to continue after sign in: a path inside this site only (never another host, never /admin or the sign in pages). */
$safeNext = static function (string $next): ?string {
    if ($next !== '' && preg_match('#^/(?!/)[A-Za-z0-9_\-./?=&%]*$#', $next) && !str_contains($next, '..') && !preg_match('#^/(login|logout|forgot|reset|verify|admin)#', $next)) {
        return $next;
    }
    return null;
};

$app->router->get('/login', static function () use ($app, $authPage): void {
    $u = $app->auth->user();
    if ($u !== null) {
        redirect($app->home($u));
    }
    $authPage('login', 'Sign in', ['vendor' => $app->hostVendor]);
});

$app->router->post('/login', static function () use ($app, $authPage, $vendorOfUser, $safeNext): void {
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
    if ($uid === null && $tenantId !== null && $app->tenant->suffix() !== '') {
        // one sign in form for everybody: an account of another vendor is carried to its own address
        $any = $app->auth->verify($email, $pw, null);
        $other = $any === null ? null : $vendorOfUser($any);
        if ($other !== null && $other['status'] === 'active') {
            $app->throttle->clear('login', $who);
            hop($app->vendorPortalUrl($other, 'auth/handoff') . '?token=' . $app->auth->makeHandoff($any) . (($nx = $safeNext((string) ($_POST['next'] ?? ''))) !== null ? '&next=' . rawurlencode($nx) : ''));
        }
    }
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
            hop($app->vendorPortalUrl($vendor, 'auth/handoff') . '?token=' . $app->auth->makeHandoff($uid) . (($nx = $safeNext((string) ($_POST['next'] ?? ''))) !== null ? '&next=' . rawurlencode($nx) : ''));
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
    $go = $app->home($app->auth->user() ?? []);
    $go = $safeNext((string) ($_POST['next'] ?? '')) ?? $go;
    hop($go);
});

// Main site -> vendor site: one-time token signs the user in on the vendor address.
$app->router->get('/auth/handoff', static function () use ($app, $vendorOfUser, $safeNext): void {
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
        redirect(url('portal'));
    }
    redirect($safeNext((string) ($_GET['next'] ?? '')) ?? $app->home($app->auth->user() ?? []));
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

// Every old or guessed sign in address ends up at the one sign in form.
foreach (['/signin', '/sign-in', '/log-in', '/user/login', '/admin/login', '/portal/login', '/staff/login', '/doctor/login', '/patient/login', '/partner/login', '/vendor/login', '/auth/login', '/account/login', '/h/{slug}/login'] as $legacy) {
    $app->router->get($legacy, static function () use ($app): void {
        redirect(url('login'));
    });
}

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
        $full = $app->db->one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]) ?? $user;
        $vendor = $user['tenant_id'] !== null ? $app->tenant->bySlug((string) $user['tenant_id']) : null;
        $app->mail->send('reset', $email, 'Reset your Self Care password', 'Reset your password',
            'Hello ' . $user['first_name'] . ",\n\nWe received a request to choose a new password for your account. The link works once and expires in 60 minutes.",
            ['Choose a new password', $app->mail->linkFor($full, 'reset') . '?token=' . $token], $vendor['name'] ?? null, $user['tenant_id'] !== null ? (string) $user['tenant_id'] : null, (int) $user['id'], 'If you did not ask for this, ignore this message. Your password stays the same.');
        $app->access->log((string) ($user['tenant_id'] ?? '_platform'), null, (int) $user['id'], null, 'password_reset_requested', null, null, $app->ip());
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
    // following the link proves the person controls the address
    $app->db->run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [(int) $row['user_id']]);
    $who = $app->db->one('SELECT * FROM users WHERE id = ?', [(int) $row['user_id']]);
    if ($who !== null) {
        $app->mail->sendPasswordChanged($who, !empty($_POST['welcome']) ? 'set' : 'reset');
        $app->access->log((string) ($who['tenant_id'] ?? '_platform'), null, (int) $who['id'], null, 'password_reset_done', null, null, $app->ip());
    }
    flash('Your password was saved. Please sign in.', 'ok');
    redirect(url('login'));
});

// ---------------------------------------------------------------- register a vendor
/** Sends (or re sends) the confirmation link of a registration request. */
$sendRegisterMail = static function (int $rid) use ($app): void {
    $r = $app->db->one("SELECT * FROM sc_registration_requests WHERE id = ? AND status = 'unverified'", [$rid]);
    if ($r === null) {
        return;
    }
    $token = $app->mail->token('register', null, $rid, (string) $r['email'], 2 * 86400);
    $app->mail->send('register_verify', (string) $r['email'], 'Confirm your email to finish registering', 'Confirm your email',
        'Hello ' . $r['first_name'] . ",\n\nThank you for registering " . $r['name'] . " with Self Care. Please confirm this email address. After that our team reviews your registration and you get another email when it is approved.\n\nThe link works once and expires in 48 hours.",
        ['Confirm my email', $app->tenant->mainUrl('verify') . '?token=' . $token], null, null, null, 'If you did not register, ignore this message.');
};

$app->router->get('/register', static function () use ($app, $authPage): void {
    if ($app->hostVendor !== null) {
        redirect($app->tenant->mainUrl('register'));
    }
    $authPage('register', 'Register your organisation', ['types' => VendorTypes::all($app->db), 'pick' => (string) ($_GET['type'] ?? ''), 'suffix' => $app->tenant->suffix(), 'plans' => $app->plans->all(true)]);
});

$app->router->post('/register', static function () use ($app, $authPage, $sendRegisterMail): void {
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
    if ($app->db->val('SELECT COUNT(*) FROM tenants WHERE hospital_name = ?', [$name]) > 0 || $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE name = ? AND status IN ('pending','unverified')", [$name]) > 0) {
        $again('That organisation name is already registered or waiting for approval.');
        return;
    }
    if ($app->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0 || $app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE email = ? AND status IN ('pending','unverified')", [$email]) > 0) {
        $again('That email already has an account. Try signing in.');
        return;
    }
    $app->throttle->hit('register', $app->ip());
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $app->db->insert('sc_registration_requests', ['type_slug' => $type, 'name' => $name, 'slug' => $slug, 'city' => trim((string) ($_POST['city'] ?? '')) ?: null, 'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null, 'first_name' => mb_substr($first, 0, 120), 'last_name' => mb_substr($last, 0, 120), 'email' => $email,
        'password_hash' => password_hash($pw, PASSWORD_BCRYPT), 'plan_id' => $planId > 0 && $app->plans->find($planId) !== null ? $planId : null, 'status' => 'unverified', 'ip' => $app->ip(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    clear_old();
    $rid = (int) $app->db->val('SELECT id FROM sc_registration_requests WHERE email = ? ORDER BY id DESC LIMIT 1', [$email]);
    $sendRegisterMail($rid);
    $authPage('register_done', 'Registration received', ['name' => $name, 'email' => $email]);
});

// ---------------------------------------------------------------- email confirmation (registration, new accounts, change of email)
$app->router->post('/register/resend', static function () use ($app, $authPage, $sendRegisterMail): void {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !$app->throttle->tooMany('regresend', $app->ip(), 5, 3600) && !$app->throttle->tooMany('regresend_mail', $email, 3, 3600)) {
        $app->throttle->hit('regresend', $app->ip());
        $app->throttle->hit('regresend_mail', $email);
        $sendRegisterMail((int) $app->db->val("SELECT id FROM sc_registration_requests WHERE email = ? AND status = 'unverified' ORDER BY id DESC LIMIT 1", [$email]));
    }
    $authPage('register_done', 'Registration received', ['name' => '', 'email' => $email, 'resent' => true]);
});

$app->router->get('/verify', static function () use ($app, $authPage): void {
    $token = (string) ($_GET['token'] ?? '');
    $t = $app->mail->peek($token);
    $msg = ['ok' => false, 'title' => 'This link is not valid', 'text' => 'The link was already used or has expired. Sign in and ask for a new confirmation email from the notice at the top of your portal, or register again.'];
    if ($t !== null) {
        if ($t['purpose'] === 'register') {
            $app->db->run("UPDATE sc_registration_requests SET status = 'pending', email_verified_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'unverified'", [(int) $t['ref_id']]);
            $app->mail->burn((int) $t['id']);
            $msg = ['ok' => true, 'title' => 'Email confirmed', 'text' => 'Thank you. Your registration is now with our team. You will get an email as soon as it is approved.'];
        } elseif ($t['purpose'] === 'verify') {
            $app->db->run('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email = ?', [(int) $t['user_id'], $t['email']]);
            $app->mail->burn((int) $t['id']);
            $msg = ['ok' => true, 'title' => 'Email confirmed', 'text' => 'Thank you. Your email address is confirmed.'];
        } elseif ($t['purpose'] === 'change') {
            $u = $app->db->one('SELECT * FROM users WHERE id = ?', [(int) $t['user_id']]);
            $taken = (int) $app->db->val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$t['email'], (int) $t['user_id']]) > 0;
            if ($u === null || $u['pending_email'] !== $t['email'] || $taken) {
                $msg = ['ok' => false, 'title' => 'This change cannot be completed', 'text' => 'The new address is already in use or the request was replaced. Start the change again from My account.'];
            } else {
                $app->db->run('UPDATE users SET email = ?, pending_email = NULL, email_verified_at = NOW(), updated_at = NOW() WHERE id = ?', [$t['email'], (int) $t['user_id']]);
                $app->mail->burn((int) $t['id']);
                $app->access->log((string) ($u['tenant_id'] ?? '_platform'), (int) $u['id'], (int) $u['id'], null, 'email_changed', (string) $u['email'], (string) $t['email'], $app->ip());
                $msg = ['ok' => true, 'title' => 'New email confirmed', 'text' => 'From now on sign in with ' . $t['email'] . '.'];
            }
        }
    }
    $authPage('verify', 'Confirm email', ['vendor' => $app->hostVendor, 'msg' => $msg]);
});

$app->router->post('/account/verify/resend', static function () use ($app): void {
    $u = $app->auth->user();
    if ($u === null) {
        redirect(url('login'));
    }
    if ($app->throttle->tooMany('verify_resend', (string) $u['id'], 3, 3600)) {
        flash('Please wait a while before asking again.', 'bad');
        redirect($_SERVER['HTTP_REFERER'] ?? url('portal'));
    }
    $app->throttle->hit('verify_resend', (string) $u['id']);
    $full = $app->db->one('SELECT * FROM users WHERE id = ?', [(int) $u['id']]) ?? $u;
    if (!empty($full['pending_email'])) {
        $app->mail->sendVerify($full, (string) $full['pending_email'], 'change');
    } elseif (empty($full['email_verified_at'])) {
        $app->mail->sendVerify($full);
    }
    flash('A new confirmation email is on its way.', 'ok');
    redirect($_SERVER['HTTP_REFERER'] ?? url('portal'));
});
