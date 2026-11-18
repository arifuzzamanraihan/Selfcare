<?php
// Selfcare original code. Tiny chart builders: pure HTML and CSS (no scripts, no libraries). Styles are in selfcare-dash.css.
declare(strict_types=1);

namespace Selfcare;

final class Charts
{
    /**
     * Horizontal bars. $items: list of [label, value, href|null, text|null].
     * @param list<array{0:string,1:float|int,2?:?string,3?:?string}> $items
     */
    public static function bars(array $items, string $empty = 'Nothing to show yet.'): string
    {
        if (!$items) {
            return '<p class="d-mute">' . e($empty) . '</p>';
        }
        $max = max(array_map(static fn ($i) => (float) $i[1], $items)) ?: 1;
        $h = '<ul class="d-bars">';
        foreach ($items as $i) {
            $w = $i[1] > 0 ? max(2, (int) round($i[1] / $max * 100)) : 0;
            $label = isset($i[2]) && $i[2] ? '<a href="' . e($i[2]) . '">' . e($i[0]) . '</a>' : '<span>' . e($i[0]) . '</span>';
            $h .= '<li>' . $label . '<div class="d-bar" role="img" aria-label="' . e($i[0] . ': ' . ($i[3] ?? (string) $i[1])) . '"><i style="width:' . $w . '%"></i></div><b>' . e((string) ($i[3] ?? $i[1])) . '</b></li>';
        }
        return $h . '</ul>';
    }

    /**
     * Vertical columns, e.g. one per month. $items: list of [label, value, text|null].
     * @param list<array{0:string,1:float|int,2?:?string}> $items
     */
    public static function columns(array $items, string $empty = 'Nothing to show yet.'): string
    {
        $max = $items ? max(array_map(static fn ($i) => (float) $i[1], $items)) : 0;
        if ($max <= 0) {
            return '<p class="d-mute">' . e($empty) . '</p>';
        }
        $h = '<div class="d-cols" role="img" aria-label="Chart">';
        foreach ($items as $i) {
            $ht = $i[1] > 0 ? max(4, (int) round($i[1] / $max * 100)) : 0;
            $h .= '<div title="' . e($i[0] . ': ' . ($i[2] ?? (string) $i[1])) . '"><b>' . e((string) ($i[2] ?? $i[1])) . '</b><span class="d-col"><i style="height:' . $ht . '%"></i></span><small>' . e($i[0]) . '</small></div>';
        }
        return $h . '</div>';
    }

    /** Last $n months as [label, key] oldest first. @return list<array{0:string,1:string}> */
    public static function months(int $n = 12): array
    {
        $out = [];
        for ($k = $n - 1; $k >= 0; $k--) {
            $t = strtotime(date('Y-m-01') . " -$k month");
            $out[] = [date($n > 6 ? 'M' : 'M Y', $t), date('Y-m', $t)];
        }
        return $out;
    }
}
