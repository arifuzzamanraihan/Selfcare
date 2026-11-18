<?php
// Selfcare original routes: structured prescriptions for the kinds of vendor whose doctors prescribe (see core/config/rx.php).
// Logic and safety checks are in core/Rx.php. Permissions: manage_rx (write, issue, copy, cancel) and view_rx (read, print). Plan module: documents.
// /portal/prescriptions               list + tiles          /new        write one          /{id}  view   /{id}/edit  (drafts only)   /{id}/pdf
// /portal/prescriptions/save          POST (draft or issue) /{id}/issue  /{id}/cancel  /{id}/delete (drafts)  /{id}/copy
// /portal/prescriptions/patient/{id}  one patient's history /blank  blank pad PDF       /templates  saved sets of medicines
declare(strict_types=1);

use Selfcare\{Billing, Crud, Documents, Listing, Portal, Rx};
use function Selfcare\{csrf_field, dt, e, flash, icon, money, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn, 'stat' => $stat] = $app->ui;
$rx = new Rx($app->db);
$db = $app->db;
$cfgAll = Rx::cfg();

$boot = static function (bool $write = false) use ($app): array {
    $p = Portal::boot($app);
    $cfg = Rx::forType((string) $p->type['slug']);
    if ($cfg === null) {
        $app->error(404, 'Prescriptions are not used by your kind of organisation.');
    }
    if ($write) {
        $p->need('manage_rx', 'documents');
    } elseif ($p->can('manage_rx')) {
        $p->need('manage_rx', 'documents');
    } else {
        $p->need('view_rx', 'documents');
    }
    return [$p, $cfg];
};
$audit = static function (Portal $p, string $action, mixed $before, mixed $after, ?string $reason = null) use ($app): void {
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, $action, $before, $after, $reason);
};
$load = static function (Portal $p, array $a, bool $any = false) use ($app, $rx): array {
    $r = $rx->find($p->tenantId, (int) $a['id']);
    if ($r === null || ($r['deleted_at'] !== null && !$any)) {
        $app->error(404, 'We could not find that prescription.');
    }
    return $r;
};
$validDate = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', strtotime($d)) === $d;
$badge = static fn (string $s): string => match ($s) { 'issued' => '<span class="d-badge is-ok">Issued</span>', 'cancelled' => '<span class="d-badge is-bad">Cancelled</span>', default => '<span class="d-badge is-warn">Draft</span>' };
$tabs = static function (string $on, Portal $p) use ($btn): string {
    $t = ['' => 'Prescriptions', 'templates' => 'Saved sets'];
    $h = '<p class="d-tools" style="gap:8px;flex-wrap:wrap">';
    foreach ($t as $k => $label) {
        $h .= '<a class="d-btn ' . ($k === $on ? 'd-btn-primary' : 'd-btn-soft') . '" href="' . e(url('portal/prescriptions' . ($k !== '' ? '/' . $k : ''))) . '">' . e($label) . '</a>';
    }
    return $h . '</p>';
};
$page = static function (Portal $p, string $title, string $body, array $o = []) use ($dash): void {
    $dash($p, 'prescriptions', $title, $body, $o);
};

