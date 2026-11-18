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
$app->router->get('/portal/ops/{m}', static function (array $a) use ($app, $boot, $dash, $card, $btn, $columnsFor, $flowAllowed): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $w = $p->writable();
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
$formPage = static function (Portal $p, array $m, string $key, ?array $row) use ($app, $dash, $card, $flowAllowed): void {
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
        if (!empty($m['print']) && in_array($row['status'], ['reported', 'delivered'], true)) {
            $acts .= '<a class="d-btn d-btn-soft" target="_blank" rel="noopener" href="' . e(Ops::url($m['_key'], $row['id'] . '/print')) . '">Print report</a>';
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
$app->router->post('/portal/ops/{m}/save', static function (array $a) use ($app, $boot, $load, $audit): void {
    [$p, $m] = $boot((string) $a['m']);
    $key = (string) $a['m'];
    $p->needWritable();
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id ? $load($p, $key, $id) : null;
    if ($id && $old === null) {
        $app->error(404, 'We could not find that record.');
    }
    $back = $id ? Ops::url($key, (string) $id) : Ops::url($key, 'new');
    [$d, $err] = Crud::collect(Ops::fields($p, $m, $m['fields'], $old));
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
    if ($old === null) {
        if (!isset($d['status'])) {
            $d['status'] = (string) $m['initial'];
        }
        if (!isset($d['code']) && !empty($m['prefix'])) {
            $d['code'] = Ops::code($app->db, $p->tenantId, $key, (string) $m['prefix']);
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
    }
    $car = (string) ($upd['cat'] ?? $row['cat'] ?? '');
    if (($f['vehicle'] ?? '') === 'busy') {
        $ok = $app->db->val("SELECT COUNT(*) FROM sc_records WHERE tenant_id = ? AND module = 'vehicles' AND code = ? AND status = 'available' AND deleted_at IS NULL", [$p->tenantId, $car]);
        if (!$ok) {
            flash('That vehicle is not free now. Choose another one.', 'bad');
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
$app->router->get('/portal/ops/{m}/{id}/print', static function (array $a) use ($app, $boot, $load): void {
    [$p, $m] = $boot((string) $a['m']);
    $row = $load($p, (string) $a['m'], (int) $a['id']);
    if ($row === null || empty($m['print']) || !in_array($row['status'], ['reported', 'delivered'], true)) {
        $app->error(404, 'There is no report to print for this sample yet.');
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $v = (string) $p->vendor['name'];
    $kv = static fn (string $k, string $val): string => $val === '' ? '' : '<tr><th>' . e($k) . '</th><td>' . e($val) . '</td></tr>';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Report ' . e((string) $row['code']) . '</title><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font:15px/1.5 system-ui,Segoe UI,Arial,sans-serif;color:#111;max-width:760px;margin:24px auto;padding:0 18px}h1{font-size:1.5rem;margin:0}h2{font-size:1rem;margin:26px 0 8px;text-transform:uppercase;letter-spacing:.06em;color:#555}'
        . 'header{border-bottom:3px solid #0f766e;padding-bottom:12px;margin-bottom:18px}table{border-collapse:collapse;width:100%}th{text-align:left;width:34%;color:#555;font-weight:600}th,td{padding:6px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}'
        . '.res{white-space:pre-wrap;border:1px solid #cbd5e1;border-radius:8px;padding:14px;min-height:120px}footer{margin-top:40px;color:#666;font-size:.8rem;display:flex;justify-content:space-between}.sig{margin-top:56px;border-top:1px solid #111;width:220px;padding-top:4px;font-size:.85rem}'
        . '.no{margin-top:18px}@media print{.no{display:none}body{margin:0}}</style>'
        . '<header><h1>' . e($v) . '</h1><div>Laboratory report</div></header><table>'
        . $kv('Report number', (string) $row['code']) . $kv('Patient', (string) $row['title']) . $kv('Phone', (string) $row['phone']) . $kv('Referred by', (string) $row['party'])
        . $kv('Test', (string) $row['cat']) . $kv('Sample', (string) $row['place_a']) . $kv('Collected on', dt((string) $row['date1'])) . $kv('Reported on', dt((string) $row['date2']))
        . '</table><h2>Result</h2><div class="res">' . e((string) $row['notes']) . '</div><div class="sig">Authorised signature</div>'
        . '<footer><span>Printed ' . e(date('d M Y H:i')) . '</span><span>' . e($v) . '</span></footer><p class="no"><button onclick="window.print()">Print</button></p></html>';
    exit;
});
