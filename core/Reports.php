<?php
// Selfcare original code. Reports for every vendor type and for the platform (super admin).
// Each report reads existing and phase 1 tables, filtered by tenant_id and a date range, and returns
// columns + rows. The same result is shown as a table, printed, or downloaded as PDF (core/Documents.php).
declare(strict_types=1);

namespace Selfcare;

final class Reports
{
    /** key => [label, description, tables read] */
    public const META = [
        'daily_collection' => ['Daily collection', 'Money received each day, by payment method.', 'sc_invoice_payments, sc_invoices'],
        'cash_book' => ['Cash book (day by day)', 'Money in and out each day with the cash at the end of the day.', 'sc_invoice_payments, sc_ledger'],
        'income_expense' => ['Income and expenses', 'Money in and out by category, and the profit, on a cash basis.', 'sc_invoice_payments, sc_ledger'],
        'expense_register' => ['Expense register', 'Every expense and bill in the period.', 'sc_ledger'],
        'payables' => ['Bills and supplier dues', 'What the vendor still has to pay.', 'sc_ledger, sc_invoices'],
        'monthly_collection' => ['Monthly collection', 'Billed, collected and outstanding for each month.', 'sc_invoices'],
        'revenue_by_item' => ['Revenue by service / item', 'Quantity and revenue for each service, product or test.', 'sc_invoice_items, sc_invoices'],
        'dues' => ['Dues and outstanding', 'Unpaid invoices with age of the debt.', 'sc_invoices'],
        'invoice_register' => ['Invoice register', 'Every invoice, receipt and credit note in the period.', 'sc_invoices'],
        'payments_register' => ['Payments register', 'Every payment received in the period.', 'sc_invoice_payments, sc_invoices'],
        'patient_register' => ['Patient / client register', 'People registered in the period.', 'patients, users'],
        'visit_register' => ['Visit / request register', 'Appointments and requests from the website and desk.', 'sc_appointment_requests, doctors, users'],
        'doctor_revenue' => ['Doctor-wise revenue and commission', 'Billed revenue per doctor and commission at the vendor rate.', 'sc_invoices, doctors, users, settings'],
        'admission_register' => ['Admission register (IPD)', 'Admission forms filled in the period.', 'sc_documents'],
        'discharge_register' => ['Discharge register', 'Discharge summaries filled in the period.', 'sc_documents'],
        'census' => ['Daily census (admitted now)', 'Patients admitted and not yet discharged.', 'sc_documents'],
        'bed_occupancy' => ['Bed occupancy', 'Beds in use by ward against the bed capacity.', 'sc_documents, settings'],
        'staff_roster' => ['Staff shift roster', 'Weekly shifts and access windows of staff.', 'staff_shifts, staff_access_windows, users'],
        'free_services_delivered' => ['Free services delivered', 'Free service delivery records by service.', 'sc_documents, free_services'],
        'test_volume' => ['Test-wise volume and revenue', 'How many of each test or study, and the revenue.', 'sc_invoice_items, sc_invoices'],
        'turnaround' => ['Turnaround time (TAT)', 'Days from sample collection to report, by department.', 'sc_documents'],
        'sales_register' => ['Sales register', 'Every sale in the period.', 'sc_invoices'],
        'rx_register' => ['Prescription register', 'Every prescription in the period with patient, doctor, diagnosis and medicines.', 'sc_prescriptions, sc_prescription_items'],
        'rx_medicines' => ['Most prescribed medicines', 'How many times each medicine was prescribed, and to how many patients.', 'sc_prescription_items, sc_prescriptions'],
        'rx_by_doctor' => ['Prescriptions by doctor', 'Prescriptions issued and medicines written by each doctor.', 'sc_prescriptions, sc_prescription_items'],
        'dispensing_register' => ['Dispensing register', 'Each item dispensed with prescriber and prescription number.', 'sc_invoice_items, sc_invoices'],
        'stock_report' => ['Stock report', 'Items in stock with value.', 'sc_stock_items'],
        'expiry_report' => ['Expiry report', 'Items expired or expiring in the next 90 days.', 'sc_stock_items'],
        'low_stock' => ['Low stock report', 'Items at or below the reorder level.', 'sc_stock_items'],
        'purchase_register' => ['Purchase register', 'Purchase orders, goods received and supplier invoices.', 'sc_invoices'],
        'trip_revenue' => ['Trip revenue by vehicle', 'Trips and revenue for each vehicle.', 'sc_invoices'],
        // platform
        'p_vendors' => ['Vendor list', 'All registered vendors with type, plan and status.', 'tenants, sc_vendors, sc_subscriptions'],
        'p_subscriptions' => ['Subscriptions', 'Active, expired and revoked subscriptions.', 'sc_subscriptions, sc_plans'],
        'p_mrr' => ['Monthly recurring revenue (MRR)', 'Recurring revenue from active paid subscriptions, by plan.', 'sc_subscriptions, sc_plans'],
        'p_plan_revenue' => ['Plan-wise revenue', 'Subscription invoices and receipts by plan.', 'sc_invoices'],
        'p_renewals' => ['Renewals and expiries', 'Subscriptions that end soon or have ended.', 'sc_subscriptions, sc_plans'],
        'p_usage' => ['Platform usage', 'Staff, patients, requests and invoices for each vendor.', 'users, patients, sc_appointment_requests, sc_invoices'],
        'p_receivables' => ['Subscription dues', 'Unpaid subscription invoices.', 'sc_invoices'],
    ];

    private const COMMON = ['daily_collection', 'cash_book', 'income_expense', 'expense_register', 'payables', 'monthly_collection', 'revenue_by_item', 'dues', 'invoice_register', 'payments_register', 'free_services_delivered'];

