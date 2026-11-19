<?php
// Selfcare original code. Scheduled jobs of the clinic laboratory suite. Run every 15 minutes from cron:
//   */15 * * * * /usr/bin/php /path/to/bin/lab-cron.php >> /path/to/storage/lab-cron.log 2>&1
// For every clinic with the suite it: sends the daily report once after the set time, retries failed emails (up to 5 tries), makes the scheduled daily backup.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi', 'Lab', 'LabDocs'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}

use Selfcare\{Lab, Portal};

$app = new Selfcare\App(dirname(__DIR__));
$db = $app->db;
$done = 0;
foreach ($db->all("SELECT DISTINCT t.id tenant, (SELECT u.id FROM users u LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = 'App\\\\Models\\\\User' LEFT JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = t.id AND u.status = 1 AND d.name IN ('Owner', 'Admin') ORDER BY u.id LIMIT 1) uid FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id WHERE v.type_slug = 'clinic' AND v.status <> 'suspended'") as $r) {
    if (!$r['uid']) {
        continue;
    }
    $p = Portal::forUser($app, (int) $r['uid']);
    if ($p === null || !Lab::enabled($p)) {
        continue;
    }
    $L = Lab::for($p);
    $today = date('Y-m-d');
    if ($L->setting('report_on') === '1' && date('H:i') >= $L->setting('report_time', '21:00') && (int) $db->val("SELECT COUNT(*) FROM sc_lab_report_runs WHERE tenant_id = ? AND report_date = ? AND status = 'sent'", [$r['tenant'], $today]) === 0 && (int) $db->val('SELECT COUNT(*) FROM sc_lab_report_runs WHERE tenant_id = ? AND report_date = ? AND tries >= 5', [$r['tenant'], $today]) === 0) {
        $e = $L->sendDailyReport($today);
        echo date('c') . " {$r['tenant']} daily report " . ($e ?? 'sent') . "\n";
        $done++;
    }
    foreach ($db->all("SELECT id, report_date FROM sc_lab_report_runs WHERE tenant_id = ? AND status = 'failed' AND tries < 5 AND updated_at < ?", [$r['tenant'], date('Y-m-d H:i:s', time() - 900)]) as $f) {
        $e = $L->sendDailyReport((string) $f['report_date'], (int) $f['id']);
        echo date('c') . " {$r['tenant']} report retry " . ($e ?? 'sent') . "\n";
    }
    foreach ($db->all("SELECT id FROM sc_lab_delivery_log WHERE tenant_id = ? AND channel = 'email' AND status = 'failed' AND tries < 5 AND updated_at < ?", [$r['tenant'], date('Y-m-d H:i:s', time() - 900)]) as $f) {
        $e = $L->sendEmail((int) $f['id']);
        echo date('c') . " {$r['tenant']} share retry {$f['id']} " . ($e ?? 'sent') . "\n";
    }
    if ($L->setting('backup_on') === '1' && (int) $db->val("SELECT COUNT(*) FROM sc_lab_backups WHERE tenant_id = ? AND kind = 'auto' AND DATE(created_at) = ?", [$r['tenant'], $today]) === 0 && date('H') >= '02') {
        $b = $L->makeBackup('auto', 'Scheduled');
        echo date('c') . " {$r['tenant']} backup " . $b['msg'] . "\n";
    }
}
