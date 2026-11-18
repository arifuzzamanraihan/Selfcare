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

    /** Fields with dynamic option lists resolved (for example the free vehicles). @return array<string,array<string,mixed>> */
    public static function fields(Portal $p, array $m, array $fields, ?array $row = null): array
    {
        foreach ($fields as $k => $f) {
            if (isset($f['options']) && is_string($f['options'])) {
                $fn = $m['dynamic'][$f['options']] ?? null;
                $fields[$k]['options'] = $fn ? $fn($p, $row, true) : [];
            }
        }
        return $fields;
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

    /** Donors who finished resting can give again. */
    public static function refresh(Database $db, Portal $p): void
    {
        if ((string) $p->type['slug'] === 'blood-bank') {
            $db->run("UPDATE sc_records SET status = 'eligible', updated_at = NOW() WHERE tenant_id = ? AND module = 'donors' AND deleted_at IS NULL AND status = 'resting' AND date2 IS NOT NULL AND date2 <= CURDATE()", [$p->tenantId]);
            if (isset(self::all()['units'])) { self::expire($db, $p->tenantId, self::all()['units'], 'units'); }
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