    /** Reports per family (plus automatic registers of the forms a vendor uses). @return list<string> */
    public static function forFamily(string $family, string $typeSlug): array
    {
        $extra = match ($family) {
            'clinical' => ['patient_register', 'visit_register', 'doctor_revenue', 'staff_roster'],
            'diagnostics' => ['patient_register', 'visit_register', 'test_volume', 'turnaround', 'staff_roster'],
            'pharmacy' => ['sales_register', 'dispensing_register', 'stock_report', 'expiry_report', 'low_stock', 'purchase_register', 'staff_roster'],
            'residential', 'rehab', 'homecare', 'telemedicine' => ['patient_register', 'visit_register', 'staff_roster'],
            'bloodbank' => ['visit_register', 'stock_report', 'expiry_report', 'low_stock', 'staff_roster'],
            'ambulance' => ['visit_register', 'trip_revenue', 'staff_roster'],
            'equipment' => ['sales_register', 'stock_report', 'low_stock', 'purchase_register', 'staff_roster'],
            default => [],
        };
        if (Rx::forType($typeSlug) !== null) {
            $extra = array_merge($extra, ['rx_register', 'rx_medicines', 'rx_by_doctor']);
        }
        if (in_array($typeSlug, ['hospital', 'maternity-center', 'mental-health'], true)) {
            $extra = array_merge($extra, ['admission_register', 'discharge_register', 'census', 'bed_occupancy']);
        }
        return array_merge(self::COMMON, $extra);
    }

    /** @return list<string> */
    public static function platform(): array
    {
        return ['p_vendors', 'p_subscriptions', 'p_mrr', 'p_plan_revenue', 'p_renewals', 'p_usage', 'p_receivables'];
    }

    public function __construct(private Database $db, private Billing $billing)
    {
    }

