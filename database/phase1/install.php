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
Selfcare\Plans::seedDefaults($db);
echo "currencies added: " . Selfcare\Currency::seed($db) . "\n";
echo "vendor types added: " . Selfcare\VendorTypes::seed($db) . "\n";
echo "done\n";
