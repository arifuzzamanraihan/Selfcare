<?php
// Selfcare original routes: the type-specific work lists (core/config/ops.php) run by core/Ops.php.
// /portal/ops/{module}            list           /new  /save  form
// /portal/ops/{module}/{id}       open / edit    /do/{action}  workflow step (with a small form when it asks something)
//                                                /delete  /restore  /print
declare(strict_types=1);

use Selfcare\{Crud, Listing, Ops, Portal};
use function Selfcare\{csrf_field, dt, e, flash, icon, keep_old, clear_old, money, old, redirect, url};

$dash = $app->dash;
['card' => $card, 'btn' => $btn] = $app->ui;

/** Boots the portal and finds the module, or stops with 404/403. @return array{0:Portal,1:array<string,mixed>} */
$boot = static function (string $key) use ($app): array {
    $p = Portal::boot($app);
    $m = Ops::find($p, $key);
    if ($m === null) {
        // either not for this kind of organisation, or this role may not use it
        $any = Ops::all()[$key] ?? null;
        if ($any !== null && in_array((string) $p->type['slug'], $any['types'], true)) {
            $p->need((string) $any['perm'], 'documents');
        }
        flash('That list is not used by your kind of organisation.', 'info');
        redirect(url('portal'));
    }
    Ops::refresh($app->db, $p);
    return [$p, $m];
};
$load = static function (Portal $p, string $key, int $id) use ($app): ?array {
    return $app->db->one('SELECT * FROM sc_records WHERE id = ? AND tenant_id = ? AND module = ?', [$id, $p->tenantId, $key]);
};
$audit = static function (Portal $p, string $action, mixed $before, mixed $after) use ($app): void {
    $app->access->log($p->tenantId, (int) $p->user['id'], null, null, $action, $before, $after);
};
$flowAllowed = static function (array $m, string $a, array $row): bool {
    $from = (array) $m['flow'][$a][1];
    return in_array($row['status'], $from, true);
};
$printOk = static function (array $m, array $row): bool {
    $pr = $m['print'] ?? null;
    if (!$pr || !empty($row['deleted_at'])) {
        return false;
    }
    if ($pr === true) {
        return in_array($row['status'], ['reported', 'delivered'], true);
    }
    $when = $pr['when'] ?? null;
    return $when === null || in_array($row['status'], (array) $when, true);
};
$bindPeople = static function (Portal $p, array $fields, array &$d, ?array $old) : ?string {
    if (array_key_exists('person_id', $d)) {
        $id = (int) ($d['person_id'] ?? 0);
        if ($id <= 0) {
            $d['person_id'] = null;
        } else {
            $info = Ops::patientInfo($p, $id);
            if ($info === null) {
                return 'That patient is not on your list.';
            }
            if (trim((string) ($d['title'] ?? '')) === '') {
                $d['title'] = $info['name'];
            }
            if (trim((string) ($d['phone'] ?? '')) === '') {
                $d['phone'] = $info['phone'];
            }
        }
    }
    if (array_key_exists('staff_id', $d)) {
        $id = (int) ($d['staff_id'] ?? 0);
        if ($id <= 0) {
            $d['staff_id'] = null;
        } else {
            $name = Ops::doctorName($p, $id);
            if ($name === null) {
                return 'That doctor is not on your list.';
            }
            if (trim((string) ($d['party'] ?? '')) === '') {
                $d['party'] = $name;
            }
        }
    }
    return null;
};
$columnsFor = static function (array $m, string $key): array {
    $cols = [];
    $first = true;
    foreach (Ops::columns($m) as $k => [$label, $type]) {
        $c = ['label' => $label, 'sort' => 't.' . $k];
        if ($type === 'date') {
            $c['type'] = 'date';
        } elseif ($type === 'num') {
            $c['class'] = 'r';
            $c['render'] = static fn (array $r): string => e(rtrim(rtrim(number_format((float) $r[$k], 2), '0'), '.'));
        } elseif ($type === 'money') {
            $c['class'] = 'r';
            $c['render'] = static fn (array $r): string => (float) $r[$k] > 0 ? e(money((float) $r[$k])) : '<span class="d-mute">—</span>';
        } elseif ($type === 'status') {
            $c['render'] = static fn (array $r): string => Ops::badge($m, (string) $r['status']) . ($r['deleted_at'] ? ' <span class="d-badge is-bad">Deleted</span>' : '');
        }
        if ($first) {
            $c['render'] = static fn (array $r): string => '<a href="' . e(Ops::url($key, (string) $r['id'])) . '"><b>' . e((string) ($r[$k] !== null && $r[$k] !== '' ? $r[$k] : $r['title'])) . '</b></a>';
            $first = false;
        }
        $cols[$k] = $c;
    }
    return $cols;
};

