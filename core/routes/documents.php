<?php
// Selfcare original routes: invoices and bills, printed forms (digitised hand-filled documents), reports, stock, bill settings.
// Same pattern as the other portal routes: Portal::boot, then need(permission, plan module). PDF = core/Documents.php + core/Pdf.php.
declare(strict_types=1);

use Selfcare\{Billing, Crud, Documents, Listing, Portal, Reports};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn] = $app->ui;
$billing = new Billing($app->db);
$cur = static fn (Portal $p): string => \Selfcare\Currency::forTenant($p->tenantId);
$back = static function (string $okMsg, string $to, ?string $err = null): void {
    if ($err !== null) {
        flash($err, 'bad');
        redirect($_SERVER['HTTP_REFERER'] ?? $to);
    }
    flash($okMsg, 'ok');
    redirect($to);
};
/** Invoice types of this vendor that the plan allows. @return array<string,array<string,mixed>> */
$invCatalog = static function (Portal $p) use ($billing): array {
    return array_filter($billing->catalog((string) $p->type['slug'], $p->family)['invoices'], static fn ($c, $k) => $p->module($billing->module($k)), ARRAY_FILTER_USE_BOTH);
};
/** Forms this role may fill in and the plan allows. @return array<string,array<string,mixed>> */
$formCatalog = static function (Portal $p) use ($billing): array {
    return array_filter($billing->catalog((string) $p->type['slug'], $p->family)['forms'], static fn ($c, $k) => $p->can((string) $c['perm']) && $p->module($billing->module($k)), ARRAY_FILTER_USE_BOTH);
};
$tiles = static function (array $items, callable $href, callable $sub): string {
    $h = '<div class="d-doclist">';
    foreach ($items as $k => $c) {
        $h .= '<a href="' . e($href($k)) . '"><span class="ic">' . icon('file', 22) . '</span><span><b>' . e($c['label']) . '</b><small>' . e($sub($k, $c)) . '</small></span></a>';
    }
    return $h . '</div>';
};
$field = static function (string $name, string $label, string $type = 'text', array $opt = [], string $val = '', bool $wide = false): string {
    $f = ['label' => $label, 'type' => $type, 'wide' => $wide];
    if ($type === 'select') {
        $f['options'] = array_combine($opt, $opt);
    }
    return Crud::field($name, $f, $val);
};

// ======================================================================= invoices
$app->router->get('/portal/billing', static function () use ($app, $dash, $card, $btn, $billing, $invCatalog, $tiles): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $cat = $invCatalog($p);
    $h = '';
    if ($p->writable() && $cat) {
        $h .= $card('Make a new document', $tiles($cat, fn ($k) => url('portal/billing/new/' . $k), fn ($k, $c) => ucfirst(['invoice' => 'Bill with payment', 'receipt' => 'Receipt', 'credit' => 'Credit or refund', 'order' => 'Order'][$c['kind']] ?? '')));
    }
    $labels = array_map(fn ($c) => $c['label'], $cat);
    $html = Listing::render($app->db, [
        'id' => 'invoices', 'select' => 'i.*', 'from' => 'sc_invoices i', 'where' => ["i.tenant_id = ?", "i.issuer = 'vendor'"], 'params' => [$p->tenantId], 'search' => ['i.invoice_no', 'i.party_name', 'i.party_phone', 'i.reference'], 'search_hint' => 'Search by number or name',
        'filters' => ['type' => ['label' => 'Type', 'sql' => 'i.doc_type = ?', 'options' => $labels], 'status' => ['label' => 'State', 'sql' => 'i.status = ?', 'options' => ['issued' => 'Unpaid', 'paid' => 'Paid', 'void' => 'Void']]],
        'columns' => ['invoice_no' => ['label' => 'Number', 'sort' => 'i.invoice_no', 'render' => fn ($r) => '<a href="' . e(url('portal/billing/' . $r['id'])) . '"><b>' . e($r['invoice_no']) . '</b></a>'], 'doc_type' => ['label' => 'Type', 'render' => fn ($r) => e((string) ($billing->config()['invoices'][$r['doc_type']]['label'] ?? $r['doc_type']))], 'party_name' => ['label' => 'Name', 'sort' => 'i.party_name'], 'invoice_date' => ['label' => 'Date', 'sort' => 'i.invoice_date', 'type' => 'date'], 'total' => ['label' => 'Total', 'sort' => 'i.total', 'type' => 'money', 'class' => 'r'], 'balance' => ['label' => 'Due', 'sort' => 'i.balance', 'type' => 'money', 'class' => 'r'], 'status' => ['label' => 'State', 'sort' => 'i.status', 'render' => fn ($r) => '<span class="d-badge is-' . ['paid' => 'ok', 'issued' => 'warn', 'void' => 'mute'][$r['status']] . '">' . e(['paid' => 'Paid', 'issued' => 'Unpaid', 'void' => 'Void'][$r['status']]) . '</span>']],
        'sort' => 'invoice_date', 'dir' => 'desc',
        'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => fn ($r) => url('portal/billing/' . $r['id'])], ['label' => 'PDF', 'icon' => 'file', 'url' => fn ($r) => url('portal/billing/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'No invoices yet', 'text' => 'Choose a document above to make the first one.'],
    ]);
    $dash($p, 'billing', 'Invoices and bills', $h . $card('All documents', $html), ['sub' => 'Bills, receipts and credit notes with your own numbers. Each one prints as a PDF.']);
});

/** Items that can be picked on an invoice line. @return list<array<string,string>> */
$picks = static function (Portal $p, array $cfg) use ($app): array {
    $out = [];
    if (!empty($cfg['stock'])) {
        foreach ($app->db->all('SELECT id, name, sku, sell_price, cost_price, quantity FROM sc_stock_items WHERE tenant_id = ? ORDER BY name LIMIT 400', [$p->tenantId]) as $s) {
            $out[] = ['name' => (string) $s['name'], 'price' => (string) ($cfg['stock'] === 'in' ? $s['cost_price'] : $s['sell_price']), 'cat' => 'Stock', 'stock' => (string) $s['id'], 'code' => (string) ($s['sku'] ?? ''), 'label' => $s['name'] . ' (' . rtrim(rtrim((string) $s['quantity'], '0'), '.') . ' in stock)'];
        }
        return $out;
    }
    $secs = array_values(array_filter(array_map(fn ($s) => $s['kind'] === 'cards' ? $s['key'] : null, $p->tpl['sections'])));
    if ($secs) {
        $in = implode(',', array_fill(0, count($secs), '?'));
        foreach ($app->db->all("SELECT title, meta, section FROM sc_items WHERE tenant_id = ? AND is_active = 1 AND section IN ($in) ORDER BY section, sort_order, title LIMIT 400", array_merge([$p->tenantId], $secs)) as $r) {
            $price = preg_match('/[0-9]+(?:\.[0-9]+)?/', str_replace(',', '', (string) $r['meta']), $m) ? $m[0] : '0';
            $out[] = ['name' => (string) $r['title'], 'price' => $price, 'cat' => (string) ($p->section((string) $r['section'])['label'] ?? ''), 'stock' => '', 'code' => '', 'label' => $r['title']];
        }
    }
    return $out;
};