// ---------------------------------------------------------------- form
$formHtml = static function (Portal $p, array $cfg, ?array $row, array $items, array $pre) use ($app, $rx, $cfgAll, $card): string {
    $t = $p->tenantId;
    $old = $_SESSION['sc_rx_old'] ?? null;
    unset($_SESSION['sc_rx_old']);
    $g = static fn (string $k, mixed $d = '') => is_array($old) && array_key_exists($k, $old) ? $old[$k] : ($pre[$k] ?? ($row[$k] ?? $d));
    if (is_array($old) && isset($old['drug'])) {
        $items = Rx::postedItems($old);
    }
    $patients = $rx->patients($t);
    $docs = $rx->doctors($t);
    $own = $rx->doctorOf($t, (int) $p->user['id']);
    $vit = $row && $row['vitals'] ? (array) json_decode((string) $row['vitals'], true) : (array) ($pre['vitals'] ?? []);
    $extra = $row && $row['extra'] ? (array) json_decode((string) $row['extra'], true) : [];
    $sel = static fn (string $name, array $opts, string $val, string $blank = '', string $attr = ''): string => '<select name="' . e($name) . '" ' . $attr . '>' . ($blank !== '' ? '<option value="">' . e($blank) . '</option>' : '') . implode('', array_map(static fn ($o) => '<option value="' . e((string) $o) . '"' . ((string) $o === $val ? ' selected' : '') . '>' . e((string) $o) . '</option>', $opts)) . '</select>';
    $h = '<form class="d-form" method="post" action="' . e(url('portal/prescriptions/save')) . '" autocomplete="off" data-rx>' . csrf_field() . ($row ? '<input type="hidden" name="id" value="' . (int) $row['id'] . '">' : '');
    $h .= '<datalist id="rx-drugs">' . implode('', array_map(static fn ($d) => '<option value="' . e($d) . '">', array_values(array_unique(array_merge($rx->usedDrugs($t), Rx::drugs()))))) . '</datalist>';
    $h .= '<datalist id="rx-freq">' . implode('', array_map(static fn ($d) => '<option value="' . e($d) . '">', $cfgAll['freq'])) . '</datalist>';
    // patient
    $h .= '<h3>Patient</h3><div class="d-form-grid">';
    $pid = (string) $g('patient_id', '');
    $po = '<option value="">Walk-in (type the name below)</option>';
    foreach ($patients as $x) {
        $nm = trim($x['first_name'] . ' ' . $x['last_name']);
        $po .= '<option value="' . (int) $x['id'] . '"' . ((string) $x['id'] === $pid ? ' selected' : '') . ' data-name="' . e($nm) . '" data-phone="' . e((string) $x['phone']) . '" data-sex="' . e([0 => 'Male', 1 => 'Female', 2 => 'Other'][(int) $x['gender']] ?? '') . '" data-age="' . e(Rx::age($x['dob'])) . '" data-allergies="' . e((string) $x['allergies']) . '">' . e($nm . ' (' . $x['patient_unique_id'] . ')') . '</option>';
    }
    $h .= '<div class="d-field is-wide"><label for="f_patient_id">Registered patient</label><select id="f_patient_id" name="patient_id" data-patient>' . $po . '</select></div>';
    $h .= Crud::field('patient_name', ['label' => 'Patient name', 'max' => 190, 'required' => true], (string) $g('patient_name'));
    $h .= Crud::field('patient_phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40], (string) $g('patient_phone'));
    $h .= Crud::field('age', ['label' => 'Age', 'max' => 20], (string) $g('age'));
    $h .= Crud::field('sex', ['label' => 'Sex', 'type' => 'select', 'options' => array_combine($cfgAll['sex'], $cfgAll['sex']), 'blank_label' => 'Choose…'], (string) $g('sex'));
    $h .= Crud::field('weight', ['label' => 'Weight (kg)', 'type' => 'number', 'step' => '0.1', 'min' => 0], (string) ($g('weight') ?: ''));
    $h .= Crud::field('rx_date', ['label' => 'Date', 'type' => 'date', 'required' => true], (string) $g('rx_date', date('Y-m-d')));
    $dopts = [];
    foreach ($docs as $d) {
        $dopts[(string) $d['id']] = Rx::doctorLabel($d) . ($d['specialist'] ? ' (' . $d['specialist'] . ')' : '');
    }
    $h .= Crud::field('doctor_id', ['label' => 'Doctor', 'type' => 'select', 'options' => $dopts, 'required' => true, 'help' => $docs ? '' : 'Add a doctor under People first.'], (string) $g('doctor_id', $own ? (string) $own['id'] : ''));
    $h .= '</div>';
    // safety
    $h .= '<div class="d-form-grid" style="margin-top:12px">' . Crud::field('allergies', ['label' => 'Known allergies (checked against the medicines)', 'max' => 500, 'wide' => true, 'help' => 'Write "none" if the patient has no known allergies. Saved to the patient record.'], (string) $g('allergies')) . '</div>';
    // examination
    $h .= '<h3 style="margin-top:22px">Examination</h3><div class="d-form-grid">';
    foreach (['bp' => 'Blood pressure', 'pulse' => 'Pulse', 'temp' => 'Temperature', 'spo2' => 'SpO2', 'height' => 'Height (cm)'] as $k => $l) {
        $h .= Crud::field('v_' . $k, ['label' => $l, 'max' => 20], (string) (is_array($old) ? ($old['v_' . $k] ?? '') : ($vit[$k] ?? '')));
    }
    foreach ($cfg['extra'] as $x) {
        $ft = ($x[2] ?? 'text');
        $f = ['label' => (string) $x[1], 'max' => 120];
        if ($ft === 'select') {
            $f += ['type' => 'select', 'options' => array_combine($x[3], $x[3])];
        } elseif ($ft === 'date') {
            $f['type'] = 'date';
        }
        $h .= Crud::field('x_' . $x[0], $f, (string) (is_array($old) ? ($old['x_' . $x[0]] ?? '') : ($extra[$x[0]] ?? '')));
    }
    $h .= Crud::field('complaints', ['label' => 'Complaints', 'type' => 'textarea', 'rows' => 3, 'max' => 2000], (string) $g('complaints'));
    $h .= Crud::field('diagnosis', ['label' => 'Diagnosis', 'type' => 'textarea', 'rows' => 3, 'max' => 2000], (string) $g('diagnosis'));
    $h .= '</div>';
    // medicines
    $tpls = $app->db->all('SELECT id, name FROM sc_rx_templates WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name', [$t]);
    $h .= '<h3 style="margin-top:22px">Medicines</h3>';
    if ($tpls && !$row) {
        $h .= '<p class="d-help">Start from a saved set: ' . implode(' ', array_map(static fn ($x) => '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/new', ['template' => $x['id']])) . '">' . e($x['name']) . '</a>', $tpls)) . '</p>';
    }
    $line = static function (array $it, int $i) use ($cfg, $cfgAll, $sel): string {
        $v = static fn (string $k) => (string) ($it[$k] ?? '');
        $l = '<fieldset class="rx-line"><div class="rx-head"><b>Medicine <span data-no>' . ($i + 1) . '</span></b><button class="d-btn d-btn-soft" type="button" data-rm>Remove</button></div><div class="rx-grid">';
        $l .= '<label class="rx-w2">Medicine<input name="drug[]" list="rx-drugs" maxlength="190" value="' . e($v('drug')) . '" placeholder="Start typing a medicine"></label>';
        $l .= '<label>Strength<input name="strength[]" maxlength="60" value="' . e($v('strength')) . '" placeholder="500 mg"></label>';
        $l .= '<label>Form' . $sel('form[]', $cfg['forms'], $v('form'), 'Choose…') . '</label>';
        $l .= '<label>Dose each time<input name="dose[]" maxlength="60" value="' . e($v('dose')) . '" placeholder="1 tablet"></label>';
        $l .= '<label>How often<input name="freq[]" list="rx-freq" maxlength="60" value="' . e($v('freq')) . '" placeholder="1+0+1 (twice daily)"></label>';
        $l .= '<label>Route' . $sel('route[]', $cfgAll['routes'], $v('route') ?: (string) ($cfg['route'] ?? ''), 'Choose…') . '</label>';
        $l .= '<label>When to take' . $sel('timing[]', array_slice($cfgAll['timing'], 1), $v('timing'), 'Any time') . '</label>';
        $l .= '<label>For how long<span class="rx-dur"><input type="number" min="0" max="999" name="dur_n[]" value="' . e($v('dur_n')) . '">' . $sel('dur_unit[]', $cfgAll['units'], $v('dur_unit') ?: 'days') . '</span></label>';
        $l .= '<label>Quantity to supply<input name="qty[]" maxlength="30" value="' . e($v('qty')) . '" placeholder="optional"></label>';
        $l .= '<label>Reason (indication)<input name="indication[]" maxlength="120" value="' . e($v('indication')) . '" placeholder="optional"></label>';
        $l .= '<label class="rx-w2">Special instruction<input name="instruction[]" maxlength="255" value="' . e($v('instruction')) . '" placeholder="optional"></label>';
        return $l . '</div></fieldset>';
    };
    $h .= '<div data-lines>';
    foreach ($items ?: [[]] as $i => $it) {
        $h .= $line($it, $i);
    }
    $h .= '</div><template data-tpl>' . $line([], 0) . '</template>';
    $h .= '<p><button type="button" class="d-btn d-btn-soft" data-add-line>' . icon('plus', 14) . ' Add another medicine</button></p>';
    $h .= '<div class="d-form-grid" style="margin-top:14px">' . Crud::field('investigations', ['label' => 'Tests advised', 'type' => 'textarea', 'rows' => 2, 'max' => 2000], (string) $g('investigations')) . Crud::field('advice', ['label' => 'Advice for the patient', 'type' => 'textarea', 'rows' => 3, 'max' => 2000], (string) $g('advice')) . Crud::field('followup_date', ['label' => 'Follow-up date', 'type' => 'date'], (string) $g('followup_date')) . Crud::field('template_name', ['label' => 'Save these medicines as a set named (optional)', 'max' => 120, 'help' => 'Saved sets save time for common illnesses.'], '') . '</div>';
    // allergy acknowledgement when the saved draft has hits
    $hits = $row ? Rx::allergyHits((string) $row['allergies'], array_column($items, 'drug')) : [];
    if ($hits) {
        $h .= '<div class="d-alert is-danger" style="margin-top:14px"><b>Check before you issue:</b><ul style="margin:6px 0 0 18px">' . implode('', array_map(static fn ($x) => '<li>' . e($x) . '</li>', $hits)) . '</ul><label class="d-check" style="margin-top:8px"><input type="checkbox" name="ack_allergy" value="1"><span>I have checked the allergy warning and want to issue this prescription.</span></label></div>';
    }
    $h .= '<div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions')) . '">Cancel</a><button class="d-btn d-btn-soft" type="submit" name="action" value="draft">Save draft</button><button class="d-btn d-btn-primary" type="submit" name="action" value="issue">Save and issue</button></div></form>';
    return $h;
};

