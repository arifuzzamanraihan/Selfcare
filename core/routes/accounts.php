<?php
// Selfcare original routes: Accounts for every vendor (cash book, daily cash summary, expenses, other income, dues).
// Logic is in core/Accounts.php. Permission manage_accounts, plan module billing.
// /portal/accounts                 overview             /cashbook  days with money in and out     /day/{date}  one day, PDF with ?format=pdf
// /portal/accounts/entries         expenses and other income (?kind=expense|income)   /new  /save  /{id}  /{id}/pay  /{id}/delete  /{id}/restore
// /portal/accounts/dues            who owes us and what we have to pay               /opening  opening cash balance
declare(strict_types=1);

use Selfcare\{Accounts, Billing, Charts, Crud, Documents, Listing, Portal};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$billing = new Billing($app->db);
$acc = new Accounts($app->db, $billing);

$boot = static function () use ($app): Portal {
    $p = Portal::boot($app);
    $p->need('manage_accounts', 'billing');
    return $p;
};
$audit = static function (Portal $p, string $action, mixed $before, mixed $after) use ($app): void {
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, $action, $before, $after);
};
$tabs = static function (string $on) use ($btn): string {
    $t = ['' => 'Overview', 'cashbook' => 'Cash book', 'entries' => 'Expenses and income', 'dues' => 'Dues'];
    $h = '<p class="d-tools" style="gap:8px;flex-wrap:wrap">';
    foreach ($t as $k => $label) {
        $h .= '<a class="d-btn ' . ($k === $on ? 'd-btn-primary' : 'd-btn-soft') . '" href="' . e(url('portal/accounts' . ($k !== '' ? '/' . $k : ''))) . '">' . e($label) . '</a>';
    }
    return $h . '</p>';
};
$validDate = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', strtotime($d)) === $d;
$range = static function () use ($validDate): array {
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    $from = $validDate($from) ? $from : date('Y-m-01');
    $to = $validDate($to) ? $to : date('Y-m-d');
    return $to < $from ? [$to, $from] : [$from, $to];
};
$table = static function (array $cols, array $rows, string $empty): string {
    if (!$rows) {
        return '<div class="d-empty"><div class="d-empty-art">' . icon('chart', 28) . '</div><h3>' . e($empty) . '</h3></div>';
    }
    $t = '<div class="d-table-wrap"><table class="d-table"><thead><tr>';
    foreach ($cols as $c) {
        $t .= '<th' . (($c[1] ?? '') === 'R' ? ' class="r"' : '') . '>' . e($c[0]) . '</th>';
    }
    $t .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $t .= '<tr>';
        foreach ($r as $i => $cell) {
            $t .= '<td data-label="' . e($cols[$i][0]) . '"' . (($cols[$i][1] ?? '') === 'R' ? ' class="r"' : '') . '>' . $cell . '</td>';
        }
        $t .= '</tr>';
    }
    return $t . '</tbody></table></div>';
};
$m = static fn (float $v): string => $v > 0 ? e(money($v)) : '<span class="d-mute">—</span>';
$pdfModel = static function (Portal $p, array $blocks, string $title, string $no = '', bool $landscape = false) use ($app): array {
    return ['org' => Documents::org($app, $p->tenantId), 'title' => strtoupper($title), 'no' => $no, 'date' => date('d M Y'), 'landscape' => $landscape, 'blocks' => $blocks, 'sign' => ['Prepared by', 'Checked by'],
        'printed_by' => trim($p->user['first_name'] . ' ' . $p->user['last_name']), 'printed_at' => date('d M Y, H:i')];
};
$fmt = static fn (float $v): string => money($v);