$itemRow = static function (array $cfg, array $picks, array $it = []): string {
    $v = static fn (string $k, string $d = '') => e((string) ($it[$k] ?? $d));
    $opts = '<option value="">Pick</option>';
    foreach ($picks as $pk) {
        $opts .= '<option value="1" data-name="' . e($pk['name']) . '" data-price="' . e($pk['price']) . '" data-cat="' . e($pk['cat']) . '" data-stock="' . e($pk['stock']) . '" data-code="' . e($pk['code']) . '">' . e($pk['label']) . '</option>';
    }
    return '<tr>' . '<td>' . ($picks ? '<select data-pick aria-label="Pick from your list">' . $opts . '</select>' : '') . '<input name="item_name[]" data-name placeholder="' . e((string) $cfg['item']) . '" value="' . $v('item_name') . '" maxlength="255">'
        . '<input type="hidden" name="code[]" data-code value="' . $v('code') . '"><input type="hidden" name="category[]" data-cat value="' . $v('category') . '"><input type="hidden" name="stock_item_id[]" data-stock value="' . $v('stock_item_id') . '"></td>'
        . '<td style="width:90px"><input class="num" type="number" step="0.01" min="0" name="qty[]" data-q value="' . $v('qty', '1') . '" aria-label="Quantity"></td>'
        . '<td style="width:120px"><input class="num" type="number" step="0.01" min="0" name="price[]" data-p value="' . $v('price', '0') . '" aria-label="Rate"></td>'
        . (!empty($cfg['tax']) ? '<td style="width:80px"><input class="num" type="number" step="0.01" min="0" max="100" name="tax_pct[]" data-t value="' . $v('tax_pct', '0') . '" aria-label="Tax percent"></td>' : '')
        . '<td style="width:110px;text-align:right" data-a>0.00</td><td style="width:40px"><button type="button" class="d-act" data-rm aria-label="Remove line">' . icon('x', 14) . '</button></td></tr>';
};

$newInvoice = static function (array $a) use ($app, $dash, $card, $billing, $invCatalog, $picks, $itemRow, $field, $cur): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $type = (string) $a['type'];
    $cat = $invCatalog($p);
    if (!isset($cat[$type])) {
        $app->error(404, 'This document is not used by your kind of organisation or plan.');
    }
    $cfg = $cat[$type];
    $kind = (string) $cfg['kind'];
    $currency = $cur($p);
    // a bill made from a work-list record (trip, sample, order): fill in what we already know
    $pre = [];
    $rr = null;
    if (!empty($_GET['rec']) && !empty($_GET['mod'])) {
        $om = \Selfcare\Ops::find($p, (string) $_GET['mod']);
        $rr = $om !== null && !empty($om['bill']) && $om['bill']['type'] === $type ? $app->db->one('SELECT * FROM sc_records WHERE id = ? AND tenant_id = ? AND module = ? AND deleted_at IS NULL AND invoice_id IS NULL', [(int) $_GET['rec'], $p->tenantId, (string) $_GET['mod']]) : null;
        $pre = $rr !== null ? \Selfcare\Ops::billPrefill($om, $rr) : [];
    }
    // a bill for a prescription: the patient, the doctor and the prescription number
    if (!empty($_GET['rx']) && $rr === null) {
        $xr = (new \Selfcare\Rx($app->db))->find($p->tenantId, (int) $_GET['rx']);
        if ($xr !== null && $xr['deleted_at'] === null) {
            $pre = ['party_name' => (string) $xr['patient_name'], 'party_phone' => (string) $xr['patient_phone'], 'patient_id' => (string) $xr['patient_id'], 'doctor_id' => (string) $xr['doctor_id'], 'reference' => (string) $xr['rx_no']];
        }
    }
    $h = '<form class="d-form" method="post" action="' . e(url('portal/billing/save')) . '" data-invoice data-currency="' . e($currency) . '" autocomplete="off">' . csrf_field() . '<input type="hidden" name="doc_type" value="' . e($type) . '">' . ($rr !== null ? '<input type="hidden" name="rec" value="' . (int) $rr['id'] . '"><input type="hidden" name="mod" value="' . e((string) $rr['module']) . '">' : '') . '<div class="d-form-grid">';
    $h .= $field('party_name', (string) $cfg['party'] . ' name', 'text', [], old('party_name', (string) ($pre['party_name'] ?? '')), false);
    $h .= $field('party_phone', 'Phone', 'tel', [], old('party_phone', (string) ($pre['party_phone'] ?? '')));
    $h .= $field('party_address', 'Address', 'text', [], old('party_address'), true);
    if ($p->can('manage_patients') && $cfg['party'] === 'Patient') {
        $pts = $app->db->all('SELECT p.id, u.first_name, u.last_name, p.patient_unique_id FROM patients p JOIN users u ON u.id = p.user_id WHERE p.tenant_id = ? ORDER BY u.first_name LIMIT 300', [$p->tenantId]);
        if ($pts) {
            $h .= Crud::field('patient_id', ['label' => 'Registered patient (optional)', 'type' => 'select', 'options' => array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => trim($r['first_name'] . ' ' . $r['last_name']) . ' (' . $r['patient_unique_id'] . ')'], $pts), 'v', 'k')], old('patient_id', (string) ($pre['patient_id'] ?? '')));
        }
    }
    if (!empty($cfg['doctor'])) {
        $docs = $app->db->all('SELECT d.id, u.first_name, u.last_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? ORDER BY u.first_name', [$p->tenantId]);
        if ($docs) {
            $h .= Crud::field('doctor_id', ['label' => 'Doctor (for doctor-wise reports)', 'type' => 'select', 'options' => array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => 'Dr. ' . $r['first_name'] . ' ' . $r['last_name']], $docs), 'v', 'k')], old('doctor_id', (string) ($pre['doctor_id'] ?? '')));
        }
    }
    $h .= $field('invoice_date', 'Date', 'date', [], old('invoice_date', date('Y-m-d')));
    if ($kind === 'invoice' || $kind === 'order') {
        $h .= $field('due_date', 'Due date (optional)', 'date', [], old('due_date'));
    }
    foreach ($cfg['x'] as $x) {
        $h .= $field('x_' . $x[0], (string) $x[1], $x[2] ?? 'text', $x[3] ?? [], old('x_' . $x[0], (string) ($pre['x_' . $x[0]] ?? '')), ($x[2] ?? '') === 'textarea');
    }
    if ($kind === 'credit') {
        $inv = $app->db->all("SELECT id, invoice_no, party_name, balance FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND status <> 'void' AND doc_type NOT IN ('credit_note','refund_receipt','sales_return','advance_receipt') ORDER BY id DESC LIMIT 80", [$p->tenantId]);
        $h .= Crud::field('related_id', ['label' => 'Against invoice (optional)', 'type' => 'select', 'options' => array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['invoice_no'] . ' - ' . $r['party_name']], $inv), 'v', 'k'), 'help' => 'The credit reduces the amount still due on that invoice.'], old('related_id', (string) ($_GET['against'] ?? '')));
    }
    $h .= '</div><h3 style="margin-top:24px">' . e(ucfirst((string) $cfg['item'])) . ' lines</h3><div class="d-table-wrap" style="overflow:visible"><table class="d-items"><thead><tr><th>' . e((string) $cfg['item']) . '</th><th>Qty</th><th>Rate</th>' . (!empty($cfg['tax']) ? '<th>Tax %</th>' : '') . '<th style="text-align:right">Amount</th><th></th></tr></thead><tbody data-rows>' . $itemRow($cfg, $picks($p, $cfg), $pre['item'] ?? []) . '</tbody></table></div>';
    $h .= '<template>' . $itemRow($cfg, $picks($p, $cfg)) . '</template><p style="margin-top:10px"><button type="button" class="d-btn d-btn-soft" data-add>' . icon('plus', 14) . ' Add a line</button></p>';
    $h .= '<div class="d-form-grid" style="margin-top:14px">';
    if (!empty($cfg['discount'])) {
        $h .= Crud::field('discount_pct', ['label' => 'Discount %', 'type' => 'number', 'step' => '0.01', 'min' => 0], old('discount_pct', '0')) . Crud::field('discount', ['label' => 'Discount amount', 'type' => 'number', 'step' => '0.01', 'min' => 0], old('discount', '0'));
    }
    if ($kind === 'invoice') {
        $h .= Crud::field('pay_amount', ['label' => 'Paid now (leave 0 for unpaid)', 'type' => 'number', 'step' => '0.01', 'min' => 0], old('pay_amount', '0'));
    }
    if ($kind !== 'order') {
        $h .= Crud::field('payment_method', ['label' => $kind === 'credit' ? 'Refunded by' : 'Payment method', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true], old('payment_method', 'Cash')) . $field('payment_ref', 'Payment reference (cheque, transaction id)', 'text', [], old('payment_ref'));
    }
    $h .= $field('reference', 'Your reference', 'text', [], old('reference', (string) ($pre['reference'] ?? ''))) . $field('note', 'Note (printed)', 'text', [], old('note'), true) . '</div>';
    $h .= '<div class="d-totals"><div><span>Subtotal</span><b data-out="sub">0.00</b></div>' . (!empty($cfg['discount']) ? '<div><span>Discount</span><b data-out="disc">0.00</b></div>' : '') . (!empty($cfg['tax']) ? '<div><span>Tax</span><b data-out="tax">0.00</b></div>' : '') . '<div class="big"><span>Total</span><b data-out="total">0.00</b></div>' . ($kind === 'invoice' ? '<div><span>Balance due</span><b data-out="due">0.00</b></div>' : '') . '</div>';
    $h .= '<div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save and open ' . e(strtolower((string) $cfg['label'])) . '</button><a class="d-btn d-btn-soft" href="' . e(url('portal/billing')) . '">Cancel</a></div></form>';
    clear_old();
    $dash($p, 'billing', 'New ' . strtolower((string) $cfg['label']), $card('', $h), ['sub' => 'Number: ' . $billing->prefix($p->tenantId, $type) . '(next free number). You can change the prefix under Bills and legal details.']);
};
$app->router->get('/portal/billing/new/{type}', $newInvoice);