// ---------------------------------------------------------------- list
$app->router->get('/portal/prescriptions', static function () use ($app, $boot, $page, $card, $btn, $stat, $badge, $tabs, $db, $rx): void {
    [$p, $cfg] = $boot();
    $t = $p->tenantId;
    $w = $p->can('manage_rx') && $p->writable();
    $n = static fn (string $sql, array $a = []): int => (int) $db->val($sql, array_merge([$t], $a));
    $tiles = '<div class="d-stats">' . $stat('Issued today', (string) $n("SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ? AND status = 'issued' AND deleted_at IS NULL AND rx_date = CURDATE()"), 'file')
        . $stat('Issued this month', (string) $n("SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ? AND status = 'issued' AND deleted_at IS NULL AND rx_date >= ?", [date('Y-m-01')]), 'chart')
        . $stat('Drafts to finish', (string) $n("SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ? AND status = 'draft' AND deleted_at IS NULL"), 'clock', 'saved, not issued')
        . $stat('Follow-ups this week', (string) $n("SELECT COUNT(*) FROM sc_prescriptions WHERE tenant_id = ? AND status = 'issued' AND deleted_at IS NULL AND followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"), 'cal') . '</div>';
    $docs = array_column($rx->doctors($t), null, 'id');
    $acts = [['label' => 'Open', 'icon' => 'eye', 'url' => static fn ($r) => url('portal/prescriptions/' . $r['id'])],
        ['label' => 'PDF', 'icon' => 'file', 'url' => static fn ($r) => url('portal/prescriptions/' . $r['id'] . '/pdf')]];
    if ($w) {
        $acts[] = ['label' => 'Edit', 'icon' => 'edit', 'if' => static fn ($r) => $r['status'] === 'draft', 'url' => static fn ($r) => url('portal/prescriptions/' . $r['id'] . '/edit')];
        $acts[] = ['label' => 'Copy', 'icon' => 'plus', 'method' => 'post', 'if' => static fn ($r) => $r['status'] !== 'draft', 'url' => static fn ($r) => url('portal/prescriptions/' . $r['id'] . '/copy')];
        $acts[] = ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this draft?', 'if' => static fn ($r) => $r['status'] === 'draft', 'url' => static fn ($r) => url('portal/prescriptions/' . $r['id'] . '/delete')];
    }
    $list = Listing::render($db, [
        'id' => 'rx', 'select' => 't.*, (SELECT GROUP_CONCAT(i.drug ORDER BY i.line_no SEPARATOR ", ") FROM sc_prescription_items i WHERE i.prescription_id = t.id AND i.tenant_id = t.tenant_id) AS drugs', 'from' => 'sc_prescriptions t',
        'where' => ['t.tenant_id = ?', 't.deleted_at IS NULL'], 'params' => [$t],
        'columns' => [
            'rx_no' => ['label' => 'No', 'sort' => 't.rx_no', 'render' => static fn ($r) => '<a href="' . e(url('portal/prescriptions/' . $r['id'])) . '"><b>' . e((string) $r['rx_no']) . '</b></a>'],
            'rx_date' => ['label' => 'Date', 'sort' => 't.rx_date', 'type' => 'date'],
            'patient_name' => ['label' => 'Patient', 'sort' => 't.patient_name', 'render' => static fn ($r) => $r['patient_id'] ? '<a href="' . e(url('portal/prescriptions/patient/' . $r['patient_id'])) . '">' . e((string) $r['patient_name']) . '</a>' : e((string) $r['patient_name']) . ' <span class="d-mute">(walk-in)</span>'],
            'doctor_name' => ['label' => 'Doctor', 'sort' => 't.doctor_name'],
            'drugs' => ['label' => 'Medicines', 'render' => static fn ($r) => e(mb_strimwidth((string) $r['drugs'], 0, 70, '...'))],
            'status' => ['label' => 'State', 'render' => static fn ($r) => $badge((string) $r['status'])],
        ],
        'search' => ['t.rx_no', 't.patient_name', 't.diagnosis', 't.doctor_name'], 'search_hint' => 'Search by patient, number, diagnosis or doctor',
        'filters' => ['status' => ['label' => 'State', 'sql' => 't.status = ?', 'options' => ['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Cancelled']], 'doctor' => ['label' => 'Doctor', 'sql' => 't.doctor_id = ?', 'options' => array_map(static fn ($d) => Rx::doctorLabel($d), $docs)]],
        'sort' => 'rx_date', 'dir' => 'desc', 'actions' => $acts,
        'empty' => ['title' => 'No prescriptions yet', 'text' => 'Write the first prescription. Issued prescriptions are locked and can be printed as a PDF.', 'cta' => $w ? ['label' => 'New prescription', 'url' => url('portal/prescriptions/new')] : null],
    ]);
    $top = ($w ? $btn('New prescription', url('portal/prescriptions/new')) . ' ' : '') . '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/blank')) . '">Blank pad (PDF)</a>';
    $page($p, 'Prescriptions', $tabs('', $p) . $tiles . $card('', $list), ['actions' => $top, 'sub' => 'Write, issue and print prescriptions. Each one is linked to a patient and a doctor.']);
});

