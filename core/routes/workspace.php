<?php
// Selfcare original routes: the doctor's one-screen workspace. Left: today's patient queue (auto token numbers, drag or arrow reorder, skip, bring back).
// Right: the patient in front of the doctor (allergies, vitals, earlier prescriptions) and the prescription editor, with "Issue and call next patient".
// The queue is the clinic work list "tokens" (sc_records) so the front desk and the doctor see the same list. Permission: manage_rx.
// /portal/workspace [?token=ID&doctor=ID]   POST /portal/workspace/call-next   POST /portal/workspace/{id}/{start|skip|requeue|finish}
declare(strict_types=1);

use Selfcare\{Ops, Portal, Rx};
use function Selfcare\{csrf_field, dt, e, flash, icon, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn] = $app->ui;
$db = $app->db;
$rxs = new Rx($db);

$wsBoot = static function () use ($app, $rxs): array {
    $p = Portal::boot($app);
    $m = Ops::all()['tokens'] ?? null;
    $cfg = Rx::forType((string) $p->type['slug']);
    if ($m === null || $cfg === null || !in_array((string) $p->type['slug'], $m['types'], true)) {
        $app->error(404, 'The doctor workspace is not used by your kind of organisation.');
    }
    $p->need('manage_rx', 'documents');
    $own = $rxs->doctorOf($p->tenantId, (int) $p->user['id']);
    $doc = $own;
    if ($own === null) {
        $want = (int) ($_GET['doctor'] ?? $_POST['doctor'] ?? 0);
        foreach ($rxs->doctors($p->tenantId) as $d) {
            if ($want === (int) $d['id'] || ($want === 0 && $doc === null)) {
                $doc = $d;
            }
        }
    }
    return [$p, $m, $cfg, $doc, $own !== null];
};
$tokenOf = static fn (Portal $p, int $id): ?array => $id > 0 ? $db->one("SELECT * FROM sc_records WHERE id = ? AND tenant_id = ? AND module = 'tokens' AND deleted_at IS NULL", [$id, $p->tenantId]) : null;
$mine = static fn (?array $doc): string => $doc ? ' AND (staff_id = ' . (int) $doc['id'] . ' OR staff_id IS NULL)' : '';

