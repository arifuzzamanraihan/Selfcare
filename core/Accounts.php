<?php
// Selfcare original code. Accounts for every vendor: cash book, expenses, other income, dues.
// Cash basis: money counts on the day it was paid or received.
//   in   payments on invoices and receipts, other income that was received
//   out  expenses that were paid, payments to suppliers, refunds
// What is still owed is shown separately as dues (unpaid invoices to collect, supplier invoices and bills to pay).
// Tables: sc_ledger (expenses and other income typed in), sc_invoices + sc_invoice_payments (billing).
declare(strict_types=1);

namespace Selfcare;

final class Accounts
{
    public const EXPENSE = ['Salaries and wages', 'Rent', 'Electricity, water and gas', 'Internet and phone', 'Medical supplies', 'Medicines and consumables', 'Equipment and repairs', 'Fuel and vehicle costs', 'Cleaning and laundry', 'Food and kitchen', 'Marketing', 'Licences, tax and fees', 'Bank charges', 'Transport', 'Other'];
    public const INCOME = ['Donations and grants', 'Rent received', 'Camps and events', 'Insurance settlement', 'Interest', 'Other'];

    public function __construct(private Database $db, private Billing $billing)
    {
    }

    /** @return list<string> */
    public static function categories(string $kind): array
    {
        return $kind === 'income' ? self::INCOME : self::EXPENSE;
    }

    private function quoted(): string
    {
        return implode(',', array_map(fn ($t) => $this->db->quote($t), $this->billing->creditTypes()));
    }

    /** One SQL that lists every cash movement of a vendor: d, pat, dir (in|out), amount, method, src, cat, ref, party, label, link. @return array{0:string,1:list<string>} */
    private function union(string $tenant): array
    {
        $credit = $this->quoted();
        $cur = Currency::forTenant($tenant);
        $pay = "FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void'";
        $cols = static fn (string $dir, string $src, string $label): string => "SELECT DATE(p.paid_at) d, p.paid_at pat, '$dir' dir, " . Currency::sql('p.amount', 'i.currency', $cur) . " amount, p.method method, '$src' src, '$src' cat, i.invoice_no ref, i.party_name party, $label label, i.id link ";
        $sql = $cols('in', 'Invoice payment', "CONCAT('Payment for ', i.invoice_no)") . $pay . " AND i.doc_type NOT IN ($credit, 'supplier_invoice') AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%')"
            . ' UNION ALL ' . $cols('out', 'Refund', "CONCAT('Refund on ', i.invoice_no)") . $pay . " AND i.doc_type IN ($credit) AND p.note = 'Refund paid'"
            . ' UNION ALL ' . $cols('out', 'Supplier payment', "CONCAT('Paid supplier invoice ', i.invoice_no)") . $pay . " AND i.doc_type = 'supplier_invoice'"
            . " UNION ALL SELECT DATE(l.paid_at) d, l.paid_at pat, IF(l.kind = 'income', 'in', 'out') dir, " . Currency::sql('l.amount', 'l.currency', $cur) . " amount, l.method method, IF(l.kind = 'income', 'Other income', 'Expense') src, l.category cat, COALESCE(l.code, '') ref, COALESCE(l.party, '') party, CONCAT(l.category, IF(COALESCE(l.description, '') <> '', CONCAT(': ', l.description), '')) label, l.id link FROM sc_ledger l WHERE l.tenant_id = ? AND l.deleted_at IS NULL AND l.status = 'paid' AND l.paid_at IS NOT NULL";
        return [$sql, [$tenant, $tenant, $tenant, $tenant]];
    }

    /** Every cash movement between two dates, oldest first. @return list<array<string,mixed>> */
    public function movements(string $tenant, string $from, string $to): array
    {
        [$u, $p] = $this->union($tenant);
        return $this->db->all("SELECT * FROM ($u) m WHERE m.d BETWEEN ? AND ? ORDER BY m.pat, m.dir DESC", array_merge($p, [$from, $to]));
    }

    public function opening(string $tenant): float
    {
        return (float) $this->billing->setting($tenant, 'acc_opening', '0');
    }

    /** Cash on hand at the start of a day: the opening balance the vendor entered plus everything before. */
    public function balanceBefore(string $tenant, string $date): float
    {
        [$u, $p] = $this->union($tenant);
        $r = $this->db->all("SELECT dir, COALESCE(SUM(amount), 0) s FROM ($u) m WHERE m.d < ? GROUP BY dir", array_merge($p, [$date]));
        $in = 0.0;
        $out = 0.0;
        foreach ($r as $x) {
            $x['dir'] === 'in' ? $in += (float) $x['s'] : $out += (float) $x['s'];
        }
        return round($this->opening($tenant) + $in - $out, 2);
    }