// ---------------------------------------------------------------- overview
$app->router->get('/portal/accounts', static function () use ($app, $boot, $acc, $dash, $card, $btn, $stat, $tabs, $m): void {
    $p = $boot();
    $t = $p->tenantId;
    $today = date('Y-m-d');
    $mo = date('Y-m-01');
    $td = $acc->totals($t, $today, $today);
    $mt = $acc->totals($t, $mo, $today);
    $recv = $acc->receivable($t);
    $pay = $acc->payable($t);
    $cash = $acc->balanceBefore($t, date('Y-m-d', strtotime('+1 day')));
    $h = $tabs('') . '<div class="d-stats">'
        . $stat('Cash on hand', money($cash), 'card', 'opening balance plus all money in and out')
        . $stat('In today', money($td['in']), 'plus')
        . $stat('Out today', money($td['out']), 'minus')
        . $stat('Income this month', money($mt['in']), 'chart', 'money received')
        . $stat('Expenses this month', money($mt['out']), 'chart', 'money paid out')
        . $stat('Profit this month', money($mt['in'] - $mt['out']), 'check', 'cash basis')
        . $stat('To collect', money(array_sum(array_column($recv, 'balance'))), 'clock', count($recv) . ' unpaid invoice(s)')
        . $stat('To pay', money(array_sum(array_column($pay, 'amount'))), 'clock', count($pay) . ' bill(s) and supplier invoice(s)')
        . '</div>';
    $months = $acc->monthly($t, 6);
    $col = static fn (string $k) => Charts::columns(array_map(static fn ($mm, $v) => [date('M', strtotime($mm . '-01')), (float) $v[$k], money((float) $v[$k])], array_keys($months), $months), 'Nothing recorded yet.');
    $by = [];
    foreach ($acc->movements($t, $mo, $today) as $x) {
        if ($x['dir'] === 'out') {
            $by[(string) $x['cat']] = ($by[(string) $x['cat']] ?? 0) + (float) $x['amount'];
        }
    }
    arsort($by);
    $bars = [];
    foreach (array_slice($by, 0, 8, true) as $k => $v) {
        $bars[] = [(string) $k, (int) round($v), null];
    }
    $recent = array_slice(array_reverse($acc->movements($t, date('Y-m-d', strtotime('-30 day')), $today)), 0, 8);
    $rows = array_map(static fn ($x) => [e(dt((string) $x['d'])), e((string) $x['src']), e((string) $x['label']), e((string) $x['party']), $x['dir'] === 'in' ? '<b>' . e(money((float) $x['amount'])) . '</b>' : '', $x['dir'] === 'out' ? '<b>' . e(money((float) $x['amount'])) . '</b>' : ''], $recent);
    $h .= '<div class="d-grid d-g2">' . $card('Money in per month', $col('in')) . $card('Money out per month', $col('out'))
        . $card('Where the money went this month', $bars ? Charts::bars($bars) : '<p class="d-help">No expenses this month.</p>')
        . $card('Latest money movements', $rows ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Date</th><th>Type</th><th>Details</th><th>Name</th><th class="r">In</th><th class="r">Out</th></tr></thead><tbody>' . implode('', array_map(static fn ($r) => '<tr><td>' . $r[0] . '</td><td>' . $r[1] . '</td><td>' . $r[2] . '</td><td>' . $r[3] . '</td><td class="r">' . $r[4] . '</td><td class="r">' . $r[5] . '</td></tr>', $rows)) . '</tbody></table></div>' : '<p class="d-help">Nothing in the last 30 days.</p>') . '</div>';
    $acts = ($p->writable() ? $btn('Add expense', url('portal/accounts/entries/new', ['kind' => 'expense'])) . ' ' : '') . '<a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/day/' . $today)) . '">Today\'s cash summary</a>';
    $dash($p, 'accounts', 'Accounts', $h, ['sub' => 'Money in and out, the daily cash book and what is still owed. Invoice payments come in by themselves.', 'actions' => $acts]);
});

