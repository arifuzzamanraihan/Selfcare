<?php
// Selfcare original code. Engine for the type-specific work lists in core/config/ops.php
// (prescription orders, samples, blood units, trips ...) and the stock ledger.
// Routes are in core/routes/ops.php. Rows live in sc_records, stock changes in sc_stock_moves.
//
// A module is one entry in a file of core/config/ops/ (one file per kind of organisation). Every row lives in sc_records;
// the generic columns are re-labelled per module:  code, title, party, phone, cat, place_a, place_b, qty, amount, date1, date2, status, notes.
//   types    vendor type slugs that get the module        perm / prefix   role permission, and the code prefix (SMP-0001)
//   fields   form fields keyed by column                  columns   key => 'Label' | ['Label', date|money|num]
//   status   value => [label, tone]                       initial   first status      status_field   true = status is a form field
//   flow     action => [label, from status(es), to status, icon, + confirm | ask (small form first) | set | clear | vehicle | then]
//   expire   statuses that turn into one status when the date column has passed      kpis / charts   overview tiles and charts
declare(strict_types=1);

namespace Selfcare;

final class Ops
{
    /** @var ?array<string,array<string,mixed>> */
    private static ?array $cfg = null;

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        if (self::$cfg === null) {
            self::$cfg = [];
            $files = glob(__DIR__ . '/config/ops/*.php') ?: [];
            sort($files);
            foreach ($files as $f) {
                self::$cfg += require $f;
            }
        }
        return self::$cfg;
    }

    /** Modules this portal may use: right kind of organisation, role permission and plan. @return array<string,array<string,mixed>> */
    public static function forPortal(Portal $p): array
    {
        if ($p->isSide() || !$p->module('documents')) {
            return [];
        }
        $out = [];
        foreach (self::all() as $k => $m) {
            if (in_array((string) $p->type['slug'], $m['types'], true) && $p->can((string) $m['perm'])) {
                $out[$k] = $m;
            }
        }
        return $out;
    }

    /** @return ?array<string,mixed> */
    public static function find(Portal $p, string $key): ?array
    {
        return self::forPortal($p)[$key] ?? null;
    }

    public static function url(string $key, string $tail = '', array $q = []): string
    {
        return url('portal/ops/' . $key . ($tail !== '' ? '/' . ltrim($tail, '/') : ''), $q);
    }

    /** Normalised column list: key => [label, type]. @return array<string,array{0:string,1:string}> */
    public static function columns(array $m): array
    {
        $out = [];
        foreach ($m['columns'] as $k => $c) {
            $out[$k] = is_array($c) ? [(string) $c[0], (string) ($c[1] ?? 'text')] : [(string) $c, $k === 'status' ? 'status' : 'text'];
        }
        return $out;
    }

    public static function badge(array $m, string $status): string
    {
        [$label, $tone] = $m['status'][$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'mute'];
        return '<span class="d-badge is-' . e($tone) . '">' . e($label) . '</span>';
    }

    /** Fields with dynamic option lists resolved (vehicles, registered patients, doctors). @return array<string,array<string,mixed>> */
    public static function fields(Portal $p, array $m, array $fields, ?array $row = null): array
    {
        foreach ($fields as $k => $f) {
            if (isset($f['options']) && is_string($f['options'])) {
                $name = (string) $f['options'];
                $fn = $m['dynamic'][$name] ?? null;
                if ($fn) {
                    $fields[$k]['options'] = $fn($p, $row, true);
                } elseif ($name === 'patients' || $name === 'doctors') {
                    [$opts, $meta] = self::peopleMeta($p, $name, $row);
                    $fields[$k]['options'] = $opts;
                    $fields[$k]['option_meta'] = $meta;
                    if ($name === 'patients') {
                        $fields[$k]['fill'] = 'patient';
                    }
                } else {
                    $fields[$k]['options'] = [];
                }
            }
        }
        return $fields;
    }

    /** Registered patients or doctors of this vendor, plus the current row's choice if it was later removed. @return array<string,string> */
    public static function people(Portal $p, string $kind, ?array $row = null): array
    {
        $db = app()->db;
        if ($kind === 'doctors') {
            $rows = $db->all("SELECT d.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, d.specialist FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? AND u.status = 1 AND u.deleted_at IS NULL ORDER BY u.first_name, u.last_name", [$p->tenantId]);
            $col = 'staff_id';
        } else {
            $rows = $db->all("SELECT p.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, p.patient_unique_id, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.tenant_id = ? AND u.status = 1 AND u.deleted_at IS NULL ORDER BY u.first_name, u.last_name", [$p->tenantId]);
            $col = 'person_id';
        }
        $o = [];
        foreach ($rows as $r) {
            $lab = (string) $r['n'];
            if ($kind === 'doctors' && $r['specialist']) {
                $lab .= ' · ' . $r['specialist'];
            } elseif ($kind !== 'doctors' && !empty($r['patient_unique_id'])) {
                $lab .= ' · ' . $r['patient_unique_id'];
            }
            $o[(string) $r['id']] = $lab;
        }
        if ($row && !empty($row[$col]) && !isset($o[(string) $row[$col]])) {
            $o[(string) $row[$col]] = 'Previously selected (#' . (int) $row[$col] . ')';
        }
        return $o;
    }

    /** @return array{0:array<string,string>,1:array<string,array{name:string,phone:string}>} */
    public static function peopleMeta(Portal $p, string $kind, ?array $row = null): array
    {
        $opts = self::people($p, $kind, $row);
        $meta = [];
        if ($kind === 'patients') {
            $rows = app()->db->all("SELECT p.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.tenant_id = ?", [$p->tenantId]);
            foreach ($rows as $r) {
                $meta[(string) $r['id']] = ['name' => (string) $r['n'], 'phone' => (string) ($r['phone'] ?? '')];
            }
        }
        return [$opts, $meta];
    }

    /** Phone and display name of a registered patient of this vendor, or null. @return ?array{name:string,phone:string} */
    public static function patientInfo(Portal $p, int $id): ?array
    {
        $r = app()->db->one("SELECT TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ?", [$id, $p->tenantId]);
        return $r ? ['name' => (string) $r['n'], 'phone' => (string) ($r['phone'] ?? '')] : null;
    }

    /** Display name of a doctor of this vendor, or null. */
    public static function doctorName(Portal $p, int $id): ?string
    {
        $n = app()->db->val("SELECT TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ? AND d.tenant_id = ?", [$id, $p->tenantId]);
        return $n ? (string) $n : null;
    }

    /** Columns that may be written on sc_records. @return list<string> */
    public static function cols(): array
    {
        return ['code', 'title', 'party', 'phone', 'cat', 'place_a', 'place_b', 'qty', 'amount', 'date1', 'date2', 'status', 'notes', 'tag', 'invoice_id', 'sort_order', 'meta', 'person_id', 'staff_id'];
    }

    /** Moves fields marked store=meta into the JSON column and drops unknown keys. @param array<string,mixed> $d */
    public static function pack(array $fields, array $d, ?array $old = null): array
    {
        $meta = $old && !empty($old['meta']) ? (json_decode((string) $old['meta'], true) ?: []) : [];
        foreach ($fields as $n => $f) {
            if (($f['store'] ?? '') === 'meta' && array_key_exists($n, $d)) {
                $meta[$n] = $d[$n];
                unset($d[$n]);
            }
        }
        $d['meta'] = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
        $ok = array_flip(self::cols());
        foreach (array_keys($d) as $k) {
            if (!isset($ok[$k])) {
                unset($d[$k]);
            }
        }
        return $d;
    }

    /** Puts JSON extras back onto the row so forms and prints can read them as ordinary fields. @param array<string,mixed> $row */
    public static function unpack(array $row): array
    {
        if (!empty($row['meta'])) {
            $meta = json_decode((string) $row['meta'], true);
            if (is_array($meta)) {
                foreach ($meta as $k => $v) {
                    if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
                        $row[$k] = is_scalar($v) || $v === null ? $v : json_encode($v);
                    }
                }
            }
        }
        return $row;
    }

    /** Next daily number such as 12, unique per vendor, list and date. */
    public static function dailyCode(Database $db, string $tenant, string $module, string $date): string
    {
        $n = (int) $db->val('SELECT COALESCE(MAX(CAST(code AS UNSIGNED)), 0) FROM sc_records WHERE tenant_id = ? AND module = ? AND date1 = ? AND code REGEXP ?', [$tenant, $module, $date, '^[0-9]+$']);
        return (string) ($n + 1);
    }

    /** Next queue position for a day (or the list as a whole when the date is empty). */
    public static function nextSort(Database $db, string $tenant, string $module, ?string $date): int
    {
        if ($date) {
            $n = (int) $db->val('SELECT COALESCE(MAX(sort_order), 0) FROM sc_records WHERE tenant_id = ? AND module = ? AND date1 <=> ? AND deleted_at IS NULL', [$tenant, $module, $date]);
        } else {
            $n = (int) $db->val('SELECT COALESCE(MAX(sort_order), 0) FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL', [$tenant, $module]);
        }
        return $n + 1;
    }

    /** Invoice type to offer on a record ("Make invoice"), or null when billing is not possible now. */
    public static function billType(Portal $p, array $m, array $row): ?string
    {
        $b = $m['bill'] ?? null;
        if ($b === null || !empty($row['invoice_id']) || !empty($row['deleted_at']) || !in_array($row['status'], (array) $b['when'], true) || !$p->can('manage_invoices') || !$p->module('billing')) {
            return null;
        }
        $billing = new Billing(app()->db);
        $cat = $billing->catalog((string) $p->type['slug'], $p->family)['invoices'];
        return isset($cat[$b['type']]) && $p->module($billing->module((string) $b['type'])) ? (string) $b['type'] : null;
    }

    /** Values to put on a new invoice from a record: party, reference, extra header fields and the first line. @return array<string,mixed> */
    public static function billPrefill(array $m, array $row): array
    {
        $b = $m['bill'];
        $x = [];
        foreach ($b['x'] ?? [] as $name => $col) {
            $x['x_' . $name] = (string) ($row[$col] ?? '');
        }
        $name = str_replace(
            ['{code}', '{cat}', '{qty}', '{title}', '{party}', '{place_a}', '{place_b}'],
            [(string) $row['code'], (string) $row['cat'], rtrim(rtrim(number_format((float) $row['qty'], 2, '.', ''), '0'), '.'), (string) $row['title'], (string) ($row['party'] ?? ''), (string) ($row['place_a'] ?? ''), (string) ($row['place_b'] ?? '')],
            (string) ($b['item'] ?? '')
        );
        return ['party_name' => (string) $row['title'], 'party_phone' => (string) $row['phone'], 'reference' => (string) $row['code']] + (isset($b['item']) ? ['item' => ['item_name' => $name, 'qty' => 1, 'price' => (float) ($row[$b['price'] ?? 'amount'] ?? 0)]] : []) + $x;
    }

    /** Writes one line of the history of a record (who changed what, when). */
    public static function event(Database $db, string $tenant, int $recordId, string $module, string $action, ?string $from, ?string $to, ?int $userId, ?string $note = null): void
    {
        $db->insert('sc_record_events', ['tenant_id' => $tenant, 'record_id' => $recordId, 'module' => $module, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'note' => $note !== null ? mb_substr($note, 0, 500) : null, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** History of one record as an HTML list, with the time since the step before. */
    public static function timeline(Database $db, string $tenant, array $m, int $recordId): string
    {
        $rows = $db->all('SELECT e.*, u.first_name, u.last_name FROM sc_record_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.tenant_id = ? AND e.record_id = ? ORDER BY e.id', [$tenant, $recordId]);
        if (!$rows) {
            return '<p class="d-help">No history yet.</p>';
        }
        $h = '<ol class="d-timeline" style="list-style:none;margin:0;padding:0">';
        $prev = null;
        foreach ($rows as $r) {
            $t = strtotime((string) $r['created_at']);
            $gap = $prev !== null ? self::span($t - $prev) : '';
            $label = match ($r['action']) {
                'created' => 'Added',
                'deleted' => 'Deleted',
                'restored' => 'Brought back',
                'billed' => 'Invoice made',
                default => (string) ($m['flow'][$r['action']][0] ?? ucfirst(str_replace('_', ' ', (string) $r['action']))),
            };
            $who = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $h .= '<li style="padding:8px 0;border-bottom:1px solid var(--d-line,#e5e7eb)"><b>' . e($label) . '</b>'
                . ($r['to_status'] && $r['action'] !== 'created' && isset($m['status'][$r['to_status']]) ? ' ' . self::badge($m, (string) $r['to_status']) : '')
                . '<div class="d-help">' . e(dt((string) $r['created_at'], 'd M Y H:i')) . ($who !== '' ? ' · ' . e($who) : '') . ($gap !== '' ? ' · ' . e($gap) . ' after the step before' : '') . '</div>'
                . ($r['note'] ? '<div class="d-help">' . e((string) $r['note']) . '</div>' : '') . '</li>';
            $prev = $t;
        }
        return $h . '</ol>';
    }

    public static function span(int $sec): string
    {
        if ($sec < 90) {
            return max(0, $sec) . ' s';
        }
        if ($sec < 5400) {
            return (int) round($sec / 60) . ' min';
        }
        if ($sec < 172800) {
            return round($sec / 3600, 1) . ' h';
        }
        return (int) round($sec / 86400) . ' days';
    }

    /** Next code such as SMP-0007 (unique per vendor and module). */
    public static function code(Database $db, string $tenant, string $module, string $prefix): string
    {
        $n = (int) $db->val('SELECT COALESCE(MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)), 0) FROM sc_records WHERE tenant_id = ? AND module = ? AND code LIKE ?', [strlen($prefix) + 1, $tenant, $module, $prefix . '%']);
        return $prefix . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Module rules applied before a row is saved. Returns an error text or null. @param array<string,mixed> $d */
    public static function prepare(string $key, array &$d, ?array $old, array $all): ?string
    {
        $get = static fn (string $f) => array_key_exists($f, $d) ? $d[$f] : ($old[$f] ?? null);
        if ($key === 'donors') {
            if (!empty($d['date1']) && $d['date1'] > date('Y-m-d')) {
                return 'The last donation cannot be in the future.';
            }
            if (!empty($d['date1']) && empty($d['date2'])) {
                $d['date2'] = date('Y-m-d', strtotime($d['date1'] . ' +90 days'));
            }
        }
        if ($key === 'units') {
            $coll = (string) $get('date1');
            if ($coll !== '' && empty($d['date2'])) {
                $days = match ((string) $get('party')) { 'Platelets' => 5, 'Plasma', 'Cryoprecipitate' => 365, default => 35 };
                $d['date2'] = date('Y-m-d', strtotime($coll . ' +' . $days . ' days'));
            }
            if ($coll !== '' && !empty($d['date2']) && $d['date2'] < $coll) {
                return 'The expiry cannot be before the collection date.';
            }
        }
        return null;
    }

    /** Bags past their expiry date become "Expired" by themselves. */
    public static function expire(Database $db, string $tenant, array $m, string $key): void
    {
        if (empty($m['expire'])) {
            return;
        }
        $e = $m['expire'];
        $in = implode(',', array_fill(0, count($e['status']), '?'));
        $db->run("UPDATE sc_records SET status = ?, updated_at = NOW() WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL AND status IN ($in) AND {$e['col']} IS NOT NULL AND {$e['col']} < CURDATE()", array_merge([$e['to'], $tenant, $key], $e['status']));
    }

    /** Time-based state changes for this vendor (donor rest, expired bags, overdue rentals, …). */
    public static function refresh(Database $db, Portal $p): void
    {
        if ((string) $p->type['slug'] === 'blood-bank') {
            $db->run("UPDATE sc_records SET status = 'eligible', updated_at = NOW() WHERE tenant_id = ? AND module = 'donors' AND deleted_at IS NULL AND status = 'resting' AND date2 IS NOT NULL AND date2 <= CURDATE()", [$p->tenantId]);
        }
        foreach (self::forPortal($p) as $key => $m) {
            self::expire($db, $p->tenantId, $m, $key);
        }
    }

    /** Changes a stock item and writes the ledger line, inside the caller's transaction or on its own. */
    public static function stockMove(Database $db, string $tenant, int $itemId, float $change, string $reason, string $note, ?int $userId): void
    {
        $db->run('UPDATE sc_stock_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [0.0, $itemId, $tenant]);
        $bal = $db->val('SELECT quantity FROM sc_stock_items WHERE id = ? AND tenant_id = ?', [$itemId, $tenant]);
        $db->insert('sc_stock_moves', ['tenant_id' => $tenant, 'item_id' => $itemId, 'change_qty' => $change, 'balance' => $bal, 'reason' => $reason, 'note' => mb_substr($note, 0, 255) ?: null, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    private static function sql(string $sql, string $tenant): array
    {
        $n = substr_count($sql, '{t}');
        return [str_replace(['{t}', '{since}'], ['?', "'" . date('Y-m-01', strtotime('-5 month')) . "'"], $sql), array_fill(0, $n, $tenant)];
    }

    /** Numbers and charts for the overview page. @return array{0:string,1:string} [stat tiles, chart cards] */
    public static function overview(Portal $p, callable $stat, callable $card): array
    {
        $db = \Selfcare\app()->db;
        self::refresh($db, $p);
        $tiles = '';
        $charts = '';
        $slug = (string) $p->type['slug'];
        if ($slug === 'pharmacy' && $p->can('manage_medicines') && $p->module('documents')) {
            $q = static fn (string $w): int => (int) $db->val("SELECT COUNT(*) FROM sc_stock_items WHERE tenant_id = ? AND $w", [$p->tenantId]);
            $tiles .= $stat('Low stock', (string) $q('reorder_level > 0 AND quantity <= reorder_level AND quantity > 0'), 'box', 'at or below the reorder level');
            $tiles .= $stat('Out of stock', (string) $q('quantity <= 0'), 'box', 'items to buy now');
            $tiles .= $stat('Expiring in 60 days', (string) $q('quantity > 0 AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)'), 'clock', 'sell or return these');
            $tiles .= $stat('Expired', (string) $q('quantity > 0 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()'), 'clock', 'take off the shelf');
        }
        $months = Charts::months(6);
        foreach (self::forPortal($p) as $key => $m) {
            foreach ($m['kpis'] ?? [] as $k) {
                [$sql, $par] = self::sql($k[1], $p->tenantId);
                if (($k[6] ?? '') === 'money') {
                    $cur = Currency::forTenant($p->tenantId);
                    $sql = preg_replace_callback('/SUM\((?:[a-z]+\.)?(amount|total|paid|balance|fare)\)/i', static fn ($m) => 'SUM(' . Currency::sql($m[1], 'currency', $cur) . ')', $sql) ?? $sql;
                }
                $v = $db->val($sql, $par);
                $tiles .= $stat((string) $k[0], ($k[6] ?? '') === 'money' ? money((float) $v) : (string) (int) $v, (string) $k[2], (string) $k[3]);
            }
            foreach ($m['charts'] ?? [] as $c) {
                $body = '';
                if ($c['kind'] === 'status') {
                    $got = array_column($db->all('SELECT status, COUNT(*) n FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL GROUP BY status', [$p->tenantId, $key]), 'n', 'status');
                    $bars = [];
                    foreach ($m['status'] as $s => [$label]) {
                        $bars[] = [(string) $label, (int) ($got[$s] ?? 0), self::url($key, '', ['f_status' => $s])];
                    }
                    $body = Charts::bars($bars);
                } elseif ($c['kind'] === 'group') {
                    $col = preg_replace('/[^a-z_0-9]/', '', (string) $c['col']);
                    $got = array_column($db->all("SELECT $col g, COUNT(*) n FROM sc_records WHERE tenant_id = ? AND module = ? AND deleted_at IS NULL" . (!empty($c['where']) ? ' AND ' . $c['where'] : '') . " GROUP BY $col", [$p->tenantId, $key]), 'n', 'g');
                    $bars = [];
                    foreach ($c['order'] as $g) {
                        $bars[] = [(string) $g, (int) ($got[$g] ?? 0), null];
                    }
                    $body = Charts::bars($bars);
                } else {
                    [$sql, $par] = self::sql($c['sql'], $p->tenantId);
                    $got = array_column($db->all($sql, $par), 'n', 'm');
                    $body = Charts::columns(array_map(static fn ($mo) => [$mo[0], (int) ($got[$mo[1]] ?? 0)], $months), 'Nothing recorded yet.');
                }
                $charts .= $card((string) $c['title'], $body);
            }
        }
        return [$tiles, $charts];
    }
}