    /** Money in and out by payment method. @return array{in:float,out:float,methods:array<string,array{in:float,out:float}>} */
    public function totals(string $tenant, string $from, string $to): array
    {
        $t = ['in' => 0.0, 'out' => 0.0, 'methods' => []];
        foreach ($this->movements($tenant, $from, $to) as $m) {
            $a = (float) $m['amount'];
            $t[$m['dir']] += $a;
            $t['methods'][$m['method']][$m['dir']] = ($t['methods'][$m['method']][$m['dir']] ?? 0.0) + $a;
        }
        foreach ($t['methods'] as $k => $v) {
            $t['methods'][$k] += ['in' => 0.0, 'out' => 0.0];
        }
        return $t;
    }

    /** One line per day with money in, out and the closing cash. @return list<array{d:string,in:float,out:float,close:float,n:int}> */
    public function daily(string $tenant, string $from, string $to): array
    {
        $days = [];
        foreach ($this->movements($tenant, $from, $to) as $m) {
            $d = (string) $m['d'];
            $days[$d] ??= ['d' => $d, 'in' => 0.0, 'out' => 0.0, 'close' => 0.0, 'n' => 0];
            $days[$d][$m['dir']] += (float) $m['amount'];
            $days[$d]['n']++;
        }
        ksort($days);
        $bal = $this->balanceBefore($tenant, $from);
        foreach ($days as &$d) {
            $bal = round($bal + $d['in'] - $d['out'], 2);
            $d['close'] = $bal;
        }
        return array_values($days);
    }

    /** Invoices still to collect. @return list<array<string,mixed>> */
    public function receivable(string $tenant): array
    {
        $credit = $this->quoted();
        return Currency::normalize($this->db->all("SELECT id, invoice_no, invoice_date, due_date, party_name, party_phone, doc_type, total, paid, balance, currency, DATEDIFF(CURDATE(), COALESCE(due_date, invoice_date)) age FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND status = 'issued' AND balance > 0 AND doc_type NOT IN ($credit, 'supplier_invoice', 'purchase_order', 'grn', 'quotation') ORDER BY COALESCE(due_date, invoice_date), id", [$tenant]), ['total', 'paid', 'balance'], Currency::forTenant($tenant));
    }

    /** What the vendor still has to pay: supplier invoices and bills marked "pay later". @return list<array<string,mixed>> */
    public function payable(string $tenant): array
    {
        $rows = $this->db->all("SELECT 'invoice' src, id, invoice_no ref, invoice_date d, due_date, party_name party, 'Supplier invoice' label, balance amount, currency, DATEDIFF(CURDATE(), COALESCE(due_date, invoice_date)) age FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND status = 'issued' AND balance > 0 AND doc_type = 'supplier_invoice'", [$tenant]);
        $rows = array_merge($rows, $this->db->all("SELECT 'bill' src, id, COALESCE(code, '') ref, entry_date d, due_date, COALESCE(party, '') party, category label, amount, currency, DATEDIFF(CURDATE(), COALESCE(due_date, entry_date)) age FROM sc_ledger WHERE tenant_id = ? AND kind = 'expense' AND status = 'due' AND deleted_at IS NULL", [$tenant]));
        $rows = Currency::normalize($rows, ['amount'], Currency::forTenant($tenant));
        usort($rows, static fn ($a, $b) => strcmp((string) ($a['due_date'] ?? $a['d']), (string) ($b['due_date'] ?? $b['d'])));
        return $rows;
    }

    /** Ageing buckets of a list of rows with an "age" key. @return array{0:float,1:float,2:float,3:float} */
    public static function ageing(array $rows, string $amountKey): array
    {
        $b = [0.0, 0.0, 0.0, 0.0];
        foreach ($rows as $r) {
            $age = (int) $r['age'];
            $b[$age <= 0 ? 0 : ($age <= 30 ? 1 : ($age <= 60 ? 2 : 3))] += (float) $r[$amountKey];
        }
        return $b;
    }

    /** Money in and out per month (cash basis) for the last n months. @return array<string,array{in:float,out:float}> */
    public function monthly(string $tenant, int $n = 6): array
    {
        $from = date('Y-m-01', strtotime('-' . ($n - 1) . ' month'));
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $out[date('Y-m', strtotime('-' . $i . ' month', strtotime(date('Y-m-01'))))] = ['in' => 0.0, 'out' => 0.0];
        }
        foreach ($this->movements($tenant, $from, date('Y-m-d')) as $m) {
            $k = substr((string) $m['d'], 0, 7);
            if (isset($out[$k])) {
                $out[$k][$m['dir']] += (float) $m['amount'];
            }
        }
        return $out;
    }

    /** Next number such as EXP-0007 (unique per vendor and kind). */
    public function code(string $tenant, string $kind): string
    {
        $prefix = $kind === 'income' ? 'INC-' : 'EXP-';
        $n = (int) $this->db->val('SELECT COALESCE(MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)), 0) FROM sc_ledger WHERE tenant_id = ? AND kind = ? AND code LIKE ?', [strlen($prefix) + 1, $tenant, $kind, $prefix . '%']);
        return $prefix . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }
}