// ---------------------------------------------------------------- list
$app->router->get('/portal/ops/{m}', static function (array $a) use ($app, $boot, $dash, $card, $btn, $columnsFor, $flowAllowed, $printOk): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $w = $p->writable();
    if (!empty($m['queue']) && ($_GET['show'] ?? '') !== 'deleted' && ($_GET['view'] ?? '') !== 'table') {
        $day = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = date('Y-m-d');
        }
        $status = (string) ($_GET['f_status'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $doc = (string) ($_GET['f_staff'] ?? '');
        $sql = 'SELECT * FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL AND date1 = ?';
        $par = [$p->tenantId, $key, $day];
        if ($status !== '' && isset($m['status'][$status])) {
            $sql .= ' AND status = ?';
            $par[] = $status;
        }
        if ($doc !== '') {
            $sql .= ' AND (staff_id = ? OR party = ?)';
            $par[] = $doc;
            $par[] = $doc;
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $cols = $m['search'] ?? ['code', 'title', 'party', 'phone'];
            $sql .= ' AND (' . implode(' OR ', array_map(static fn ($c) => $c . ' LIKE ?', $cols)) . ')';
            $par = array_merge($par, array_fill(0, count($cols), $like));
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $app->db->all($sql, $par);
        $h = '<div class="d-queue" data-ops-queue data-reorder-url="' . e(Ops::url($key, 'reorder')) . '">';
        $h .= '<form class="d-queue-toolbar" method="get" action="' . e(Ops::url($key)) . '">';
        $h .= '<label class="d-field" style="margin:0"><span class="d-help">Date</span><input type="date" name="date" value="' . e($day) . '" onchange="this.form.submit()"></label>';
        $h .= '<label class="d-field" style="margin:0"><span class="d-help">State</span><select name="f_status" onchange="this.form.submit()"><option value="">All</option>';
        foreach ($m['status'] as $s => [$lab]) {
            $h .= '<option value="' . e($s) . '"' . ($status === $s ? ' selected' : '') . '>' . e($lab) . '</option>';
        }
        $h .= '</select></label>';
        $h .= '<label class="d-field" style="margin:0;flex:1"><span class="d-help">Search</span><input type="search" name="q" value="' . e($q) . '" placeholder="Name, token, doctor"></label>';
        $h .= '<button class="d-btn d-btn-soft" type="submit">Show</button></form>';
        if (!$rows) {
            $h .= '<div class="d-empty"><h3>No ' . e(strtolower($m['title'])) . ' for this day</h3><p>' . e((string) $m['sub']) . '</p>';
            if ($w) {
                $h .= '<p><a class="d-btn d-btn-primary" href="' . e(Ops::url($key, 'new', ['date1' => $day])) . '">Add ' . e($m['singular']) . '</a></p>';
            }
            $h .= '</div>';
        } else {
            $h .= '<form method="post" data-reorder action="' . e(Ops::url($key, 'reorder')) . '" hidden>' . csrf_field() . '</form>';
            $h .= '<table class="d-queue-table"><thead><tr><th class="d-q-handle"></th>';
            foreach (Ops::columns($m) as $ck => [$lab]) {
                $h .= '<th>' . e($lab) . '</th>';
            }
            $h .= '<th class="d-actions-h">Actions</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $h .= '<tr data-id="' . (int) $r['id'] . '" draggable="true" tabindex="0">';
                $h .= '<td class="d-q-handle" title="Drag to change the order" aria-hidden="true">☰</td>';
                $first = true;
                foreach (Ops::columns($m) as $ck => [$lab, $type]) {
                    if ($type === 'status') {
                        $cell = Ops::badge($m, (string) $r['status']);
                    } elseif ($type === 'money') {
                        $cell = (float) $r[$ck] > 0 ? e(money((float) $r[$ck])) : '<span class="d-mute">—</span>';
                    } elseif ($type === 'date') {
                        $cell = e(dt((string) $r[$ck]));
                    } elseif ($type === 'num') {
                        $cell = e(rtrim(rtrim(number_format((float) $r[$ck], 2), '0'), '.'));
                    } else {
                        $cell = e((string) ($r[$ck] ?? ''));
                    }
                    if ($first) {
                        $cell = '<a href="' . e(Ops::url($key, (string) $r['id'])) . '"><b>' . ($cell !== '' ? $cell : e((string) $r['title'])) . '</b></a>';
                        $first = false;
                    }
                    $h .= '<td data-label="' . e($lab) . '">' . $cell . '</td>';
                }
                $acts = '<span class="d-queue-move">'
                    . '<button type="button" class="d-btn d-btn-soft" data-move="up" title="Move up (Alt+Up)" aria-label="Move up">↑</button>'
                    . '<button type="button" class="d-btn d-btn-soft" data-move="down" title="Move down (Alt+Down)" aria-label="Move down">↓</button></span> ';
                if ($w) {
                    foreach ($m['flow'] ?? [] as $act => $f) {
                        if (!$flowAllowed($m, $act, $r)) {
                            continue;
                        }
                        if (isset($f['ask'])) {
                            $acts .= '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, $r['id'] . '/do/' . $act)) . '">' . e($f[0]) . '</a> ';
                        } else {
                            $acts .= '<form class="d-inline" method="post" action="' . e(Ops::url($key, $r['id'] . '/do/' . $act)) . '"' . (!empty($f['confirm']) ? ' data-confirm="' . e($f['confirm']) . '"' : '') . '>' . csrf_field() . '<button class="d-btn d-btn-soft" type="submit">' . e($f[0]) . '</button></form> ';
                        }
                    }
                    if (Ops::billType($p, $m, $r)) {
                        $acts .= '<a class="d-btn d-btn-soft" href="' . e(url('portal/billing/new/' . Ops::billType($p, $m, $r), ['rec' => (string) $r['id'], 'mod' => $key])) . '">Make invoice</a> ';
                    }
                    if ($printOk($m, $r)) {
                        $acts .= '<a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e(Ops::url($key, $r['id'] . '/print')) . '">' . e(is_array($m['print']) ? (string) ($m['print']['label'] ?? 'Print') : 'Print') . '</a> ';
                    }
                }
                $h .= '<td class="d-actions" data-label="Actions"><div class="d-queue-actions">' . $acts . '</div></td></tr>';
            }
            $h .= '</tbody></table>';
        }
        $h .= '</div>';
        $acts = '';
        if ($w) {
            $acts .= $btn('Add ' . $m['singular'], Ops::url($key, 'new', ['date1' => $day])) . ' ';
            if (!empty($m['call'])) {
                $acts .= '<form class="d-inline" method="post" action="' . e(Ops::url($key, 'call-next')) . '">' . csrf_field() . '<input type="hidden" name="date" value="' . e($day) . '"><button class="d-btn d-btn-primary" type="submit">Call next</button></form> ';
            }
        }
        if (!empty($m['board'])) {
            $acts .= '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, 'board', ['date' => $day])) . '">Now-serving board</a> ';
        }
        $acts .= '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, '', ['view' => 'table'])) . '">Table view</a> ';
        $acts .= '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, '', ['show' => 'deleted'])) . '">Deleted</a>';
        $dash($p, 'ops-' . $key, (string) $m['title'], $card('', $h), ['actions' => $acts, 'sub' => (string) $m['sub'] . ' · ' . dt($day)]);
        return;
    }
    $deleted = ($_GET['show'] ?? '') === 'deleted';
    $statusOpts = [];
    foreach ($m['status'] as $s => [$label]) {
        $statusOpts[$s] = $label;
    }
    $actions = [['label' => 'Open', 'icon' => 'eye', 'url' => static fn ($r) => Ops::url($key, (string) $r['id'])]];
    if ($w && !$deleted) {
        foreach ($m['flow'] ?? [] as $act => $f) {
            $actions[] = ['label' => $f[0], 'icon' => $f[3] ?? 'check', 'if' => static fn ($r) => $flowAllowed($m, $act, $r), 'method' => isset($f['ask']) ? 'get' : 'post', 'confirm' => $f['confirm'] ?? null,
                'url' => static fn ($r) => Ops::url($key, $r['id'] . '/do/' . $act)];
        }
        $actions[] = ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this ' . $m['singular'] . '? You can bring it back from the deleted list.', 'url' => static fn ($r) => Ops::url($key, $r['id'] . '/delete')];
    } elseif ($w && $deleted) {
        $actions[] = ['label' => 'Restore', 'icon' => 'undo', 'method' => 'post', 'url' => static fn ($r) => Ops::url($key, $r['id'] . '/restore')];
    }
    $list = Listing::render($app->db, [
        'id' => 'ops-' . $key, 'select' => 't.*', 'from' => 'sc_records t', 'where' => ['t.tenant_id = ?', 't.module = ?', $deleted ? 't.deleted_at IS NOT NULL' : 't.deleted_at IS NULL'], 'params' => [$p->tenantId, $key],
        'columns' => $columnsFor($m, $key), 'search' => array_map(static fn ($c) => 't.' . $c, $m['search']), 'search_hint' => 'Search ' . strtolower($m['title']),
        'filters' => ['status' => ['label' => 'State', 'sql' => 't.status = ?', 'options' => $statusOpts]],
        'sort' => $m['sort'] ?? array_key_first($m['columns']), 'dir' => $m['dir'] ?? 'desc', 'actions' => $actions,
        'empty' => ['title' => 'No ' . strtolower($m['title']) . ' yet', 'text' => (string) $m['sub'], 'cta' => $w ? ['label' => 'Add ' . $m['singular'], 'url' => Ops::url($key, 'new')] : null],
    ]);
    $acts = ($w ? $btn('Add ' . $m['singular'], Ops::url($key, 'new')) . ' ' : '') . '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, '', $deleted ? [] : ['show' => 'deleted'])) . '">' . ($deleted ? 'Back to the list' : 'Deleted') . '</a>';
    $dash($p, 'ops-' . $key, (string) $m['title'], $card('', $list), ['actions' => $acts, 'sub' => (string) $m['sub']]);
});