$app->router->post('/portal/billing/save', static function () use ($app, $billing, $invCatalog, $back, $cur): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $p->needWritable();
    $type = (string) ($_POST['doc_type'] ?? '');
    $cat = $invCatalog($p);
    if (!isset($cat[$type])) {
        $app->error(404, 'This document is not used by your kind of organisation or plan.');
    }
    $items = [];
    foreach ((array) ($_POST['item_name'] ?? []) as $i => $n) {
        $items[] = ['item_name' => $n, 'qty' => $_POST['qty'][$i] ?? 0, 'price' => $_POST['price'][$i] ?? 0, 'tax_pct' => $_POST['tax_pct'][$i] ?? 0, 'code' => $_POST['code'][$i] ?? '', 'category' => $_POST['category'][$i] ?? '', 'stock_item_id' => $_POST['stock_item_id'][$i] ?? ''];
    }
    $in = $_POST;
    $sub = 0.0;
    foreach ($items as $it) {
        $sub += max(0, (float) $it['qty']) * max(0, (float) $it['price']);
    }
    $in['discount'] = round($sub * max(0, (float) ($_POST['discount_pct'] ?? 0)) / 100 + max(0, (float) ($_POST['discount'] ?? 0)), 2);
    $in['paid_now'] = $_POST['pay_amount'] ?? 0;
    if (!empty($in['patient_id']) && !$app->db->val('SELECT COUNT(*) FROM patients WHERE id = ? AND tenant_id = ?', [(int) $in['patient_id'], $p->tenantId])) {
        $in['patient_id'] = null;
    }
    if (!empty($in['doctor_id']) && !$app->db->val('SELECT COUNT(*) FROM doctors WHERE id = ? AND tenant_id = ?', [(int) $in['doctor_id'], $p->tenantId])) {
        $in['doctor_id'] = null;
    }
    if (!empty($in['related_id']) && !$app->db->val('SELECT COUNT(*) FROM sc_invoices WHERE id = ? AND tenant_id = ?', [(int) $in['related_id'], $p->tenantId])) {
        $in['related_id'] = null;
    }
    try {
        $id = $billing->create($p->tenantId, 'vendor', $type, $in, $items, (int) $p->user['id'], $cur($p));
    } catch (\InvalidArgumentException $e) {
        keep_old($_POST);
        $back('', url('portal/billing/new/' . $type), $e->getMessage());
    }
    clear_old();
    if (!empty($_POST['rec']) && !empty($_POST['mod'])) {
        $om = \Selfcare\Ops::find($p, (string) $_POST['mod']);
        $rr = $om !== null && !empty($om['bill']) && $om['bill']['type'] === $type ? $app->db->one('SELECT id, status FROM sc_records WHERE id = ? AND tenant_id = ? AND module = ? AND deleted_at IS NULL AND invoice_id IS NULL', [(int) $_POST['rec'], $p->tenantId, (string) $_POST['mod']]) : null;
        if ($rr !== null) {
            $app->db->run('UPDATE sc_records SET invoice_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [$id, $rr['id'], $p->tenantId]);
            \Selfcare\Ops::event($app->db, $p->tenantId, (int) $rr['id'], (string) $_POST['mod'], 'billed', (string) $rr['status'], (string) $rr['status'], (int) $p->user['id'], 'Invoice ' . (string) $app->db->val('SELECT invoice_no FROM sc_invoices WHERE id = ?', [$id]));
        }
    }
    $back('Saved.', url('portal/billing/' . $id));
});

