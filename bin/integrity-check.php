<?php
// Selfcare original code. Data integrity check: finds orphans, cross-vendor links and numbers that do not add up.
//   php bin/integrity-check.php          exit code 0 = clean, 1 = something is broken
// Read only. Run it after every deploy (deploy-live.sh does) and from cron if you like.
declare(strict_types=1);

foreach (['Env', 'Database', 'Router', 'View', 'helpers', 'Auth', 'Throttle', 'Mailer', 'Tenant', 'Site', 'Plans', 'Access', 'VendorTypes', 'Listing', 'Crud', 'Vendors', 'Domains', 'Currency', 'Charts', 'App', 'Portal', 'People', 'Billing', 'Pdf', 'Documents', 'Reports', 'Accounts', 'Rx', 'Ops', 'RoleUi'] as $f) {
    require_once dirname(__DIR__) . '/core/' . $f . '.php';
}
$app = new Selfcare\App(dirname(__DIR__));
$db = $app->db;
$fix = in_array('--fix-orphans', $argv ?? [], true);
if ($fix) {
    // only link rows that point to a user that no longer exists; never touches real records
    foreach (['staff_shifts', 'staff_access_windows', 'nurses', 'receptionists', 'pharmacists', 'accountants', 'lab_technicians', 'case_handlers'] as $t) {
        $n = $db->run("DELETE x FROM $t x LEFT JOIN users u ON u.id = x.user_id WHERE u.id IS NULL");
        echo $n ? "  fixed $t: $n orphan row(s) removed\n" : '';
    }
    $n = $db->run('DELETE m FROM model_has_roles m LEFT JOIN users u ON u.id = m.model_id WHERE u.id IS NULL AND m.model_type = ' . $db->quote(Selfcare\Auth::ROLE_MODEL));
    echo $n ? "  fixed model_has_roles: $n orphan row(s) removed\n" : '';
}
$bad = 0;
$warn = 0;
/** One check: the query must return the offending rows (id, why). */
$check = static function (string $name, string $sql, bool $warnOnly = false) use ($db, &$bad, &$warn): void {
    $rows = $db->all($sql);
    if (!$rows) {
        echo "  ok    $name\n";
        return;
    }
    $warnOnly ? $warn++ : $bad++;
    echo ($warnOnly ? '  WARN  ' : '  FAIL  ') . $name . ' (' . count($rows) . ")\n";
    foreach (array_slice($rows, 0, 5) as $r) {
        echo '        ' . json_encode($r) . "\n";
    }
};
echo "Self Care integrity check " . date('Y-m-d H:i:s') . "\n";

echo "vendors and tenants\n";
$check('every vendor has a tenant', 'SELECT v.tenant_id FROM sc_vendors v LEFT JOIN tenants t ON t.id = v.tenant_id WHERE t.id IS NULL');
$check('every tenant has a vendor row', "SELECT t.id FROM tenants t LEFT JOIN sc_vendors v ON v.tenant_id = t.id WHERE v.tenant_id IS NULL AND t.id <> 'main'");
$check('every vendor has a subscription', 'SELECT v.tenant_id FROM sc_vendors v LEFT JOIN sc_subscriptions s ON s.tenant_id = v.tenant_id WHERE s.id IS NULL AND v.deleted_at IS NULL');
$check('every live vendor has an admin user', 'SELECT v.tenant_id FROM sc_vendors v WHERE v.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.tenant_id = v.tenant_id AND u.is_admin_default = 1 AND u.deleted_at IS NULL)');
$check('vendor types exist', 'SELECT v.tenant_id, v.type_slug FROM sc_vendors v LEFT JOIN sc_vendor_types t ON t.slug = v.type_slug WHERE t.slug IS NULL');
$check('domains belong to a vendor', "SELECT d.id, d.tenant_id FROM sc_domains d LEFT JOIN sc_vendors v ON v.tenant_id = d.tenant_id WHERE v.tenant_id IS NULL AND d.status <> 'removed'");
$check('web addresses are unique', "SELECT host, COUNT(*) n FROM sc_domains WHERE status <> 'removed' GROUP BY host HAVING n > 1");

