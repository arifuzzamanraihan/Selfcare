<?php
// Selfcare original code. Gives each branch of a clinic lab vendor its own Branch Admin account (sign in at /login, lands only in that branch).
// php bin/lab-branch-admins.php <slug> <passwords.json>    passwords.json = {"MAIN":"...","BR1":"...",...} keyed by branch code. Existing accounts get the new password.
declare(strict_types=1);
foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}
$app = new Selfcare\App(dirname(__DIR__));
$db = $app->db;
$T = (string) ($argv[1] ?? '');
$pw = json_decode((string) @file_get_contents((string) ($argv[2] ?? '')), true);
if ($T === '' || !is_array($pw)) { fwrite(STDERR, "usage: php bin/lab-branch-admins.php <slug> <passwords.json>\n"); exit(1); }
$name = (string) $db->val('SELECT hospital_name FROM tenants WHERE id = ?', [$T]);
$rid = (int) $db->val("SELECT id FROM departments WHERE name = 'Branch Admin' AND (tenant_id IS NULL OR tenant_id = ?) ORDER BY tenant_id DESC LIMIT 1", [$T]);
if ($name === '' || !$rid) { fwrite(STDERR, "vendor or Branch Admin role not found\n"); exit(1); }
$domain = preg_replace('/[^a-z0-9]/', '', strtolower($T)) . '.easybzi.com';
foreach ($db->all('SELECT id, code, name FROM sc_branches WHERE tenant_id = ? AND active = 1 ORDER BY is_main DESC, sort_order, id', [$T]) as $b) {
    $code = (string) $b['code'];
    if (!isset($pw[$code])) { continue; }
    $email = strtolower($code) . '.admin@' . $domain;
    $hash = password_hash((string) $pw[$code], PASSWORD_BCRYPT);
    $uid = (int) $db->val('SELECT id FROM users WHERE email = ?', [$email]);
    if ($uid) {
        $db->run('UPDATE users SET password = ?, status = 1, deleted_at = NULL, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$hash, $uid]);
    } else {
        $db->run("INSERT INTO users (department_id,first_name,last_name,email,password,is_super_admin_default,gender,is_admin_default,status,language,hospital_name,tenant_id,created_at,updated_at,email_verified_at) VALUES (?,?,?,?,?,0,0,0,1,'en',?,?,NOW(),NOW(),NOW())", [$rid, (string) $b['name'], 'Admin', $email, $hash, $name, $T]);
        $uid = (int) $db->val('SELECT id FROM users WHERE email = ?', [$email]);
        $db->run("INSERT INTO model_has_roles (role_id,model_type,model_id) VALUES (?,'App\\\\Models\\\\User',?)", [$rid, $uid]);
    }
    $db->run('DELETE FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$T, $uid]);
    $db->run('INSERT INTO sc_user_branches (tenant_id,user_id,branch_id) VALUES (?,?,?)', [$T, $uid, (int) $b['id']]);
    echo $code . ' ' . $email . "\n";
}
