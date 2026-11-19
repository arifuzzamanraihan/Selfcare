<?php
// Selfcare original code. Retry job for the email queue: sends every message that failed and is due again.
//   */5 * * * * /usr/bin/php /path/to/bin/mail-queue.php >> /path/to/storage/mail-queue.log 2>&1
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}
$app = new Selfcare\App(dirname(__DIR__));
$n = $app->mail->flush(100);
$app->db->run("DELETE FROM sc_email_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$app->db->run("DELETE FROM sc_mail_queue WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
if ($n > 0) {
    echo date('c') . " mail queue: $n sent\n";
}