// ---------------------------------------------------------------- cash book
$app->router->get('/portal/accounts/cashbook', static function () use ($app, $boot, $acc, $dash, $card, $tabs, $range, $table, $m, $pdfModel): void {
    $p = $boot();
    [$from, $to] = $range();
    $days = $acc->daily($p->tenantId, $from, $to);
    $open = $acc->balanceBefore($p->tenantId, $from);
    $tot = $acc->totals($p->tenantId, $from, $to);
    $close = round($open + $tot['in'] - $tot['out'], 2);
    if (($_GET['format'] ?? '') === 'pdf') {
        $rows = array_map(static fn ($d) => [dt($d['d']), (string) $d['n'], $d['in'] > 0 ? money($d['in']) : '', $d['out'] > 0 ? money($d['out']) : '', money($d['close'])], $days);
        Documents::send($pdfModel($p, [['kv', 'title' => 'Summary', 'items' => [['Period', dt($from) . ' to ' . dt($to)], ['Cash at start', money($open)], ['Money in', money($tot['in'])], ['Money out', money($tot['out'])], ['Cash at end', money($close)]]],
            ['table', 'title' => 'Day by day', 'cols' => [['Date', 2], ['Entries', 1, 'R'], ['Money in', 2, 'R'], ['Money out', 2, 'R'], ['Cash at end of day', 2.2, 'R']], 'rows' => $rows, 'size' => 9]], 'Cash book'), 'cash-book-' . $from . '_' . $to . '.pdf');
    }
    $form = '<form class="d-tools" method="get" action="' . e(url('portal/accounts/cashbook')) . '"><label class="d-filter"><span>From</span><input class="d-in" type="date" name="from" value="' . e($from) . '"></label><label class="d-filter"><span>To</span><input class="d-in" type="date" name="to" value="' . e($to) . '"></label><button class="d-btn d-btn-soft" type="submit">Show</button><span class="d-count"><a class="d-btn d-btn-primary" href="' . e(url('portal/accounts/cashbook', ['from' => $from, 'to' => $to, 'format' => 'pdf'])) . '">' . icon('file', 14) . ' Download PDF</a></span></form>';
    $sum = '<div class="d-stats"><div class="d-stat"><span>Cash at start</span><b style="font-size:1.4rem">' . e(money($open)) . '</b></div><div class="d-stat"><span>Money in</span><b style="font-size:1.4rem">' . e(money($tot['in'])) . '</b></div><div class="d-stat"><span>Money out</span><b style="font-size:1.4rem">' . e(money($tot['out'])) . '</b></div><div class="d-stat"><span>Cash at end</span><b style="font-size:1.4rem">' . e(money($close)) . '</b></div></div>';
    $rows = array_map(static fn ($d) => ['<a href="' . e(url('portal/accounts/day/' . $d['d'])) . '"><b>' . e(dt($d['d'])) . '</b></a>', (string) $d['n'], $m($d['in']), $m($d['out']), '<b>' . e(money($d['close'])) . '</b>'], $days);
    $meth = '';
    if ($tot['methods']) {
        $meth = $card('By payment method', $table([['Method'], ['In', 'R'], ['Out', 'R']], array_map(static fn ($k, $v) => [e((string) $k), $m($v['in']), $m($v['out'])], array_keys($tot['methods']), $tot['methods']), ''));
    }
    $open_form = $p->writable() ? $card('Opening cash balance', '<form class="d-form" method="post" action="' . e(url('portal/accounts/opening')) . '">' . csrf_field() . '<div class="d-form-grid">' . Crud::field('opening', ['label' => 'Cash and bank you had before you started using Self Care', 'type' => 'number', 'step' => '0.01'], (string) $acc->opening($p->tenantId)) . '</div><div class="d-form-actions"><button class="d-btn d-btn-soft" type="submit">Save</button></div></form>') : '';
    $dash($p, 'accounts', 'Cash book', $tabs('cashbook') . $card('', $form) . $sum . $card('Day by day', $table([['Date'], ['Entries', 'R'], ['Money in', 'R'], ['Money out', 'R'], ['Cash at end of day', 'R']], $rows, 'No money moved in this period')) . $meth . $open_form, ['sub' => 'Every day with the money received and paid. Open a day for the daily cash summary.']);
});
$app->router->post('/portal/accounts/opening', static function () use ($app, $boot, $billing, $audit): void {
    $p = $boot();
    $p->needWritable();
    $v = trim((string) ($_POST['opening'] ?? '0'));
    if (!is_numeric($v) || abs((float) $v) > 1e12) {
        flash('Please enter a number.', 'bad');
        redirect(url('portal/accounts/cashbook'));
    }
    $old = $billing->setting($p->tenantId, 'acc_opening', '0');
    $billing->setSetting($p->tenantId, 'acc_opening', (string) round((float) $v, 2));
    $audit($p, 'accounts_opening', $old, round((float) $v, 2));
    flash('Opening balance saved.', 'ok');
    redirect(url('portal/accounts/cashbook'));
});

