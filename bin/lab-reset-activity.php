<?php
// Selfcare original code. Clears the day to day records of ONE clinic laboratory vendor and keeps its set up
// (branches, roles, settings, test catalogue, signatory, the owner login). Used after sample data was entered for screenshots.
//   php bin/lab-reset-activity.php haider-lab --yes
// Make a database backup first. Touches only rows of the named vendor.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi', 'Lab', 'LabDocs'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}

$t = (string) ($argv[1] ?? '');
if ($t === '' || !in_array('--yes', $argv, true)) {
    fwrite(STDERR, "usage: php bin/lab-reset-activity.php <vendor-slug> --yes\n");
    exit(1);
}
$app = new Selfcare\App(dirname(__DIR__));
$db = $app->db;
if ((int) $db->val("SELECT COUNT(*) FROM sc_vendors WHERE tenant_id = ? AND type_slug = 'clinic'", [$t]) !== 1) {
    fwrite(STDERR, "not a clinic vendor: $t\n");
    exit(1);
}
$owner = (int) $db->val('SELECT id FROM users WHERE tenant_id = ? AND is_admin_default = 1 ORDER BY id LIMIT 1', [$t]);
$db->run('SET FOREIGN_KEY_CHECKS = 0');
$db->run('DELETE i FROM sc_invoice_items i JOIN sc_invoices v ON v.id = i.invoice_id WHERE v.tenant_id = ?', [$t]);
$db->run('DELETE p FROM sc_invoice_payments p JOIN sc_invoices v ON v.id = p.invoice_id WHERE v.tenant_id = ?', [$t]);
$clear = ['sc_lab_results', 'sc_lab_report_log', 'sc_lab_delivery_log', 'sc_lab_samples', 'sc_lab_order_items', 'sc_lab_orders', 'sc_lab_home', 'sc_lab_report_runs', 'sc_lab_backups', 'sc_commissions', 'sc_comm_rules', 'sc_referrers',
    'sc_attendance', 'sc_salary_adj', 'sc_salary', 'sc_staff_profiles', 'sc_record_history', 'sc_ledger', 'sc_stock_moves', 'sc_stock_items', 'sc_records', 'sc_record_events', 'sc_invoices', 'sc_documents', 'sc_prescription_items', 'sc_prescriptions',
    'sc_appointment_requests', 'sc_messages', 'sc_mail_queue', 'permission_change_log', 'patients', 'doctors', 'sc_number_sequences', 'sc_user_perms', 'sc_notifications'];
foreach ($clear as $tbl) {
    try {
        if ((int) $db->val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'tenant_id'", [$tbl]) === 1) {
            $db->run("DELETE FROM `$tbl` WHERE tenant_id = ?", [$t]);
        }
    } catch (Throwable $e) {
        echo "skipped $tbl: ", $e->getMessage(), "\n";
    }
}
$others = array_column($db->all('SELECT id FROM users WHERE tenant_id = ? AND id <> ?', [$t, $owner]), 'id');
foreach ($others as $uid) {
    $db->run('DELETE FROM model_has_roles WHERE model_id = ? AND model_type = ?', [(int) $uid, Selfcare\Auth::ROLE_MODEL]);
    foreach (['sc_user_branches', 'sc_password_resets', 'sc_login_tokens', 'sc_email_tokens'] as $tbl) {
        try { $db->run("DELETE FROM `$tbl` WHERE user_id = ?", [(int) $uid]); } catch (Throwable $e) { }
    }
    $db->run('DELETE FROM users WHERE id = ?', [(int) $uid]);
}
$db->run('SET FOREIGN_KEY_CHECKS = 1');
echo "reset $t: removed ", count($others), " other user(s); kept owner #$owner, branches, roles, settings and the test catalogue\n";