// ---------------------------------------------------------------- form
$formPage = static function (Portal $p, array $m, string $key, ?array $row) use ($app, $dash, $card, $flowAllowed, $printOk): void {
    if ($row) {
        $row = Ops::unpack($row);
    }
    $id = $row ? (int) $row['id'] : null;
    $fields = Ops::fields($p, $m, $m['fields'], $row);
    $h = '';
    if ($row) {
        $by = '';
        $dl = '<dl class="d-kv"><dt>State</dt><dd>' . Ops::badge($m, (string) $row['status']) . ($row['deleted_at'] ? ' <span class="d-badge is-bad">Deleted</span>' : '') . '</dd>'
            . (!empty($row['code']) ? '<dt>Number</dt><dd>' . e((string) $row['code']) . '</dd>' : '')
            . '<dt>Added</dt><dd>' . e(dt((string) $row['created_at'], 'd M Y H:i')) . '</dd></dl>';
        $acts = '';
        if ($p->writable() && !$row['deleted_at']) {
            foreach ($m['flow'] ?? [] as $act => $f) {
                if (!$flowAllowed($m, $act, $row)) {
                    continue;
                }
                $cls = in_array($f[2], ['cancelled', 'rejected', 'discarded'], true) ? 'd-btn-soft' : 'd-btn-primary';
                if (isset($f['ask'])) {
                    $acts .= '<a class="d-btn ' . $cls . '" href="' . e(Ops::url($m['_key'], $row['id'] . '/do/' . $act)) . '">' . e($f[0]) . '</a> ';
                } else {
                    $acts .= '<form class="d-inline" method="post" action="' . e(Ops::url($m['_key'], $row['id'] . '/do/' . $act)) . '"' . (!empty($f['confirm']) ? ' data-confirm="' . e($f['confirm']) . '"' : '') . '>' . csrf_field() . '<button class="d-btn ' . $cls . '" type="submit">' . e($f[0]) . '</button></form> ';
                }
            }
        }
        if ($printOk($m, $row)) {
            $plab = is_array($m['print'] ?? null) ? (string) (($m['print']['label'] ?? 'Print')) : 'Print report';
            $acts .= '<a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e(Ops::url($m['_key'], $row['id'] . '/print')) . '">' . e($plab) . '</a>';
        }
        $bill = Ops::billType($p, $m, $row);
        if ($bill !== null && $p->writable()) {
            $acts .= ' <a class="d-btn d-btn-soft" href="' . e(url('portal/billing/new/' . $bill, ['rec' => (string) $row['id'], 'mod' => $key])) . '">Make invoice</a>';
        }
        if (!empty($row['invoice_id']) && $p->can('manage_invoices')) {
            $inv = $app->db->one('SELECT id, invoice_no, status FROM sc_invoices WHERE id = ? AND tenant_id = ?', [(int) $row['invoice_id'], $p->tenantId]);
            if ($inv !== null) {
                $dl = str_replace('</dl>', '<dt>Invoice</dt><dd><a href="' . e(url('portal/billing/' . $inv['id'])) . '"><b>' . e((string) $inv['invoice_no']) . '</b></a> ' . ($inv['status'] === 'paid' ? '<span class="d-badge is-ok">Paid</span>' : ($inv['status'] === 'void' ? '<span class="d-badge is-mute">Void</span>' : '<span class="d-badge is-warn">Unpaid</span>')) . '</dd></dl>', $dl);
            }
        }
        $h .= $card('Progress', $dl . ($acts !== '' ? '<p style="margin-top:14px">' . $acts . '</p>' : ''));
    }
    $f = '<form class="d-form" method="post" action="' . e(Ops::url($m['_key'], 'save')) . '">' . csrf_field() . ($id ? '<input type="hidden" name="id" value="' . $id . '">' : '') . '<div class="d-form-grid">';
    foreach ($fields as $n => $fs) {
        $cur = $row ? (string) ($row[$n] ?? '') : (string) ($_GET[$n] ?? ($fs['default'] ?? ''));
        if ($n === 'qty' || $n === 'amount') {
            $cur = $row ? (string) (float) $row[$n] : $cur;
        }
        $f .= Crud::field($n, $fs, old($n, $cur));
    }
    $f .= '</div><div class="d-form-actions">' . ($p->writable() ? '<button class="d-btn d-btn-primary" type="submit">Save</button>' : '') . '<a class="d-btn d-btn-soft" href="' . e(Ops::url($m['_key'])) . '">Back</a></div></form>';
    clear_old();
    $h .= $card($id ? 'Details' : 'New ' . $m['singular'], $f);
    if ($row) {
        $h .= $card('History', Ops::timeline($app->db, $p->tenantId, $m, (int) $row['id']));
    }
    $dash($p, 'ops-' . $key, $id ? ucfirst($m['singular']) . ' ' . ($row['code'] ?: $row['title']) : 'Add ' . $m['singular'], '<div class="d-grid d-g2">' . $h . '</div>', ['sub' => (string) $m['sub']]);
};
$app->router->get('/portal/ops/{m}/new', static function (array $a) use ($boot, $formPage): void {
    [$p, $m] = $boot((string) $a['m']);
    $p->needWritable();
    $m['_key'] = (string) $a['m'];
    $formPage($p, $m, (string) $a['m'], null);
});
$app->router->post('/portal/ops/{m}/save', static function (array $a) use ($app, $boot, $load, $audit, $bindPeople): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? $load($p, $key, $id) : null;
    if ($id && $old === null) {
        $app->error(404, 'We could not find that record.');
    }
    $back = $id ? Ops::url($key, (string) $id) : Ops::url($key, 'new');
    $fields = Ops::fields($p, $m, $m['fields'], $old);
    [$d, $err] = Crud::collect($fields);
    if ($err === null) {
        $err = $bindPeople($p, $fields, $d, $old);
    }
    if ($err === null) {
        $err = Ops::prepare($key, $d, $old, Ops::all());
    }
    if ($old !== null && $key === 'trips' && in_array($old['status'], ['dispatched', 'onroute'], true)) {
        unset($d['cat']); // the vehicle of a trip on the road changes only by completing or cancelling it
    }
    if ($err === null && isset($d['code']) && $d['code'] !== '') {
        $dup = $app->db->val('SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = ? AND code = ? AND id <> ? AND deleted_at IS NULL', [$p->tenantId, $key, $d['code'], $id]);
        $err = $dup ? 'Another record already uses "' . $d['code'] . '".' : null;
    }
    if ($err !== null) {
        keep_old($_POST);
        flash($err, 'bad');
        redirect($back);
    }
    $now = date('Y-m-d H:i:s');
    $d = Ops::pack($fields, $d, $old);
    if ($old === null) {
        if (!isset($d['status'])) {
            $d['status'] = (string) $m['initial'];
        }
        $day = (string) ($d['date1'] ?? date('Y-m-d'));
        if (array_key_exists('code', $d) && ($d['code'] === '' || $d['code'] === null) && empty($m['code_required'])) {
            unset($d['code']);
        }
        if (!isset($d['code']) && (($m['number'] ?? '') === 'daily')) {
            $d['code'] = Ops::dailyCode($app->db, $p->tenantId, $key, $day !== '' ? $day : date('Y-m-d'));
        } elseif (!isset($d['code']) && !empty($m['prefix'])) {
            $d['code'] = Ops::code($app->db, $p->tenantId, $key, (string) $m['prefix']);
        }
        if (!isset($d['sort_order'])) {
            $d['sort_order'] = Ops::nextSort($app->db, $p->tenantId, $key, $day !== '' ? $day : null);
        }
        $d += ['tenant_id' => $p->tenantId, 'module' => $key, 'created_by' => (int) $p->user['id'], 'created_at' => $now, 'updated_at' => $now];
        $id = $app->db->insert('sc_records', $d);
        Ops::event($app->db, $p->tenantId, $id, $key, 'created', null, (string) $d['status'], (int) $p->user['id']);
        $audit($p, $key . '_added', null, ['id' => $id, 'code' => $d['code'] ?? null]);
    } else {
        $d['updated_at'] = $now;
        $app->db->update('sc_records', $d, 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
        $audit($p, $key . '_edited', ['id' => $id], ['id' => $id]);
    }
    flash('Saved.', 'ok');
    redirect(empty($m['flow']) ? Ops::url($key) : Ops::url($key, (string) $id));
});


