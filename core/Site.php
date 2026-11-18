<?php
// Selfcare original code. Website content: settings and repeating items.
// Settings use the existing front_settings table (key, value, type, tenant_id). tenant_id NULL = main website.
declare(strict_types=1);

namespace Selfcare;

final class Site
{
    /** @var array<string,array<string,string>> */
    private array $cache = [];
    /** @var array<string,mixed> */
    private array $defaults;

    public function __construct(private Database $db)
    {
        $this->defaults = require __DIR__ . '/config/site.php';
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /** @return array<string,string> */
    private function load(?string $tenantId): array
    {
        $k = $tenantId ?? '';
        if (!isset($this->cache[$k])) {
            $rows = $tenantId === null
                ? $this->db->all('SELECT `key`, `value` FROM front_settings WHERE tenant_id IS NULL')
                : $this->db->all('SELECT `key`, `value` FROM front_settings WHERE tenant_id = ?', [$tenantId]);
            $this->cache[$k] = array_column($rows, 'value', 'key');
        }
        return $this->cache[$k];
    }

    public function get(?string $tenantId, string $key, string $default = ''): string
    {
        $v = $this->load($tenantId)[$key] ?? null;
        if ($v !== null && $v !== '') {
            return (string) $v;
        }
        return $tenantId === null ? (string) ($this->defaults['settings'][$key] ?? $default) : $default;
    }

    public function set(?string $tenantId, string $key, string $value): void
    {
        $exists = $tenantId === null
            ? $this->db->val('SELECT id FROM front_settings WHERE tenant_id IS NULL AND `key` = ?', [$key])
            : $this->db->val('SELECT id FROM front_settings WHERE tenant_id = ? AND `key` = ?', [$tenantId, $key]);
        if ($exists !== null) {
            $this->db->run('UPDATE front_settings SET `value` = ?, updated_at = NOW() WHERE id = ?', [$value, (int) $exists]);
        } else {
            $this->db->run('INSERT INTO front_settings (`key`, `value`, `type`, tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [$key, $value, 'text', $tenantId]);
        }
        unset($this->cache[$tenantId ?? '']);
    }

    /** Repeating items of one section. Falls back to defaults for the main site. @return list<array<string,mixed>> */
    public function items(?string $tenantId, string $section, bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM sc_items WHERE ' . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ?') . ' AND section = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id';
        $rows = $this->db->all($sql, $tenantId === null ? [$section] : [$tenantId, $section]);
        if (!$rows && $tenantId === null && isset($this->defaults[$section])) {
            return array_map(static fn ($r) => $r + ['id' => 0, 'image' => null, 'published_at' => null], $this->defaults[$section]);
        }
        return $rows;
    }

    /** Opening hours as day => text. @return array<string,string> */
    public function hours(string $tenantId): array
    {
        $out = [];
        foreach (['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $k => $label) {
            $out[$label] = $this->get($tenantId, 'hours_' . $k, '');
        }
        return $out;
    }
}
