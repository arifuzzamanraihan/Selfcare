<?php
// Selfcare original code. Prints, per vendor, which invoice types, forms, reports, website sections and work lists its kind may use.
// Used by tests/isolation.py to know what a vendor must be refused. php bin/catalog-dump.php
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}
$app = new Selfcare\App(dirname(__DIR__));
$bill = new Selfcare\Billing($app->db);
foreach ($app->db->all('SELECT v.tenant_id, v.type_slug FROM sc_vendors v WHERE v.deleted_at IS NULL') as $v) {
    $type = $app->typeRow((string) $v['type_slug']);
    $tpl = Selfcare\VendorTypes::template($type);
    $cat = $bill->catalog((string) $v['type_slug'], (string) $tpl['family']);
    $ops = [];
    foreach (Selfcare\Ops::all() as $k => $m) {
        if (in_array($v['type_slug'], $m['types'], true)) {
            $ops[] = $k;
        }
    }
    echo json_encode([
        'tenant' => $v['tenant_id'], 'type' => $v['type_slug'], 'family' => $tpl['family'], 'people' => $tpl['people'] ?? '', 'team' => in_array('team', array_column($tpl['sections'], 'kind'), true),
        'invoices' => array_keys($cat['invoices']), 'forms' => array_keys($cat['forms']), 'reports' => Selfcare\Reports::forFamily((string) $tpl['family'], (string) $v['type_slug']),
        'sections' => array_column($tpl['sections'], 'key'), 'ops' => $ops, 'stock' => in_array($tpl['family'], ['pharmacy', 'bloodbank', 'equipment'], true),
    ]), "\n";
}