// ---------------------------------------------------------------- queue actions and now-serving board
$app->router->post('/portal/ops/{m}/reorder', static function (array $a) use ($app, $boot): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn ($i) => $i > 0));
    if (!$ids) {
        flash('Nothing to reorder.', 'bad');
        redirect(Ops::url($key));
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = $app->db->all("SELECT id FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL AND id IN ($ph)", array_merge([$p->tenantId, $key], $ids));
    $ok = array_flip(array_map(static fn ($r) => (int) $r['id'], $rows));
    $n = 1;
    $app->db->begin();
    try {
        foreach ($ids as $id) {
            if (!isset($ok[$id])) {
                continue;
            }
            $app->db->update('sc_records', ['sort_order' => $n, 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$id, $p->tenantId]);
            $n++;
        }
        $app->db->commit();
    } catch (\Throwable $e) {
        $app->db->rollBack();
        throw $e;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":true}';
        exit;
    }
    flash('Order updated.', 'ok');
    redirect(Ops::url($key, '', array_filter(['date' => (string) ($_POST['date'] ?? '')])));
});
$app->router->post('/portal/ops/{m}/call-next', static function (array $a) use ($app, $boot, $flowAllowed, $audit): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $act = (string) ($m['call'] ?? '');
    $day = (string) ($_POST['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = date('Y-m-d');
    }
    $to = Ops::url($key, '', ['date' => $day]);
    if ($act === '' || !isset($m['flow'][$act])) {
        flash('This list has no “call next” step.', 'bad');
        redirect($to);
    }
    $from = (array) $m['flow'][$act][1];
    $ph = implode(',', array_fill(0, count($from), '?'));
    $row = $app->db->one("SELECT * FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL AND date1 = ? AND status IN ($ph) ORDER BY sort_order ASC, id ASC LIMIT 1", array_merge([$p->tenantId, $key, $day], $from));
    if ($row === null || !$flowAllowed($m, $act, $row)) {
        flash('There is no one waiting to call.', 'info');
        redirect($to);
    }
    if (isset($m['flow'][$act]['ask'])) {
        redirect(Ops::url($key, $row['id'] . '/do/' . $act));
    }
    $app->db->update('sc_records', ['status' => $m['flow'][$act][2], 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
    Ops::event($app->db, $p->tenantId, (int) $row['id'], $key, $act, (string) $row['status'], (string) $m['flow'][$act][2], (int) $p->user['id']);
    $audit($p, $key . '_' . $act, ['id' => (int) $row['id'], 'status' => $row['status']], ['status' => $m['flow'][$act][2]]);
    flash($m['flow'][$act][0] . ': ' . ($row['code'] ? 'token ' . $row['code'] : $row['title']) . '.', 'ok');
    redirect($to);
});
$app->router->get('/portal/ops/{m}/board', static function (array $a) use ($app, $boot, $dash, $card): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    if (empty($m['board'])) {
        $app->error(404, 'This list has no now-serving board.');
    }
    $day = (string) ($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = date('Y-m-d');
    }
    $rows = $app->db->all('SELECT * FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL AND date1 = ? ORDER BY sort_order ASC, id ASC', [$p->tenantId, $key, $day]);
    $by = [];
    foreach ($rows as $r) {
        $doc = (string) ($r['party'] ?: 'General');
        $by[$doc][] = $r;
    }
    $now = (array) ($m['board']['now'] ?? ['called', 'in_consult']);
    $wait = (array) ($m['board']['wait'] ?? ['waiting']);
    $h = '<div class="d-nowboard">';
    if (!$by) {
        $h .= '<p class="d-help">No tokens for this day yet.</p>';
    }
    foreach ($by as $doc => $list) {
        $current = null;
        $nexts = [];
        foreach ($list as $r) {
            if ($current === null && in_array($r['status'], $now, true)) {
                $current = $r;
            } elseif (in_array($r['status'], $wait, true) && count($nexts) < 3) {
                $nexts[] = $r['code'] ?: $r['title'];
            }
        }
        $h .= '<div class="d-nowcard"><h3>' . e($doc) . '</h3>';
        if ($current) {
            $h .= '<div class="num">' . e((string) ($current['code'] ?: '—')) . '</div><div>' . e((string) ($m['status'][$current['status']][0] ?? $current['status'])) . '</div>';
        } else {
            $h .= '<div class="num">—</div><div class="d-help">No one is being seen</div>';
        }
        $h .= '<div class="next">Next: ' . e($nexts ? implode(', ', $nexts) : '—') . '</div></div>';
    }
    $h .= '</div><p class="d-help">Patient names are not shown on the waiting-room board. Refresh the page to update. · ' . e(dt($day)) . '</p>';
    $meta = '<meta http-equiv="refresh" content="15">';
    $dash($p, 'ops-' . $key, 'Now serving', $card('', $h), ['actions' => '<a class="d-btn d-btn-soft" href="' . e(Ops::url($key, '', ['date' => $day])) . '">Back to the queue</a>', 'sub' => (string) $p->vendor['name'], 'head' => $meta]);
});

// ---------------------------------------------------------------- open
$app->router->get('/portal/ops/{m}/{id}', static function (array $a) use ($app, $boot, $load, $formPage): void {
    [$p, $m] = $boot((string) $a['m']);
    $row = $load($p, (string) $a['m'], (int) $a['id']);
    if ($row === null) {
        $app->error(404, 'We could not find that record.');
    }
    $m['_key'] = (string) $a['m'];
    $formPage($p, $m, (string) $a['m'], $row);
});

// ---------------------------------------------------------------- workflow
$flowForm = static function (array $a) use ($app, $boot, $load, $dash, $card, $flowAllowed): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $row = $load($p, $key, (int) $a['id']);
    if ($row) {
        $row = Ops::unpack($row);
    }
    $f = $m['flow'][$a['a']] ?? null;
    if ($row === null || $f === null || $row['deleted_at'] || !isset($f['ask']) || !$flowAllowed($m, (string) $a['a'], $row)) {
        flash('That step is not possible now.', 'bad');
        redirect(Ops::url($key, (string) $a['id']));
    }
    $fields = Ops::fields($p, $m, $f['ask'], $row);
    $h = '<form class="d-form" method="post" action="' . e(Ops::url($key, $row['id'] . '/do/' . $a['a'])) . '">' . csrf_field() . '<div class="d-form-grid">';
    foreach ($fields as $n => $fs) {
        $cur = $n === 'notes' ? '' : (string) ($row[$n] ?? ($fs['default'] ?? ''));
        $h .= Crud::field($n, $fs, old($n, $cur));
    }
    $h .= '</div><div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">' . e($f[0]) . '</button><a class="d-btn d-btn-soft" href="' . e(Ops::url($key, (string) $row['id'])) . '">Back</a></div></form>';
    clear_old();
    $dash($p, 'ops-' . $key, $f[0] . ': ' . ($row['code'] ?: $row['title']), $card('', $h), ['sub' => (string) $row['title']]);
};
$app->router->get('/portal/ops/{m}/{id}/do/{a}', $flowForm);
$app->router->post('/portal/ops/{m}/{id}/do/{a}', static function (array $a) use ($app, $boot, $load, $audit, $flowAllowed): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $row = $load($p, $key, (int) $a['id']);
    $f = $m['flow'][$a['a']] ?? null;
    $to = Ops::url($key, (string) $a['id']);
    if ($row === null || $f === null || $row['deleted_at'] || !$flowAllowed($m, (string) $a['a'], $row)) {
        flash('That step is not possible now.', 'bad');
        redirect($to);
    }
    $upd = [];
    if (isset($f['ask'])) {
        [$upd, $err] = Crud::collect(Ops::fields($p, $m, $f['ask'], $row));
        if ($err !== null) {
            keep_old($_POST);
            flash($err, 'bad');
            redirect(Ops::url($key, $row['id'] . '/do/' . $a['a']));
        }
        if (isset($upd['notes']) && !empty($row['notes']) && $key !== 'samples') {
            $upd['notes'] = trim($row['notes'] . "\n" . $f[0] . ': ' . $upd['notes']);
        }
        $upd = Ops::pack(Ops::fields($p, $m, $f['ask'], $row), $upd, $row);
    }
    $car = (string) ($upd['cat'] ?? $row['cat'] ?? '');
    if (($f['vehicle'] ?? '') === 'busy') {
        $ok = $app->db->val("SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = 'vehicles' AND code = ? AND status = 'available' AND deleted_at IS NULL", [$p->tenantId, $car]);
        if (!$ok) {
            flash('That vehicle is not free now. Choose another one.', 'bad');
            redirect(Ops::url($key, $row['id'] . '/do/' . $a['a']));
        }
    }
    if (!empty($f['asset']['need'])) {
        $as = $f['asset'];
        $acol = (string) ($as['col'] ?? 'cat');
        $acode = (string) ($upd[$acol] ?? $row[$acol] ?? '');
        $need = (string) $as['need'];
        $ok = $acode !== '' && $app->db->val('SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = ? AND code = ? AND status = ? AND deleted_at IS NULL', [$p->tenantId, $as['module'], $acode, $need]);
        if (!$ok) {
            flash('That item is not free now. Choose another one.', 'bad');
            redirect(Ops::url($key, $row['id'] . '/do/' . $a['a']));
        }
    }
    foreach ($f['clear'] ?? [] as $c) {
        $upd[$c] = null;
    }
    foreach ($f['set'] ?? [] as $c => $v) {
        $upd[$c] = $v === 'today' ? date('Y-m-d') : $v;
    }
    if ($key === 'donors' && $a['a'] === 'donated') {
        $upd += ['date1' => date('Y-m-d'), 'date2' => date('Y-m-d', strtotime('+90 days')), 'qty' => (float) $row['qty'] + 1];
    }
    $upd['status'] = $f[2];
    $upd['updated_at'] = date('Y-m-d H:i:s');
    $app->db->begin();
    try {
        $app->db->update('sc_records', $upd, 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
        if (($f['vehicle'] ?? '') === 'busy') {
            $app->db->run("UPDATE sc_records SET status = 'on_trip', updated_at = NOW() WHERE tenant_id = ? AND module = 'vehicles' AND code = ?", [$p->tenantId, $car]);
        } elseif (($f['vehicle'] ?? '') === 'free' && $car !== '') {
            $app->db->run("UPDATE sc_records SET status = 'available', updated_at = NOW() WHERE tenant_id = ? AND module = 'vehicles' AND code = ? AND status = 'on_trip'", [$p->tenantId, $car]);
        }
        if (!empty($f['asset'])) {
            $as = $f['asset'];
            $acol = (string) ($as['col'] ?? 'cat');
            $acode = (string) ($upd[$acol] ?? $row[$acol] ?? '');
            $amod = (string) $as['module'];
            if ($acode !== '' && !empty($as['to'])) {
                $app->db->run("UPDATE sc_records SET status = ?, updated_at = NOW() WHERE tenant_id = ? AND module = ? AND code = ? AND deleted_at IS NULL", [$as['to'], $p->tenantId, $amod, $acode]);
            }
        }
        $app->db->commit();
    } catch (\Throwable $e) {
        $app->db->rollBack();
        throw $e;
    }
    Ops::event($app->db, $p->tenantId, (int) $row['id'], $key, (string) $a['a'], (string) $row['status'], (string) $f[2], (int) $p->user['id'], isset($f['ask']) ? ($upd['notes'] ?? ($upd['cat'] ?? null)) : null);
    $audit($p, $key . '_' . $a['a'], ['id' => (int) $row['id'], 'status' => $row['status']], ['status' => $f[2]]);
    flash($f[0] . ': done.', 'ok');
    if (!empty($f['then'])) {
        [$nextKey, $map] = $f['then'];
        $q = [];
        foreach ($map as $to2 => $from) {
            $q[$to2] = (string) ($row[$from] ?? '');
        }
        redirect(Ops::url($nextKey, 'new', $q));
    }
    redirect($to);
});

