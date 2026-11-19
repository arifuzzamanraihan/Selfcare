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
// one time: vendors keep the currency they were showing, then the platform default becomes Pakistan (PKR)
if ((string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'currency_defaults_v2' LIMIT 1") !== '1') {
    echo "vendors pinned to their currency: " . Selfcare\Currency::pinExisting($db) . "\n";
    foreach (['platform_currency' => 'PKR', 'default_country' => 'Pakistan', 'default_timezone' => 'Asia/Karachi', 'currency_defaults_v2' => '1'] as $k => $v) {
        $id = $db->val("SELECT id FROM front_settings WHERE tenant_id IS NULL AND `key` = ? LIMIT 1", [$k]);
        $id ? $db->run("UPDATE front_settings SET `value` = ?, updated_at = NOW() WHERE id = ?", [$v, $id]) : $db->insert('front_settings', ['key' => $k, 'value' => $v, 'type' => 'text', 'tenant_id' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }
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
echo "vendor types added: " . Selfcare\VendorTypes::seed($db) . "\n";
echo "done\n";