echo "people and access\n";
$check('users point to a real tenant', "SELECT u.id, u.tenant_id FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.tenant_id IS NOT NULL AND u.tenant_id <> 'main' AND t.id IS NULL");
$check('every user has exactly one role', 'SELECT u.id, COUNT(m.role_id) roles FROM users u LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ' . $db->quote(Selfcare\Auth::ROLE_MODEL) . ' WHERE u.deleted_at IS NULL GROUP BY u.id HAVING roles <> 1');
$check('role links point to a real role and user', 'SELECT m.model_id, m.role_id FROM model_has_roles m LEFT JOIN users u ON u.id = m.model_id LEFT JOIN departments d ON d.id = m.role_id WHERE m.model_type = ' . $db->quote(Selfcare\Auth::ROLE_MODEL) . ' AND (u.id IS NULL OR d.id IS NULL)');
foreach (['patients', 'doctors', 'nurses', 'receptionists', 'pharmacists', 'accountants', 'lab_technicians', 'case_handlers'] as $t) {
    $check("$t link to a user of the same vendor", "SELECT x.id FROM $t x LEFT JOIN users u ON u.id = x.user_id WHERE u.id IS NULL OR u.tenant_id <> x.tenant_id");
}
$check('shifts belong to staff of the same vendor', 'SELECT s.id FROM staff_shifts s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL OR u.tenant_id <> s.tenant_id');
$check('access windows belong to staff of the same vendor', 'SELECT s.id FROM staff_access_windows s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL OR u.tenant_id <> s.tenant_id');
$check('role switches belong to a vendor', 'SELECT r.tenant_id FROM tenant_roles r LEFT JOIN tenants t ON t.id = r.tenant_id WHERE t.id IS NULL');
$check('role permissions belong to a vendor', 'SELECT r.tenant_id FROM tenant_role_has_permissions r LEFT JOIN tenants t ON t.id = r.tenant_id WHERE t.id IS NULL');
$check('requests point to a doctor of the same vendor', 'SELECT r.id FROM sc_appointment_requests r LEFT JOIN doctors d ON d.id = r.doctor_id WHERE r.doctor_id IS NOT NULL AND (d.id IS NULL OR d.tenant_id <> r.tenant_id)');

echo "money\n";
$check('invoice lines have an invoice', 'SELECT i.id FROM sc_invoice_items i LEFT JOIN sc_invoices v ON v.id = i.invoice_id WHERE v.id IS NULL');
$check('payments have an invoice of the same vendor', 'SELECT p.id FROM sc_invoice_payments p LEFT JOIN sc_invoices v ON v.id = p.invoice_id WHERE v.id IS NULL OR v.tenant_id <> p.tenant_id');
$check('invoices point to a patient of the same vendor', 'SELECT i.id FROM sc_invoices i LEFT JOIN patients p ON p.id = i.patient_id WHERE i.patient_id IS NOT NULL AND (p.id IS NULL OR p.tenant_id <> i.tenant_id)');
$check('invoices point to a doctor of the same vendor', 'SELECT i.id FROM sc_invoices i LEFT JOIN doctors d ON d.id = i.doctor_id WHERE i.doctor_id IS NOT NULL AND (d.id IS NULL OR d.tenant_id <> i.tenant_id)');
$check('credit notes point to an invoice of the same vendor', 'SELECT i.id FROM sc_invoices i LEFT JOIN sc_invoices r ON r.id = i.related_id WHERE i.related_id IS NOT NULL AND (r.id IS NULL OR r.tenant_id <> i.tenant_id)');
$check('invoice lines use stock of the same vendor', 'SELECT l.id FROM sc_invoice_items l JOIN sc_invoices v ON v.id = l.invoice_id LEFT JOIN sc_stock_items s ON s.id = l.stock_item_id WHERE l.stock_item_id IS NOT NULL AND (s.id IS NULL OR s.tenant_id <> v.tenant_id)');
$check('paid = sum of payments', "SELECT i.id, i.paid, COALESCE(SUM(p.amount), 0) pays FROM sc_invoices i LEFT JOIN sc_invoice_payments p ON p.invoice_id = i.id WHERE i.status <> 'void' GROUP BY i.id HAVING ABS(i.paid - pays) > 0.01");
$check('balance = total - paid', "SELECT id, total, paid, balance FROM sc_invoices WHERE status <> 'void' AND doc_type NOT LIKE '%order%' AND ABS((total - paid) - balance) > 0.01 AND balance > 0", true);
$check('invoice total = lines - discount + tax', "SELECT i.id, i.total, ROUND(i.subtotal - i.discount + i.tax, 2) calc FROM sc_invoices i WHERE ABS(i.total - (i.subtotal - i.discount + i.tax)) > 0.02");
$check('documents point to a patient of the same vendor', 'SELECT d.id FROM sc_documents d LEFT JOIN patients p ON p.id = d.patient_id WHERE d.patient_id IS NOT NULL AND (p.id IS NULL OR p.tenant_id <> d.tenant_id)');
$check('invoice numbers are unique per vendor', "SELECT tenant_id, issuer, invoice_no, COUNT(*) n FROM sc_invoices GROUP BY tenant_id, issuer, invoice_no HAVING n > 1");

