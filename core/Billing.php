<?php
// Selfcare original code. Invoices, receipts, credit notes, orders and filled-in forms.
// Tables: sc_invoices, sc_invoice_items, sc_invoice_payments, sc_documents, sc_stock_items, sc_number_sequences.
// Number prefixes are kept in the existing "settings" table (key invoice_prefix_<doc type>, tenant_id).
declare(strict_types=1);

namespace Selfcare;

final class Billing
{
    public const METHODS = ['Cash', 'Card', 'Mobile banking', 'Bank transfer', 'Cheque', 'Insurance', 'Other'];

    /** @var array<string,mixed> */
    private array $cfg;

    public function __construct(private Database $db)
    {
        $this->cfg = require __DIR__ . '/config/documents.php';
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->cfg;
    }

    /**
     * Invoices and forms a vendor type uses.
     * @return array{invoices:array<string,array<string,mixed>>,forms:array<string,array<string,mixed>>}
     */
    public function catalog(string $typeSlug, string $family): array
    {
        $fam = $this->cfg['by_family'][$family] ?? ['invoices' => [], 'forms' => []];
        $pick = function (array $keys, array $all) use ($typeSlug): array {
            $out = [];
            foreach ($keys as $k) {
                $c = $all[$k] ?? null;
                if ($c !== null && (empty($c['types']) || in_array($typeSlug, $c['types'], true))) {
                    $out[$k] = $c;
                }
            }
            return $out;
        };
        return ['invoices' => $pick($fam['invoices'], $this->cfg['invoices']), 'forms' => $pick($fam['forms'], $this->cfg['forms'])];
    }

    /** Document types that give money back (credit notes, refunds, returns). Not counted as collections. @return list<string> */
    public function creditTypes(): array
    {
        $out = [];
        foreach ($this->cfg['invoices'] as $k => $c) {
            if (($c['kind'] ?? '') === 'credit') {
                $out[] = $k;
            }
        }
        return $out;
    }

    /** SQL fragment (with quoted names) for "not a credit type", for the alias $a. */
    public function notCreditSql(string $a = 'i'): string
    {
        return $a . '.doc_type NOT IN (' . implode(',', array_map(static fn ($t) => "'" . $t . "'", $this->creditTypes())) . ')';
    }

    /** Plan module that gates a document type. */
    public function module(string $docType): string
    {
        return match ($docType) {
            'insurance_claim' => 'insurance',
            'ipd_final_bill', 'interim_bill', 'discharge_bill', 'admission_form', 'nursing_notes', 'discharge_summary' => 'ipd',
            'purchase_order', 'grn', 'supplier_invoice' => 'procurement',
            default => isset($this->cfg['forms'][$docType]) ? 'documents' : 'billing',
        };
    }

    // ------------------------------------------------------------------ settings (existing "settings" table)

    public function setting(string $tenantId, string $key, string $default = ''): string
    {
        $v = $this->db->val('SELECT `value` FROM settings WHERE tenant_id = ? AND `key` = ? LIMIT 1', [$tenantId, $key]);
        return $v === null || $v === '' ? $default : (string) $v;
    }