$loadInvoice = static function (Portal $p, array $a) use ($app, $billing): array {
    $inv = $billing->find((int) $a['id'], $p->tenantId);
    if ($inv === null) {
        $app->error(404, 'We could not find that document.');
    }
    return $inv;
};
$app->router->get('/portal/billing/{id}', static function (array $a) use ($app, $dash, $card, $btn, $billing, $loadInvoice): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $inv = $loadInvoice($p, $a);
    $cfg = $billing->config()['invoices'][$inv['doc_type']];
    $bar = '<div class="dx-bar"><a class="d-btn d-btn-primary" href="' . e(url('portal/billing/' . $inv['id'] . '/pdf')) . '">' . icon('file', 16) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button><a class="d-btn d-btn-soft" href="' . e(url('portal/billing')) . '">Back to list</a>';
    if ($inv['status'] !== 'void' && $cfg['kind'] === 'invoice' && $p->writable() && isset($billing->catalog((string) $p->type['slug'], $p->family)['invoices']['credit_note'])) {
        $bar .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/billing/new/credit_note', ['against' => $inv['id']])) . '">Make a credit note</a>';
    }
    $bar .= '</div>';
    $extra = '';
    if ($p->writable() && $inv['status'] !== 'void') {
        if ($cfg['kind'] === 'invoice' && (float) $inv['balance'] > 0) {
            $extra .= $card('Record a payment (due ' . Billing::fmt((float) $inv['balance'], (string) $inv['currency']) . ')', '<form class="d-form" method="post" action="' . e(url('portal/billing/' . $inv['id'] . '/pay')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('amount', ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true], (string) $inv['balance']) . Crud::field('method', ['label' => 'Method', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true], 'Cash') . Crud::field('reference', ['label' => 'Reference', 'max' => 120], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save payment</button></div></form>');
        }
        $extra .= $card('Cancel this document', '<form class="d-form" method="post" action="' . e(url('portal/billing/' . $inv['id'] . '/void')) . '" data-confirm="Cancel (void) this document? Stock is put back and it stays in the list as VOID.">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('reason', ['label' => 'Reason', 'required' => true, 'max' => 200], '') . '</div><div class="d-form-actions"><button class="d-btn d-btn-danger" type="submit">Void document</button></div></form>');
    }
    $dash($p, 'billing', (string) $inv['invoice_no'], $bar . Documents::html($billing->invoiceModel($app, $inv, $p->user)) . $extra);
});
$app->router->get('/portal/billing/{id}/pdf', static function (array $a) use ($app, $billing, $loadInvoice): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $inv = $loadInvoice($p, $a);
    Documents::send($billing->invoiceModel($app, $inv, $p->user), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $inv['invoice_no']) . '.pdf');
});
$app->router->post('/portal/billing/{id}/pay', static function (array $a) use ($app, $billing, $loadInvoice, $back): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $p->needWritable();
    $inv = $loadInvoice($p, $a);
    $err = $billing->addPayment($p->tenantId, (int) $inv['id'], (float) ($_POST['amount'] ?? 0), (string) ($_POST['method'] ?? 'Cash'), trim((string) ($_POST['reference'] ?? '')) ?: null, (int) $p->user['id']);
    $back('Payment saved.', url('portal/billing/' . $inv['id']), $err);
});
$app->router->post('/portal/billing/{id}/void', static function (array $a) use ($app, $billing, $loadInvoice, $back): void {
    $p = Portal::boot($app);
    $p->need('manage_invoices', 'billing');
    $p->needWritable();
    $inv = $loadInvoice($p, $a);
    $err = $billing->void($p->tenantId, (int) $inv['id'], (string) ($_POST['reason'] ?? ''));
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'invoice_voided', $inv['invoice_no'], (string) ($_POST['reason'] ?? ''));
    $back('Document voided.', url('portal/billing/' . $inv['id']), $err);
});

// ======================================================================= printed forms
$app->router->get('/portal/documents', static function () use ($app, $dash, $card, $formCatalog, $tiles): void {
    $p = Portal::boot($app);
    $cat = $formCatalog($p);
    if (!$cat || !$p->module('documents')) {
        $app->error(403, 'Your role or plan has no forms to fill in.', 'No access');
    }
    $h = $card('Fill in a form on screen', $tiles($cat, fn ($k) => url('portal/documents/' . $k . '/new'), fn ($k, $c) => 'Saved with a number, prints as PDF'));
    $reg = '';
    foreach ($cat as $k => $c) {
        $n = (int) $app->db->val('SELECT COUNT(*) FROM sc_documents WHERE tenant_id = ? AND doc_type = ?', [$p->tenantId, $k]);
        $reg .= '<tr><td data-label="Form"><b>' . e($c['label']) . '</b></td><td data-label="Saved">' . $n . '</td><td class="d-actions" data-label="Actions"><a class="d-act" href="' . e(url('portal/documents/' . $k)) . '">' . icon('eye', 14) . '<span>Register</span></a><a class="d-act" href="' . e(url('portal/documents/' . $k . '/blank')) . '">' . icon('file', 14) . '<span>Blank PDF to hand-write</span></a></td></tr>';
    }
    $h .= $card('Registers and blank forms', '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Form</th><th>Saved</th><th>Actions</th></tr></thead><tbody>' . $reg . '</tbody></table></div>');
    $dash($p, 'documents', 'Forms and records', $h, ['sub' => 'What used to be filled in by hand. Fill it in here, print it, or download a PDF.']);
});

$formGuard = static function (Portal $p, string $type) use ($app, $formCatalog): array {
    $cat = $formCatalog($p);
    if (!isset($cat[$type])) {
        $app->error(404, 'This form is not available for your role, plan or kind of organisation.');
    }
    return $cat[$type];
};
$app->router->get('/portal/documents/{type}', static function (array $a) use ($app, $dash, $card, $btn, $formGuard): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    $type = (string) $a['type'];
    $cols = ['doc_no' => ['label' => 'Number', 'sort' => 'd.doc_no', 'render' => fn ($r) => '<a href="' . e(url('portal/documents/' . $type . '/' . $r['id'])) . '"><b>' . e($r['doc_no']) . '</b></a>'], 'party_name' => ['label' => (string) $form['party'], 'sort' => 'd.party_name'], 'doc_date' => ['label' => 'Date', 'sort' => 'd.doc_date', 'type' => 'date']];
    foreach (($form['register'] ?? []) as $k => $lab) {
        $cols['f_' . $k] = ['label' => (string) $lab, 'render' => function ($r) use ($k) { $d = $r['data'] ? json_decode((string) $r['data'], true) : []; return e((string) ($d[$k] ?? '')); }];
    }
    $cols['status'] = ['label' => 'State', 'render' => fn ($r) => $r['status'] === 'void' ? '<span class="d-badge is-mute">Void</span>' : '<span class="d-badge is-ok">Saved</span>'];
    $html = Listing::render($app->db, ['id' => 'docs', 'select' => 'd.*', 'from' => 'sc_documents d', 'where' => ['d.tenant_id = ?', 'd.doc_type = ?'], 'params' => [$p->tenantId, $type], 'search' => ['d.doc_no', 'd.party_name', 'd.party_phone'], 'search_hint' => 'Search by number or name', 'columns' => $cols, 'sort' => 'doc_date', 'dir' => 'desc',
        'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => fn ($r) => url('portal/documents/' . $type . '/' . $r['id'])], ['label' => 'PDF', 'icon' => 'file', 'url' => fn ($r) => url('portal/documents/' . $type . '/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'Nothing saved yet', 'text' => 'Fill in the first one. You can also download a blank form to write by hand.', 'cta' => ['label' => 'Fill in now', 'url' => url('portal/documents/' . $type . '/new')]]]);
    $dash($p, 'documents', (string) $form['label'] . ' register', $card('', $html), ['actions' => ($p->writable() ? $btn('Fill in a new one', url('portal/documents/' . $type . '/new')) . ' ' : '') . $btn('Blank PDF', url('portal/documents/' . $type . '/blank'), 'd-btn-soft')]);
});

