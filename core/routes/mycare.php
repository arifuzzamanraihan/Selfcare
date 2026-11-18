<?php
// Selfcare original routes: "My care" for a signed-in patient of one vendor. A patient sees only their own records (patients.user_id = me, same vendor):
// issued prescriptions with PDF, bills and receipts with PDF, reports and forms with PDF, visits and tokens, and the requests they sent.
// /portal/my/prescriptions[/{id}[/pdf]]  /portal/my/bills[/{id}/pdf]  /portal/my/records[/{id}/pdf]  /portal/my/visits
declare(strict_types=1);

use Selfcare\{Billing, Documents, Listing, Portal, Rx};
use function Selfcare\{dt, e, icon, money, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$db = $app->db;

/** The patient row of the signed-in user, 404 for anyone else. */
$meBoot = static function () use ($app, $db): array {
    $p = Portal::boot($app);
    if ($p->role !== 'Patient') {
        $app->error(403, 'This page is for patients. Ask your administrator.', 'No access');
    }
    $pt = $db->one('SELECT p.id, p.patient_unique_id, p.allergies, p.conditions FROM patients p WHERE p.user_id = ? AND p.tenant_id = ?', [(int) $p->user['id'], $p->tenantId]);
    return [$p, $pt];
};
$noRecord = static fn (): string => '<div class="d-empty"><h3>Your record is not linked yet</h3><p>Ask the front desk to link your account to your patient record with your phone number. Then your prescriptions, bills and reports appear here.</p></div>';
$bill = new Billing($db);

$app->myOverview = static function (Portal $p) use ($db, $dash, $card, $btn, $stat, $meBoot, $noRecord): void {
    [, $pt] = $meBoot();
    $t = $p->tenantId;
    $rxOk = Rx::forType((string) $p->type['slug']) !== null && $p->module('documents');
    $h = '';
    if ($pt === null) {
        $h = $card('Welcome, ' . $p->user['first_name'], $noRecord() . '<p>' . $btn('My requests', url('portal/mine'), 'd-btn-soft') . ' ' . $btn('My account', url('portal/account'), 'd-btn-soft') . '</p>');
        $dash($p, 'overview', 'Overview', $h);
    }
    $pid = (int) $pt['id'];
    $n = static fn (string $sql, array $a = []): int => (int) $db->val($sql, array_merge([$t, $pid], $a));
    $due = (float) $db->val("SELECT COALESCE(SUM(balance), 0) FROM sc_invoices WHERE tenant_id = ? AND patient_id = ? AND status = 'issued' AND issuer = 'vendor'", [$t, $pid]);
    $next = $db->one("SELECT r.date1, r.code FROM sc_records r WHERE r.tenant_id = ? AND r.module = 'tokens' AND r.person_id = ? AND r.deleted_at IS NULL AND r.date1 >= CURDATE() AND r.status IN ('waiting','called','in_consult') ORDER BY r.date1 LIMIT 1", [$t, $pid]);
    $tiles = '<div class="d-stats">';
    if ($rxOk) {
        $tiles .= $stat('Prescriptions', (string) $n("SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ? AND patient_id = ? AND status = 'issued' AND deleted_at IS NULL"), 'file', 'ready to download');
    }
    $tiles .= $stat('To pay', money($due), 'card', $due > 0 ? 'open bills' : 'nothing due') . $stat('Visits', (string) $n("SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND person_id = ? AND module = 'tokens' AND deleted_at IS NULL"), 'cal', $next ? 'next: token ' . $next['code'] . ' on ' . dt((string) $next['date1']) : 'no visit planned') . '</div>';
    $links = ($rxOk ? $btn('My prescriptions', url('portal/my/prescriptions')) . ' ' : '') . $btn('My bills', url('portal/my/bills'), 'd-btn-soft') . ' ' . $btn('My reports and forms', url('portal/my/records'), 'd-btn-soft') . ' ' . $btn('My visits', url('portal/my/visits'), 'd-btn-soft') . ' ' . $btn('My account', url('portal/account'), 'd-btn-soft');
    $info = '<p>Patient ID <b>' . e((string) $pt['patient_unique_id']) . '</b>' . (trim((string) $pt['allergies']) !== '' ? ' · Allergies: ' . e((string) $pt['allergies']) : '') . '</p>';
    $dash($p, 'overview', 'Overview', $card('Welcome, ' . $p->user['first_name'], $info . '<p class="d-tools" style="gap:8px;flex-wrap:wrap">' . $links . '</p>') . $tiles);
};

// ---- prescriptions
$app->router->get('/portal/my/prescriptions', static function () use ($app, $meBoot, $noRecord, $db, $dash, $card): void {
    [$p, $pt] = $meBoot();
    if (Rx::forType((string) $p->type['slug']) === null) {
        $app->error(404, 'Prescriptions are not used here.');
    }
    if ($pt === null) {
        $dash($p, 'my-rx', 'My prescriptions', $card('', $noRecord()));
    }
    $list = Listing::render($db, [
        'id' => 'myrx', 'select' => 't.*, (SELECT GROUP_CONCAT(i.drug ORDER BY i.line_no SEPARATOR ", ") FROM sc_prescription_items i WHERE i.prescription_id = t.id AND i.tenant_id = t.tenant_id) AS drugs', 'from' => 'sc_prescriptions t',
        'where' => ['t.tenant_id = ?', 't.patient_id = ?', "t.status = 'issued'", 't.deleted_at IS NULL'], 'params' => [$p->tenantId, (int) $pt['id']],
        'columns' => [
            'rx_no' => ['label' => 'No', 'sort' => 't.rx_no', 'render' => static fn ($r) => '<a href="' . e(url('portal/my/prescriptions/' . $r['id'])) . '"><b>' . e((string) $r['rx_no']) . '</b></a>'],
            'rx_date' => ['label' => 'Date', 'sort' => 't.rx_date', 'type' => 'date'], 'doctor_name' => ['label' => 'Doctor'],
            'diagnosis' => ['label' => 'Diagnosis', 'render' => static fn ($r) => e(mb_strimwidth((string) $r['diagnosis'], 0, 50, '…'))],
            'drugs' => ['label' => 'Medicines', 'render' => static fn ($r) => e(mb_strimwidth((string) $r['drugs'], 0, 70, '…'))],
        ],
        'search' => ['t.rx_no', 't.diagnosis', 't.doctor_name'], 'search_hint' => 'Search by number, doctor or diagnosis', 'sort' => 'rx_date', 'dir' => 'desc',
        'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => static fn ($r) => url('portal/my/prescriptions/' . $r['id'])], ['label' => 'Download PDF', 'icon' => 'file', 'url' => static fn ($r) => url('portal/my/prescriptions/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'No prescriptions yet', 'text' => 'When your doctor issues a prescription it appears here with a PDF you can download.'],
    ]);
    $dash($p, 'my-rx', 'My prescriptions', $card('', $list), ['sub' => 'Only your own prescriptions are shown.']);
});
$myRx = static function (Portal $p, ?array $pt, int $id) use ($app, $db): array {
    $r = $pt ? $db->one("SELECT * FROM sc_prescriptions WHERE id = ? AND tenant_id = ? AND patient_id = ? AND status = 'issued' AND deleted_at IS NULL", [$id, $p->tenantId, (int) $pt['id']]) : null;
    $cfg = Rx::forType((string) $p->type['slug']);
    if ($r === null || $cfg === null) {
        $app->error(404, 'We could not find that prescription.');
    }
    return [$r, $cfg];
};
$app->router->get('/portal/my/prescriptions/{id}', static function (array $a) use ($meBoot, $myRx, $dash, $card, $btn, $app): void {
    [$p, $pt] = $meBoot();
    [$r, $cfg] = $myRx($p, $pt, (int) $a['id']);
    $bar = '<div class="dx-bar"><a class="d-btn d-btn-primary" href="' . e(url('portal/my/prescriptions/' . $r['id'] . '/pdf')) . '">' . icon('file', 16) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button><a class="d-btn d-btn-soft" href="' . e(url('portal/my/prescriptions')) . '">Back to my prescriptions</a></div>';
    $rxs = new Rx($app->db);
    $send = $card('Send to a pharmacy', '<p class="d-help">Pick the pharmacy that will prepare your medicines. You choose who sees it.</p>' . $rxs->shareBox(url('portal/my/prescriptions/' . $r['id'] . '/send'), $r, 'patient'));
    $dash($p, 'my-rx', (string) $r['rx_no'], $bar . Documents::html(($app->rxModel)($p, $cfg, $r)) . $send);
});
$app->router->get('/portal/my/prescriptions/{id}/pdf', static function (array $a) use ($meBoot, $myRx, $app): void {
    [$p, $pt] = $meBoot();
    [$r, $cfg] = $myRx($p, $pt, (int) $a['id']);
    Documents::send(($app->rxModel)($p, $cfg, $r), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $r['rx_no']) . '.pdf');
});