    public function setSetting(string $tenantId, string $key, string $value): void
    {
        $id = $this->db->val('SELECT id FROM settings WHERE tenant_id = ? AND `key` = ? LIMIT 1', [$tenantId, $key]);
        if ($id !== null) {
            $this->db->run('UPDATE settings SET `value` = ?, updated_at = NOW() WHERE id = ?', [$value, (int) $id]);
        } else {
            $this->db->insert('settings', ['key' => $key, 'value' => $value, 'tenant_id' => $tenantId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function prefix(string $tenantId, string $docType): string
    {
        $def = (string) (($this->cfg['invoices'][$docType]['prefix'] ?? $this->cfg['forms'][$docType]['prefix'] ?? 'DOC-'));
        return $this->setting($tenantId, 'invoice_prefix_' . $docType, $def);
    }

    /** Next number for a tenant and type, for example OPD-00042. Safe when two people save at once. */
    public function nextNo(string $tenantId, string $docType, string $seqTenant = ''): string
    {
        $key = $seqTenant !== '' ? $seqTenant : $tenantId;
        $inTx = false;
        try {
            $this->db->begin();
        } catch (\PDOException) {
            $inTx = true;
        }
        $this->db->run('INSERT IGNORE INTO sc_number_sequences (tenant_id, doc_type, last_no) VALUES (?, ?, 0)', [$key, $docType]);
        $n = (int) $this->db->val('SELECT last_no FROM sc_number_sequences WHERE tenant_id = ? AND doc_type = ? FOR UPDATE', [$key, $docType]) + 1;
        $this->db->run('UPDATE sc_number_sequences SET last_no = ? WHERE tenant_id = ? AND doc_type = ?', [$n, $key, $docType]);
        if (!$inTx) {
            $this->db->commit();
        }
        $prefix = $seqTenant === '_platform' ? $this->platformPrefix($docType) : $this->prefix($tenantId, $docType);
        return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function platformPrefix(string $docType): string
    {
        $v = $this->db->val('SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = ? LIMIT 1', ['invoice_prefix_' . $docType]);
        return $v !== null && $v !== '' ? (string) $v : (string) ($this->cfg['invoices'][$docType]['prefix'] ?? 'DOC-');
    }

    public static function fmt(float $amount, string $currency = 'USD'): string
    {
        $sym = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'BDT' => '৳', 'INR' => '₹'][$currency] ?? ($currency . ' ');
        return ($amount < 0 ? '-' : '') . $sym . number_format(abs($amount), 2);
    }

    // ------------------------------------------------------------------ invoices

    /**
     * Saves an invoice with its items (and the payment taken now). Throws InvalidArgumentException with a plain message.
     * @param array<string,mixed> $in header fields @param list<array<string,mixed>> $items
     */
    public function create(string $tenantId, string $issuer, string $docType, array $in, array $items, ?int $userId, string $currency = 'USD'): int
    {
        $cfg = $this->cfg['invoices'][$docType] ?? null;
        if ($cfg === null) {
            throw new \InvalidArgumentException('Unknown document type.');
        }
        $party = trim((string) ($in['party_name'] ?? ''));
        if ($party === '') {
            throw new \InvalidArgumentException('Please enter the ' . strtolower((string) $cfg['party']) . ' name.');
        }
        $date = (string) ($in['invoice_date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Please enter a valid date.');
        }
        $clean = [];
        foreach ($items as $i => $it) {
            $name = trim((string) ($it['item_name'] ?? ''));
            $qty = (float) ($it['qty'] ?? 0);
            $price = (float) ($it['price'] ?? 0);
            if ($name === '' && $qty == 0.0 && $price == 0.0) {
                continue;
            }
            if ($name === '' || $qty <= 0 || $price < 0) {
                throw new \InvalidArgumentException('Check line ' . ($i + 1) . ': needs a name, a quantity above zero and a price.');
            }
            $tax = !empty($cfg['tax']) ? max(0.0, min(100.0, (float) ($it['tax_pct'] ?? 0))) : 0.0;
            $clean[] = ['code' => mb_substr(trim((string) ($it['code'] ?? '')), 0, 60) ?: null, 'item_name' => mb_substr($name, 0, 255), 'category' => mb_substr(trim((string) ($it['category'] ?? '')), 0, 80) ?: ((string) $cfg['label']), 'qty' => $qty, 'unit' => mb_substr(trim((string) ($it['unit'] ?? '')), 0, 20) ?: null, 'price' => $price, 'tax_pct' => $tax, 'amount' => round($qty * $price, 2), 'stock_item_id' => !empty($it['stock_item_id']) ? (int) $it['stock_item_id'] : null];
        }
        if (!$clean) {
            throw new \InvalidArgumentException('Add at least one line.');
        }
        $subtotal = round(array_sum(array_column($clean, 'amount')), 2);
        $discount = !empty($cfg['discount']) ? max(0.0, min($subtotal, round((float) ($in['discount'] ?? 0), 2))) : 0.0;
        $factor = $subtotal > 0 ? ($subtotal - $discount) / $subtotal : 1;
        $tax = 0.0;
        foreach ($clean as $c) {
            $tax += $c['amount'] * $c['tax_pct'] / 100 * $factor;
        }
        $tax = round($tax, 2);
        $total = round($subtotal - $discount + $tax, 2);
        $kind = (string) $cfg['kind'];
        $method = in_array((string) ($in['payment_method'] ?? ''), self::METHODS, true) ? (string) $in['payment_method'] : 'Cash';
        $paidNow = $kind === 'invoice' ? max(0.0, min($total, round((float) ($in['paid_now'] ?? 0), 2))) : ($kind === 'order' ? 0.0 : $total);
        $meta = [];
        foreach ($cfg['x'] as $x) {
            $v = trim((string) ($in['x_' . $x[0]] ?? ''));
            if ($v !== '') {
                $meta[$x[0]] = mb_substr($v, 0, 500);
            }
        }
        $related = !empty($in['related_id']) ? (int) $in['related_id'] : null;
        $seq = $issuer === 'platform' ? '_platform' : '';
        $this->db->begin();
        try {
            // stock check first
            if (!empty($cfg['stock'])) {
                foreach ($clean as $c) {
                    if ($c['stock_item_id'] === null) {
                        continue;
                    }
                    $row = $this->db->one('SELECT name, quantity FROM sc_stock_items WHERE id = ? AND tenant_id = ? FOR UPDATE', [$c['stock_item_id'], $tenantId]);
                    if ($row === null) {
                        throw new \InvalidArgumentException('A stock item was not found.');
                    }
                    if ($cfg['stock'] === 'out' && (float) $row['quantity'] < $c['qty']) {
                        throw new \InvalidArgumentException('Not enough stock of ' . $row['name'] . ' (' . rtrim(rtrim((string) $row['quantity'], '0'), '.') . ' left).');
                    }
                }
            }
            $no = $this->nextNo($tenantId, $docType, $seq);
            $status = $kind === 'order' ? 'issued' : (round($total - $paidNow, 2) <= 0 ? 'paid' : 'issued');
            $now = date('Y-m-d H:i:s');
            $id = $this->db->insert('sc_invoices', [
                'tenant_id' => $tenantId, 'issuer' => $issuer, 'doc_type' => $docType, 'invoice_no' => $no, 'invoice_date' => $date,
                'due_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['due_date'] ?? '')) ? $in['due_date'] : null,
                'party_name' => mb_substr($party, 0, 190), 'party_phone' => mb_substr(trim((string) ($in['party_phone'] ?? '')), 0, 40) ?: null,
                'party_address' => mb_substr(trim((string) ($in['party_address'] ?? '')), 0, 255) ?: null,
                'patient_id' => !empty($in['patient_id']) ? (int) $in['patient_id'] : null, 'doctor_id' => !empty($in['doctor_id']) ? (int) $in['doctor_id'] : null,
                'related_id' => $related, 'reference' => mb_substr(trim((string) ($in['reference'] ?? '')), 0, 190) ?: null, 'meta' => $meta ? json_encode($meta) : null,
                'currency' => $currency, 'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $total, 'paid' => $paidNow,
                'balance' => $kind === 'order' ? 0 : round($total - $paidNow, 2), 'payment_method' => $paidNow > 0 ? $method : null, 'status' => $status,
                'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 500) ?: null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($clean as $pos => $c) {
                $this->db->insert('sc_invoice_items', $c + ['invoice_id' => $id, 'position' => $pos, 'created_at' => $now, 'updated_at' => $now]);
                if (!empty($cfg['stock']) && $c['stock_item_id'] !== null) {
                    $sign = $cfg['stock'] === 'out' ? '-' : '+';
                    $this->db->run("UPDATE sc_stock_items SET quantity = quantity $sign ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$c['qty'], $c['stock_item_id'], $tenantId]);
                }
            }
            if ($paidNow > 0) {
                $this->db->insert('sc_invoice_payments', ['tenant_id' => $tenantId, 'invoice_id' => $id, 'paid_at' => $now, 'amount' => $paidNow, 'method' => $method, 'reference' => mb_substr(trim((string) ($in['payment_ref'] ?? '')), 0, 120) ?: null, 'note' => $kind === 'credit' ? 'Refund paid' : null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            }
            // a credit note that points at an invoice reduces what is due on it
            if ($kind === 'credit' && $related !== null) {
                $rel = $this->db->one('SELECT id, balance FROM sc_invoices WHERE id = ? AND tenant_id = ? AND issuer = ? AND status <> ? FOR UPDATE', [$related, $tenantId, $issuer, 'void']);
                if ($rel !== null && (float) $rel['balance'] > 0) {
                    $apply = min($total, (float) $rel['balance']);
                    $this->db->insert('sc_invoice_payments', ['tenant_id' => $tenantId, 'invoice_id' => $related, 'paid_at' => $now, 'amount' => $apply, 'method' => 'Other', 'reference' => $no, 'note' => 'Credit note ' . $no, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
                    $this->recalc($related);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function recalc(int $invoiceId): void
    {
        $paid = (float) $this->db->val('SELECT COALESCE(SUM(amount), 0) FROM sc_invoice_payments WHERE invoice_id = ?', [$invoiceId]);
        $inv = $this->db->one('SELECT total, status FROM sc_invoices WHERE id = ?', [$invoiceId]);
        if ($inv === null || $inv['status'] === 'void') {
            return;
        }
        $bal = round((float) $inv['total'] - $paid, 2);
        $this->db->run('UPDATE sc_invoices SET paid = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?', [$paid, max(0, $bal), $bal <= 0 ? 'paid' : 'issued', $invoiceId]);
    }

    public function addPayment(string $tenantId, int $invoiceId, float $amount, string $method, ?string $ref, ?int $userId): ?string
    {
        $inv = $this->db->one('SELECT * FROM sc_invoices WHERE id = ? AND tenant_id = ?', [$invoiceId, $tenantId]);
        if ($inv === null || $inv['status'] === 'void') {
            return 'This document cannot take payments.';
        }
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > (float) $inv['balance'] + 0.001) {
            return 'Enter an amount above zero and not more than the balance due (' . self::fmt((float) $inv['balance'], (string) $inv['currency']) . ').';
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('sc_invoice_payments', ['tenant_id' => $tenantId, 'invoice_id' => $invoiceId, 'paid_at' => $now, 'amount' => $amount, 'method' => in_array($method, self::METHODS, true) ? $method : 'Cash', 'reference' => $ref ? mb_substr($ref, 0, 120) : null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        $this->recalc($invoiceId);
        return null;
    }

    /** Voids an invoice and puts stock back. */
    public function void(string $tenantId, int $invoiceId, string $reason): ?string
    {
        $inv = $this->db->one('SELECT * FROM sc_invoices WHERE id = ? AND tenant_id = ?', [$invoiceId, $tenantId]);
        if ($inv === null || $inv['status'] === 'void') {
            return 'Already void or not found.';
        }
        if (trim($reason) === '') {
            return 'Please write the reason.';
        }
        $cfg = $this->cfg['invoices'][$inv['doc_type']] ?? [];
        $this->db->begin();
        try {
            if (!empty($cfg['stock'])) {
                $sign = $cfg['stock'] === 'out' ? '+' : '-';
                foreach ($this->items($invoiceId) as $it) {
                    if ($it['stock_item_id']) {
                        $this->db->run("UPDATE sc_stock_items SET quantity = quantity $sign ? WHERE id = ? AND tenant_id = ?", [$it['qty'], $it['stock_item_id'], $tenantId]);
                    }
                }
            }
            $this->db->run('UPDATE sc_invoices SET status = ?, note = ?, balance = 0, updated_at = NOW() WHERE id = ?', ['void', mb_substr('VOID: ' . trim($reason), 0, 500), $invoiceId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, string $tenantId, string $issuer = 'vendor'): ?array
    {
        return $this->db->one('SELECT * FROM sc_invoices WHERE id = ? AND tenant_id = ? AND issuer = ?', [$id, $tenantId, $issuer]);
    }

    /** @return list<array<string,mixed>> */
    public function items(int $invoiceId): array
    {
        return $this->db->all('SELECT * FROM sc_invoice_items WHERE invoice_id = ? ORDER BY position, id', [$invoiceId]);
    }

    /** @return list<array<string,mixed>> */
    public function payments(int $invoiceId): array
    {
        return $this->db->all('SELECT * FROM sc_invoice_payments WHERE invoice_id = ? ORDER BY paid_at, id', [$invoiceId]);
    }

    // ------------------------------------------------------------------ document models

    /** @param array<string,mixed> $inv @return array<string,mixed> */
    public function invoiceModel(App $app, array $inv, ?array $user): array
    {
        $cfg = $this->cfg['invoices'][$inv['doc_type']];
        $cur = (string) $inv['currency'];
        $platform = $inv['issuer'] === 'platform';
        $items = $this->items((int) $inv['id']);
        $pays = $this->payments((int) $inv['id']);
        $meta = $inv['meta'] ? (array) json_decode((string) $inv['meta'], true) : [];
        $kv = [[(string) $cfg['party'], (string) $inv['party_name']]];
        if ($inv['party_phone']) {
            $kv[] = ['Phone', (string) $inv['party_phone']];
        }
        if ($inv['party_address']) {
            $kv[] = ['Address', (string) $inv['party_address']];
        }
        if ($inv['patient_id']) {
            $kv[] = ['Patient ID', (string) $inv['patient_id']];
        }
        if ($inv['reference']) {
            $kv[] = ['Reference', (string) $inv['reference']];
        }
        if (!empty($inv['doctor_id'])) {
            $dn = $this->db->one('SELECT u.first_name, u.last_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ?', [(int) $inv['doctor_id']]);
            if ($dn) {
                $kv[] = ['Doctor', 'Dr. ' . $dn['first_name'] . ' ' . $dn['last_name']];
            }
        }
        foreach ($cfg['x'] as $x) {
            if (isset($meta[$x[0]])) {
                $kv[] = [(string) $x[1], (string) $meta[$x[0]]];
            }
        }
        if ($inv['due_date']) {
            $kv[] = ['Due date', dt((string) $inv['due_date'])];
        }
        if ($inv['related_id']) {
            $rn = $this->db->val('SELECT invoice_no FROM sc_invoices WHERE id = ?', [(int) $inv['related_id']]);
            if ($rn) {
                $kv[] = ['Against invoice', (string) $rn];
            }
        }
        $cols = [['#', 0.5], [(string) $cfg['item'], 4.2], ['Qty', 0.9, 'R'], ['Rate', 1.4, 'R']];
        if (!empty($cfg['tax'])) {
            $cols[] = ['Tax %', 0.9, 'R'];
        }
        $cols[] = ['Amount', 1.5, 'R'];
        $rows = [];
        foreach ($items as $i => $it) {
            $row = [(string) ($i + 1), ($it['code'] ? '[' . $it['code'] . '] ' : '') . $it['item_name'], rtrim(rtrim((string) $it['qty'], '0'), '.'), self::fmt((float) $it['price'], $cur)];
            if (!empty($cfg['tax'])) {
                $row[] = rtrim(rtrim((string) $it['tax_pct'], '0'), '.') ?: '0';
            }
            $row[] = self::fmt((float) $it['amount'], $cur);
            $rows[] = $row;
        }
        $totals = [['Subtotal', self::fmt((float) $inv['subtotal'], $cur)]];
        if ((float) $inv['discount'] > 0) {
            $totals[] = ['Discount', '-' . self::fmt((float) $inv['discount'], $cur)];
        }
        if (!empty($cfg['tax'])) {
            $totals[] = ['Tax', self::fmt((float) $inv['tax'], $cur)];
        }
        $kind = (string) $cfg['kind'];
        $totals[] = [$kind === 'credit' ? 'Credit amount' : ($kind === 'receipt' ? 'Amount received' : 'Total'), self::fmt((float) $inv['total'], $cur), true];
        if ($kind === 'invoice') {
            $totals[] = ['Paid', self::fmt((float) $inv['paid'], $cur)];
            $totals[] = ['Balance due', self::fmt((float) $inv['balance'], $cur), true];
        }
        $blocks = [['kv', 'title' => 'Details', 'items' => $kv], ['table', 'title' => 'Items', 'cols' => $cols, 'rows' => $rows], ['totals', 'rows' => $totals], ['words', 'text' => Documents::amountWords((float) $inv['total'], $cur)]];
        if ($kind !== 'order') {
            $pm = [];
            foreach ($pays as $p) {
                $pm[] = [dt((string) $p['paid_at'], 'd M Y, H:i'), str_starts_with((string) ($p['note'] ?? ''), 'Credit note') ? 'Credit note' : (string) $p['method'], (string) ($p['reference'] ?? ''), self::fmt((float) $p['amount'], $cur)];
            }
            if ($pm) {
                $blocks[] = ['table', 'title' => $kind === 'credit' ? 'Refund' : 'Payments received', 'cols' => [['Date', 2], ['Method', 2], ['Reference', 2.5], ['Amount', 1.5, 'R']], 'rows' => $pm];
            }
        }
        if ($inv['note']) {
            $blocks[] = ['text', 'title' => 'Note', 'body' => (string) $inv['note']];
        }
        return [
            'org' => Documents::org($app, $platform ? null : (string) $inv['tenant_id']), 'title' => (string) $cfg['title'], 'no' => (string) $inv['invoice_no'],
            'date' => dt((string) $inv['invoice_date']), 'status' => $inv['status'] === 'void' ? 'VOID' : ($kind === 'invoice' ? ($inv['status'] === 'paid' ? 'PAID' : 'UNPAID') : ''),
            'blocks' => $blocks, 'terms' => (string) $cfg['terms'], 'sign' => $cfg['sign'], 'printed_by' => $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '', 'printed_at' => date('d M Y, H:i'),
        ];
    }

    /**
     * Model of a form, filled ($doc) or blank for hand writing.
     * @param array<string,mixed> $form @param array<string,mixed>|null $doc
     * @return array<string,mixed>
     */
    public function formModel(App $app, string $tenantId, array $form, ?array $doc, ?array $user): array
    {
        $data = $doc && $doc['data'] ? (array) json_decode((string) $doc['data'], true) : [];
        $blank = $doc === null;
        $blocks = [];
        $blocks[] = ['kv', 'title' => 'Details', 'items' => [[(string) $form['party'], (string) ($doc['party_name'] ?? '')], ['Phone', (string) ($doc['party_phone'] ?? '')], ['Date', $doc ? dt((string) $doc['doc_date']) : ''], ['Patient ID / file no', (string) ($doc['patient_id'] ?? ($data['patient_ref'] ?? ''))]]];
        if (!empty($form['text'])) {
            $blocks[] = ['text', 'title' => 'Statement', 'body' => (string) $form['text']];
        }
        foreach ($form['sections'] as $sec) {
            if (isset($sec['rows'])) {
                $cols = array_map(static fn ($c) => [(string) $c[1], (float) $c[2]], $sec['cols']);
                $rows = [];
                foreach ((array) ($data[$sec['rows']] ?? []) as $r) {
                    $cells = [];
                    foreach ($sec['cols'] as $c) {
                        $cells[] = (string) ($r[$c[0]] ?? '');
                    }
                    if (implode('', $cells) !== '') {
                        $rows[] = $cells;
                    }
                }
                $blocks[] = ['table', 'title' => $sec['t'], 'cols' => $cols, 'rows' => $rows, 'grid' => $blank || count($rows) < 3, 'min' => $blank ? (int) ($sec['min'] ?? 6) : count($rows)];
                continue;
            }
            $kv = [];
            $texts = [];
            foreach ($sec['f'] as $f) {
                $v = (string) ($data[$f[0]] ?? '');
                if (($f[2] ?? 'text') === 'textarea') {
                    $texts[] = [$f[1], $v];
                } else {
                    $kv[] = [$f[1], ($f[2] ?? '') === 'date' && $v !== '' ? dt($v) : $v];
                }
            }
            if ($kv) {
                $blocks[] = ['kv', 'title' => $sec['t'], 'items' => $kv];
            }
            foreach ($texts as $i => [$label, $v]) {
                if ($v === '') {
                    $blocks[] = ['lines', 'title' => $label, 'n' => 3];
                } else {
                    $blocks[] = ['text', 'title' => $label, 'body' => $v];
                }
            }
        }
        if (!empty($form['note'])) {
            $blocks[] = ['note', 'body' => (string) $form['note']];
        }
        return [
            'org' => Documents::org($app, $tenantId), 'title' => (string) $form['title'], 'no' => $doc ? (string) $doc['doc_no'] : '', 'date' => $doc ? dt((string) $doc['doc_date']) : '',
            'status' => $doc && $doc['status'] === 'void' ? 'VOID' : '', 'blocks' => $blocks, 'terms' => $blank ? '' : '', 'sign' => $form['sign'] ?? [],
            'printed_by' => $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '', 'printed_at' => date('d M Y, H:i'),
        ];
    }
}
