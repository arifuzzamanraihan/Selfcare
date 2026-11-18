<?php
// Selfcare original code. Web addresses of a vendor: its sub domain and the custom domains it adds.
// Table sc_domains keeps one row per address with its DNS and SSL state. A worker (bin/ssl-worker.php, run by cron on the
// server) checks DNS, asks Let's Encrypt for a certificate over HTTP-01 and writes the Apache virtual host for the address.
declare(strict_types=1);

namespace Selfcare;

final class Domains
{
    public function __construct(private Database $db, private Env $env, private Tenant $tenant)
    {
    }

    /** The public IP that custom domains must point to (A record). */
    public function serverIp(): string
    {
        return $this->env->get('SERVER_IP', '194.31.52.240');
    }

    /** Sub domain every vendor has, used as the CNAME target. */
    public function cnameTarget(string $slug): string
    {
        return $this->tenant->hostFor($slug) ?? $this->env->get('MAIN_HOST');
    }

    public function enabled(): bool
    {
        return $this->env->get('SSL_AUTOMATION') === '1';
    }

    /** Lower case host, or null when it is not a usable domain name. */
    public function clean(string $raw): ?string
    {
        $h = strtolower(trim($raw));
        $h = preg_replace('#^https?://#', '', $h) ?? '';
        $h = rtrim(explode('/', $h)[0], '.');
        $h = preg_replace('/:\d+$/', '', $h) ?? '';
        return preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $h) ? $h : null;
    }

    /** Why a host cannot be added, or null. */
    public function error(string $host, ?string $tenantId = null): ?string
    {
        $suffix = $this->tenant->suffix();
        $main = strtolower($this->env->get('MAIN_HOST'));
        $base = $main !== '' ? preg_replace('/^[^.]+\./', '', $main) : '';
        if ($host === $main || ($suffix !== '' && str_ends_with($host, $suffix)) || ($base !== '' && ($host === $base || str_ends_with($host, '.' . $base)))) {
            return 'That is a Self Care address. Use your own domain, for example care.yourname.com.';
        }
        $row = $this->db->one("SELECT tenant_id, status FROM sc_domains WHERE host = ?", [$host]);
        if ($row !== null && $row['status'] !== 'removed') {
            return (string) $row['tenant_id'] === (string) $tenantId ? 'You already added this domain.' : 'This domain is already used by another vendor.';
        }
        return null;
    }

    /** @return string|null error */
    public function add(string $tenantId, string $raw, ?int $by): ?string
    {
        $host = $this->clean($raw);
        if ($host === null) {
            return 'Please type a domain name such as care.yourname.com.';
        }
        if (($e = $this->error($host, $tenantId)) !== null) {
            return $e;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->run('DELETE FROM sc_domains WHERE host = ? AND status = ?', [$host, 'removed']);
        $this->db->insert('sc_domains', ['tenant_id' => $tenantId, 'host' => $host, 'kind' => 'custom', 'status' => 'pending_dns', 'next_try_at' => $now, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now]);
        return null;
    }

    /** Queues the vendor's own sub domain for a certificate (new vendors). Does nothing unless SSL_AUTOMATION=1. */
    public function queueSubdomain(string $slug): void
    {
        $host = $this->tenant->hostFor($slug);
        if (!$this->enabled() || $host === null || $this->db->val('SELECT COUNT(*) FROM sc_domains WHERE host = ?', [$host]) > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('sc_domains', ['tenant_id' => $slug, 'host' => $host, 'kind' => 'subdomain', 'status' => 'pending_cert', 'next_try_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    }

    /** @return list<array<string,mixed>> */
    public function forTenant(string $tenantId): array
    {
        return $this->db->all("SELECT * FROM sc_domains WHERE tenant_id = ? AND status <> 'removed' ORDER BY kind, id", [$tenantId]);
    }

    /** Looks up DNS from this server. @return array{0:bool,1:string} ok, message */
    public function checkDns(string $host, string $slug): array
    {
        $ip = $this->serverIp();
        $a = @dns_get_record($host, DNS_A) ?: [];
        foreach ($a as $r) {
            if (($r['ip'] ?? '') === $ip) {
                return [true, 'The A record points to ' . $ip . '.'];
            }
        }
        $c = @dns_get_record($host, DNS_CNAME) ?: [];
        $target = strtolower($this->cnameTarget($slug));
        foreach ($c as $r) {
            $t = strtolower(rtrim((string) ($r['target'] ?? ''), '.'));
            if ($t === $target || ($this->tenant->suffix() !== '' && str_ends_with($t, $this->tenant->suffix())) || $t === strtolower($this->env->get('MAIN_HOST'))) {
                return [true, 'The CNAME points to ' . $t . '.'];
            }
        }
        $seen = $a ? implode(', ', array_map(static fn ($r) => (string) $r['ip'], $a)) : ($c ? 'CNAME ' . (string) $c[0]['target'] : 'nothing yet');
        return [false, 'DNS does not point to us yet (it shows ' . $seen . '). Changes can take up to a few hours to spread.'];
    }

    /** Marks a custom domain as ready for its certificate when DNS is right. @return array{0:bool,1:string} */
    public function verify(array $row): array
    {
        if ($row['kind'] === 'subdomain') { // the address always points to us; just ask the worker to try again now
            $now = date('Y-m-d H:i:s');
            $this->db->run("UPDATE sc_domains SET status = 'pending_cert', attempts = 0, next_try_at = ?, updated_at = ? WHERE id = ? AND status <> 'active'", [$now, $now, (int) $row['id']]);
            return [true, 'Queued. The certificate is requested within a few minutes.'];
        }
        [$ok, $msg] = $this->checkDns((string) $row['host'], (string) $row['tenant_id']);
        $now = date('Y-m-d H:i:s');
        if ($ok && in_array($row['status'], ['pending_dns', 'failed'], true)) {
            $this->db->run("UPDATE sc_domains SET status = 'pending_cert', dns_ok_at = ?, last_error = NULL, attempts = 0, next_try_at = ?, updated_at = ? WHERE id = ?", [$now, $now, $now, (int) $row['id']]);
        } elseif (!$ok) {
            $this->db->run('UPDATE sc_domains SET last_error = ?, updated_at = ? WHERE id = ?', [mb_substr($msg, 0, 250), $now, (int) $row['id']]);
        }
        return [$ok, $msg];
    }

    public function remove(string $tenantId, int $id): bool
    {
        $n = $this->db->run("UPDATE sc_domains SET status = 'removed', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND kind = 'custom'", [$id, $tenantId]);
        return $n > 0;
    }

    /** HTML panel: the DNS records to set, the list of addresses with their state and the add form. $postBase is a path like portal/domains. */
    public function panel(string $slug, string $postBase): string
    {
        $label = ['pending_dns' => ['Waiting for DNS', 'warn'], 'pending_cert' => ['Getting the certificate', 'info'], 'active' => ['Secure (HTTPS)', 'ok'], 'failed' => ['Needs attention', 'bad']];
        $rows = $this->forTenant($slug);
        $h = '<p class="d-help">Your website is always available at <b>' . e($this->tenant->siteUrl($slug)) . '</b>. You can also use your own domain.</p>';
        $h .= '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Address</th><th>Kind</th><th>State</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            [$lab, $cls] = $label[$r['status']] ?? [(string) $r['status'], 'mute'];
            $act = '';
            if ($r['kind'] === 'subdomain' && $r['status'] !== 'active') {
                $act .= '<form class="d-inline" method="post" action="' . e(url($postBase . '/verify/' . (int) $r['id'])) . '">' . csrf_field() . '<button class="d-act" type="submit">Try again now</button></form>';
            }
            if ($r['kind'] === 'custom') {
                $act .= '<form class="d-inline" method="post" action="' . e(url($postBase . '/verify/' . (int) $r['id'])) . '">' . csrf_field() . '<button class="d-act" type="submit">Check DNS now</button></form> ';
                $act .= '<form class="d-inline" method="post" action="' . e(url($postBase . '/remove/' . (int) $r['id'])) . '" data-confirm="Remove this domain? Visitors will no longer reach your website on it.">' . csrf_field() . '<button class="d-act is-danger" type="submit">Remove</button></form>';
            }
            $h .= '<tr><td data-label="Address"><b>' . e((string) $r['host']) . '</b>' . ($r['last_error'] && $r['status'] !== 'active' ? '<br><small class="d-mute">' . e((string) $r['last_error']) . '</small>' : '') . '</td><td data-label="Kind">' . ($r['kind'] === 'custom' ? 'Your domain' : 'Self Care address') . '</td><td data-label="State"><span class="d-badge is-' . $cls . '">' . e($lab) . '</span></td><td class="d-actions" data-label="Actions">' . $act . '</td></tr>';
        }
        if (!$rows) {
            $h .= '<tr><td colspan="4" class="d-mute">No addresses yet.</td></tr>';
        }
        $h .= '</tbody></table></div>';
        $h .= '<h3 style="margin-top:22px">Add your own domain</h3><form class="d-form" method="post" action="' . e(url($postBase . '/add')) . '">' . csrf_field()
            . '<div class="d-form-grid">' . Crud::field('host', ['label' => 'Domain name', 'max' => 190, 'required' => true, 'placeholder' => 'care.yourname.com', 'help' => 'A name you own. Use a sub domain such as care.yourname.com or a full domain.'], '') . '</div>'
            . '<div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">Add domain</button></div></form>';
        $h .= '<div class="d-note" style="margin-top:18px"><b>Then set one of these at your domain provider (DNS):</b><ul style="margin:8px 0 0 18px"><li><b>A record:</b> name = your domain (or the sub domain), value = <code>' . e($this->serverIp()) . '</code></li><li><b>or CNAME record</b> (sub domains only): value = <code>' . e($this->cnameTarget($slug)) . '</code></li></ul><p class="d-help" style="margin:8px 0 0">DNS can take from a few minutes up to a few hours. We check it every few minutes and then get a free HTTPS certificate on our own. Press "Check DNS now" to check at once.</p></div>';
        return $h;
    }
}