    /**
     * @param array{from:string,to:string,q:string} $f
     * @return array{cols:list<array<int,mixed>>,rows:list<mixed>,summary:list<array{0:string,1:string}>}
     */
    public function run(string $key, ?string $tid, array $f, ?string $currency = null): array
    {
        $db = $this->db;
        $from = $f['from'];
        $to = $f['to'];
        $q = $f['q'] ?? '';
        $currency = $currency && isset(Currency::all()[$currency]) ? $currency : ($tid ? Currency::forTenant($tid) : Currency::platform());
        $fm = static fn ($v) => Billing::fmt((float) $v, $currency);
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $res = static fn (array $cols, array $rows, array $sum = []) => ['cols' => $cols, 'rows' => $rows, 'summary' => $sum];
        $sumCol = static fn (array $rows, int $i) => array_sum(array_map(static fn ($r) => (float) preg_replace('/[^0-9.\-]/', '', (string) ($r[$i] ?? 0)), $rows));

        switch ($key) {
            case 'rx_register':
                $rows = $db->all("SELECT r.*, (SELECT GROUP_CONCAT(i.drug ORDER BY i.line_no SEPARATOR ', ') FROM sc_prescription_items i WHERE i.prescription_id = r.id AND i.tenant_id = r.tenant_id) drugs FROM sc_prescriptions r WHERE r.tenant_id = ? AND r.deleted_at IS NULL AND r.rx_date BETWEEN ? AND ?" . ($q !== '' ? ' AND (r.patient_name LIKE ? OR r.rx_no LIKE ? OR r.diagnosis LIKE ? OR r.doctor_name LIKE ?)' : '') . ' ORDER BY r.rx_date, r.id', array_merge([(string) $tid, $from, $to], $q !== '' ? [$like, $like, $like, $like] : []));
                $out = array_map(static fn ($r) => [(string) $r['rx_no'], dt((string) $r['rx_date']), (string) $r['patient_name'], (string) $r['doctor_name'], mb_strimwidth(trim((string) $r['diagnosis']), 0, 60, '...'), mb_strimwidth((string) $r['drugs'], 0, 90, '...'), ucfirst((string) $r['status'])], $rows);
                return $res([['No', 1.1], ['Date', 1.1], ['Patient', 1.9], ['Doctor', 1.7], ['Diagnosis', 2], ['Medicines', 3], ['State', 0.9]], $out, [['Prescriptions', (string) count($rows)], ['Issued', (string) count(array_filter($rows, static fn ($r) => $r['status'] === 'issued'))], ['Drafts', (string) count(array_filter($rows, static fn ($r) => $r['status'] === 'draft'))], ['Cancelled', (string) count(array_filter($rows, static fn ($r) => $r['status'] === 'cancelled'))]]);
            case 'rx_medicines':
                $rows = $db->all("SELECT i.drug, COUNT(*) n, COUNT(DISTINCT COALESCE(r.patient_id, CONCAT('w', r.id))) pts FROM sc_prescription_items i JOIN sc_prescriptions r ON r.id = i.prescription_id AND r.tenant_id = i.tenant_id WHERE r.tenant_id = ? AND r.status = 'issued' AND r.deleted_at IS NULL AND r.rx_date BETWEEN ? AND ?" . ($q !== '' ? ' AND i.drug LIKE ?' : '') . ' GROUP BY i.drug ORDER BY n DESC, i.drug LIMIT 200', array_merge([(string) $tid, $from, $to], $q !== '' ? [$like] : []));
                $out = array_map(static fn ($r) => [(string) $r['drug'], (string) $r['n'], (string) $r['pts']], $rows);
                return $res([['Medicine', 4], ['Times prescribed', 1.4, 'R'], ['Patients', 1.2, 'R']], $out, [['Different medicines', (string) count($rows)], ['Lines written', (string) array_sum(array_column($rows, 'n'))]]);
            case 'rx_by_doctor':
                $rows = $db->all("SELECT r.doctor_name, COUNT(*) n, COUNT(DISTINCT r.patient_id) pts, (SELECT COUNT(*) FROM sc_prescription_items i JOIN sc_prescriptions r2 ON r2.id = i.prescription_id AND r2.tenant_id = i.tenant_id WHERE r2.tenant_id = r.tenant_id AND r2.doctor_name = r.doctor_name AND r2.status = 'issued' AND r2.deleted_at IS NULL AND r2.rx_date BETWEEN ? AND ?) meds FROM sc_prescriptions r WHERE r.tenant_id = ? AND r.status = 'issued' AND r.deleted_at IS NULL AND r.rx_date BETWEEN ? AND ? GROUP BY r.doctor_name ORDER BY n DESC", [$from, $to, (string) $tid, $from, $to]);
                $out = array_map(static fn ($r) => [(string) $r['doctor_name'], (string) $r['n'], (string) $r['pts'], (string) $r['meds'], $r['n'] ? number_format($r['meds'] / $r['n'], 1) : '0'], $rows);
                return $res([['Doctor', 3], ['Prescriptions', 1.3, 'R'], ['Registered patients', 1.5, 'R'], ['Medicine lines', 1.3, 'R'], ['Lines per prescription', 1.6, 'R']], $out, [['Issued', (string) array_sum(array_column($rows, 'n'))]]);
            case 'cash_book':
                $acc = new Accounts($db, $this->billing);
                $days = $acc->daily((string) $tid, $from, $to);
                $open = $acc->balanceBefore((string) $tid, $from);
                $in = array_sum(array_column($days, 'in'));
                $outv = array_sum(array_column($days, 'out'));
                $out = array_map(static fn ($d) => [dt($d['d']), (string) $d['n'], $d['in'] > 0 ? $fm($d['in']) : '', $d['out'] > 0 ? $fm($d['out']) : '', $fm($d['close'])], $days);
                return $res([['Date', 1.6], ['Entries', 0.9, 'R'], ['Money in', 1.6, 'R'], ['Money out', 1.6, 'R'], ['Cash at end of day', 1.8, 'R']], $out, [['Cash at start', $fm($open)], ['Money in', $fm($in)], ['Money out', $fm($outv)], ['Cash at end', $fm($open + $in - $outv)]]);
            case 'income_expense':
                $acc = new Accounts($db, $this->billing);
                $cat = ['in' => [], 'out' => []];
                foreach ($acc->movements((string) $tid, $from, $to) as $x) {
                    $cat[$x['dir']][(string) $x['cat']] = ($cat[$x['dir']][(string) $x['cat']] ?? 0) + (float) $x['amount'];
                }
                $out = [];
                foreach (['in' => 'Income', 'out' => 'Expense'] as $dir => $lab) {
                    arsort($cat[$dir]);
                    foreach ($cat[$dir] as $c => $v) {
                        $out[] = [$lab, (string) $c, $fm($v)];
                    }
                }
                $ti = array_sum($cat['in']);
                $to2 = array_sum($cat['out']);
                return $res([['Type', 1.2], ['Category', 3.2], ['Amount', 1.6, 'R']], $out, [['Income', $fm($ti)], ['Expenses', $fm($to2)], [$ti - $to2 >= 0 ? 'Profit' : 'Loss', $fm(abs($ti - $to2))]]);
            case 'expense_register':
                $rows = $db->all("SELECT * FROM sc_ledger WHERE tenant_id = ? AND kind = 'expense' AND deleted_at IS NULL AND entry_date BETWEEN ? AND ?" . ($q !== '' ? ' AND (party LIKE ? OR category LIKE ? OR code LIKE ?)' : '') . ' ORDER BY entry_date, id', $q !== '' ? [$tid, $from, $to, $like, $like, $like] : [$tid, $from, $to]);
                $rows = Currency::normalize($rows, ['amount'], $currency);
                $out = array_map(fn ($r) => [(string) $r['code'], dt((string) $r['entry_date']), (string) $r['category'], (string) $r['party'], (string) $r['method'], $r['status'] === 'due' ? 'To pay' : 'Paid', $fm($r['amount'])], $rows);
                return $res([['No', 1.2], ['Date', 1.2], ['Category', 2.2], ['Paid to', 2], ['Method', 1.2], ['State', 0.9], ['Amount', 1.4, 'R']], $out, [['Entries', (string) count($rows)], ['Total', $fm(array_sum(array_column($rows, 'amount')))], ['Still to pay', $fm(array_sum(array_map(static fn ($r) => $r['status'] === 'due' ? (float) $r['amount'] : 0, $rows)))]]);
            case 'payables':
                $acc = new Accounts($db, $this->billing);
                $rows = $acc->payable((string) $tid);
                $b = Accounts::ageing($rows, 'amount');
                $out = array_map(fn ($r) => [(string) $r['ref'], dt((string) $r['d']), (string) $r['party'], (string) $r['label'], $r['due_date'] ? dt((string) $r['due_date']) : '', $fm($r['amount']), max(0, (int) $r['age']) . ' d'], $rows);
                return $res([['No', 1.3], ['Date', 1.2], ['To', 2.2], ['For', 2], ['Pay by', 1.2], ['Amount', 1.4, 'R'], ['Late', 0.8, 'R']], $out, [['Total to pay', $fm(array_sum($b))], ['Not late', $fm($b[0])], ['1-30 days late', $fm($b[1])], ['Over 30 days late', $fm($b[2] + $b[3])]]);
            case 'daily_collection':
                $rows = $db->all('SELECT DATE(p.paid_at) d, p.method, COUNT(*) n, SUM(' . Currency::sql('p.amount', 'i.currency', $currency) . ') amt FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND i.issuer = ? AND i.status <> ? AND DATE(p.paid_at) BETWEEN ? AND ? AND ' . $this->billing->notCreditSql('i') . ' AND (p.note IS NULL OR p.note NOT LIKE \'Credit note%\') GROUP BY DATE(p.paid_at), p.method ORDER BY d', [$tid, 'vendor', 'void', $from, $to]);
                $days = [];
                foreach ($rows as $r) {
                    $days[$r['d']][$r['method']] = ($days[$r['d']][$r['method']] ?? 0) + (float) $r['amt'];
                    $days[$r['d']]['_n'] = ($days[$r['d']]['_n'] ?? 0) + (int) $r['n'];
                }
                $out = [];
                foreach ($days as $d => $m) {
                    $cash = $m['Cash'] ?? 0;
                    $card = $m['Card'] ?? 0;
                    $mob = ($m['Mobile banking'] ?? 0) + ($m['Bank transfer'] ?? 0);
                    $oth = array_sum($m) - $m['_n'] - $cash - $card - $mob;
                    $out[] = [dt($d), (string) $m['_n'], $fm($cash), $fm($card), $fm($mob), $fm($oth), $fm($cash + $card + $mob + $oth)];
                }
                $tot = $sumCol($out, 6);
                return $res([['Date', 1.6], ['Receipts', 1, 'R'], ['Cash', 1.4, 'R'], ['Card', 1.4, 'R'], ['Mobile / bank', 1.5, 'R'], ['Other', 1.2, 'R'], ['Total', 1.6, 'R']], $out, [['Total collected', $fm($tot)], ['Days with collections', (string) count($out)]]);
            case 'monthly_collection':
                $rows = $db->all('SELECT DATE_FORMAT(invoice_date, \'%Y-%m\') m, SUM(' . Currency::sql('total', 'currency', $currency) . ') billed, SUM(' . Currency::sql('paid', 'currency', $currency) . ') paid, SUM(' . Currency::sql('balance', 'currency', $currency) . ') due, COUNT(*) n FROM sc_invoices WHERE tenant_id = ? AND issuer = ? AND status <> ? AND doc_type NOT IN (\'credit_note\',\'refund_receipt\',\'sales_return\',\'purchase_order\',\'grn\',\'quotation\',\'supplier_invoice\') AND invoice_date BETWEEN ? AND ? GROUP BY m ORDER BY m', [$tid, 'vendor', 'void', $from, $to]);
                $out = array_map(fn ($r) => [date('F Y', strtotime($r['m'] . '-01')), (string) $r['n'], $fm($r['billed']), $fm($r['paid']), $fm($r['due'])], $rows);
                return $res([['Month', 2], ['Invoices', 1, 'R'], ['Billed', 1.6, 'R'], ['Collected', 1.6, 'R'], ['Outstanding', 1.6, 'R']], $out, [['Billed', $fm(array_sum(array_column($rows, 'billed')))], ['Collected', $fm(array_sum(array_column($rows, 'paid')))], ['Outstanding', $fm(array_sum(array_column($rows, 'due')))]]);
            case 'revenue_by_item':
            case 'test_volume':
                $types = $key === 'test_volume' ? "AND i.doc_type IN ('test_invoice','imaging_invoice','sample_receipt')" : "AND i.doc_type NOT IN ('credit_note','refund_receipt','sales_return','purchase_order','grn','quotation','supplier_invoice','advance_receipt')";
                $rows = $db->all("SELECT x.category, x.item_name, SUM(x.qty) qty, SUM(".Currency::sql('x.amount','i.currency',$currency).") amt FROM sc_invoice_items x JOIN sc_invoices i ON i.id = x.invoice_id WHERE i.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND i.invoice_date BETWEEN ? AND ? $types GROUP BY x.category, x.item_name ORDER BY amt DESC", [$tid, $from, $to]);
                $out = array_map(fn ($r) => [(string) $r['category'], (string) $r['item_name'], rtrim(rtrim((string) $r['qty'], '0'), '.'), $fm($r['amt'])], $rows);
                return $res([['Department / group', 2], [$key === 'test_volume' ? 'Test' : 'Service / item', 3.5], ['Qty', 1, 'R'], ['Revenue', 1.6, 'R']], $out, [['Total revenue', $fm(array_sum(array_column($rows, 'amt')))], ['Lines', (string) count($out)]]);
            case 'dues':
                $rows = Currency::normalize($db->all("SELECT * FROM sc_invoices WHERE tenant_id = ? AND issuer = ? AND status = 'issued' AND balance > 0 AND invoice_date BETWEEN ? AND ? ORDER BY invoice_date", [$tid, 'vendor', $from, $to]), ['total', 'paid', 'balance'], $currency);
                $b = [0, 0, 0, 0];
                $out = [];
                foreach ($rows as $r) {
                    $age = (int) floor((time() - strtotime((string) $r['invoice_date'])) / 86400);
                    $b[$age <= 30 ? 0 : ($age <= 60 ? 1 : ($age <= 90 ? 2 : 3))] += (float) $r['balance'];
                    $out[] = [(string) $r['invoice_no'], dt((string) $r['invoice_date']), (string) $r['party_name'], (string) ($r['party_phone'] ?? ''), $fm($r['total']), $fm($r['paid']), $fm($r['balance']), $age . ' d'];
                }
                return $res([['Invoice', 1.6], ['Date', 1.2], ['Name', 2.2], ['Phone', 1.4], ['Total', 1.2, 'R'], ['Paid', 1.2, 'R'], ['Due', 1.2, 'R'], ['Age', 0.8, 'R']], $out, [['Total outstanding', $fm(array_sum($b))], ['0-30 days', $fm($b[0])], ['31-60 days', $fm($b[1])], ['61-90 days', $fm($b[2])], ['Over 90 days', $fm($b[3])]]);
            case 'invoice_register':
            case 'sales_register':
            case 'purchase_register':
                $in = match ($key) { 'sales_register' => "AND doc_type IN ('sales_invoice','equipment_invoice','rental_invoice','sales_return')", 'purchase_register' => "AND doc_type IN ('purchase_order','grn','supplier_invoice')", default => '' };
                $rows = Currency::normalize($db->all("SELECT * FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND invoice_date BETWEEN ? AND ? $in " . ($q !== '' ? 'AND (party_name LIKE ? OR invoice_no LIKE ?)' : '') . ' ORDER BY invoice_date, id', $q !== '' ? [$tid, $from, $to, $like, $like] : [$tid, $from, $to]), ['total', 'paid', 'balance'], $currency);
                $out = array_map(fn ($r) => [(string) $r['invoice_no'], dt((string) $r['invoice_date']), (string) ($this->billing->config()['invoices'][$r['doc_type']]['label'] ?? $r['doc_type']), (string) $r['party_name'], $fm($r['total']), $fm($r['paid']), $fm($r['balance']), (string) $r['status']], $rows);
                $valid = array_filter($rows, static fn ($r) => $r['status'] !== 'void');
                return $res([['No', 1.5], ['Date', 1.1], ['Type', 2], ['Name', 2.2], ['Total', 1.2, 'R'], ['Paid', 1.2, 'R'], ['Due', 1.2, 'R'], ['Status', 0.9]], $out, [['Documents', (string) count($rows)], ['Total value (excl. void)', $fm(array_sum(array_column($valid, 'total')))], ['Collected', $fm(array_sum(array_column($valid, 'paid')))]]);
            case 'payments_register':
                $rows = Currency::normalize($db->all('SELECT p.*, i.invoice_no, i.party_name, i.currency FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND i.issuer = ? AND DATE(p.paid_at) BETWEEN ? AND ? ORDER BY p.paid_at', [$tid, 'vendor', $from, $to]), ['amount'], $currency);
                $out = array_map(fn ($r) => [dt((string) $r['paid_at'], 'd M Y H:i'), (string) $r['invoice_no'], (string) $r['party_name'], (string) $r['method'], (string) ($r['reference'] ?? ''), $fm($r['amount'])], $rows);
                return $res([['Date', 1.6], ['Invoice', 1.5], ['Name', 2.2], ['Method', 1.3], ['Reference', 1.5], ['Amount', 1.3, 'R']], $out, [['Payments', (string) count($rows)], ['Total', $fm(array_sum(array_column($rows, 'amount')))]]);
            case 'patient_register':
                $rows = $db->all('SELECT p.id, p.patient_unique_id, u.first_name, u.last_name, u.phone, u.gender, u.city, p.created_at FROM patients p JOIN users u ON u.id = p.user_id WHERE p.tenant_id = ? AND DATE(p.created_at) BETWEEN ? AND ?' . ($q !== '' ? ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)' : '') . ' ORDER BY p.created_at', $q !== '' ? [$tid, $from, $to, $like, $like, $like] : [$tid, $from, $to]);
                $out = array_map(static fn ($r) => [(string) ($r['patient_unique_id'] ?: $r['id']), trim($r['first_name'] . ' ' . $r['last_name']), (string) ($r['phone'] ?? ''), ['Male', 'Female', 'Other'][(int) $r['gender']] ?? '', (string) ($r['city'] ?? ''), dt((string) $r['created_at'])], $rows);
                return $res([['ID', 1.3], ['Name', 3], ['Phone', 1.6], ['Sex', 0.8], ['City', 1.4], ['Registered', 1.3]], $out, [['People registered', (string) count($out)]]);
            case 'visit_register':
                $rows = $db->all('SELECT r.*, u.first_name, u.last_name FROM sc_appointment_requests r LEFT JOIN doctors d ON d.id = r.doctor_id LEFT JOIN users u ON u.id = d.user_id WHERE r.tenant_id = ? AND r.preferred_date BETWEEN ? AND ? ORDER BY r.preferred_date, r.id', [$tid, $from, $to]);
                $out = array_map(static fn ($r) => [dt((string) $r['preferred_date']), (string) $r['name'], (string) $r['phone'], $r['first_name'] ? 'Dr. ' . $r['first_name'] . ' ' . $r['last_name'] : '', (string) ($r['message'] ?? ''), ucfirst((string) $r['status'])], $rows);
                return $res([['Date', 1.2], ['Name', 2], ['Phone', 1.4], ['Doctor', 1.8], ['Note', 3], ['Status', 1]], $out, [['Requests', (string) count($out)], ['Confirmed or done', (string) count(array_filter($rows, static fn ($r) => in_array($r['status'], ['confirmed', 'done'], true)))]]);
            case 'doctor_revenue':
                $pct = (float) $this->billing->setting((string) $tid, 'doctor_commission_pct', '0');
                $rows = $db->all("SELECT d.id, u.first_name, u.last_name, COUNT(i.id) n, COALESCE(SUM(".Currency::sql('i.total','i.currency',$currency)."), 0) billed, COALESCE(SUM(".Currency::sql('i.paid','i.currency',$currency)."), 0) paid FROM doctors d JOIN users u ON u.id = d.user_id LEFT JOIN sc_invoices i ON i.doctor_id = d.id AND i.status <> 'void' AND i.issuer = 'vendor' AND i.invoice_date BETWEEN ? AND ? WHERE d.tenant_id = ? GROUP BY d.id, u.first_name, u.last_name ORDER BY billed DESC", [$from, $to, $tid]);
                $out = array_map(fn ($r) => ['Dr. ' . $r['first_name'] . ' ' . $r['last_name'], (string) $r['n'], $fm($r['billed']), $fm($r['paid']), $fm($r['paid'] * $pct / 100)], $rows);
                return $res([['Doctor', 3], ['Invoices', 1, 'R'], ['Billed', 1.6, 'R'], ['Collected', 1.6, 'R'], ['Commission', 1.6, 'R']], $out, [['Commission rate', rtrim(rtrim((string) $pct, '0'), '.') . '% (set in Settings)'], ['Total billed', $fm(array_sum(array_column($rows, 'billed')))]]);
            case 'admission_register':
            case 'discharge_register':
                $type = $key === 'admission_register' ? 'admission_form' : 'discharge_summary';
                $docs = $this->docs((string) $tid, $type, $from, $to, $q);
                $out = [];
                foreach ($docs as $d) {
                    $x = $d['_d'];
                    $out[] = $key === 'admission_register'
                        ? [(string) $d['doc_no'], dt((string) ($x['admit_date'] ?? $d['doc_date'])), (string) $d['party_name'], (string) ($x['ward'] ?? ''), (string) ($x['bed'] ?? ''), (string) ($x['doctor'] ?? ''), (string) ($x['diagnosis'] ?? '')]
                        : [(string) $d['doc_no'], dt((string) ($x['discharge_date'] ?? $d['doc_date'])), (string) $d['party_name'], (string) ($x['admission_no'] ?? ''), (string) ($x['doctor'] ?? ''), (string) ($x['condition'] ?? ''), (string) ($x['diagnosis'] ?? '')];
                }
                $cols = $key === 'admission_register' ? [['No', 1.2], ['Admitted', 1.1], ['Patient', 2], ['Ward', 1.1], ['Bed', 0.8], ['Doctor', 1.6], ['Diagnosis', 2.2]] : [['No', 1.2], ['Discharged', 1.1], ['Patient', 2], ['Admission', 1.2], ['Doctor', 1.6], ['Condition', 1.2], ['Diagnosis', 2.2]];
                return $res($cols, $out, [['Records', (string) count($out)]]);
            case 'census':
            case 'bed_occupancy':
                $adm = $this->docs((string) $tid, 'admission_form', '2000-01-01', date('Y-m-d'), '');
                $dis = array_column(array_map(static fn ($d) => ['n' => (string) ($d['_d']['admission_no'] ?? '')], $this->docs((string) $tid, 'discharge_summary', '2000-01-01', date('Y-m-d'), '')), 'n');
                $now = array_values(array_filter($adm, static fn ($d) => !in_array($d['doc_no'], $dis, true)));
                if ($key === 'census') {
                    $out = array_map(static fn ($d) => [(string) $d['doc_no'], (string) $d['party_name'], dt((string) ($d['_d']['admit_date'] ?? $d['doc_date'])), (string) ($d['_d']['ward'] ?? ''), (string) ($d['_d']['bed'] ?? ''), (string) ($d['_d']['doctor'] ?? ''), (string) max(1, (int) ceil((time() - strtotime((string) ($d['_d']['admit_date'] ?? $d['doc_date']))) / 86400))], $now);
                    return $res([['Admission', 1.3], ['Patient', 2.2], ['Admitted', 1.2], ['Ward', 1.2], ['Bed', 0.8], ['Doctor', 1.7], ['Days', 0.7, 'R']], $out, [['Admitted now', (string) count($out)]]);
                }
                $cap = (int) $this->billing->setting((string) $tid, 'bed_capacity', '0');
                $by = [];
                foreach ($now as $d) {
                    $w = (string) ($d['_d']['ward'] ?? 'Unassigned') ?: 'Unassigned';
                    $by[$w] = ($by[$w] ?? 0) + 1;
                }
                ksort($by);
                $out = [];
                foreach ($by as $w => $n) {
                    $out[] = [$w, (string) $n];
                }
                return $res([['Ward', 3], ['Beds in use', 1, 'R']], $out, [['Beds in use', (string) count($now)], ['Bed capacity (Settings)', $cap ? (string) $cap : 'not set'], ['Occupancy', $cap ? round(count($now) / $cap * 100) . '%' : 'set the bed capacity']]);
            case 'staff_roster':
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                $rows = $db->all("SELECT u.id, u.first_name, u.last_name, d.name role, w.valid_from, w.valid_until FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id LEFT JOIN staff_access_windows w ON w.user_id = u.id WHERE u.tenant_id = ? AND u.status = 1 AND d.name NOT IN ('Patient','Family Member','Referring Doctor','B2B Customer') ORDER BY u.first_name", [Auth::ROLE_MODEL, $tid]);
                $out = [];
                foreach ($rows as $r) {
                    $sh = $db->all('SELECT day_of_week, start_time, end_time FROM staff_shifts WHERE user_id = ? AND tenant_id = ? ORDER BY day_of_week', [(int) $r['id'], $tid]);
                    $txt = $sh ? implode(', ', array_map(static fn ($s) => $days[(int) $s['day_of_week']] . ' ' . substr((string) $s['start_time'], 0, 5) . '-' . substr((string) $s['end_time'], 0, 5), $sh)) : 'Any time';
                    $win = $r['valid_from'] || $r['valid_until'] ? dt((string) $r['valid_from']) . ' to ' . dt((string) $r['valid_until']) : 'Always';
                    $out[] = [trim($r['first_name'] . ' ' . $r['last_name']), (string) $r['role'], $txt, $win];
                }
                return $res([['Staff', 2], ['Role', 1.5], ['Weekly shifts', 4], ['Access window', 2]], $out, [['Staff', (string) count($out)]]);
            case 'free_services_delivered':
                $docs = $this->docs((string) $tid, 'free_service_delivery', $from, $to, '');
                $by = [];
                foreach ($docs as $d) {
                    $s = (string) ($d['_d']['service'] ?? 'Other');
                    $by[$s]['n'] = ($by[$s]['n'] ?? 0) + 1;
                    $by[$s]['qty'] = ($by[$s]['qty'] ?? 0) + (float) ($d['_d']['qty'] ?? 1);
                }
                $out = [];
                foreach ($by as $s => $v) {
                    $out[] = [$s, (string) $v['n'], rtrim(rtrim((string) $v['qty'], '0'), '.')];
                }
                return $res([['Free service', 4], ['People served', 1.3, 'R'], ['Total quantity', 1.3, 'R']], $out, [['Records', (string) count($docs)]]);
            case 'turnaround':
                $docs = $this->docs((string) $tid, 'result_report', $from, $to, '');
                $by = [];
                foreach ($docs as $d) {
                    $c = $d['_d']['collected_at'] ?? null;
                    $r = $d['_d']['reported_on'] ?? null;
                    if ($c && $r && strtotime((string) $c) && strtotime((string) $r)) {
                        $dep = (string) ($d['_d']['department'] ?? 'Other');
                        $by[$dep][] = max(0, (strtotime((string) $r) - strtotime((string) $c)) / 86400);
                    }
                }
                $out = [];
                foreach ($by as $dep => $v) {
                    $out[] = [$dep, (string) count($v), number_format(array_sum($v) / count($v), 1) . ' d', number_format(max($v), 1) . ' d'];
                }
                return $res([['Department', 3], ['Reports', 1, 'R'], ['Average TAT', 1.4, 'R'], ['Longest', 1.4, 'R']], $out, [['Reports with both dates', (string) array_sum(array_map('count', $by))]]);
            case 'dispensing_register':
                $rows = $db->all("SELECT i.invoice_no, i.invoice_date, i.party_name, i.meta, x.item_name, x.qty FROM sc_invoice_items x JOIN sc_invoices i ON i.id = x.invoice_id WHERE i.tenant_id = ? AND i.issuer = 'vendor' AND i.doc_type = 'sales_invoice' AND i.status <> 'void' AND i.invoice_date BETWEEN ? AND ? ORDER BY i.invoice_date, i.id", [$tid, $from, $to]);
                $out = array_map(static function ($r) { $m = $r['meta'] ? (array) json_decode((string) $r['meta'], true) : []; return [dt((string) $r['invoice_date']), (string) $r['invoice_no'], (string) $r['party_name'], (string) $r['item_name'], rtrim(rtrim((string) $r['qty'], '0'), '.'), (string) ($m['prescriber'] ?? ''), (string) ($m['rx_no'] ?? '')]; }, $rows);
                return $res([['Date', 1.1], ['Memo', 1.3], ['Customer', 2], ['Item', 2.6], ['Qty', 0.7, 'R'], ['Prescriber', 1.6], ['Rx no', 1]], $out, [['Items dispensed', (string) count($out)]]);
            case 'stock_report':
            case 'expiry_report':
            case 'low_stock':
                $where = $key === 'expiry_report' ? 'AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)' : ($key === 'low_stock' ? 'AND quantity <= reorder_level' : '');
                $rows = Currency::normalize($db->all("SELECT * FROM sc_stock_items WHERE tenant_id = ? $where " . ($q !== '' ? 'AND (name LIKE ? OR sku LIKE ?)' : '') . ' ORDER BY ' . ($key === 'expiry_report' ? 'expiry_date' : 'name'), $q !== '' ? [$tid, $like, $like] : [$tid]), ['cost_price', 'sell_price'], $currency);
                $out = array_map(static fn ($r) => [(string) $r['name'], (string) ($r['sku'] ?? ''), (string) ($r['batch_no'] ?? ''), $r['expiry_date'] ? dt((string) $r['expiry_date']) . ($r['expiry_date'] < date('Y-m-d') ? ' (expired)' : '') : '', rtrim(rtrim((string) $r['quantity'], '0'), '.'), rtrim(rtrim((string) $r['reorder_level'], '0'), '.'), Billing::fmt((float) $r['quantity'] * (float) $r['cost_price'], $currency)], $rows);
                return $res([['Item', 3], ['SKU', 1], ['Batch', 1.1], ['Expiry', 1.5], ['In stock', 0.9, 'R'], ['Reorder at', 0.9, 'R'], ['Value (cost)', 1.4, 'R']], $out, [['Items', (string) count($rows)], ['Stock value at cost', $fm(array_sum(array_map(static fn ($r) => $r['quantity'] * $r['cost_price'], $rows)))]]);
            case 'trip_revenue':
                $rows = Currency::normalize($db->all("SELECT meta, total, paid, currency FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND doc_type = 'trip_invoice' AND status <> 'void' AND invoice_date BETWEEN ? AND ?", [$tid, $from, $to]), ['total', 'paid'], $currency);
                $by = [];
                foreach ($rows as $r) {
                    $m = $r['meta'] ? (array) json_decode((string) $r['meta'], true) : [];
                    $v = (string) ($m['vehicle_no'] ?? 'Not recorded');
                    $by[$v]['n'] = ($by[$v]['n'] ?? 0) + 1;
                    $by[$v]['t'] = ($by[$v]['t'] ?? 0) + (float) $r['total'];
                    $by[$v]['p'] = ($by[$v]['p'] ?? 0) + (float) $r['paid'];
                }
                $out = [];
                foreach ($by as $v => $x) {
                    $out[] = [$v, (string) $x['n'], $fm($x['t']), $fm($x['p'])];
                }
                return $res([['Vehicle', 3], ['Trips', 1, 'R'], ['Billed', 1.6, 'R'], ['Collected', 1.6, 'R']], $out, [['Trips', (string) count($rows)]]);
            // ---------------------------------------------------------- platform
            case 'p_vendors':
                $rows = $db->all("SELECT t.hospital_name, t.tenant_username, v.type_slug, v.status, v.city, t.created_at, (SELECT CONCAT(p.name, '|', COALESCE(s.ends_at, '')) FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.tenant_id = t.id AND s.status = 'active' ORDER BY s.id DESC LIMIT 1) sub FROM tenants t LEFT JOIN sc_vendors v ON v.tenant_id = t.id ORDER BY t.created_at");
                $out = array_map(static function ($r) { [$pn, $ends] = array_pad(explode('|', (string) $r['sub']), 2, ''); return [(string) $r['hospital_name'], (string) $r['tenant_username'], (string) $r['type_slug'], (string) ($r['city'] ?? ''), $pn ?: 'No plan', $pn ? ($ends ? dt($ends) : 'Never') : '', ucfirst((string) $r['status']), dt((string) $r['created_at'])]; }, $rows);
                return $res([['Vendor', 2.2], ['Address name', 1.4], ['Type', 1.4], ['City', 1], ['Plan', 1.1], ['Ends', 1], ['Status', 0.9], ['Joined', 1]], $out, [['Vendors', (string) count($out)]]);
            case 'p_subscriptions':
                $rows = $db->all("SELECT s.*, p.name pn, p.price, p.currency pcur, p.other_prices, t.hospital_name FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id JOIN tenants t ON t.id = s.tenant_id WHERE DATE(s.starts_at) BETWEEN ? AND ? ORDER BY s.id DESC", [$from, $to]);
                $out = array_map(fn ($r) => [(string) $r['hospital_name'], (string) $r['pn'], $fm(Plans::price(['price' => $r['price'], 'currency' => $r['pcur'], 'other_prices' => $r['other_prices']], $currency)[0]), dt((string) $r['starts_at']), $r['ends_at'] ? dt((string) $r['ends_at']) : 'Never', ucfirst((string) $r['status']), (string) ($r['note'] ?? '')], $rows);
                return $res([['Vendor', 2.2], ['Plan', 1.2], ['Price', 1, 'R'], ['Starts', 1.1], ['Ends', 1.1], ['State', 0.9], ['Note', 2]], $out, [['Subscriptions', (string) count($out)]]);
            case 'p_mrr':
                $rows = $db->all("SELECT p.*, COUNT(*) n FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id WHERE s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW()) GROUP BY p.id ORDER BY p.price");
                $out = [];
                $mrr = 0.0;
                foreach ($rows as $r) {
                    [$amount] = Plans::price($r, $currency);
                    $monthly = (int) ($r['duration_days'] ?? 0) >= 360 ? $amount / 12 : $amount;
                    $mrr += $monthly * $r['n'];
                    $out[] = [(string) $r['name'], (string) $r['n'], $fm($monthly), $fm($monthly * $r['n'])];
                }
                return $res([['Plan', 3], ['Active vendors', 1.4, 'R'], ['Monthly price', 1.6, 'R'], ['MRR', 1.6, 'R']], $out, [['MRR', $fm($mrr)], ['ARR (MRR x 12)', $fm($mrr * 12)]]);
            case 'p_plan_revenue':
                $rows = $db->all("SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '\$.plan_name')), 'Other') pn, COUNT(*) n, SUM(".Currency::sql('total','currency',$currency).") billed, SUM(".Currency::sql('paid','currency',$currency).") paid FROM sc_invoices WHERE issuer = 'platform' AND status <> 'void' AND doc_type = 'subscription_invoice' AND invoice_date BETWEEN ? AND ? GROUP BY pn ORDER BY billed DESC", [$from, $to]);
                $out = array_map(fn ($r) => [(string) $r['pn'], (string) $r['n'], $fm($r['billed']), $fm($r['paid'])], $rows);
                return $res([['Plan', 3], ['Invoices', 1, 'R'], ['Billed', 1.6, 'R'], ['Collected', 1.6, 'R']], $out, [['Billed', $fm(array_sum(array_column($rows, 'billed')))], ['Collected', $fm(array_sum(array_column($rows, 'paid')))]]);
            case 'p_renewals':
                $rows = $db->all("SELECT s.*, p.name pn, t.hospital_name FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id JOIN tenants t ON t.id = s.tenant_id WHERE s.status = 'active' AND s.ends_at IS NOT NULL AND DATE(s.ends_at) BETWEEN ? AND ? ORDER BY s.ends_at", [$from, $to]);
                $out = array_map(static fn ($r) => [(string) $r['hospital_name'], (string) $r['pn'], dt((string) $r['ends_at']), $r['ends_at'] < date('Y-m-d H:i:s') ? 'Expired' : (int) ceil((strtotime((string) $r['ends_at']) - time()) / 86400) . ' days left'], $rows);
                return $res([['Vendor', 3], ['Plan', 1.5], ['Ends', 1.3], ['Status', 1.4]], $out, [['Subscriptions in range', (string) count($out)]]);
            case 'p_usage':
                $rows = $db->all("SELECT t.id, t.hospital_name, v.type_slug, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 1) staff, (SELECT COUNT(*) FROM patients p WHERE p.tenant_id = t.id) pts, (SELECT COUNT(*) FROM sc_appointment_requests r WHERE r.tenant_id = t.id AND DATE(r.created_at) BETWEEN ? AND ?) reqs, (SELECT COUNT(*) FROM sc_invoices i WHERE i.tenant_id = t.id AND i.issuer = 'vendor' AND i.invoice_date BETWEEN ? AND ?) invs FROM tenants t LEFT JOIN sc_vendors v ON v.tenant_id = t.id ORDER BY t.hospital_name", [$from, $to, $from, $to]);
                $out = array_map(static fn ($r) => [(string) $r['hospital_name'], (string) $r['type_slug'], (string) $r['staff'], (string) $r['pts'], (string) $r['reqs'], (string) $r['invs']], $rows);
                return $res([['Vendor', 3], ['Type', 1.6], ['Users', 0.9, 'R'], ['Patients', 1, 'R'], ['Requests', 1, 'R'], ['Invoices', 1, 'R']], $out, [['Vendors', (string) count($out)], ['Users in total', (string) array_sum(array_column($rows, 'staff'))]]);
            case 'p_receivables':
                $rows = $db->all("SELECT i.*, t.hospital_name FROM sc_invoices i JOIN tenants t ON t.id = i.tenant_id WHERE i.issuer = 'platform' AND i.status = 'issued' AND i.balance > 0 AND i.invoice_date BETWEEN ? AND ? ORDER BY i.invoice_date", [$from, $to]);
                $rows = Currency::normalize($rows, ['total', 'paid', 'balance'], $currency);
                $out = array_map(fn ($r) => [(string) $r['invoice_no'], dt((string) $r['invoice_date']), (string) $r['hospital_name'], $fm($r['total']), $fm($r['paid']), $fm($r['balance'])], $rows);
                return $res([['Invoice', 1.6], ['Date', 1.2], ['Vendor', 3], ['Total', 1.2, 'R'], ['Paid', 1.2, 'R'], ['Due', 1.2, 'R']], $out, [['Outstanding', $fm(array_sum(array_column($rows, 'balance')))]]);
        }
        // form registers: key "reg-<form>"
        if (str_starts_with($key, 'reg-')) {
            $form = $this->billing->config()['forms'][substr($key, 4)] ?? null;
            if ($form === null) {
                return $res([['Report', 1]], []);
            }
            $docs = $this->docs((string) $tid, substr($key, 4), $from, $to, $q);
            $reg = $form['register'] ?? [];
            $cols = [['No', 1.3], ['Date', 1.1], [(string) $form['party'], 2.2]];
            foreach ($reg as $lab) {
                $cols[] = [(string) $lab, 1.6];
            }
            $out = [];
            foreach ($docs as $d) {
                $row = [(string) $d['doc_no'], dt((string) $d['doc_date']), (string) $d['party_name']];
                foreach (array_keys($reg) as $f) {
                    $row[] = (string) ($d['_d'][$f] ?? '');
                }
                $out[] = $row;
            }
            return $res($cols, $out, [['Records', (string) count($out)]]);
        }
        return $res([['Report', 1]], []);
    }

    /** Form documents with decoded data. @return list<array<string,mixed>> */
    private function docs(string $tid, string $type, string $from, string $to, string $q): array
    {
        $rows = $this->db->all('SELECT * FROM sc_documents WHERE tenant_id = ? AND doc_type = ? AND status = ? AND doc_date BETWEEN ? AND ?' . ($q !== '' ? ' AND (party_name LIKE ? OR doc_no LIKE ?)' : '') . ' ORDER BY doc_date, id', $q !== '' ? [$tid, $type, 'final', $from, $to, '%' . $q . '%', '%' . $q . '%'] : [$tid, $type, 'final', $from, $to]);
        foreach ($rows as &$r) {
            $r['_d'] = $r['data'] ? (array) json_decode((string) $r['data'], true) : [];
        }
        return $rows;
    }

    /** Model for the PDF / print view of a report result. @param array<string,mixed> $res */
    public function model(App $app, ?string $tid, string $key, string $title, array $res, array $f, ?array $user): array
    {
        $kv = [['Period', dt($f['from']) . ' to ' . dt($f['to'])]];
        foreach ($res['summary'] as $s) {
            $kv[] = [$s[0], $s[1]];
        }
        $cols = array_map(static fn ($c) => [$c[0], $c[1], $c[2] ?? 'L'], $res['cols']);
        return [
            'org' => Documents::org($app, $tid), 'title' => strtoupper($title), 'no' => '', 'date' => date('d M Y'), 'landscape' => count($cols) >= 7,
            'blocks' => [['kv', 'title' => 'Summary', 'items' => $kv], ['table', 'title' => 'Details', 'cols' => $cols, 'rows' => $res['rows'], 'size' => 8]],
            'printed_by' => $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '', 'printed_at' => date('d M Y, H:i'),
        ];
    }
}