$formInputs = static function (Portal $p, string $type, array $form, array $data) use ($app, $field): string {
    $h = '';
    foreach ($form['sections'] as $si => $sec) {
        $h .= '<fieldset class="d-fieldset"><legend>' . e((string) $sec['t']) . '</legend>';
        if (isset($sec['rows'])) {
            $id = 'tbl_' . $si;
            $row = static function (array $r) use ($sec): string {
                $c = '<tr>';
                foreach ($sec['cols'] as $col) {
                    $c .= '<td><input name="r[' . e($sec['rows']) . '][' . e($col[0]) . '][]" value="' . e((string) ($r[$col[0]] ?? '')) . '" maxlength="300" aria-label="' . e($col[1]) . '"></td>';
                }
                return $c . '</tr>';
            };
            $h .= '<div class="d-table-wrap" style="overflow:visible"><table class="d-items" id="' . $id . '"><thead><tr>' . implode('', array_map(fn ($c) => '<th>' . e($c[1]) . '</th>', $sec['cols'])) . '</tr></thead><tbody>';
            $existing = (array) ($data[$sec['rows']] ?? []);
            $n = max(count($existing), 2);
            for ($i = 0; $i < $n; $i++) {
                $h .= $row((array) ($existing[$i] ?? []));
            }
            $h .= '</tbody><template>' . $row([]) . '</template></table></div><p style="margin-top:8px"><button type="button" class="d-btn d-btn-soft" data-addrow="' . $id . '">' . icon('plus', 14) . ' Add a row</button></p>';
        } else {
            $h .= '<div class="d-form-grid">';
            foreach ($sec['f'] as $f) {
                $opts = $f[3] ?? [];
                if ($type === 'free_service_delivery' && $f[0] === 'service') {
                    $fs = array_column($app->db->all('SELECT title FROM free_services WHERE tenant_id = ? AND is_active = 1 ORDER BY title', [$p->tenantId]), 'title');
                    $f[2] = $fs ? 'select' : 'text';
                    $opts = $fs;
                }
                $h .= $field('f_' . $f[0], (string) $f[1], (string) ($f[2] ?? 'text'), $opts, (string) ($data[$f[0]] ?? ''), ($f[2] ?? '') === 'textarea');
            }
            $h .= '</div>';
        }
        $h .= '</fieldset>';
    }
    return $h;
};
$app->router->get('/portal/documents/{type}/new', static function (array $a) use ($app, $dash, $card, $formGuard, $formInputs, $field): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    $type = (string) $a['type'];
    $h = '<form class="d-form" method="post" action="' . e(url('portal/documents/' . $type . '/save')) . '" autocomplete="off">' . csrf_field() . '<div class="d-form-grid">'
        . $field('party_name', (string) $form['party'] . ' name', 'text', [], old('party_name')) . $field('party_phone', 'Phone', 'tel', [], old('party_phone')) . $field('doc_date', 'Date', 'date', [], old('doc_date', date('Y-m-d'))) . $field('patient_ref', 'Patient ID / file no', 'text', [], old('patient_ref')) . '</div>'
        . $formInputs($p, $type, $form, []) . '<div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save and open</button><a class="d-btn d-btn-soft" href="' . e(url('portal/documents')) . '">Cancel</a></div></form>';
    clear_old();
    $dash($p, 'documents', (string) $form['label'], $card('', $h), ['sub' => 'Fill in what applies. Empty fields stay blank on the printout.']);
});
$app->router->post('/portal/documents/{type}/save', static function (array $a) use ($app, $billing, $formGuard, $back): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    $type = (string) $a['type'];
    $p->needWritable();
    $name = trim((string) ($_POST['party_name'] ?? ''));
    $date = (string) ($_POST['doc_date'] ?? '');
    if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        keep_old($_POST);
        $back('', url('portal/documents/' . $type . '/new'), 'Please enter the ' . strtolower((string) $form['party']) . ' name and a date.');
    }
    $data = ['patient_ref' => mb_substr(trim((string) ($_POST['patient_ref'] ?? '')), 0, 60)];
    foreach ($form['sections'] as $sec) {
        if (isset($sec['rows'])) {
            $rows = [];
            $cols = (array) ($_POST['r'][$sec['rows']] ?? []);
            $n = max(array_map('count', array_map('array_values', array_filter($cols, 'is_array'))) ?: [0]);
            for ($i = 0; $i < $n; $i++) {
                $r = [];
                foreach ($sec['cols'] as $c) {
                    $r[$c[0]] = mb_substr(trim((string) ($cols[$c[0]][$i] ?? '')), 0, 300);
                }
                if (implode('', $r) !== '') {
                    $rows[] = $r;
                }
            }
            $data[$sec['rows']] = $rows;
            continue;
        }
        foreach ($sec['f'] as $f) {
            $v = trim((string) ($_POST['f_' . $f[0]] ?? ''));
            if (($f[2] ?? 'text') === 'select' && $v !== '' && !empty($f[3]) && !in_array($v, $f[3], true)) {
                $v = '';
            }
            $data[$f[0]] = mb_substr($v, 0, ($f[2] ?? '') === 'textarea' ? 4000 : 300);
        }
    }
    $no = $billing->nextNo($p->tenantId, $type);
    $now = date('Y-m-d H:i:s');
    $id = $app->db->insert('sc_documents', ['tenant_id' => $p->tenantId, 'doc_type' => $type, 'doc_no' => $no, 'doc_date' => $date, 'party_name' => mb_substr($name, 0, 190), 'party_phone' => mb_substr(trim((string) ($_POST['party_phone'] ?? '')), 0, 40) ?: null, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'status' => 'final', 'created_by' => (int) $p->user['id'], 'created_at' => $now, 'updated_at' => $now]);
    clear_old();
    $back('Saved as ' . $no . '.', url('portal/documents/' . $type . '/' . $id));
});
$app->router->get('/portal/documents/{type}/blank', static function (array $a) use ($app, $billing, $formGuard): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    Documents::send($billing->formModel($app, $p->tenantId, $form, null, $p->user), 'blank-' . $a['type'] . '.pdf');
});
$loadDoc = static function (Portal $p, array $a) use ($app): array {
    $d = $app->db->one('SELECT * FROM sc_documents WHERE id = ? AND tenant_id = ? AND doc_type = ?', [(int) $a['id'], $p->tenantId, (string) $a['type']]);
    if ($d === null) {
        $app->error(404, 'We could not find that record.');
    }
    return $d;
};
$app->router->get('/portal/documents/{type}/{id}', static function (array $a) use ($app, $dash, $card, $billing, $formGuard, $loadDoc): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    $d = $loadDoc($p, $a);
    $bar = '<div class="dx-bar"><a class="d-btn d-btn-primary" href="' . e(url('portal/documents/' . $a['type'] . '/' . $d['id'] . '/pdf')) . '">' . icon('file', 16) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button><a class="d-btn d-btn-soft" href="' . e(url('portal/documents/' . $a['type'])) . '">Back to register</a></div>';
    $extra = $p->writable() && $d['status'] !== 'void' ? $card('Cancel this record', '<form class="d-form" method="post" action="' . e(url('portal/documents/' . $a['type'] . '/' . $d['id'] . '/void')) . '" data-confirm="Mark this record as void? It stays in the register.">' . csrf_field() . '<button class="d-btn d-btn-danger" type="submit">Void record</button></form>') : '';
    $dash($p, 'documents', (string) $d['doc_no'], $bar . Documents::html($billing->formModel($app, $p->tenantId, $form, $d, $p->user)) . $extra);
});
$app->router->get('/portal/documents/{type}/{id}/pdf', static function (array $a) use ($app, $billing, $formGuard, $loadDoc): void {
    $p = Portal::boot($app);
    $form = $formGuard($p, (string) $a['type']);
    $d = $loadDoc($p, $a);
    Documents::send($billing->formModel($app, $p->tenantId, $form, $d, $p->user), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $d['doc_no']) . '.pdf');
});
$app->router->post('/portal/documents/{type}/{id}/void', static function (array $a) use ($app, $formGuard, $loadDoc, $back): void {
    $p = Portal::boot($app);
    $formGuard($p, (string) $a['type']);
    $p->needWritable();
    $d = $loadDoc($p, $a);
    $app->db->run("UPDATE sc_documents SET status = 'void', updated_at = NOW() WHERE id = ?", [(int) $d['id']]);
    $back('Record voided.', url('portal/documents/' . $a['type'] . '/' . $d['id']));
});