// ---------------------------------------------------------------- delete, restore, print
$app->router->post('/portal/ops/{m}/{id}/delete', static function (array $a) use ($app, $boot, $load, $audit): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $row = $load($p, $key, (int) $a['id']);
    if ($row !== null && !$row['deleted_at']) {
        $app->db->update('sc_records', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
        if ($key === 'trips' && in_array($row['status'], ['dispatched', 'onroute'], true) && $row['cat']) {
            $app->db->run("UPDATE sc_records SET status = 'available' WHERE tenant_id = ? AND module = 'vehicles' AND code = ? AND status = 'on_trip'", [$p->tenantId, $row['cat']]);
        }
        Ops::event($app->db, $p->tenantId, (int) $row['id'], $key, 'deleted', (string) $row['status'], (string) $row['status'], (int) $p->user['id']);
        $audit($p, $key . '_deleted', ['id' => (int) $row['id']], null);
        flash('Deleted. You can bring it back from the deleted list.', 'ok');
    }
    redirect(Ops::url($key));
});
$app->router->post('/portal/ops/{m}/{id}/restore', static function (array $a) use ($app, $boot, $load, $audit): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $row = $load($p, $key, (int) $a['id']);
    if ($row !== null && $row['deleted_at']) {
        $app->db->update('sc_records', ['deleted_at' => null], 'id = ? AND tenant_id = ?', [$row['id'], $p->tenantId]);
        Ops::event($app->db, $p->tenantId, (int) $row['id'], $key, 'restored', (string) $row['status'], (string) $row['status'], (int) $p->user['id']);
        $audit($p, $key . '_restored', null, ['id' => (int) $row['id']]);
        flash('Brought back.', 'ok');
    }
    redirect(Ops::url($key, (string) $a['id']));
});
$app->router->get('/portal/ops/{m}/{id}/print', static function (array $a) use ($app, $boot, $load, $printOk): void {
    [$p, $m] = $boot((string) $a['m']);
    $row = $load($p, (string) $a['m'], (int) $a['id']);
    if ($row === null || !$printOk($m, $row)) {
        $app->error(404, 'There is nothing to print for this record yet.');
    }
    $row = Ops::unpack($row);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $v = (string) $p->vendor['name'];
    $kv = static fn (string $k, string $val): string => $val === '' ? '' : '<tr><th>' . e($k) . '</th><td>' . e($val) . '</td></tr>';
    $pr = $m['print'];
    $docTitle = is_array($pr) ? (string) ($pr['title'] ?? 'Document') : 'Laboratory report';
    $pairs = '';
    if (is_array($pr) && !empty($pr['rows'])) {
        foreach ($pr['rows'] as $col => $lab) {
            $val = (string) ($row[$col] ?? '');
            if ($col === 'date1' || $col === 'date2') {
                $val = $val !== '' ? dt($val) : '';
            } elseif ($col === 'amount' && $val !== '') {
                $val = money((float) $val);
            } elseif ($col === 'status' && isset($m['status'][$val])) {
                $val = (string) $m['status'][$val][0];
            }
            $pairs .= $kv((string) $lab, $val);
        }
    } else {
        $pairs .= $kv('Report number', (string) $row['code']) . $kv('Patient', (string) $row['title']) . $kv('Phone', (string) $row['phone']) . $kv('Referred by', (string) $row['party'])
            . $kv('Test', (string) $row['cat']) . $kv('Sample', (string) $row['place_a']) . $kv('Collected on', dt((string) $row['date1'])) . $kv('Reported on', dt((string) $row['date2']));
    }
    $notes = '';
    $showNotes = is_array($pr) ? ($pr['notes'] ?? true) : true;
    $notesLabel = is_array($pr) ? (string) ($pr['notes_label'] ?? 'Notes') : 'Result';
    if ($showNotes && trim((string) ($row['notes'] ?? '')) !== '') {
        $notes = '<h2>' . e($notesLabel) . '</h2><div class="res">' . e((string) $row['notes']) . '</div>';
    }
    $extra = '';
    if (is_array($pr)) {
        foreach (($pr['extra'] ?? []) as $col => $lab) {
            $val = (string) ($row[$col] ?? '');
            if ($val !== '') {
                $extra .= '<h2>' . e((string) $lab) . '</h2><div class="res">' . e($val) . '</div>';
            }
        }
    }
    $sig = (is_array($pr) ? ($pr['sign'] ?? true) : true) ? '<div class="sig">' . e(is_array($pr) ? (string) ($pr['sign_label'] ?? 'Authorised signature') : 'Authorised signature') . '</div>' : '';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>' . e($docTitle) . ' ' . e((string) $row['code']) . '</title><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font:15px/1.5 system-ui,Segoe UI,Arial,sans-serif;color:#111;max-width:760px;margin:24px auto;padding:0 18px}h1{font-size:1.5rem;margin:0}h2{font-size:1rem;margin:26px 0 8px;text-transform:uppercase;letter-spacing:.06em;color:#555}'
        . 'header{border-bottom:3px solid #0f766e;padding-bottom:12px;margin-bottom:18px}table{border-collapse:collapse;width:100%}th{text-align:left;width:34%;color:#555;font-weight:600}th,td{padding:6px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}'
        . '.res{white-space:pre-wrap;border:1px solid #cbd5e1;border-radius:8px;padding:14px;min-height:80px}footer{margin-top:40px;color:#666;font-size:.8rem;display:flex;justify-content:space-between}.sig{margin-top:56px;border-top:1px solid #111;width:220px;padding-top:4px;font-size:.85rem}'
        . '.no{margin-top:18px}@media print{.no{display:none}body{margin:0}}</style>'
        . '<header><h1>' . e($v) . '</h1><div>' . e($docTitle) . '</div></header><table>'
        . $pairs . '</table>' . $notes . $extra . $sig
        . '<footer><span>Printed ' . e(date('d M Y H:i')) . '</span><span>' . e($v) . '</span></footer><p class="no"><button onclick="window.print()">Print</button></p></html>';
    exit;
});