// ---------------------------------------------------------------- new / edit
$app->router->get('/portal/prescriptions/new', static function () use ($app, $boot, $page, $card, $rx, $db, $formHtml): void {
    [$p, $cfg] = $boot(true);
    $pre = [];
    $items = [];
    if (!empty($_GET['patient'])) {
        $pt = $db->one('SELECT p.id, p.allergies, u.first_name, u.last_name, u.phone, u.gender, u.dob FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ? AND u.deleted_at IS NULL', [(int) $_GET['patient'], $p->tenantId]);
        if ($pt !== null) {
            $pre = ['patient_id' => (string) $pt['id'], 'patient_name' => trim($pt['first_name'] . ' ' . $pt['last_name']), 'patient_phone' => (string) $pt['phone'], 'sex' => [0 => 'Male', 1 => 'Female', 2 => 'Other'][(int) $pt['gender']] ?? '', 'age' => Rx::age($pt['dob']), 'allergies' => (string) $pt['allergies']];
        }
    }
    if (!empty($_GET['template'])) {
        $tp = $db->one('SELECT * FROM sc_rx_templates WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL', [(int) $_GET['template'], $p->tenantId]);
        if ($tp !== null) {
            $items = (array) json_decode((string) $tp['items'], true);
            $pre += ['diagnosis' => (string) $tp['diagnosis'], 'advice' => (string) $tp['advice']];
        }
    }
    $page($p, 'New prescription', $card('', $formHtml($p, $cfg, null, $items, $pre)), ['sub' => 'Fill in the patient, the examination and the medicines. Save a draft, or issue it when it is ready.']);
});
$app->router->get('/portal/prescriptions/{id}/edit', static function (array $a) use ($app, $boot, $page, $card, $rx, $load, $formHtml): void {
    [$p, $cfg] = $boot(true);
    $r = $load($p, $a);
    if ($r['status'] !== 'draft') {
        flash('An issued prescription is locked. Copy it to make a changed one.', 'bad');
        redirect(url('portal/prescriptions/' . $r['id']));
    }
    $page($p, 'Edit ' . $r['rx_no'], $card('', $formHtml($p, $cfg, $r, $rx->items($p->tenantId, (int) $r['id']), [])), ['sub' => 'This is a draft. You can change it until you issue it.']);
});