// ---------------------------------------------------------------- one day
$app->router->get('/portal/accounts/day/{date}', static function (array $a) use ($app, $boot, $acc, $dash, $card, $tabs, $table, $m, $validDate, $pdfModel): void {
    $p = $boot();
    $d = (string) $a['date'];
    if (!$validDate($d)) {
        $app->error(404, 'That is not a date.');
    }
    $t = $p->tenantId;
    $mv = $acc->movements($t, $d, $d);
    $open = $acc->balanceBefore($t, $d);
    $tot = $acc->totals($t, $d, $d);
    $close = round($open + $tot['in'] - $tot['out'], 2);
    if (($_GET['format'] ?? '') === 'pdf') {
        $rows = array_map(static fn ($x) => [dt((string) $x['pat'], 'H:i'), (string) $x['src'], (string) $x['label'], (string) $x['party'], (string) $x['method'], $x['dir'] === 'in' ? money((float) $x['amount']) : '', $x['dir'] === 'out' ? money((float) $x['amount']) : ''], $mv);
        $meth = array_map(static fn ($k, $v) => [(string) $k, $v['in'] > 0 ? money($v['in']) : '', $v['out'] > 0 ? money($v['out']) : ''], array_keys($tot['methods']), $tot['methods']);
        $blocks = [['kv', 'title' => 'Daily cash summary', 'items' => [['Date', dt($d, 'l, d M Y')], ['Cash at start of day', money($open)], ['Money in', money($tot['in'])], ['Money out', money($tot['out'])], ['Net for the day', money($tot['in'] - $tot['out'])], ['Cash at end of day', money($close)]]],
            ['table', 'title' => 'Money in and out', 'cols' => [['Time', 0.8], ['Type', 1.6], ['Details', 3], ['Name', 2], ['Method', 1.3], ['In', 1.3, 'R'], ['Out', 1.3, 'R']], 'rows' => $rows, 'size' => 8]];
        if ($meth) {
            $blocks[] = ['table', 'title' => 'By payment method', 'cols' => [['Method', 3], ['In', 1.5, 'R'], ['Out', 1.5, 'R']], 'rows' => $meth, 'size' => 9];
        }
        Documents::send($pdfModel($p, $blocks, 'Daily cash summary', '', true), 'cash-summary-' . $d . '.pdf');
    }
    $rows = array_map(static fn ($x) => [e(dt((string) $x['pat'], 'H:i')), e((string) $x['src']), $x['src'] === 'Invoice payment' || $x['src'] === 'Refund' || $x['src'] === 'Supplier payment' ? '<a href="' . e(url('portal/billing/' . $x['link'])) . '">' . e((string) $x['label']) . '</a>' : '<a href="' . e(url('portal/accounts/entries/' . $x['link'])) . '">' . e((string) $x['label']) . '</a>', e((string) $x['party']), e((string) $x['method']), $x['dir'] === 'in' ? '<b>' . e(money((float) $x['amount'])) . '</b>' : '', $x['dir'] === 'out' ? '<b>' . e(money((float) $x['amount'])) . '</b>' : ''], $mv);
    $sum = '<div class="d-stats"><div class="d-stat"><span>Cash at start of day</span><b style="font-size:1.4rem">' . e(money($open)) . '</b></div><div class="d-stat"><span>Money in</span><b style="font-size:1.4rem">' . e(money($tot['in'])) . '</b></div><div class="d-stat"><span>Money out</span><b style="font-size:1.4rem">' . e(money($tot['out'])) . '</b></div><div class="d-stat"><span>Cash at end of day</span><b style="font-size:1.4rem">' . e(money($close)) . '</b></div></div>';
    $nav = '<p class="d-tools" style="gap:8px"><a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/day/' . date('Y-m-d', strtotime($d . ' -1 day')))) . '">Day before</a><a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/day/' . date('Y-m-d', strtotime($d . ' +1 day')))) . '">Day after</a><a class="d-btn d-btn-primary" href="' . e(url('portal/accounts/day/' . $d, ['format' => 'pdf'])) . '">' . icon('file', 14) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button></p>';
    $meth = $tot['methods'] ? $card('By payment method', $table([['Method'], ['In', 'R'], ['Out', 'R']], array_map(static fn ($k, $v) => [e((string) $k), $m($v['in']), $m($v['out'])], array_keys($tot['methods']), $tot['methods']), '')) : '';
    $dash($p, 'accounts', 'Cash summary for ' . dt($d, 'd M Y'), $tabs('cashbook') . $nav . $sum . $card('Money in and out', $table([['Time'], ['Type'], ['Details'], ['Name'], ['Method'], ['In', 'R'], ['Out', 'R']], $rows, 'No money moved on this day')) . $meth, ['sub' => dt($d, 'l')]);
});

// ---------------------------------------------------------------- expenses and other income
$entryFields = static function (Portal $p, string $kind, ?array $row) use ($app): array {
    $cats = array_combine(Accounts::categories($kind), Accounts::categories($kind));
    $staff = [];
    foreach ($app->db->all("SELECT u.id, u.first_name, u.last_name FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND u.deleted_at IS NULL AND d.name NOT IN ('Patient', 'Family Member', 'Referring Doctor', 'B2B Customer') ORDER BY u.first_name LIMIT 300", [\Selfcare\Auth::ROLE_MODEL, $p->tenantId]) as $s) {
        $staff[(string) $s['id']] = trim($s['first_name'] . ' ' . $s['last_name']);
    }
    $f = [
        'entry_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')],
        'category' => ['label' => 'Category', 'type' => 'select', 'options' => $cats, 'required' => true],
        'party' => ['label' => $kind === 'income' ? 'Received from' : 'Paid to', 'max' => 190],
        'amount' => ['label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'min' => 0.01, 'required' => true],
        'method' => ['label' => $kind === 'income' ? 'Received by' : 'Paid by', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true, 'default' => 'Cash'],
        'reference' => ['label' => 'Reference (cheque, transaction id, voucher)', 'max' => 120],
    ];
    if ($kind === 'expense') {
        $f['status'] = ['label' => 'Payment', 'type' => 'select', 'options' => ['paid' => 'Paid now', 'due' => 'Bill to pay later'], 'required' => true, 'default' => 'paid'];
        $f['due_date'] = ['label' => 'Pay by (for a bill to pay later)', 'type' => 'date', 'null' => true];
        if ($staff) {
            $f['user_id'] = ['label' => 'Staff member (for salary)', 'type' => 'select', 'options' => $staff, 'blank_label' => 'Not a salary payment'];
        }
    }
    $f['description'] = ['label' => 'Note', 'type' => 'textarea', 'rows' => 3, 'max' => 255, 'wide' => true];
    return $f;
};
$loadEntry = static function (Portal $p, int $id) use ($app): ?array {
    return $app->db->one('SELECT * FROM sc_ledger WHERE id = ? AND tenant_id = ?', [$id, $p->tenantId]);
};
$kindOf = static fn (): string => ($_GET['kind'] ?? $_POST['kind'] ?? 'expense') === 'income' ? 'income' : 'expense';