echo "stock\n";
$check('stock ledger lines have an item of the same vendor', 'SELECT m.id FROM sc_stock_moves m LEFT JOIN sc_stock_items s ON s.id = m.item_id WHERE s.id IS NULL OR s.tenant_id <> m.tenant_id');
$check('stock never below zero', 'SELECT id, name, quantity FROM sc_stock_items WHERE quantity < 0');
$check('stock ledger balance matches the item', 'SELECT s.id, s.name, s.quantity, m.balance FROM sc_stock_items s JOIN sc_stock_moves m ON m.id = (SELECT MAX(x.id) FROM sc_stock_moves x WHERE x.item_id = s.id) WHERE ABS(s.quantity - m.balance) > 0.01', true);

echo "work lists\n";
$mods = Selfcare\Ops::all();
$known = $db->quote(implode(',', array_keys($mods)));
$check('records belong to a vendor', 'SELECT r.id FROM sc_records r LEFT JOIN sc_vendors v ON v.tenant_id = r.tenant_id WHERE v.tenant_id IS NULL');
$check('records are for the right kind of vendor', "SELECT r.id, r.tenant_id, r.module FROM sc_records r JOIN sc_vendors v ON v.tenant_id = r.tenant_id WHERE FIND_IN_SET(r.module, $known) = 0");
foreach ($mods as $key => $m) {
    $st = implode(',', array_map([$db, 'quote'], array_keys($m['status'])));
    $types = implode(',', array_map([$db, 'quote'], $m['types']));
    $check("$key: only vendors of its kind", "SELECT r.id, r.tenant_id FROM sc_records r JOIN sc_vendors v ON v.tenant_id = r.tenant_id WHERE r.module = '$key' AND v.type_slug NOT IN ($types)");
    $check("$key: valid states", "SELECT id, status FROM sc_records WHERE module = '$key' AND status NOT IN ($st)");
}
if (isset($mods['units'])) {
    $check('blood units: expiry not before collection', "SELECT id FROM sc_records WHERE module = 'units' AND date2 < date1");
    $check('blood units: past expiry are marked expired', "SELECT id, code FROM sc_records WHERE module = 'units' AND deleted_at IS NULL AND status IN ('quarantine','available','reserved') AND date2 < CURDATE()", true);
    $check('blood units: issued ones name a recipient', "SELECT id FROM sc_records WHERE module = 'units' AND status = 'issued' AND (place_a IS NULL OR place_a = '')");
}
if (isset($mods['vehicles'])) {
    $check('trips on the road name a vehicle that exists', "SELECT t.id, t.cat FROM sc_records t LEFT JOIN sc_records v ON v.tenant_id = t.tenant_id AND v.module = 'vehicles' AND v.code = t.cat WHERE t.module = 'trips' AND t.status IN ('dispatched','onroute') AND t.deleted_at IS NULL AND v.id IS NULL");
    $check('a vehicle on a trip has a trip on the road', "SELECT v.id, v.code FROM sc_records v WHERE v.module = 'vehicles' AND v.status = 'on_trip' AND v.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM sc_records t WHERE t.tenant_id = v.tenant_id AND t.module = 'trips' AND t.cat = v.code AND t.status IN ('dispatched','onroute') AND t.deleted_at IS NULL)");
}
$check('records point to an invoice of the same vendor', 'SELECT r.id, r.invoice_id FROM sc_records r LEFT JOIN sc_invoices i ON i.id = r.invoice_id WHERE r.invoice_id IS NOT NULL AND (i.id IS NULL OR i.tenant_id <> r.tenant_id)');
$check('history lines belong to a record of the same vendor and list', 'SELECT e.id FROM sc_record_events e LEFT JOIN sc_records r ON r.id = e.record_id WHERE r.id IS NULL OR r.tenant_id <> e.tenant_id OR r.module <> e.module');
$check('every record has a history', 'SELECT r.id FROM sc_records r WHERE NOT EXISTS (SELECT 1 FROM sc_record_events e WHERE e.record_id = r.id)', true);
$check('plate numbers / codes are unique per vendor and list', "SELECT tenant_id, module, code, COUNT(*) n FROM sc_records WHERE code IS NOT NULL AND code <> '' AND deleted_at IS NULL GROUP BY tenant_id, module, code HAVING n > 1");
$check('records point to a patient of the same vendor', 'SELECT r.id FROM sc_records r LEFT JOIN patients p ON p.id = r.person_id WHERE r.person_id IS NOT NULL AND (p.id IS NULL OR p.tenant_id <> r.tenant_id)');
$check('records point to a doctor of the same vendor', 'SELECT r.id FROM sc_records r LEFT JOIN doctors d ON d.id = r.staff_id WHERE r.staff_id IS NOT NULL AND (d.id IS NULL OR d.tenant_id <> r.tenant_id)');