// ---- bills and receipts
$app->router->get('/portal/my/bills', static function () use ($meBoot, $noRecord, $db, $dash, $card): void {
    [$p, $pt] = $meBoot();
    if ($pt === null) {
        $dash($p, 'my-bills', 'My bills', $card('', $noRecord()));
    }
    $list = Listing::render($db, [
        'id' => 'mybills', 'select' => 'i.*', 'from' => 'sc_invoices i', 'where' => ['i.tenant_id = ?', 'i.patient_id = ?', "i.issuer = 'vendor'", "i.status <> 'void'"], 'params' => [$p->tenantId, (int) $pt['id']],
        'columns' => [
            'invoice_no' => ['label' => 'Number', 'sort' => 'i.invoice_no', 'render' => static fn ($r) => '<b>' . e((string) $r['invoice_no']) . '</b>'], 'invoice_date' => ['label' => 'Date', 'sort' => 'i.invoice_date', 'type' => 'date'],
            'total' => ['label' => 'Total', 'render' => static fn ($r) => e(money((float) $r['total'], (string) $r['currency']))], 'paid' => ['label' => 'Paid', 'render' => static fn ($r) => e(money((float) $r['paid'], (string) $r['currency']))],
            'balance' => ['label' => 'To pay', 'render' => static fn ($r) => (float) $r['balance'] > 0 ? '<b>' . e(money((float) $r['balance'], (string) $r['currency'])) . '</b>' : '<span class="d-badge is-ok">Paid</span>'],
        ],
        'search' => ['i.invoice_no', 'i.reference'], 'sort' => 'invoice_date', 'dir' => 'desc',
        'actions' => [['label' => 'Download PDF', 'icon' => 'file', 'url' => static fn ($r) => url('portal/my/bills/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'No bills yet', 'text' => 'Your bills and receipts appear here after a visit.'],
    ]);
    $dash($p, 'my-bills', 'My bills', $card('', $list), ['sub' => 'Bills and receipts issued to you.']);
});
$app->router->get('/portal/my/bills/{id}/pdf', static function (array $a) use ($app, $meBoot, $db, $bill): void {
    [$p, $pt] = $meBoot();
    $inv = $pt ? $db->one("SELECT * FROM sc_invoices WHERE id = ? AND tenant_id = ? AND patient_id = ? AND issuer = 'vendor' AND status <> 'void'", [(int) $a['id'], $p->tenantId, (int) $pt['id']]) : null;
    if ($inv === null) {
        $app->error(404, 'We could not find that bill.');
    }
    Documents::send($bill->invoiceModel($app, $inv, $p->user), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $inv['invoice_no']) . '.pdf');
});

// ---- reports and forms (lab results, imaging reports, discharge summaries, certificates ...)
$app->router->get('/portal/my/records', static function () use ($meBoot, $noRecord, $db, $dash, $card, $bill): void {
    [$p, $pt] = $meBoot();
    if ($pt === null) {
        $dash($p, 'my-records', 'My reports and forms', $card('', $noRecord()));
    }
    $forms = $bill->catalog((string) $p->type['slug'], $p->family)['forms'];
    $list = Listing::render($db, [
        'id' => 'myrec', 'select' => 'd.*', 'from' => 'sc_documents d', 'where' => ['d.tenant_id = ?', 'd.patient_id = ?', "d.status = 'final'", "d.doc_type IN ('" . implode("','", array_map(static fn ($k) => preg_replace('/[^a-z0-9_]/', '', (string) $k), array_keys($forms))) . "')"], 'params' => [$p->tenantId, (int) $pt['id']],
        'columns' => [
            'doc_no' => ['label' => 'Number', 'sort' => 'd.doc_no', 'render' => static fn ($r) => '<b>' . e((string) $r['doc_no']) . '</b>'],
            'doc_type' => ['label' => 'Kind', 'render' => static fn ($r) => e((string) ($forms[$r['doc_type']]['label'] ?? $r['doc_type']))], 'doc_date' => ['label' => 'Date', 'sort' => 'd.doc_date', 'type' => 'date'],
        ],
        'search' => ['d.doc_no'], 'sort' => 'doc_date', 'dir' => 'desc',
        'actions' => [['label' => 'Download PDF', 'icon' => 'file', 'url' => static fn ($r) => url('portal/my/records/' . $r['id'] . '/pdf')]],
        'empty' => ['title' => 'No reports yet', 'text' => 'Lab results, imaging reports, certificates and discharge summaries for you appear here.'],
    ]);
    $dash($p, 'my-records', 'My reports and forms', $card('', $list), ['sub' => 'Records the team finished and linked to you.']);
});
$app->router->get('/portal/my/records/{id}/pdf', static function (array $a) use ($app, $meBoot, $db, $bill): void {
    [$p, $pt] = $meBoot();
    $d = $pt ? $db->one("SELECT * FROM sc_documents WHERE id = ? AND tenant_id = ? AND patient_id = ? AND status = 'final'", [(int) $a['id'], $p->tenantId, (int) $pt['id']]) : null;
    $form = $d ? ($bill->catalog((string) $p->type['slug'], $p->family)['forms'][$d['doc_type']] ?? null) : null;
    if ($d === null || $form === null) {
        $app->error(404, 'We could not find that record.');
    }
    Documents::send($bill->formModel($app, $p->tenantId, $form, $d, $p->user), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $d['doc_no']) . '.pdf');
});

// ---- visits (queue tokens) and appointments
$app->router->get('/portal/my/visits', static function () use ($meBoot, $noRecord, $db, $dash, $card): void {
    [$p, $pt] = $meBoot();
    if ($pt === null) {
        $dash($p, 'my-visits', 'My visits', $card('', $noRecord()));
    }
    $rows = $db->all("SELECT r.date1, r.code, r.cat, r.status, r.party FROM sc_records r WHERE r.tenant_id = ? AND r.module = 'tokens' AND r.person_id = ? AND r.deleted_at IS NULL ORDER BY r.date1 DESC, r.id DESC LIMIT 60", [$p->tenantId, (int) $pt['id']]);
    $lab = ['waiting' => 'Waiting', 'called' => 'Called', 'in_consult' => 'With the doctor', 'done' => 'Seen', 'skipped' => 'Missed', 'cancelled' => 'Cancelled'];
    $h = '';
    foreach ($rows as $r) {
        $h .= '<tr><td data-label="Date">' . e(dt((string) $r['date1'])) . '</td><td data-label="Token">' . e((string) $r['code']) . '</td><td data-label="Doctor">' . e((string) $r['party']) . '</td><td data-label="Visit">' . e((string) $r['cat']) . '</td><td data-label="State">' . e($lab[$r['status']] ?? (string) $r['status']) . '</td></tr>';
    }
    $dash($p, 'my-visits', 'My visits', $card('', $rows ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Date</th><th>Token</th><th>Doctor</th><th>Visit</th><th>State</th></tr></thead><tbody>' . $h . '</tbody></table></div>' : '<div class="d-empty"><h3>No visits yet</h3><p>Your visits and queue tokens appear here.</p></div>'), ['sub' => 'Your visits at ' . $p->vendor['name'] . '.']);
});

$app->router->post('/portal/my/prescriptions/{id}/send', static function (array $a) use ($app, $meBoot, $myRx): void {
    [$p, $pt] = $meBoot();
    [$r] = $myRx($p, $pt, (int) $a['id']);
    $p->needWritable();
    $back = url('portal/my/prescriptions/' . $r['id']);
    if (empty($_POST['agree'])) {
        \Selfcare\flash('Please tick the box to agree.', 'bad');
        \Selfcare\redirect($back);
    }
    $err = (new Rx($app->db))->sendToPharmacy($r, (string) ($_POST['pharmacy'] ?? ''), 'patient', (int) $p->user['id'], (string) $p->vendor['name']);
    if ($err === null) {
        $app->access->log($p->tenantId, (int) $p->user['id'], null, null, 'rx_sent_to_pharmacy', null, ['rx' => (int) $r['id'], 'to' => (string) $_POST['pharmacy'], 'by' => 'patient']);
    }
    \Selfcare\flash($err ?? 'Sent. The pharmacy will prepare your medicines.', $err ? 'bad' : 'ok');
    \Selfcare\redirect($back);
});
