<?php
// Selfcare original code. Resolves a vendor type into its website template.
declare(strict_types=1);

namespace Selfcare;

final class VendorTypes
{
    /** @var array<string,mixed> */
    private static array $cfg = [];

    /** @return array<string,mixed> */
    private static function cfg(): array
    {
        return self::$cfg ?: (self::$cfg = require __DIR__ . '/config/vendor_types.php');
    }

    /** @return array<string,array<string,mixed>> default types keyed by slug */
    public static function defaults(): array
    {
        $out = [];
        $i = 0;
        foreach (self::cfg()['types'] as $slug => $t) {
            $out[$slug] = ['slug' => $slug, 'name' => $t[0], 'plural' => $t[1], 'family' => $t[2], 'icon' => $t[3], 'tagline' => $t[4], 'description' => $t[5], 'serves' => implode("\n", $t[6]), 'is_enabled' => $t[7], 'sort_order' => ++$i];
        }
        return $out;
    }

    /** Adds the default types that are missing from the database. Returns how many were added. */
    public static function seed(Database $db): int
    {
        $n = 0;
        foreach (self::defaults() as $slug => $t) {
            if ($db->val('SELECT COUNT(*) FROM sc_vendor_types WHERE slug = ?', [$slug]) == 0) {
                $db->insert('sc_vendor_types', $t);
                $n++;
            }
        }
        return $n;
    }

    /** Types from the database, in display order. @return list<array<string,mixed>> */
    public static function all(Database $db, bool $enabledOnly = true): array
    {
        return $db->all('SELECT * FROM sc_vendor_types' . ($enabledOnly ? ' WHERE is_enabled = 1' : '') . ' ORDER BY sort_order, name');
    }

    public static function find(Database $db, string $slug): ?array
    {
        return $db->one('SELECT * FROM sc_vendor_types WHERE slug = ?', [$slug]);
    }

    /** @return list<string> */
    public static function familyKeys(): array
    {
        return array_keys(self::cfg()['families']);
    }

    /** Website template for a type row (from sc_vendor_types). @param array<string,mixed> $type @return array<string,mixed> */
    public static function template(array $type): array
    {
        $cfg = self::cfg();
        $family = $cfg['families'][$type['family'] ?? 'clinical'] ?? $cfg['families']['clinical'];
        $over = $cfg['types'][$type['slug'] ?? ''][8] ?? [];
        foreach ($over as $path => $value) {
            $parts = explode('.', $path);
            if ($parts[0] === 'sections') {
                foreach ($family['sections'] as $i => $s) {
                    if ($s['key'] === $parts[1]) {
                        $family['sections'][$i][$parts[2]] = $value;
                    }
                }
            } elseif (count($parts) === 2) {
                $family[$parts[0]][$parts[1]] = $value;
            } else {
                $family[$path] = $value;
            }
        }
        $family['family'] = $type['family'] ?? 'clinical';
        return $family;
    }

    /** Item sections the vendor admin can edit (cards, faq, gallery ...). @return list<array<string,mixed>> */
    public static function editableSections(array $template): array
    {
        return array_values(array_filter($template['sections'], static fn ($s) => in_array($s['kind'], ['cards', 'faq', 'testimonials', 'gallery', 'news'], true)));
    }

    /** Text boxes the vendor admin can edit. @return list<array<string,mixed>> */
    public static function textSections(array $template): array
    {
        return array_values(array_filter($template['sections'], static fn ($s) => $s['kind'] === 'text'));
    }

    /** Portal module keys this template supports for the vendor admin. @return list<string> */
    public static function modules(array $template): array
    {
        $m = ['requests', 'website', 'messages', 'staff', 'plan'];
        foreach ($template['sections'] as $s) {
            if ($s['kind'] === 'team') {
                $m[] = 'doctors';
            }
        }
        if (!empty($template['people'])) {
            $m[] = 'patients';
        }
        return $m;
    }
}