// ---------------------------------------------------------------- save
$saveRx = static function (Portal $p, array $cfg, array $post, ?array $row) use ($app, $rx, $db, $audit, $validDate): array {
    $t = $p->tenantId;
    $now = date('Y-m-d H:i:s');
    $err = [];
    $pid = (int) ($post['patient_id'] ?? 0);
    $pt = null;
    if ($pid > 0) {
        $pt = $db->one('SELECT p.id, p.allergies, u.first_name, u.last_name, u.phone, u.gender, u.dob FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ? AND u.deleted_at IS NULL', [$pid, $t]);
        if ($pt === null) {
            return [null, ['That patient is not in your records.']];
        }
    }
    $name = trim((string) ($post['patient_name'] ?? ''));
    if ($pt !== null && $name === '') {
        $name = trim($pt['first_name'] . ' ' . $pt['last_name']);
    }
    if ($name === '') {
        $err[] = 'Write the patient name or choose a registered patient.';
    }
    $did = (int) ($post['doctor_id'] ?? 0);
    $doc = null;
    if ($did > 0) {
        foreach ($rx->doctors($t) as $d) {
            if ((int) $d['id'] === $did) {
                $doc = $d;
            }
        }
        if ($doc === null) {
            return [null, ['That doctor is not in your team.']];
        }
    }
    $date = (string) ($post['rx_date'] ?? '');
    if (!$validDate($date)) {
        $err[] = 'Choose a valid date.';
    }
    $fu = (string) ($post['followup_date'] ?? '');
    if ($fu !== '' && !$validDate($fu)) {
        $err[] = 'The follow-up date is not valid.';
    }
    $wt = trim((string) ($post['weight'] ?? ''));
    if ($wt !== '' && (!is_numeric($wt) || (float) $wt < 0 || (float) $wt > 700)) {
        $err[] = 'The weight is not valid.';
    }
    if ($err) {
        return [null, $err];
    }
    $vit = [];
    foreach (['bp', 'pulse', 'temp', 'spo2', 'height'] as $k) {
        $v = mb_substr(trim((string) ($post['v_' . $k] ?? '')), 0, 20);
        if ($v !== '') {
            $vit[$k] = $v;
        }
    }
    $extra = [];
    foreach ($cfg['extra'] as $x) {
        $v = mb_substr(trim((string) ($post['x_' . $x[0]] ?? '')), 0, 120);
        if ($v !== '' && (($x[2] ?? '') !== 'select' || in_array($v, $x[3], true)) && (($x[2] ?? '') !== 'date' || $validDate($v))) {
            $extra[$x[0]] = $v;
        }
    }
    $sex = (string) ($post['sex'] ?? '');
    $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
    $allergies = $s('allergies', 500);
    $items = Rx::postedItems($post);
    $data = [
        'patient_id' => $pt['id'] ?? null, 'patient_name' => mb_substr($name, 0, 190), 'patient_phone' => $s('patient_phone', 40) ?: ($pt['phone'] ?? null) ?: null, 'age' => $s('age', 20) ?: ($pt ? Rx::age($pt['dob']) : '') ?: null,
        'sex' => in_array($sex, ['Male', 'Female', 'Other'], true) ? $sex : null, 'weight' => $wt !== '' ? round((float) $wt, 2) : null, 'rx_date' => $date,
        'doctor_id' => $doc['id'] ?? null, 'doctor_name' => $doc ? Rx::doctorLabel($doc) : '', 'doctor_info' => $doc ? mb_substr(Rx::doctorInfo($doc), 0, 255) : null,
        'vitals' => $vit ? json_encode($vit) : null, 'allergies' => $allergies !== '' ? $allergies : null, 'complaints' => $s('complaints', 2000) ?: null, 'diagnosis' => $s('diagnosis', 2000) ?: null,
        'investigations' => $s('investigations', 2000) ?: null, 'advice' => $s('advice', 2000) ?: null, 'followup_date' => $fu !== '' ? $fu : null, 'extra' => $extra ? json_encode($extra) : null, 'updated_at' => $now,
    ];
    $db->begin();
    try {
        if ($row === null) {
            $data += ['tenant_id' => $t, 'rx_no' => $rx->code($t), 'status' => 'draft', 'created_by' => (int) $p->user['id'], 'created_at' => $now];
            $id = $db->insert('sc_prescriptions', $data);
        } else {
            $id = (int) $row['id'];
            $db->update('sc_prescriptions', $data, 'id = ? AND tenant_id = ? AND status = ?', [$id, $t, 'draft']);
            $db->run('DELETE FROM sc_prescription_items WHERE prescription_id = ? AND tenant_id = ?', [$id, $t]);
        }
        foreach ($items as $i => $it) {
            $db->insert('sc_prescription_items', ['tenant_id' => $t, 'prescription_id' => $id, 'line_no' => $i + 1, 'drug' => $it['drug'], 'strength' => $it['strength'] ?: null, 'form' => $it['form'] ?: null, 'route' => $it['route'] ?: null, 'dose' => $it['dose'] ?: null, 'freq' => $it['freq'] ?: null,
                'timing' => $it['timing'] ?: null, 'dur_n' => $it['dur_n'], 'dur_unit' => $it['dur_unit'] ?: null, 'qty' => $it['qty'] ?: null, 'indication' => $it['indication'] ?: null, 'instruction' => $it['instruction'] ?: null]);
        }
        if ($pt !== null && $allergies !== (string) ($pt['allergies'] ?? '')) {
            $db->update('patients', ['allergies' => $allergies !== '' ? $allergies : null, 'updated_at' => $now], 'id = ? AND tenant_id = ?', [(int) $pt['id'], $t]);
        }
        $tn = $s('template_name', 120);
        if ($tn !== '' && $items) {
            $db->insert('sc_rx_templates', ['tenant_id' => $t, 'name' => $tn, 'diagnosis' => $data['diagnosis'], 'advice' => $data['advice'], 'items' => json_encode($items), 'created_by' => (int) $p->user['id'], 'created_at' => $now]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    $audit($p, $row === null ? 'rx_created' : 'rx_edited', null, ['id' => $id, 'patient_id' => $data['patient_id']]);
    return [$id, []];
};
$issueRx = static function (Portal $p, int $id, bool $ack) use ($app, $rx, $db, $audit): array {
    $r = $rx->find($p->tenantId, $id);
    if ($r === null || $r['status'] !== 'draft' || $r['deleted_at'] !== null) {
        return ['This prescription cannot be issued.'];
    }
    $items = $rx->items($p->tenantId, $id);
    $problems = Rx::issueProblems($r, $items);
    if (Rx::allergyHits((string) $r['allergies'], array_column($items, 'drug')) && !$ack) {
        $problems[] = 'There is an allergy warning. Open the draft, read it and tick the box to confirm.';
    }
    if ($problems) {
        return $problems;
    }
    $db->update('sc_prescriptions', ['status' => 'issued', 'issued_at' => date('Y-m-d H:i:s'), 'issued_by' => (int) $p->user['id'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ? AND status = ?', [$id, $p->tenantId, 'draft']);
    $audit($p, 'rx_issued', ['status' => 'draft'], ['id' => $id, 'status' => 'issued']);
    return [];
};
$app->router->post('/portal/prescriptions/save', static function () use ($app, $boot, $saveRx, $issueRx, $rx, $load): void {
    [$p, $cfg] = $boot(true);
    $p->needWritable();
    $row = null;
    if (!empty($_POST['id'])) {
        $row = $load($p, ['id' => $_POST['id']]);
        if ($row['status'] !== 'draft') {
            flash('An issued prescription is locked. Copy it to make a changed one.', 'bad');
            redirect(url('portal/prescriptions/' . $row['id']));
        }
    }
    [$id, $err] = $saveRx($p, $cfg, $_POST, $row);
    if ($id === null) {
        $_SESSION['sc_rx_old'] = $_POST;
        flash(implode(' ', $err), 'bad');
        redirect($row ? url('portal/prescriptions/' . $row['id'] . '/edit') : url('portal/prescriptions/new'));
    }
    if (($_POST['action'] ?? '') === 'issue') {
        $problems = $issueRx($p, (int) $id, !empty($_POST['ack_allergy']));
        if ($problems) {
            flash('Saved as a draft, not issued yet. ' . implode(' ', $problems), 'bad');
            redirect(url('portal/prescriptions/' . $id . '/edit'));
        }
        flash('Prescription issued.', 'ok');
        redirect(url('portal/prescriptions/' . $id));
    }
    flash('Draft saved.', 'ok');
    redirect(url('portal/prescriptions/' . $id));
});
$app->router->post('/portal/prescriptions/{id}/issue', static function (array $a) use ($boot, $load, $issueRx): void {
    [$p] = $boot(true);
    $p->needWritable();
    $r = $load($p, $a);
    $problems = $issueRx($p, (int) $r['id'], !empty($_POST['ack_allergy']));
    flash($problems ? implode(' ', $problems) : 'Prescription issued.', $problems ? 'bad' : 'ok');
    redirect(url('portal/prescriptions/' . ($problems ? $r['id'] . '/edit' : $r['id'])));
});
$app->router->post('/portal/prescriptions/{id}/cancel', static function (array $a) use ($boot, $load, $db, $audit): void {
    [$p] = $boot(true);
    $p->needWritable();
    $r = $load($p, $a);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($r['status'] !== 'issued' || $reason === '') {
        flash($reason === '' ? 'Write the reason for cancelling.' : 'Only an issued prescription can be cancelled.', 'bad');
        redirect(url('portal/prescriptions/' . $r['id']));
    }
    $db->update('sc_prescriptions', ['status' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 240), 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => (int) $p->user['id'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [(int) $r['id'], $p->tenantId]);
    $audit($p, 'rx_cancelled', ['status' => 'issued'], ['id' => (int) $r['id'], 'status' => 'cancelled'], $reason);
    flash('Prescription cancelled. It stays in the register.', 'ok');
    redirect(url('portal/prescriptions/' . $r['id']));
});
$app->router->post('/portal/prescriptions/{id}/delete', static function (array $a) use ($boot, $load, $db, $audit): void {
    [$p] = $boot(true);
    $p->needWritable();
    $r = $load($p, $a);
    if ($r['status'] !== 'draft') {
        flash('Only a draft can be deleted. Cancel an issued prescription instead.', 'bad');
        redirect(url('portal/prescriptions/' . $r['id']));
    }
    $db->update('sc_prescriptions', ['deleted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [(int) $r['id'], $p->tenantId]);
    $audit($p, 'rx_deleted', ['id' => (int) $r['id']], null);
    flash('Draft deleted.', 'ok');
    redirect(url('portal/prescriptions'));
});
$app->router->post('/portal/prescriptions/{id}/copy', static function (array $a) use ($boot, $load, $rx, $db, $audit): void {
    [$p] = $boot(true);
    $p->needWritable();
    $r = $load($p, $a);
    $now = date('Y-m-d H:i:s');
    $own = $rx->doctorOf($p->tenantId, (int) $p->user['id']);
    $doc = null;
    foreach ($rx->doctors($p->tenantId) as $d) {
        if ((int) $d['id'] === (int) ($own['id'] ?? $r['doctor_id'])) {
            $doc = $d;
        }
    }
    $pt = $r['patient_id'] ? $db->one('SELECT allergies FROM patients WHERE id = ? AND tenant_id = ?', [(int) $r['patient_id'], $p->tenantId]) : null;
    $db->begin();
    try {
        $new = $db->insert('sc_prescriptions', ['tenant_id' => $p->tenantId, 'rx_no' => $rx->code($p->tenantId), 'rx_date' => date('Y-m-d'), 'patient_id' => $r['patient_id'], 'patient_name' => $r['patient_name'], 'patient_phone' => $r['patient_phone'], 'age' => $r['age'], 'sex' => $r['sex'], 'weight' => $r['weight'],
            'doctor_id' => $doc['id'] ?? null, 'doctor_name' => $doc ? Rx::doctorLabel($doc) : '', 'doctor_info' => $doc ? mb_substr(Rx::doctorInfo($doc), 0, 255) : null, 'allergies' => $pt['allergies'] ?? $r['allergies'], 'complaints' => $r['complaints'], 'diagnosis' => $r['diagnosis'],
            'investigations' => $r['investigations'], 'advice' => $r['advice'], 'extra' => $r['extra'], 'status' => 'draft', 'parent_id' => (int) $r['id'], 'created_by' => (int) $p->user['id'], 'created_at' => $now, 'updated_at' => $now]);
        foreach ($rx->items($p->tenantId, (int) $r['id']) as $it) {
            unset($it['id']);
            $it['prescription_id'] = $new;
            $db->insert('sc_prescription_items', $it);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    $audit($p, 'rx_copied', ['id' => (int) $r['id']], ['id' => $new]);
    flash('Copied as a new draft. Check it, then issue it.', 'ok');
    redirect(url('portal/prescriptions/' . $new . '/edit'));
});

// ---------------------------------------------------------------- view / pdf
$modelFor = static function (Portal $p, array $cfg, array $r) use ($app, $rx, $db): array {
    $pt = $r['patient_id'] ? $db->one('SELECT patient_unique_id FROM patients WHERE id = ? AND tenant_id = ?', [(int) $r['patient_id'], $p->tenantId]) : null;
    return $rx->model($app, $p->tenantId, $r, $rx->items($p->tenantId, (int) $r['id']), $p->user, $cfg, $pt);
};
$app->router->get('/portal/prescriptions/blank', static function () use ($app, $boot, $rx): void {
    [$p, $cfg] = $boot();
    Documents::send($rx->model($app, $p->tenantId, null, [], $p->user, $cfg), 'prescription-pad.pdf');
});
$app->router->get('/portal/prescriptions/templates', static function () use ($app, $boot, $page, $card, $tabs, $db): void {
    [$p] = $boot();
    $rows = $db->all('SELECT * FROM sc_rx_templates WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name', [$p->tenantId]);
    $w = $p->can('manage_rx') && $p->writable();
    $h = '';
    foreach ($rows as $r) {
        $its = (array) json_decode((string) $r['items'], true);
        $h .= '<tr><td data-label="Name"><b>' . e((string) $r['name']) . '</b></td><td data-label="Medicines">' . e(implode(', ', array_slice(array_column($its, 'drug'), 0, 6))) . '</td><td data-label="Actions">' . ($w ? '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/new', ['template' => $r['id']])) . '">Use</a> <form class="d-inline" method="post" action="' . e(url('portal/prescriptions/templates/' . $r['id'] . '/delete')) . '" data-confirm="Delete this saved set?">' . csrf_field() . '<button class="d-btn d-btn-danger" type="submit">Delete</button></form>' : '') . '</td></tr>';
    }
    $body = $rows ? '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Name</th><th>Medicines</th><th>Actions</th></tr></thead><tbody>' . $h . '</tbody></table></div>' : '<div class="d-empty"><h3>No saved sets yet</h3><p class="d-mute">When you write a prescription, type a name in "Save these medicines as a set" to keep them for next time.</p></div>';
    $page($p, 'Saved sets of medicines', $tabs('templates', $p) . $card('', $body), ['sub' => 'Common medicine sets for common illnesses. Use one to start a new prescription.']);
});
$app->router->post('/portal/prescriptions/templates/{id}/delete', static function (array $a) use ($boot, $db): void {
    [$p] = $boot(true);
    $p->needWritable();
    $db->update('sc_rx_templates', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [(int) $a['id'], $p->tenantId]);
    flash('Saved set deleted.', 'ok');
    redirect(url('portal/prescriptions/templates'));
});
$app->router->get('/portal/prescriptions/patient/{id}', static function (array $a) use ($app, $boot, $page, $card, $btn, $rx, $db, $badge, $cfgAll): void {
    [$p, $cfg] = $boot();
    $t = $p->tenantId;
    $pt = $db->one('SELECT p.id, p.patient_unique_id, p.allergies, p.conditions, u.first_name, u.last_name, u.phone, u.gender, u.dob, u.blood_group FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ?', [(int) $a['id'], $t]);
    if ($pt === null) {
        $app->error(404, 'We could not find that patient.');
    }
    $name = trim($pt['first_name'] . ' ' . $pt['last_name']);
    $list = $rx->forPatient($t, (int) $pt['id']);
    if (($_GET['format'] ?? '') === 'pdf') {
        $rows = array_map(static fn ($r) => [dt((string) $r['rx_date']), (string) $r['rx_no'], (string) $r['doctor_name'], mb_strimwidth(trim((string) $r['diagnosis']), 0, 70, '...'), mb_strimwidth((string) $r['drugs'], 0, 130, '...'), ucfirst((string) $r['status'])], $list);
        Documents::send(['org' => Documents::org($app, $t), 'title' => 'PRESCRIPTION HISTORY', 'no' => (string) $pt['patient_unique_id'], 'date' => date('d M Y'), 'landscape' => true, 'blocks' => [
            ['kv', 'title' => 'Patient', 'items' => [['Name', $name], ['Patient ID', (string) $pt['patient_unique_id']], ['Age / sex', trim(Rx::age($pt['dob']) . ' / ' . ([0 => 'Male', 1 => 'Female', 2 => 'Other'][(int) $pt['gender']] ?? ''), ' /')], ['Blood group', (string) $pt['blood_group']], ['Allergies', (string) $pt['allergies']], ['Long-term conditions', (string) $pt['conditions']]]],
            ['table', 'title' => 'Prescriptions', 'cols' => [['Date', 1.1], ['No', 1.1], ['Doctor', 1.8], ['Diagnosis', 2.4], ['Medicines', 4], ['State', 0.9]], 'rows' => $rows]], 'sign' => [],
            'printed_by' => trim($p->user['first_name'] . ' ' . $p->user['last_name']), 'printed_at' => date('d M Y, H:i')], 'history-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $pt['patient_unique_id']) . '.pdf');
    }
    $cur = $rx->currentMedicines($t, (int) $pt['id']);
    $w = $p->can('manage_rx') && $p->writable();
    $curH = '';
    foreach ($cur as $c) {
        $curH .= '<li><b>' . e((string) $c['drug']) . '</b> ' . e(trim((string) $c['dose'] . ' · ' . (string) $c['freq'], ' ·')) . '<small>' . e((string) $c['rx_no']) . ' · ' . e($c['ends'] ? 'until ' . dt((string) $c['ends']) : 'ongoing') . '</small></li>';
    }
    $rowsH = '';
    foreach ($list as $r) {
        $rowsH .= '<li><a href="' . e(url('portal/prescriptions/' . $r['id'])) . '"><b>' . e((string) $r['rx_no']) . '</b></a> ' . $badge((string) $r['status']) . ' · ' . e((string) $r['doctor_name']) . '<small>' . e(dt((string) $r['rx_date'])) . ' · ' . e(trim((string) $r['diagnosis']) !== '' ? mb_strimwidth(trim((string) $r['diagnosis']), 0, 80, '...') . ' · ' : '') . e(mb_strimwidth((string) $r['drugs'], 0, 110, '...')) . '</small></li>';
    }
    $inv = $db->all("SELECT id, invoice_no, invoice_date, total, currency, status FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND patient_id = ? ORDER BY id DESC LIMIT 8", [$t, (int) $pt['id']]);
    $invH = '';
    foreach ($inv as $r) {
        $invH .= '<li>' . ($p->can('manage_invoices') ? '<a href="' . e(url('portal/billing/' . $r['id'])) . '"><b>' . e((string) $r['invoice_no']) . '</b></a>' : '<b>' . e((string) $r['invoice_no']) . '</b>') . ' · ' . e(money((float) $r['total'], (string) $r['currency'])) . ' · ' . e(ucfirst((string) $r['status'])) . '<small>' . e(dt((string) $r['invoice_date'])) . '</small></li>';
    }
    $docs = $db->all('SELECT doc_type, doc_no, doc_date FROM sc_documents WHERE tenant_id = ? AND patient_id = ? AND status <> ? ORDER BY id DESC LIMIT 8', [$t, (int) $pt['id'], 'void']);
    $docH = implode('', array_map(static fn ($r) => '<li><b>' . e((string) $r['doc_no']) . '</b> ' . e(ucwords(str_replace('_', ' ', (string) $r['doc_type']))) . '<small>' . e(dt((string) $r['doc_date'])) . '</small></li>', $docs));
    $facts = '<dl class="d-kv"><dt>Patient ID</dt><dd>' . e((string) $pt['patient_unique_id']) . '</dd><dt>Age</dt><dd>' . e(Rx::age($pt['dob']) ?: '—') . '</dd><dt>Blood group</dt><dd>' . e((string) ($pt['blood_group'] ?: '—')) . '</dd><dt>Allergies</dt><dd>' . ($pt['allergies'] ? '<b style="color:var(--bad,#b3261e)">' . e((string) $pt['allergies']) . '</b>' : '<span class="d-mute">none recorded</span>') . '</dd><dt>Long-term conditions</dt><dd>' . e((string) ($pt['conditions'] ?: '—')) . '</dd></dl>';
    $body = '<div class="d-grid d-g2"><div class="d-stack">' . $card('Patient', $facts) . $card('Medicines now', $curH ? '<ul class="d-tl">' . $curH . '</ul>' : '<p class="d-mute">No medicine in use from issued prescriptions.</p>') . '</div><div class="d-stack">'
        . $card('All prescriptions (' . count($list) . ')', $rowsH ? '<ul class="d-tl">' . $rowsH . '</ul>' : '<p class="d-mute">No prescriptions yet.</p>') . $card('Invoices', $invH ? '<ul class="d-tl">' . $invH . '</ul>' : '<p class="d-mute">No invoices yet.</p>') . ($docH ? $card('Forms and records', '<ul class="d-tl">' . $docH . '</ul>') : '') . '</div></div>';
    $top = ($w ? $btn('New prescription', url('portal/prescriptions/new', ['patient' => $pt['id']])) . ' ' : '') . '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/patient/' . $pt['id'], ['format' => 'pdf'])) . '">History PDF</a>';
    $page($p, $name . ' — history', $body, ['actions' => $top, 'sub' => 'Everything written and billed for this patient, newest first.']);
});
$app->router->get('/portal/prescriptions/{id}', static function (array $a) use ($app, $boot, $page, $card, $rx, $load, $modelFor, $badge, $db): void {
    [$p, $cfg] = $boot();
    $r = $load($p, $a);
    $t = $p->tenantId;
    $w = $p->can('manage_rx') && $p->writable();
    $items = $rx->items($t, (int) $r['id']);
    $bar = '<div class="dx-bar"><a class="d-btn d-btn-primary" href="' . e(url('portal/prescriptions/' . $r['id'] . '/pdf')) . '">' . icon('file', 16) . ' Download PDF</a><button class="d-btn d-btn-soft" type="button" data-print>Print</button>';
    if ($r['patient_id']) {
        $bar .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/patient/' . $r['patient_id'])) . '">Patient history</a>';
    }
    if ($p->can('manage_invoices') && $p->module('billing')) {
        $bar .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/billing')) . '">Billing</a>';
    }
    if ($w && $r['status'] === 'draft') {
        $bar .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/prescriptions/' . $r['id'] . '/edit')) . '">Edit</a><form class="d-inline" method="post" action="' . e(url('portal/prescriptions/' . $r['id'] . '/issue')) . '">' . csrf_field() . '<button class="d-btn d-btn-primary" type="submit">Issue</button></form>';
    }
    if ($w && $r['status'] !== 'draft') {
        $bar .= '<form class="d-inline" method="post" action="' . e(url('portal/prescriptions/' . $r['id'] . '/copy')) . '">' . csrf_field() . '<button class="d-btn d-btn-soft" type="submit">Copy to a new draft</button></form>';
    }
    $bar .= '</div>';
    $alerts = '';
    $pt = $r['patient_id'] ? $db->one('SELECT allergies FROM patients WHERE id = ? AND tenant_id = ?', [(int) $r['patient_id'], $t]) : null;
    $hits = Rx::allergyHits((string) ($pt['allergies'] ?? $r['allergies']), array_column($items, 'drug'));
    if ($hits) {
        $alerts .= '<div class="d-alert is-danger"><b>Allergy warning</b><ul style="margin:6px 0 0 18px">' . implode('', array_map(static fn ($x) => '<li>' . e($x) . '</li>', $hits)) . '</ul></div>';
    }
    $dups = Rx::duplicates(array_column($items, 'drug'));
    if ($dups) {
        $alerts .= '<div class="d-alert is-info">' . e(implode('. ', $dups)) . '.</div>';
    }
    if ($r['status'] === 'cancelled') {
        $alerts .= '<div class="d-alert is-danger"><b>Cancelled</b> on ' . e(dt((string) $r['cancelled_at'], 'd M Y H:i')) . ': ' . e((string) $r['cancel_reason']) . '</div>';
    }
    $info = '<p class="d-help">' . $badge((string) $r['status']) . ' Written ' . e(dt((string) $r['created_at'], 'd M Y H:i')) . ($r['issued_at'] ? ' · issued ' . e(dt((string) $r['issued_at'], 'd M Y H:i')) : '') . ($r['parent_id'] ? ' · copied from <a href="' . e(url('portal/prescriptions/' . $r['parent_id'])) . '">an earlier prescription</a>' : '') . '</p>';
    $extra = '';
    if ($w && $r['status'] === 'issued') {
        $extra = $card('Cancel this prescription', '<form class="d-form" method="post" action="' . e(url('portal/prescriptions/' . $r['id'] . '/cancel')) . '" data-confirm="Cancel this prescription? It stays in the register.">' . csrf_field() . '<div class="d-field"><label for="f_reason">Reason</label><input id="f_reason" name="reason" maxlength="240" required></div><div class="d-form-actions d-form-actions-sticky"><button class="d-btn d-btn-danger" type="submit">Cancel prescription</button></div></form>');
    }
    $page($p, (string) $r['rx_no'], $bar . $alerts . $info . Documents::html($modelFor($p, $cfg, $r)) . $extra);
});
$app->router->get('/portal/prescriptions/{id}/pdf', static function (array $a) use ($boot, $load, $modelFor): void {
    [$p, $cfg] = $boot();
    $r = $load($p, $a);
    Documents::send($modelFor($p, $cfg, $r), preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $r['rx_no']) . '.pdf');
});