echo "accounts\n";
$check('ledger entries belong to a vendor', 'SELECT l.id FROM sc_ledger l LEFT JOIN sc_vendors v ON v.tenant_id = l.tenant_id WHERE v.tenant_id IS NULL');
$check('ledger: kind, amount and state are valid', "SELECT id FROM sc_ledger WHERE kind NOT IN ('expense','income') OR amount <= 0 OR status NOT IN ('paid','due') OR (kind = 'income' AND status = 'due')");
$check('ledger: paid has a payment time, due has none', "SELECT id, status FROM sc_ledger WHERE deleted_at IS NULL AND ((status = 'paid' AND paid_at IS NULL) OR (status = 'due' AND paid_at IS NOT NULL))");
$check('ledger: salary links to staff of the same vendor', 'SELECT l.id FROM sc_ledger l LEFT JOIN users u ON u.id = l.user_id WHERE l.user_id IS NOT NULL AND (u.id IS NULL OR u.tenant_id <> l.tenant_id)');
$check('ledger numbers are unique per vendor and kind', 'SELECT tenant_id, kind, code, COUNT(*) n FROM sc_ledger WHERE code IS NOT NULL GROUP BY tenant_id, kind, code HAVING n > 1');

echo "prescriptions\n";
$check('prescriptions belong to an existing vendor', 'SELECT r.id FROM sc_prescriptions r LEFT JOIN tenants t ON t.id = r.tenant_id WHERE t.id IS NULL');
$check('prescription patient is a patient of the same vendor', 'SELECT r.id FROM sc_prescriptions r LEFT JOIN patients p ON p.id = r.patient_id AND p.tenant_id = r.tenant_id WHERE r.patient_id IS NOT NULL AND p.id IS NULL');
$check('prescription doctor is a doctor of the same vendor', 'SELECT r.id FROM sc_prescriptions r LEFT JOIN doctors d ON d.id = r.doctor_id AND d.tenant_id = r.tenant_id WHERE r.doctor_id IS NOT NULL AND d.id IS NULL');
$check('medicine lines belong to a prescription of the same vendor', 'SELECT i.id FROM sc_prescription_items i LEFT JOIN sc_prescriptions r ON r.id = i.prescription_id AND r.tenant_id = i.tenant_id WHERE r.id IS NULL');
$check('prescription states are valid and issued ones carry the time', "SELECT id FROM sc_prescriptions WHERE status NOT IN ('draft','issued','cancelled') OR (status = 'issued' AND issued_at IS NULL) OR (status = 'cancelled' AND (cancel_reason IS NULL OR cancelled_at IS NULL))");
$check('issued prescriptions have at least one medicine line', "SELECT r.id FROM sc_prescriptions r WHERE r.status = 'issued' AND r.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM sc_prescription_items i WHERE i.prescription_id = r.id)");
$check('prescription copies point to a prescription of the same vendor', 'SELECT r.id FROM sc_prescriptions r LEFT JOIN sc_prescriptions o ON o.id = r.parent_id AND o.tenant_id = r.tenant_id WHERE r.parent_id IS NOT NULL AND o.id IS NULL');
$check('saved medicine sets belong to an existing vendor', 'SELECT s.id FROM sc_rx_templates s LEFT JOIN tenants t ON t.id = s.tenant_id WHERE t.id IS NULL');

echo "audit\n";
$check('audit lines belong to a vendor or the platform', "SELECT l.id FROM permission_change_log l LEFT JOIN tenants t ON t.id = l.tenant_id WHERE t.id IS NULL AND l.tenant_id NOT IN ('_platform', 'main')", true);

echo "\n" . ($bad === 0 ? 'RESULT: clean' : "RESULT: $bad problem(s)") . ($warn ? ", $warn warning(s)" : '') . "\n";
exit($bad === 0 ? 0 : 1);