$app->router->get('/portal/workspace', static function () use ($app, $wsBoot, $tokenOf, $mine, $dash, $card, $btn, $db, $rxs): void {
    [$p, $m, $cfg, $doc, $isDoc] = $wsBoot();
    $t = $p->tenantId;
    $w = $p->writable();
    $today = date('Y-m-d');
    $rows = $db->all("SELECT * FROM sc_records WHERE tenant_id = ? AND module = 'tokens' AND deleted_at IS NULL AND date1 = ? AND status IN ('waiting','called','in_consult','skipped')" . $mine($doc) . " ORDER BY FIELD(status,'in_consult','called','waiting','skipped'), sort_order ASC, id ASC", [$t, $today]);
    $done = (int) $db->val("SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = 'tokens' AND deleted_at IS NULL AND date1 = ? AND status = 'done'" . $mine($doc), [$t, $today]);
    $sel = $tokenOf($p, (int) ($_GET['token'] ?? 0));
    if ($sel === null || $sel['date1'] !== $today) {
        $sel = null;
        foreach ($rows as $r) {
            if (in_array($r['status'], ['in_consult', 'called'], true)) {
                $sel = $r;
                break;
            }
        }
    }
    // ---- doctor picker for people who are not doctors (owner, manager)
    $top = '';
    if (!$isDoc) {
        $top .= '<form class="d-queue-toolbar" method="get" action="' . e(url('portal/workspace')) . '"><label class="d-field" style="margin:0"><span class="d-help">Doctor</span><select name="doctor" onchange="this.form.submit()">';
        foreach ($rxs->doctors($t) as $d) {
            $top .= '<option value="' . (int) $d['id'] . '"' . ($doc && (int) $doc['id'] === (int) $d['id'] ? ' selected' : '') . '>' . e(Rx::doctorLabel($d)) . '</option>';
        }
        $top .= '</select></label></form>';
    }
    $dq = $doc ? ['doctor' => (string) $doc['id']] : [];
    // ---- queue panel
    $q = '<div class="ws-queue" data-ops-queue data-reorder-url="' . e(url('portal/ops/tokens/reorder')) . '"><form method="post" data-reorder action="' . e(url('portal/ops/tokens/reorder')) . '" hidden>' . csrf_field() . '</form>';
    $q .= '<div class="ws-qhead"><b>Today\'s queue</b><span class="d-mute"> ' . count($rows) . ' waiting · ' . $done . ' seen</span></div>';
    if ($w) {
        $q .= '<form method="post" action="' . e(url('portal/workspace/call-next')) . '" class="ws-callnext">' . csrf_field() . ($doc ? '<input type="hidden" name="doctor" value="' . (int) $doc['id'] . '">' : '') . '<button class="d-btn d-btn-primary" type="submit">' . icon('play', 16) . ' Call next patient</button>'
            . '<a class="d-btn d-btn-soft" href="' . e(Ops::url('tokens', 'new', ['date1' => $today] + ($doc ? ['staff_id' => (string) $doc['id']] : []))) . '">' . icon('plus', 16) . ' Add to queue</a></form>';
    }
    if (!$rows) {
        $q .= '<div class="d-empty"><h3>Nobody is waiting</h3><p>New patients appear here when the front desk gives them a token.</p></div>';
    } else {
        $q .= '<table class="d-queue-table ws-qtable"><tbody>';
        foreach ($rows as $r) {
            $on = $sel && (int) $sel['id'] === (int) $r['id'];
            $pri = strtolower((string) $r['tag']);
            $q .= '<tr data-id="' . (int) $r['id'] . '" draggable="true" tabindex="0" class="ws-qrow' . ($on ? ' is-on' : '') . ($r['status'] === 'skipped' ? ' is-skipped' : '') . '">'
                . '<td class="d-q-handle" title="Drag to change the order" aria-hidden="true">☰</td>'
                . '<td class="ws-tok">' . e((string) $r['code']) . '</td>'
                . '<td class="ws-who"><a href="' . e(url('portal/workspace', ['token' => (string) $r['id']] + $dq)) . '"><b>' . e((string) $r['title']) . '</b></a><br><small class="d-mute">' . e(trim((string) $r['cat'] . ($pri !== '' && $pri !== 'normal' ? ' · ' . $r['tag'] : ''), ' ·')) . '</small> ' . Ops::badge($m, (string) $r['status']) . '</td>'
                . '<td class="ws-act"><span class="d-queue-move"><button type="button" class="d-btn d-btn-soft" data-move="up" title="Move up (Alt+Up)" aria-label="Move up">↑</button><button type="button" class="d-btn d-btn-soft" data-move="down" title="Move down (Alt+Down)" aria-label="Move down">↓</button></span>';
            if ($w) {
                $btnf = static fn (string $act, string $label, string $cls = 'd-btn-soft') => '<form class="d-inline" method="post" action="' . e(url('portal/workspace/' . (int) $r['id'] . '/' . $act)) . '">' . csrf_field() . '<button class="d-btn ' . $cls . '" type="submit">' . e($label) . '</button></form>';
                if (in_array($r['status'], ['waiting', 'called', 'skipped'], true)) {
                    $q .= '';
                    $q2 = $btnf('start', 'See now', 'd-btn-primary');
                } else {
                    $q2 = '';
                }
                $q3 = in_array($r['status'], ['waiting', 'called'], true) ? $btnf('skip', 'Skip') : ($r['status'] === 'skipped' ? $btnf('requeue', 'Back to waiting') : '');
                $q .= $q2 . $q3;
            }
            $q .= '</td></tr>';
        }
        $q .= '</tbody></table>';
    }
    $q .= '</div>';
    // ---- patient + editor panel
    $main = '';
    if ($sel === null) {
        $main = '<div class="d-empty"><h3>No patient in front of you</h3><p>' . ($rows ? 'Press <b>Call next patient</b> or choose someone from the queue.' : 'The queue is empty. You can still write a prescription for a walk-in.') . '</p><p><a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/new')) . '">New prescription without a token</a></p></div>';
    } else {
        $pt = $sel['person_id'] ? $db->one('SELECT p.id, p.patient_unique_id, p.allergies, p.conditions, u.first_name, u.last_name, u.phone, u.gender, u.dob FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ?', [(int) $sel['person_id'], $t]) : null;
        $sexs = [0 => 'Male', 1 => 'Female', 2 => 'Other'];
        $hist = $pt ? $db->all("SELECT id, rx_no, rx_date, diagnosis, status FROM sc_prescriptions WHERE tenant_id = ? AND patient_id = ? AND deleted_at IS NULL AND status <> 'cancelled' ORDER BY rx_date DESC, id DESC LIMIT 5", [$t, (int) $pt['id']]) : [];
        $lastVit = $pt ? $db->one("SELECT vitals, rx_date FROM sc_prescriptions WHERE tenant_id = ? AND patient_id = ? AND vitals IS NOT NULL AND deleted_at IS NULL ORDER BY rx_date DESC, id DESC LIMIT 1", [$t, (int) $pt['id']]) : null;
        $hdr = '<div class="ws-patient"><div class="ws-ptitle"><span class="ws-tokbig">' . e((string) $sel['code']) . '</span><div><h2 style="margin:0">' . e((string) $sel['title']) . '</h2><span class="d-mute">'
            . e(trim(($pt ? ($sexs[(int) $pt['gender']] ?? '') . ' · ' . Rx::age($pt['dob']) . ' · ' : '') . ($sel['phone'] ?: ($pt['phone'] ?? '')) . ($pt ? ' · ID ' . $pt['patient_unique_id'] : ' · walk-in'), ' ·')) . '</span></div> ' . Ops::badge($m, (string) $sel['status']) . '</div>';
        if (trim((string) ($pt['allergies'] ?? '')) !== '' && strtolower(trim((string) $pt['allergies'])) !== 'none') {
            $hdr .= '<div class="d-alert is-danger"><b>Allergies:</b> ' . e((string) $pt['allergies']) . '</div>';
        }
        if (!empty($sel['notes'])) {
            $hdr .= '<p><b>Reason for visit:</b> ' . e((string) $sel['notes']) . '</p>';
        }
        if ($pt && trim((string) $pt['conditions']) !== '') {
            $hdr .= '<p class="d-help"><b>Conditions:</b> ' . e((string) $pt['conditions']) . '</p>';
        }
        if ($lastVit) {
            $v = (array) json_decode((string) $lastVit['vitals'], true);
            $hdr .= '<p class="d-help"><b>Last vitals (' . e(dt((string) $lastVit['rx_date'])) . '):</b> ' . e(implode(' · ', array_map(static fn ($k, $x) => $k . ' ' . $x, array_keys($v), $v))) . '</p>';
        }
        if ($hist) {
            $hdr .= '<details class="ws-hist"><summary>Earlier prescriptions (' . count($hist) . ')</summary><ul>';
            foreach ($hist as $h) {
                $hdr .= '<li><a href="' . e(url('portal/prescriptions/' . $h['id'])) . '" target="_blank" rel="noopener">' . e((string) $h['rx_no']) . '</a> · ' . e(dt((string) $h['rx_date'])) . ' · ' . e(mb_strimwidth((string) $h['diagnosis'], 0, 60, '…')) . '</li>';
            }
            $hdr .= '</ul></details>';
        }
        $hdr .= '</div>';
        $existing = $db->one("SELECT * FROM sc_prescriptions WHERE tenant_id = ? AND queue_record_id = ? AND deleted_at IS NULL AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$t, (int) $sel['id']]);
        if ($existing !== null && $existing['status'] === 'issued') {
            $main = $hdr . $card('Prescription issued', '<p>' . e((string) $existing['rx_no']) . ' was issued for this visit.</p><p class="d-tools" style="gap:8px;flex-wrap:wrap"><a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e(url('portal/prescriptions/' . $existing['id'] . '/pdf')) . '">Open PDF</a>'
                . ($w ? '<form class="d-inline" method="post" action="' . e(url('portal/workspace/' . (int) $sel['id'] . '/finish')) . '">' . csrf_field() . '<button class="d-btn d-btn-primary" type="submit">Finish and call next patient</button></form>' : '') . '</p>');
        } elseif (!$w) {
            $main = $hdr . $card('', '<p class="d-help">Your account can read the queue but not write prescriptions now.</p>');
        } else {
            $pre = ['_queue' => (int) $sel['id']];
            if ($existing === null) {
                $pre += ['patient_name' => (string) $sel['title'], 'patient_phone' => (string) $sel['phone'], 'complaints' => (string) $sel['notes'], 'doctor_id' => $doc ? (string) $doc['id'] : ''];
                if ($pt) {
                    $pre += ['patient_id' => (string) $pt['id'], 'sex' => $sexs[(int) $pt['gender']] ?? '', 'age' => Rx::age($pt['dob']), 'allergies' => (string) $pt['allergies']];
                    if ($lastVit === null) {
                        $pre['vitals'] = [];
                    }
                }
            }
            $form = ($app->rxForm)($p, $cfg, $existing, $existing ? $rxs->items($t, (int) $existing['id']) : [], $pre);
            $main = $hdr . $card('Prescription', $form);
        }
    }
    $dash($p, 'workspace', 'Doctor workspace', $top . '<div class="ws-grid"><aside class="ws-side">' . $q . '</aside><section class="ws-main">' . $main . '</section></div>', ['sub' => ($doc ? Rx::doctorLabel($doc) . ' · ' : '') . dt($today, 'l, d M Y')]);
});

