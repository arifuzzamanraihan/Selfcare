<?php
// Selfcare original code. Finds the vendor (tenant) from the web address.
//
// Sub domain mode: TENANT_SUFFIX in .env, for example ".easybzi.com" gives
// demo.easybzi.com for the vendor "demo". On staging the suffix is
// "-staging-selfcare.easybzi.com". With no suffix the app uses /h/{slug} only.
declare(strict_types=1);

namespace Selfcare;

final class Tenant
{
    /** Names that can never be a vendor sub domain. */
    private const BUILTIN_RESERVED = ['www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2', 'admin', 'api', 'app', 'office', 'area', 'selfcare', 'staging', 'staging-selfcare', 'demo-admin', 'webmail', 'cpanel', 'status', 'static', 'cdn', 'assets', 'login', 'register', 'dashboard', 'h'];

    public function __construct(private Env $env, private Database $db)
    {
    }

    public function suffix(): string
    {
        return strtolower($this->env->get('TENANT_SUFFIX'));
    }

    /** @return list<string> */
    public function reserved(): array
    {
        $extra = array_filter(array_map('trim', explode(',', strtolower($this->env->get('RESERVED_SLUGS')))));
        return array_values(array_unique(array_merge(self::BUILTIN_RESERVED, $extra)));
    }

    public function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $slug) && !in_array($slug, $this->reserved(), true);
    }

    public static function requestHost(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    }

    private static function port(): string
    {
        return preg_match('/:(\d+)$/', (string) ($_SERVER['HTTP_HOST'] ?? ''), $m) ? ':' . $m[1] : '';
    }

    /** Slug from the Host header, or null on the main site. */
    public function slugFromHost(): ?string
    {
        $suffix = $this->suffix();
        $host = self::requestHost();
        if ($host === strtolower($this->env->get('MAIN_HOST'))) {
            return null; // the main site, even when its name ends with the tenant suffix
        }
        if ($host !== '' && ($suffix === '' || !str_ends_with($host, $suffix) || strlen($host) <= strlen($suffix))) {
            $custom = $this->customDomainSlug($host);
            if ($custom !== null) {
                return $custom;
            }
        }
        if ($suffix === '' || $host === '' || !str_ends_with($host, $suffix) || strlen($host) <= strlen($suffix)) {
            return null;
        }
        $slug = substr($host, 0, -strlen($suffix));
        return $this->validSlug($slug) ? $slug : '';
    }

    /** Vendor slug for a custom domain a vendor added in its settings (table sc_domains), or null. */
    public function customDomainSlug(string $host): ?string
    {
        try {
            $slug = $this->db->val("SELECT tenant_id FROM sc_domains WHERE host = ? AND kind = 'custom' AND status <> 'removed' LIMIT 1", [$host]);
        } catch (\Throwable) {
            return null; // table not there yet
        }
        return $slug === null || $slug === false ? null : (string) $slug;
    }

    /** @return array<string,mixed>|null */
    public function bySlug(string $slug): ?array
    {
        return $this->db->one(
            "SELECT t.id, t.tenant_username AS slug, t.hospital_name AS name, COALESCE(v.type_slug, 'hospital') AS type_slug, COALESCE(v.status, 'active') AS status, v.city, v.phone, t.created_at FROM tenants t LEFT JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.tenant_username = ? LIMIT 1",
            [$slug]
        );
    }

    /** Host name for a vendor, with the current port kept for local testing. */
    public function hostFor(string $slug): ?string
    {
        $suffix = $this->suffix();
        return $suffix === '' ? null : $slug . $suffix . self::port();
    }

    /** Public address of a vendor website. */
    public function siteUrl(string $slug, string $path = ''): string
    {
        $host = $this->hostFor($slug);
        return $host === null ? url('h/' . $slug . ($path !== '' ? '/' . ltrim($path, '/') : '')) : host_url($host, $path);
    }

    /** Address of the main site, used from vendor sites. */
    public function mainUrl(string $path = ''): string
    {
        $main = $this->env->get('MAIN_HOST');
        return $main === '' ? url($path) : host_url($main . self::port(), $path);
    }
}
