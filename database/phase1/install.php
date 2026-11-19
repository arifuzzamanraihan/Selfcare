<?php
// Selfcare original code. Creates the phase 1 tables.
//   php database/phase1/install.php            runs legacy-core.sql (only if "users" is missing) and schema.sql
//   php database/phase1/install.php --fresh    drops every table in the configured database first (staging only)
declare(strict_types=1);

require dirname(__DIR__, 2) . '/core/Env.php';
require dirname(__DIR__, 2) . '/core/Database.php';
require dirname(__DIR__, 2) . '/core/VendorTypes.php';
require dirname(__DIR__, 2) . '/core/Plans.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Currency.php';

$env = new Selfcare\Env();
$env->load(dirname(__DIR__, 2) . '/.env');
$db = new Selfcare\Database($env);

$run = static function (string $file) use ($db): void {
    $sql = preg_replace('/^--.*$/m', '', (string) file_get_contents($file));
    foreach (preg_split('/;\s*\n/', (string) $sql) as $stmt) {
        $stmt = trim((string) $stmt);
        if ($stmt !== '') {
            $db->run($stmt);
        }
    }
    echo "ran " . basename($file) . "\n";
};

if (in_array('--fresh', $argv, true)) {
    $db->run('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($db->all('SHOW TABLES') as $r) {
        $db->run('DROP TABLE `' . array_values($r)[0] . '`');
    }
    $db->run('SET FOREIGN_KEY_CHECKS = 1');
    echo "dropped all tables\n";
}
$hasUsers = (bool) $db->all("SHOW TABLES LIKE 'users'");
if (!$hasUsers) {
    $run(__DIR__ . '/legacy-core.sql');
}
$run(__DIR__ . '/schema.sql');
$run(__DIR__ . '/migrate-r4.sql'); // idempotent: adds columns that older installs do not have
$mig = glob(__DIR__ . '/migrate-2*.sql') ?: []; // timestamped migrations from every builder, run in name order (all idempotent)
sort($mig);
foreach ($mig as $m) {
    $run($m);
}
Selfcare\Plans::seedDefaults($db);
echo "currencies added: " . Selfcare\Currency::seed($db) . "\n";
Selfcare\Currency::reset();
// clinic lab money rows that were written before branches had a currency are in the vendor's currency
foreach (['sc_lab_orders', 'sc_lab_order_items', 'sc_commissions', 'sc_lab_home', 'sc_staff_profiles', 'sc_salary', 'sc_salary_adj'] as $mt) {
    $db->run("UPDATE `$mt` x SET x.currency = COALESCE((SELECT s.`value` FROM settings s WHERE s.tenant_id = x.tenant_id AND s.`key` = 'currency' LIMIT 1), 'PKR') WHERE x.currency IS NULL");
}
// one time: vendors keep the currency they were showing, then the platform default becomes Pakistan (PKR)
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'currency_defaults_v2' LIMIT 1") !== '1') {
    echo "vendors pinned to their currency: " . Selfcare\Currency::pinExisting($db) . "\n";
    foreach (['platform_currency' => 'PKR', 'default_country' => 'Pakistan', 'default_timezone' => 'Asia/Karachi', 'currency_defaults_v2' => '1'] as $k => $v) {
        $id = $db->val("SELECT id FROM front_settings WHERE tenant_id IS NULL AND `key` = ? LIMIT 1", [$k]);
        $id ? $db->run("UPDATE front_settings SET `value` = ?, updated_at = NOW() WHERE id = ?", [$v, $id]) : $db->insert('front_settings', ['key' => $k, 'value' => $v, 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
// one time: the main website copy is organised around the real modules (and carries no invented testimonials)
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'site_copy_v2' LIMIT 1") !== '1') {
    $def = require __DIR__ . '/../../core/config/site.php';
    foreach (['features', 'steps', 'faq', 'testimonials'] as $sec) {
        $db->run('DELETE FROM sc_items WHERE tenant_id IS NULL AND section = ?', [$sec]);
        foreach ($def[$sec] ?? [] as $i => $r) {
            $db->insert('sc_items', ['tenant_id' => null, 'section' => $sec, 'title' => $r['title'], 'body' => $r['body'] ?? null, 'meta' => $r['meta'] ?? null, 'sort_order' => $i, 'is_active' => 1]);
        }
    }
    $db->insert('front_settings', ['key' => 'site_copy_v2', 'value' => '1', 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    echo "main website copy refreshed\n";
}
// one time: subscription plans that were priced in US dollars are priced in Pakistani Rupees (clean amounts, nearest 500)
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'plans_pkr_v1' LIMIT 1") !== '1') {
    $rate = Selfcare\Currency::rate('PKR');
    foreach ($db->all("SELECT id, price, price_year FROM sc_plans WHERE currency = 'USD'") as $pl) {
        $m = (float) $pl['price'];
        $mp = $m > 0 ? round($m * $rate / 500) * 500 : 0;
        $yp = $pl['price_year'] !== null && $m > 0 ? round($mp * ((float) $pl['price_year'] / $m)) : $pl['price_year'];
        $db->run("UPDATE sc_plans SET currency = 'PKR', price = ?, price_year = ? WHERE id = ?", [$mp, $yp, $pl['id']]);
    }
    $db->insert('front_settings', ['key' => 'plans_pkr_v1', 'value' => '1', 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
// one time: the public catalogue (three sizes per kind of provider, in Rs) replaces the old generic paid plans, which stay for vendors that use them but are switched off for new sign ups
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'plans_catalog_v1' LIMIT 1") !== '1') {
    echo "catalogue plans added: " . Selfcare\Plans::seedCatalogue($db) . "\n";
    $db->run("UPDATE sc_plans SET is_active = 0 WHERE (audience IS NULL OR audience = '') AND is_private = 0 AND name <> 'Free'");
    $db->run("UPDATE sc_plans SET features = 'Your own website and portal\nPatients, doctors and billing\nSelf Care badge', description = 'Try Self Care with a very small team. Data export always allowed.' WHERE name = 'Free' AND (features IS NULL OR features = '')");
    $db->insert('front_settings', ['key' => 'plans_catalog_v1', 'value' => '1', 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
// one time: market based prices, free trial, extra branch price and monthly email allowance
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'plans_market_v1' LIMIT 1") !== '1') {
    echo "catalogue plans updated: " . Selfcare\Plans::refreshCatalogue($db) . "\n";
    $db->insert('front_settings', ['key' => 'plans_market_v1', 'value' => '1', 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
echo "private plans added: " . Selfcare\Plans::seedUltimate($db) . "\n";
echo "vendor types added: " . Selfcare\VendorTypes::seed($db) . "\n";
echo "done\n";