// ======================================================================= reports
$reportList = static function (Portal $p) use ($billing): array {
    $out = [];
    foreach (Reports::forFamily($p->family, (string) $p->type['slug']) as $k) {
        $out[$k] = Reports::META[$k];
    }
    foreach ($billing->catalog((string) $p->type['slug'], $p->family)['forms'] as $k => $f) {
        if (!empty($f['register']) && $p->can((string) $f['perm'])) {
            $out['reg-' . $k] = [$f['label'] . ' register', 'Every ' . strtolower((string) $f['label']) . ' saved in the period.', 'sc_documents'];
        }
    }
    return $out;
};
$filters = static function (): array {
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    $ok = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $from = $ok($from) ? $from : date('Y-m-01');
    $to = $ok($to) ? $to : date('Y-m-d');
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }
    return ['from' => $from, 'to' => $to, 'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80)];
};
$app->router->get('/portal/reports', static function () use ($app, $dash, $card, $tiles, $reportList): void {
    $p = Portal::boot($app);
    $p->need('manage_reports', 'reports');
    $list = $reportList($p);
    $labels = array_map(fn ($m) => ['label' => $m[0]], $list);
    $dash($p, 'reports', 'Reports', $card('Choose a report', $tiles($labels, fn ($k) => url('portal/reports/' . $k), fn ($k, $c) => $list[$k][1])), ['sub' => 'Pick the dates, then read it on screen, print it, or download a PDF.']);
});
$app->router->get('/portal/reports/{key}', static function (array $a) use ($app, $dash, $card, $billing, $reportList, $filters, $cur): void {
    $p = Portal::boot($app);
    $p->need('manage_reports', 'reports');
    $key = (string) $a['key'];
    $list = $reportList($p);
    if (!isset($list[$key])) {
        $app->error(404, 'We could not find that report.');
    }
    $f = $filters();
    $rep = new Reports($app->db, $billing);
    $res = $rep->run($key, $p->tenantId, $f, $cur($p));
    if (($_GET['format'] ?? '') === 'pdf') {
        Documents::send($rep->model($app, $p->tenantId, $key, $list[$key][0], $res, $f, $p->user), $key . '-' . $f['from'] . '_' . $f['to'] . '.pdf');
    }
    $qs = http_build_query(array_filter($f));
    $form = '<form class="d-tools" method="get" action="' . e(url('portal/reports/' . $key)) . '"><label class="d-filter"><span>From</span><input class="d-in" type="date" name="from" value="' . e($f['from']) . '"></label><label class="d-filter"><span>To</span><input class="d-in" type="date" name="to" value="' . e($f['to']) . '"></label><label class="d-search">' . icon('search', 16) . '<input type="search" name="q" value="' . e($f['q']) . '" placeholder="Filter by name or number"></label><button class="d-btn d-btn-soft" type="submit">Show</button><span class="d-count"><a class="d-btn d-btn-primary" href="' . e(url('portal/reports/' . $key) . '?' . $qs . '&format=pdf') . '">' . icon('file', 14) . ' Download PDF</a> <button class="d-btn d-btn-soft" type="button" data-print>Print</button></span></form>';
    $sum = '<div class="d-stats">';
    foreach ($res['summary'] as $s) {
        $sum .= '<div class="d-stat"><span>' . e($s[0]) . '</span><b style="font-size:1.4rem">' . e($s[1]) . '</b></div>';
    }
    $sum .= '</div>';
    $t = '<div class="d-table-wrap"><table class="d-table"><thead><tr>';
    foreach ($res['cols'] as $c) {
        $t .= '<th' . (($c[2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . e($c[0]) . '</th>';
    }
    $t .= '</tr></thead><tbody>';
    foreach ($res['rows'] as $r) {
        $t .= '<tr>';
        foreach ($r as $i => $cell) {
            $t .= '<td data-label="' . e($res['cols'][$i][0]) . '"' . (($res['cols'][$i][2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . e((string) $cell) . '</td>';
        }
        $t .= '</tr>';
    }
    $t .= '</tbody></table></div>';
    if (!$res['rows']) {
        $t = '<div class="d-empty"><div class="d-empty-art">' . icon('chart', 28) . '</div><h3>Nothing in this period</h3><p>Try wider dates. Reports read what you saved under Invoices, Forms and Stock.</p></div>';
    }
    $dash($p, 'reports', $list[$key][0], $card('', $form) . $sum . $card('', $t), ['sub' => $list[$key][1]]);
});

// ======================================================================= bills and legal settings
$app->router->get('/portal/settings', static function () use ($app, $dash, $card, $billing, $invCatalog, $cur): void {
    $p = Portal::boot($app);
    $p->need('manage_settings');
    $t = $p->tenantId;
    $h = '<form class="d-form" method="post" action="' . e(url('portal/settings')) . '">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('currency', ['label' => 'Currency on bills', 'type' => 'select', 'options' => \Selfcare\Currency::options($cur($p)), 'required' => true, 'help' => 'Every amount in your portal, website, invoices, PDFs and reports uses this currency. Old invoices keep the currency they were made in.'], $cur($p))
        . Crud::field('doctor_commission_pct', ['label' => 'Doctor commission % (for the doctor report)', 'type' => 'number', 'step' => '0.01', 'min' => 0], $billing->setting($t, 'doctor_commission_pct', '0'))
        . Crud::field('bed_capacity', ['label' => 'Number of beds (for bed occupancy)', 'type' => 'number', 'min' => 0], $billing->setting($t, 'bed_capacity', '0'))
        . '</div><p class="d-help" style="margin-top:12px">The registration or licence number printed on every document is set under Profile and hours.</p><h3 style="margin-top:22px">Number prefixes</h3><p class="d-help">Each document type counts up from 1 on its own. The prefix is the letters in front, for example OPD-00042.</p><div class="d-form-grid">';
    foreach ($invCatalog($p) + $billing->catalog((string) $p->type['slug'], $p->family)['forms'] as $k => $c) {
        $h .= Crud::field('prefix_' . $k, ['label' => (string) $c['label'], 'max' => 12], $billing->prefix($t, $k));
    }
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit"' . ($p->writable() ? '' : ' disabled') . '>Save</button></div></form>';
    $dash($p, 'settings', 'Bills and legal details', $card('', $h));
});
$app->router->post('/portal/settings', static function () use ($app, $billing, $invCatalog, $back, $cur): void {
    $p = Portal::boot($app);
    $p->need('manage_settings');
    $p->needWritable();
    $t = $p->tenantId;
    if (isset(\Selfcare\Currency::all(true)[(string) ($_POST['currency'] ?? '')])) {
        $billing->setSetting($t, 'currency', (string) $_POST['currency']);
    }
    foreach (['doctor_commission_pct' => 100.0, 'bed_capacity' => 100000.0] as $k => $max) {
        $v = (float) ($_POST[$k] ?? 0);
        $billing->setSetting($t, $k, (string) max(0, min($max, $v)));
    }
    foreach ($invCatalog($p) + $billing->catalog((string) $p->type['slug'], $p->family)['forms'] as $k => $c) {
        $v = preg_replace('/[^A-Za-z0-9\-_\/]/', '', (string) ($_POST['prefix_' . $k] ?? '')) ?? '';
        if ($v !== '') {
            $billing->setSetting($t, 'invoice_prefix_' . $k, mb_substr($v, 0, 12));
        }
    }
    $back('Saved.', url('portal/settings'));
});

// ======================================================================= stock
$stockPrev = null;
$stockMod = static function (Portal $p) use (&$stockPrev, $app): array {
    return ['key' => 'stock', 'title' => 'Stock', 'singular' => 'Stock item', 'url' => 'portal/stock', 'table' => 'sc_stock_items', 'scope' => ['tenant_id' => $p->tenantId],
        'fields' => ['name' => ['label' => 'Name', 'required' => true, 'max' => 190, 'wide' => true], 'sku' => ['label' => 'Code / SKU', 'max' => 60], 'batch_no' => ['label' => 'Batch no', 'max' => 60], 'unit' => ['label' => 'Unit', 'max' => 20, 'placeholder' => 'strip, bag, unit'], 'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'null' => true], 'quantity' => ['label' => 'In stock', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], 'reorder_level' => ['label' => 'Reorder at', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], 'cost_price' => ['label' => 'Cost price', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], 'sell_price' => ['label' => 'Selling price', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0]],
        'columns' => ['name' => ['label' => 'Item'], 'sku' => ['label' => 'Code'], 'batch_no' => ['label' => 'Batch'], 'expiry_date' => ['label' => 'Expiry', 'type' => 'date'], 'quantity' => ['label' => 'In stock', 'class' => 'r'], 'reorder_level' => ['label' => 'Reorder at', 'class' => 'r'], 'sell_price' => ['label' => 'Price', 'type' => 'money', 'class' => 'r']],
        'search' => ['t.name', 't.sku', 't.batch_no'], 'sort' => 'name', 'empty' => 'Add the items you keep. Sales take stock out and purchases or goods received put it back in.',
        'extra_actions' => $p->writable() ? [['label' => 'Adjust', 'icon' => 'edit', 'url' => static fn ($r) => url('portal/stock/' . $r['id'] . '/adjust')]] : [],
        'before_save' => function (array $d, ?array $old) use (&$stockPrev) { $stockPrev = $old ? (float) $old['quantity'] : 0.0; $d['updated_at'] = date('Y-m-d H:i:s'); if (!$old) { $d['created_at'] = $d['updated_at']; } return $d; },
        'after_save' => function (int $id, array $d) use (&$stockPrev, $p, $app): void { $delta = (float) $d['quantity'] - (float) $stockPrev; if (abs($delta) > 0.0001) { $app->db->insert('sc_stock_moves', ['tenant_id' => $p->tenantId, 'item_id' => $id, 'change_qty' => $delta, 'balance' => $d['quantity'], 'reason' => !empty($_POST['id']) ? 'edit' : 'opening', 'note' => 'Changed on the item form', 'user_id' => (int) $p->user['id'], 'created_at' => date('Y-m-d H:i:s')]); } }];
};
$stockPage = static function (callable $body) use ($app, $stockMod): callable {
    return static function (array $a) use ($app, $stockMod, $body): void {
        $p = Portal::boot($app);
        $p->need('manage_medicines', 'documents');
        if (!in_array($p->family, ['pharmacy', 'bloodbank', 'equipment'], true)) {
            flash('That list is not used by your kind of organisation.', 'info');
            redirect(url('portal'));
        }
        $body($p, new Crud($app->db, $stockMod($p)), $a);
    };
};
$app->router->get('/portal/stock', $stockPage(static function (Portal $p, Crud $c) use ($dash, $card, $btn): void {
    $dash($p, 'stock', 'Stock', $card('', $c->list()), ['actions' => $p->writable() ? $btn('Add an item', $c->url('new')) : '', 'sub' => 'Used by sales, returns, purchases and the stock, expiry and low stock reports.']);
}));
$stockForm = static function (Portal $p, Crud $c, array $a) use ($dash, $card): void {
    $id = isset($a['id']) ? (int) $a['id'] : null;
    if ($id !== null && $c->find($id) === null) {
        \Selfcare\app()->error(404, 'We could not find that item.');
    }
    $dash($p, 'stock', $id ? 'Edit stock item' : 'Add a stock item', $card('', $c->form($id)));
};
$app->router->get('/portal/stock/new', $stockPage($stockForm));
$app->router->post('/portal/stock/save', $stockPage(static function (Portal $p, Crud $c) use ($back): void {
    $p->needWritable();
    $err = $c->save();
    $back('Saved.', url('portal/stock'), $err);
}));
$stockReasons = ['received' => 'Delivery received', 'damaged' => 'Damaged or broken', 'expired' => 'Expired, taken off', 'returned' => 'Returned to supplier', 'count' => 'Stock count correction', 'other' => 'Other'];
$app->router->get('/portal/stock/alerts', $stockPage(static function (Portal $p, Crud $c) use ($app, $dash, $card): void {
    $t = $p->tenantId;
    $w = $p->writable();
    $table = static function (array $rows, string $empty, string $extraHead = '', ?callable $extra = null) use ($w): string {
        if (!$rows) {
            return '<p class="d-mute">' . e($empty) . '</p>';
        }
        $h = '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Item</th><th>Batch</th><th class="r">In stock</th><th class="r">Reorder at</th>' . ($extraHead ? '<th>' . e($extraHead) . '</th>' : '') . '<th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr><td data-label="Item"><b>' . e((string) $r['name']) . '</b></td><td data-label="Batch">' . e((string) ($r['batch_no'] ?: '—')) . '</td><td data-label="In stock" class="r">' . e((string) (float) $r['quantity']) . '</td><td data-label="Reorder at" class="r">' . e((string) (float) $r['reorder_level']) . '</td>'
                . ($extra ? '<td data-label="' . e($extraHead) . '">' . $extra($r) . '</td>' : '') . '<td class="d-actions">' . ($w ? '<a class="d-act" href="' . e(url('portal/stock/' . $r['id'] . '/adjust')) . '">' . icon('edit', 16) . '<span>Adjust</span></a>' : '') . '</td></tr>';
        }
        return $h . '</tbody></table></div>';
    };
    $cols = 'id, name, batch_no, quantity, reorder_level, expiry_date';
    $low = $app->db->all("SELECT $cols FROM sc_stock_items WHERE tenant_id = ? AND ((reorder_level > 0 AND quantity <= reorder_level) OR quantity <= 0) ORDER BY quantity, name LIMIT 100", [$t]);
    $soon = $app->db->all("SELECT $cols FROM sc_stock_items WHERE tenant_id = ? AND quantity > 0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY expiry_date LIMIT 100", [$t]);
    $gone = $app->db->all("SELECT $cols FROM sc_stock_items WHERE tenant_id = ? AND quantity > 0 AND expiry_date < CURDATE() ORDER BY expiry_date LIMIT 100", [$t]);
    $left = static fn (array $r): string => e(dt((string) $r['expiry_date'])) . ' · ' . max(0, (int) ((strtotime((string) $r['expiry_date']) - strtotime(date('Y-m-d'))) / 86400)) . ' days';
    $h = $card('Running low or out (' . count($low) . ')', $table($low, 'Nothing is running low. Set a "Reorder at" number on an item to get a warning here.'))
        . $card('Expire in the next 60 days (' . count($soon) . ')', $table($soon, 'Nothing expires in the next 60 days.', 'Expires', $left))
        . $card('Already expired, still in stock (' . count($gone) . ')', $table($gone, 'No expired items on the shelf.', 'Expired on', static fn (array $r): string => e(dt((string) $r['expiry_date']))));
    $dash($p, 'stock-alerts', 'Stock alerts', $h, ['sub' => 'What to buy, sell first or take off the shelf.']);
}));
$app->router->get('/portal/stock/moves', $stockPage(static function (Portal $p) use ($app, $dash, $card): void {
    $reasons = ['opening' => 'Opening stock', 'edit' => 'Edited', 'sale' => 'Sold', 'purchase' => 'Bought', 'void' => 'Invoice voided', 'adjust' => 'Adjusted'] + ['received' => 'Delivery received', 'damaged' => 'Damaged or broken', 'expired' => 'Expired, taken off', 'returned' => 'Returned to supplier', 'count' => 'Stock count', 'other' => 'Other'];
    $list = Listing::render($app->db, [
        'id' => 'stock-moves', 'select' => 'm.*, i.name AS item_name', 'from' => 'sc_stock_moves m JOIN sc_stock_items i ON i.id = m.item_id', 'where' => ['m.tenant_id = ?'], 'params' => [$p->tenantId],
        'columns' => [
            'created_at' => ['label' => 'When', 'sort' => 'm.id', 'type' => 'datetime'],
            'item_name' => ['label' => 'Item', 'sort' => 'i.name'],
            'change_qty' => ['label' => 'Change', 'sort' => 'm.change_qty', 'class' => 'r', 'render' => static fn (array $r): string => '<span class="d-badge is-' . ((float) $r['change_qty'] < 0 ? 'bad' : 'ok') . '">' . ((float) $r['change_qty'] > 0 ? '+' : '') . e((string) (float) $r['change_qty']) . '</span>'],
            'balance' => ['label' => 'Balance', 'sort' => 'm.balance', 'class' => 'r', 'render' => static fn (array $r): string => e((string) (float) $r['balance'])],
            'reason' => ['label' => 'Reason', 'sort' => 'm.reason', 'render' => static fn (array $r): string => e($reasons[$r['reason']] ?? ucfirst((string) $r['reason']))],
            'note' => ['label' => 'Note', 'sort' => 'm.note'],
        ],
        'search' => ['i.name', 'm.note'], 'search_hint' => 'Search item or note', 'sort' => 'created_at', 'dir' => 'desc', 'per_page' => 15,
        'filters' => ['reason' => ['label' => 'Reason', 'sql' => 'm.reason = ?', 'options' => $reasons]],
        'empty' => ['title' => 'No stock movements yet', 'text' => 'Sales, purchases and adjustments will be listed here with the balance after each one.'],
    ]);
    $dash($p, 'stock-moves', 'Stock movements', $card('', $list), ['sub' => 'Every change to the quantity of an item, newest first.']);
}));
$app->router->get('/portal/stock/{id}/adjust', $stockPage(static function (Portal $p, Crud $c, array $a) use ($app, $dash, $card, $stockReasons): void {
    $p->needWritable();
    $row = $c->find((int) $a['id']);
    if ($row === null) {
        $app->error(404, 'We could not find that item.');
    }
    $f = '<p>' . e((string) $row['name']) . ' now has <b>' . e((string) (float) $row['quantity']) . '</b> ' . e((string) ($row['unit'] ?: 'in stock')) . '.</p><form class="d-form" method="post" action="' . e(url('portal/stock/' . $row['id'] . '/adjust')) . '">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('change', ['label' => 'Add or remove', 'type' => 'number', 'step' => '0.01', 'required' => true, 'help' => 'Use a minus sign to take stock out, for example -5.'], old('change', ''))
        . Crud::field('reason', ['label' => 'Reason', 'type' => 'select', 'options' => $stockReasons, 'required' => true], old('reason', 'received'))
        . Crud::field('note', ['label' => 'Note', 'max' => 200, 'wide' => true], old('note', ''))
        . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Save</button><a class="d-btn d-btn-soft" href="' . e(url('portal/stock')) . '">Back</a></div></form>';
    clear_old();
    $dash($p, 'stock', 'Adjust stock', $card('', $f), ['sub' => 'The change is written to the stock movements list.']);
}));
$app->router->post('/portal/stock/{id}/adjust', $stockPage(static function (Portal $p, Crud $c, array $a) use ($app, $stockReasons): void {
    $p->needWritable();
    $row = $c->find((int) $a['id']);
    $back = url('portal/stock/' . (int) $a['id'] . '/adjust');
    if ($row === null) {
        $app->error(404, 'We could not find that item.');
    }
    $chg = trim((string) ($_POST['change'] ?? ''));
    $reason = (string) ($_POST['reason'] ?? '');
    $err = null;
    if (!is_numeric($chg) || (float) $chg == 0.0) {
        $err = 'Write how much to add or remove, for example 20 or -5.';
    } elseif (!isset($stockReasons[$reason])) {
        $err = 'Choose a reason.';
    } elseif ((float) $row['quantity'] + (float) $chg < 0) {
        $err = 'That would take the stock below zero. There are only ' . (float) $row['quantity'] . ' left.';
    }
    if ($err !== null) {
        keep_old($_POST);
        flash($err, 'bad');
        redirect($back);
    }
    $app->db->run('UPDATE sc_stock_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [(float) $chg, $row['id'], $p->tenantId]);
    \Selfcare\Ops::stockMove($app->db, $p->tenantId, (int) $row['id'], (float) $chg, $reason, trim((string) ($_POST['note'] ?? '')), (int) $p->user['id']);
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'stock_adjusted', ['id' => (int) $row['id'], 'qty' => (float) $row['quantity']], ['change' => (float) $chg, 'reason' => $reason]);
    flash('Stock updated.', 'ok');
    redirect(url('portal/stock'));
}));
$app->router->get('/portal/stock/{id}', $stockPage($stockForm));
$app->router->post('/portal/stock/{id}/delete', $stockPage(static function (Portal $p, Crud $c, array $a): void {
    $p->needWritable();
    $c->delete((int) $a['id']);
    redirect(url('portal/stock'));
}));