$app->router->get('/portal/accounts/entries', static function () use ($app, $boot, $dash, $card, $btn, $tabs, $kindOf, $audit): void {
    $p = $boot();
    $kind = $kindOf();
    $w = $p->writable();
    $deleted = ($_GET['show'] ?? '') === 'deleted';
    $acts = [['label' => 'Open', 'icon' => 'eye', 'url' => static fn ($r) => url('portal/accounts/entries/' . $r['id'])]];
    if ($w && !$deleted) {
        $acts[] = ['label' => 'Pay now', 'icon' => 'check', 'method' => 'get', 'if' => static fn ($r) => $r['status'] === 'due', 'url' => static fn ($r) => url('portal/accounts/entries/' . $r['id'] . '/pay')];
        $acts[] = ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this entry? You can bring it back from the deleted list.', 'url' => static fn ($r) => url('portal/accounts/entries/' . $r['id'] . '/delete')];
    } elseif ($w) {
        $acts[] = ['label' => 'Restore', 'icon' => 'undo', 'method' => 'post', 'url' => static fn ($r) => url('portal/accounts/entries/' . $r['id'] . '/restore')];
    }
    $cols = [
        'code' => ['label' => 'No', 'sort' => 't.code', 'render' => static fn ($r) => '<a href="' . e(url('portal/accounts/entries/' . $r['id'])) . '"><b>' . e((string) $r['code']) . '</b></a>'],
        'entry_date' => ['label' => 'Date', 'sort' => 't.entry_date', 'type' => 'date'],
        'category' => ['label' => 'Category', 'sort' => 't.category'],
        'party' => ['label' => $kind === 'income' ? 'From' : 'Paid to', 'sort' => 't.party'],
        'amount' => ['label' => 'Amount', 'sort' => 't.amount', 'type' => 'money', 'class' => 'r'],
        'method' => ['label' => 'Method'],
        'status' => ['label' => 'State', 'render' => static fn ($r) => ($r['status'] === 'due' ? '<span class="d-badge is-warn">To pay</span>' : '<span class="d-badge is-ok">Paid</span>') . ($r['deleted_at'] ? ' <span class="d-badge is-bad">Deleted</span>' : '')],
    ];
    $list = Listing::render($app->db, [
        'id' => 'ledger-' . $kind, 'select' => 't.*', 'from' => 'sc_ledger t', 'where' => ['t.tenant_id = ?', 't.kind = ?', $deleted ? 't.deleted_at IS NOT NULL' : 't.deleted_at IS NULL'], 'params' => [$p->tenantId, $kind],
        'columns' => $cols, 'search' => ['t.code', 't.party', 't.category', 't.description', 't.reference'], 'search_hint' => 'Search by name, category or number',
        'filters' => ['status' => ['label' => 'State', 'sql' => 't.status = ?', 'options' => ['paid' => 'Paid', 'due' => 'To pay']], 'category' => ['label' => 'Category', 'sql' => 't.category = ?', 'options' => array_combine(Accounts::categories($kind), Accounts::categories($kind))]],
        'sort' => 'entry_date', 'dir' => 'desc', 'actions' => $acts,
        'empty' => ['title' => 'No ' . ($kind === 'income' ? 'other income' : 'expenses') . ' yet', 'text' => $kind === 'income' ? 'Record money that did not come from an invoice, like a donation or a grant.' : 'Record rent, salaries, supplies and other costs here. Invoice payments need no entry.', 'cta' => $w ? ['label' => 'Add ' . $kind, 'url' => url('portal/accounts/entries/new', ['kind' => $kind])] : null],
    ]);
    $switch = '<p class="d-tools" style="gap:8px"><a class="d-btn ' . ($kind === 'expense' ? 'd-btn-primary' : 'd-btn-soft') . '" href="' . e(url('portal/accounts/entries', ['kind' => 'expense'])) . '">Expenses</a><a class="d-btn ' . ($kind === 'income' ? 'd-btn-primary' : 'd-btn-soft') . '" href="' . e(url('portal/accounts/entries', ['kind' => 'income'])) . '">Other income</a></p>';
    $top = ($w ? $btn('Add ' . $kind, url('portal/accounts/entries/new', ['kind' => $kind])) . ' ' : '') . '<a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/entries', ['kind' => $kind] + ($deleted ? [] : ['show' => 'deleted']))) . '">' . ($deleted ? 'Back to the list' : 'Deleted') . '</a>';
    $dash($p, 'accounts', $kind === 'income' ? 'Other income' : 'Expenses', $tabs('entries') . $switch . $card('', $list), ['actions' => $top, 'sub' => $kind === 'income' ? 'Money received that is not an invoice payment.' : 'Money you spend. Bills you pay later stay in Dues until you pay them.']);
});
$entryForm = static function (Portal $p, string $kind, ?array $row) use ($app, $dash, $card, $tabs, $entryFields): void {
    $fields = $entryFields($p, $kind, $row);
    $id = $row ? (int) $row['id'] : null;
    $h = '';
    if ($row) {
        $dl = '<dl class="d-kv"><dt>Number</dt><dd>' . e((string) $row['code']) . '</dd><dt>State</dt><dd>' . ($row['status'] === 'due' ? '<span class="d-badge is-warn">To pay</span>' : '<span class="d-badge is-ok">Paid</span>') . ($row['deleted_at'] ? ' <span class="d-badge is-bad">Deleted</span>' : '') . '</dd>'
            . ($row['paid_at'] ? '<dt>Paid</dt><dd>' . e(dt((string) $row['paid_at'], 'd M Y H:i')) . '</dd>' : '') . '<dt>Added</dt><dd>' . e(dt((string) $row['created_at'], 'd M Y H:i')) . '</dd></dl>';
        $a = ($p->writable() && !$row['deleted_at'] && $row['status'] === 'due') ? '<p style="margin-top:14px"><a class="d-btn d-btn-primary" href="' . e(url('portal/accounts/entries/' . $row['id'] . '/pay')) . '">Pay now</a></p>' : '';
        $h .= $card('Entry', $dl . $a);
    }
    $f = '<form class="d-form" method="post" action="' . e(url('portal/accounts/entries/save')) . '">' . csrf_field() . '<input type="hidden" name="kind" value="' . e($kind) . '">' . ($id ? '<input type="hidden" name="id" value="' . $id . '">' : '') . '<div class="d-form-grid">';
    foreach ($fields as $n => $fs) {
        $cur = $row ? (string) ($row[$n] ?? '') : (string) ($_GET[$n] ?? ($fs['default'] ?? ''));
        if ($n === 'amount' && $row) {
            $cur = (string) (float) $row['amount'];
        }
        $f .= Crud::field($n, $fs, old($n, $cur));
    }
    $f .= '</div><div class="d-form-actions">' . ($p->writable() ? '<button class="d-btn d-btn-primary" type="submit">Save</button>' : '') . '<a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/entries', ['kind' => $kind])) . '">Back</a></div></form>';
    clear_old();
    $h .= $card($id ? 'Details' : 'New ' . $kind, $f);
    $dash($p, 'accounts', $id ? ucfirst($kind) . ' ' . $row['code'] : 'Add ' . $kind, $tabs('entries') . '<div class="d-grid d-g2">' . $h . '</div>', ['sub' => $kind === 'income' ? 'Money received that did not come from an invoice.' : 'Money paid out, or a bill to pay later.']);
};
$app->router->get('/portal/accounts/entries/new', static function () use ($boot, $kindOf, $entryForm): void {
    $p = $boot();
    $p->needWritable();
    $entryForm($p, $kindOf(), null);
});
$app->router->post('/portal/accounts/entries/save', static function () use ($app, $boot, $acc, $kindOf, $entryFields, $loadEntry, $audit, $validDate): void {
    $p = $boot();
    $p->needWritable();
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? $loadEntry($p, $id) : null;
    if ($id && $old === null) {
        $app->error(404, 'We could not find that entry.');
    }
    $kind = $old ? (string) $old['kind'] : $kindOf();
    $back = $id ? url('portal/accounts/entries/' . $id) : url('portal/accounts/entries/new', ['kind' => $kind]);
    [$d, $err] = Crud::collect($entryFields($p, $kind, $old));
    if ($err === null && !$validDate((string) $d['entry_date'])) {
        $err = 'Please choose a date.';
    }
    if ($err === null && !in_array($d['category'], Accounts::categories($kind), true)) {
        $err = 'Please choose a category.';
    }
    if ($err === null && !empty($d['user_id']) && !$app->db->val('SELECT COUNT(*) FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL', [(int) $d['user_id'], $p->tenantId])) {
        $err = 'That staff member is not in your team.';
    }
    if ($err === null && $kind === 'expense' && ($d['status'] ?? 'paid') === 'due' && !empty($d['due_date']) && $d['due_date'] < $d['entry_date']) {
        $err = 'The pay-by date cannot be before the bill date.';
    }
    if ($err === null && $old !== null && $old['status'] === 'paid' && ($d['status'] ?? 'paid') === 'due') {
        $err = 'A paid entry cannot go back to "pay later". Delete it and add the bill again.';
    }
    if ($err !== null) {
        keep_old($_POST);
        flash($err, 'bad');
        redirect($back);
    }
    $d['amount'] = round((float) $d['amount'], 2);
    $d['user_id'] = !empty($d['user_id']) ? (int) $d['user_id'] : null;
    $status = $kind === 'income' ? 'paid' : (string) ($d['status'] ?? 'paid');
    $d['status'] = $status;
    $d['due_date'] = $status === 'due' ? ($d['due_date'] ?? null) : null;
    $now = date('Y-m-d H:i:s');
    if ($status === 'paid') {
        $time = $old && $old['paid_at'] ? date('H:i:s', strtotime((string) $old['paid_at'])) : ($d['entry_date'] === date('Y-m-d') ? date('H:i:s') : '12:00:00');
        $d['paid_at'] = $d['entry_date'] . ' ' . $time;
    } else {
        $d['paid_at'] = null;
    }
    if ($old === null) {
        $d += ['tenant_id' => $p->tenantId, 'kind' => $kind, 'code' => $acc->code($p->tenantId, $kind), 'created_by' => (int) $p->user['id'], 'created_at' => $now, 'updated_at' => $now];
        $id = $app->db->insert('sc_ledger', $d);
        $audit($p, 'ledger_added', null, ['id' => $id, 'kind' => $kind, 'amount' => $d['amount']]);
    } else {
        $d['updated_at'] = $now;
        $app->db->update('sc_ledger', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        $audit($p, 'ledger_edited', ['id' => $id, 'amount' => (float) $old['amount']], ['id' => $id, 'amount' => $d['amount']]);
    }
    flash('Saved.', 'ok');
    redirect(url('portal/accounts/entries', ['kind' => $kind]));
});
$app->router->get('/portal/accounts/entries/{id}', static function (array $a) use ($app, $boot, $loadEntry, $entryForm): void {
    $p = $boot();
    $row = $loadEntry($p, (int) $a['id']);
    if ($row === null) {
        $app->error(404, 'We could not find that entry.');
    }
    $entryForm($p, (string) $row['kind'], $row);
});
$app->router->get('/portal/accounts/entries/{id}/pay', static function (array $a) use ($app, $boot, $loadEntry, $dash, $card, $tabs): void {
    $p = $boot();
    $p->needWritable();
    $row = $loadEntry($p, (int) $a['id']);
    if ($row === null || $row['deleted_at'] || $row['status'] !== 'due') {
        flash('That bill is not waiting for payment.', 'bad');
        redirect(url('portal/accounts/entries'));
    }
    $h = '<form class="d-form" method="post" action="' . e(url('portal/accounts/entries/' . $row['id'] . '/pay')) . '">' . csrf_field() . '<div class="d-form-grid">'
        . Crud::field('method', ['label' => 'Paid by', 'type' => 'select', 'options' => array_combine(Billing::METHODS, Billing::METHODS), 'required' => true], old('method', 'Cash'))
        . Crud::field('reference', ['label' => 'Reference (cheque, transaction id)', 'max' => 120], old('reference')) . '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Pay ' . e(money((float) $row['amount'])) . '</button><a class="d-btn d-btn-soft" href="' . e(url('portal/accounts/dues')) . '">Back</a></div></form>';
    clear_old();
    $dash($p, 'accounts', 'Pay ' . $row['code'], $tabs('dues') . $card((string) $row['category'] . ($row['party'] ? ' to ' . $row['party'] : ''), $h), ['sub' => 'The payment goes into the cash book today.']);
});
$app->router->post('/portal/accounts/entries/{id}/pay', static function (array $a) use ($app, $boot, $loadEntry, $audit): void {
    $p = $boot();
    $p->needWritable();
    $row = $loadEntry($p, (int) $a['id']);
    $method = (string) ($_POST['method'] ?? 'Cash');
    if ($row === null || $row['deleted_at'] || $row['status'] !== 'due') {
        flash('That bill is not waiting for payment.', 'bad');
        redirect(url('portal/accounts/dues'));
    }
    $app->db->run("UPDATE sc_ledger SET status = 'paid', paid_at = NOW(), method = ?, reference = COALESCE(NULLIF(?, ''), reference), updated_at = NOW() WHERE id = ? AND tenant_id = ? AND status = 'due'", [in_array($method, Billing::METHODS, true) ? $method : 'Cash', mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 120), $row['id'], $p->tenantId]);
    $audit($p, 'ledger_paid', ['id' => (int) $row['id'], 'status' => 'due'], ['status' => 'paid', 'amount' => (float) $row['amount']]);
    flash('Paid and added to the cash book.', 'ok');
    redirect(url('portal/accounts/dues'));
});
$app->router->post('/portal/accounts/entries/{id}/delete', static function (array $a) use ($app, $boot, $loadEntry, $audit): void {
    $p = $boot();
    $p->needWritable();
    $row = $loadEntry($p, (int) $a['id']);
    if ($row !== null && !$row['deleted_at']) {
        $app->db->update('sc_ledger', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
        $audit($p, 'ledger_deleted', ['id' => (int) $row['id'], 'amount' => (float) $row['amount']], null);
        flash('Deleted. You can bring it back from the deleted list.', 'ok');
    }
    redirect(url('portal/accounts/entries', ['kind' => $row ? (string) $row['kind'] : 'expense']));
});
$app->router->post('/portal/accounts/entries/{id}/restore', static function (array $a) use ($app, $boot, $loadEntry, $audit): void {
    $p = $boot();
    $p->needWritable();
    $row = $loadEntry($p, (int) $a['id']);
    if ($row !== null && $row['deleted_at']) {
        $app->db->update('sc_ledger', ['deleted_at' => null], 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
        $audit($p, 'ledger_restored', null, ['id' => (int) $row['id']]);
        flash('Brought back.', 'ok');
    }
    redirect(url('portal/accounts/entries/' . (int) $a['id']));
});

// ---------------------------------------------------------------- dues
$app->router->get('/portal/accounts/dues', static function () use ($app, $boot, $acc, $dash, $card, $tabs, $table, $m, $pdfModel): void {
    $p = $boot();
    $t = $p->tenantId;
    $recv = $acc->receivable($t);
    $pay = $acc->payable($t);
    $rb = Accounts::ageing($recv, 'balance');
    $pb = Accounts::ageing($pay, 'amount');
    if (($_GET['format'] ?? '') === 'pdf') {
        $rr = array_map(static fn ($r) => [(string) $r['invoice_no'], dt((string) $r['invoice_date']), (string) $r['party_name'], (string) ($r['party_phone'] ?? ''), money((float) $r['total']), money((float) $r['balance']), max(0, (int) $r['age']) . ' d'], $recv);
        $pr = array_map(static fn ($r) => [(string) $r['ref'], dt((string) $r['d']), (string) $r['party'], (string) $r['label'], $r['due_date'] ? dt((string) $r['due_date']) : '', money((float) $r['amount'])], $pay);
        Documents::send($pdfModel($p, [['kv', 'title' => 'Dues on ' . date('d M Y'), 'items' => [['To collect', money(array_sum($rb))], ['Not yet due / current', money($rb[0])], ['1-30 days late', money($rb[1])], ['31-60 days late', money($rb[2])], ['Over 60 days late', money($rb[3])], ['To pay', money(array_sum($pb))]]],
            ['table', 'title' => 'Who owes us', 'cols' => [['Invoice', 1.5], ['Date', 1.2], ['Name', 2.4], ['Phone', 1.5], ['Total', 1.3, 'R'], ['Due', 1.3, 'R'], ['Age', 0.8, 'R']], 'rows' => $rr, 'size' => 8],
            ['table', 'title' => 'What we have to pay', 'cols' => [['No', 1.3], ['Date', 1.2], ['To', 2.4], ['For', 2], ['Pay by', 1.2], ['Amount', 1.4, 'R']], 'rows' => $pr, 'size' => 8]], 'Dues'), 'dues-' . date('Y-m-d') . '.pdf');
    }
    $rrows = array_map(static fn ($r) => ['<a href="' . e(url('portal/billing/' . $r['id'])) . '"><b>' . e((string) $r['invoice_no']) . '</b></a>', e(dt((string) $r['invoice_date'])), e((string) $r['party_name']), e((string) ($r['party_phone'] ?? '')), e(money((float) $r['total'])), '<b>' . e(money((float) $r['balance'])) . '</b>', (int) $r['age'] > 0 ? '<span class="d-badge is-' . ((int) $r['age'] > 30 ? 'bad' : 'warn') . '">' . (int) $r['age'] . ' d late</span>' : '<span class="d-mute">not late</span>'], $recv);
    $prows = array_map(static fn ($r) => [$r['src'] === 'invoice' ? '<a href="' . e(url('portal/billing/' . $r['id'])) . '"><b>' . e((string) $r['ref']) . '</b></a>' : '<a href="' . e(url('portal/accounts/entries/' . $r['id'])) . '"><b>' . e((string) $r['ref']) . '</b></a>', e((string) $r['party']), e((string) $r['label']), $r['due_date'] ? e(dt((string) $r['due_date'])) : '<span class="d-mute">—</span>', '<b>' . e(money((float) $r['amount'])) . '</b>', $r['src'] === 'invoice' ? '<a class="d-btn d-btn-soft" href="' . e(url('portal/billing/' . $r['id'])) . '">Open to pay</a>' : ($p->writable() ? '<a class="d-btn d-btn-primary" href="' . e(url('portal/accounts/entries/' . $r['id'] . '/pay')) . '">Pay now</a>' : '')], $pay);
    $sum = '<div class="d-stats"><div class="d-stat"><span>To collect</span><b style="font-size:1.4rem">' . e(money(array_sum($rb))) . '</b></div><div class="d-stat"><span>Late over 30 days</span><b style="font-size:1.4rem">' . e(money($rb[2] + $rb[3])) . '</b></div><div class="d-stat"><span>To pay</span><b style="font-size:1.4rem">' . e(money(array_sum($pb))) . '</b></div></div>';
    $top = '<a class="d-btn d-btn-primary" href="' . e(url('portal/accounts/dues', ['format' => 'pdf'])) . '">' . icon('file', 14) . ' Download PDF</a>';
    $dash($p, 'accounts', 'Dues', $tabs('dues') . $sum . $card('Who owes us', $table([['Invoice'], ['Date'], ['Name'], ['Phone'], ['Total', 'R'], ['Due', 'R'], ['Age']], $rrows, 'Nobody owes you anything')) . $card('What we have to pay', $table([['No'], ['To'], ['For'], ['Pay by'], ['Amount', 'R'], ['']], $prows, 'No bills waiting')), ['actions' => $top, 'sub' => 'Unpaid invoices to collect, and supplier invoices and bills to pay.']);
});