// ---- queue actions that keep the doctor on this screen
$app->router->post('/portal/workspace/call-next', static function () use ($app, $wsBoot, $db): void {
    [$p, , , $doc] = $wsBoot();
    $p->needWritable();
    $cur = $db->one("SELECT id FROM sc_records WHERE tenant_id = ? AND module = 'tokens' AND deleted_at IS NULL AND date1 = ? AND status IN ('in_consult','called')" . ($doc ? ' AND (staff_id = ' . (int) $doc['id'] . ' OR staff_id IS NULL)' : '') . ' ORDER BY id LIMIT 1', [$p->tenantId, date('Y-m-d')]);
    $nx = Rx::queueAdvance($db, $p, (int) ($doc['id'] ?? 0), $cur ? (int) $cur['id'] : null);
    flash($nx ? 'Next patient called.' : 'There is no one waiting.', $nx ? 'ok' : 'info');
    redirect(url('portal/workspace', array_filter(['token' => $nx ? (string) $nx : '', 'doctor' => $doc ? (string) $doc['id'] : ''])));
});
$app->router->post('/portal/workspace/{id}/{act}', static function (array $a) use ($app, $wsBoot, $tokenOf, $db): void {
    [$p, , , $doc] = $wsBoot();
    $p->needWritable();
    $r = $tokenOf($p, (int) $a['id']);
    if ($r === null || !in_array($a['act'], ['start', 'skip', 'requeue', 'finish'], true)) {
        $app->error(404, 'We could not find that token.');
    }
    $back = static fn (?int $tok) => redirect(url('portal/workspace', array_filter(['token' => $tok ? (string) $tok : '', 'doctor' => $doc ? (string) $doc['id'] : ''])));
    $t = $p->tenantId;
    $uid = (int) $p->user['id'];
    $set = static function (array $r, string $to, string $act) use ($db, $t, $uid): void {
        $db->update('sc_records', ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [(int) $r['id'], $t]);
        Ops::event($db, $t, (int) $r['id'], 'tokens', $act, (string) $r['status'], $to, $uid);
    };
    switch ($a['act']) {
        case 'start':
            if (!in_array($r['status'], ['waiting', 'called', 'skipped'], true)) {
                flash('That patient is not waiting.', 'bad');
                $back((int) $r['id']);
            }
            // anyone already being seen by this doctor goes back to the queue
            $others = $db->all("SELECT * FROM sc_records WHERE tenant_id = ? AND module = 'tokens' AND deleted_at IS NULL AND date1 = ? AND status IN ('in_consult','called') AND id <> ?" . ($doc ? ' AND (staff_id = ' . (int) $doc['id'] . ' OR staff_id IS NULL)' : ''), [$t, date('Y-m-d'), (int) $r['id']]);
            foreach ($others as $o) {
                $set($o, 'waiting', 'requeue');
            }
            $set($r, 'in_consult', 'start');
            $back((int) $r['id']);
            // no break: redirect exits
        case 'skip':
            if (in_array($r['status'], ['waiting', 'called', 'in_consult'], true)) {
                $set($r, 'skipped', 'skip');
                $db->run('UPDATE sc_records SET sort_order = (SELECT m FROM (SELECT COALESCE(MAX(sort_order), 0) + 1 m FROM sc_records WHERE tenant_id = ? AND module = ? AND date1 = ?) x) WHERE id = ? AND tenant_id = ?', [$t, 'tokens', $r['date1'], (int) $r['id'], $t]);
                flash('Skipped. They can be brought back from the queue.', 'ok');
            }
            $back(null);
        case 'requeue':
            if ($r['status'] === 'skipped') {
                $set($r, 'waiting', 'requeue');
                flash('Back in the queue.', 'ok');
            }
            $back(null);
        case 'finish':
            $nx = Rx::queueAdvance($db, $p, (int) ($doc['id'] ?? 0), (int) $r['id']);
            flash($nx ? 'Done. Next patient called.' : 'Done. No one else is waiting.', 'ok');
            $back($nx);
    }
});
