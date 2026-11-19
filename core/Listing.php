<?php
// Selfcare original code. Reusable table / list component.
//
// Give it a columns config and a base query and it renders a searchable, sortable,
// filterable, paged table with row actions and an empty state. On phones every row
// becomes a card with the column labels in front of each value (see selfcare-dash.css).
// Usage is documented in docs/ui-system.md.
declare(strict_types=1);

namespace Selfcare;

final class Listing
{
    /**
     * @param array<string,mixed> $c config:
     *   id        short name used for the form id
     *   select    "u.id, u.name, ..."          (column list)
     *   from      "users u JOIN ..."           (from + joins)
     *   where     ["u.tenant_id = ?"]          (always applied, with 'params')
     *   params    [value, ...]
     *   columns   [key => ['label'=>, 'sort'=>'sql expr', 'type'=>text|date|datetime|bool|badge|money|html|image, 'render'=>fn($row)=>html, 'class'=>]]
     *   search    ["u.name", "u.email"]        columns searched by ?q=
     *   filters   [name => ['label'=>, 'sql'=>'u.status = ?', 'options'=>[value=>label]]]
     *   sort      default sort key (a key of columns), 'dir' default asc|desc
     *   per_page  default 10
     *   actions   [['label'=>, 'url'=>fn($row)=>string, 'method'=>'get|post', 'confirm'=>'text', 'icon'=>, 'if'=>fn($row)=>bool, 'danger'=>bool]]
     *   empty     ['title'=>, 'text'=>, 'cta'=>['label'=>, 'url'=>]]
     *   badges    [column => [value => 'tone']]   tone: ok | warn | bad | info | mute
     */
    public static function render(Database $db, array $c): string
    {
        $cols = $c['columns'];
        $q = trim((string) ($_GET['q'] ?? ''));
        $sortKey = (string) ($_GET['sort'] ?? ($c['sort'] ?? array_key_first($cols)));
        if (!isset($cols[$sortKey]['sort'])) {
            $sortKey = (string) ($c['sort'] ?? array_key_first($cols));
        }
        $dir = strtolower((string) ($_GET['dir'] ?? ($c['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
        $per = (int) ($c['per_page'] ?? 10);
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $where = $c['where'] ?? [];
        $params = $c['params'] ?? [];
        if ($q !== '' && !empty($c['search'])) {
            $likes = [];
            foreach ($c['search'] as $expr) {
                $likes[] = $expr . ' LIKE ?';
                $params[] = '%' . addcslashes($q, '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        $activeFilters = [];
        foreach ($c['filters'] ?? [] as $name => $f) {
            $val = (string) ($_GET['f_' . $name] ?? '');
            if ($val !== '' && isset($f['options'][$val])) {
                if (isset($f['sql_map'][$val])) {
                    $where[] = $f['sql_map'][$val]; // fixed condition without a parameter, e.g. "deleted"
                } else {
                    $where[] = $f['sql'];
                    $params[] = $val;
                }
                $activeFilters[$name] = $val;
            }
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $db->val('SELECT COUNT(*) FROM ' . $c['from'] . $w, $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $order = ' ORDER BY ' . $cols[$sortKey]['sort'] . ' ' . $dir . ', 1 DESC';
        $rows = $db->all('SELECT ' . $c['select'] . ' FROM ' . $c['from'] . $w . $order . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);

        $self = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
        $keep = static function (array $over) use ($q, $sortKey, $dir, $activeFilters): string {
            $base = ['q' => $q, 'sort' => $sortKey, 'dir' => $dir];
            foreach ($activeFilters as $n => $v) {
                $base['f_' . $n] = $v;
            }
            return '?' . http_build_query(array_filter(array_merge($base, $over), static fn ($v) => $v !== '' && $v !== null));
        };

        $h = '<div class="d-list" data-listing="' . e((string) ($c['id'] ?? 'list')) . '">';
        // toolbar
        $h .= '<form class="d-tools" method="get" action="' . e((string) $self) . '">';
        if (!empty($c['search'])) {
            $h .= '<label class="d-search">' . icon('search', 18) . '<input type="search" name="q" value="' . e($q) . '" placeholder="' . e((string) ($c['search_hint'] ?? 'Search')) . '" aria-label="Search"></label>';
        }
        foreach ($c['filters'] ?? [] as $name => $f) {
            $search = count($f['options'] ?? []) > 12 ? ' data-searchable' : '';
            $h .= '<label class="d-filter"><span>' . e($f['label']) . '</span><select name="f_' . e($name) . '" data-autosubmit' . $search . ' aria-label="' . e($f['label']) . '"><option value="">All</option>';
            foreach ($f['options'] as $v => $lab) {
                $h .= '<option value="' . e((string) $v) . '"' . (($activeFilters[$name] ?? '') === (string) $v ? ' selected' : '') . '>' . e((string) $lab) . '</option>';
            }
            $h .= '</select></label>';
        }
        $h .= '<input type="hidden" name="sort" value="' . e($sortKey) . '"><input type="hidden" name="dir" value="' . e($dir) . '">';
        if (!empty($c['search']) || !empty($c['filters'])) {
            $h .= '<button class="d-btn d-btn-soft" type="submit">Apply</button>';
            if ($q !== '' || $activeFilters) {
                $h .= '<a class="d-link" href="' . e((string) $self) . '">Clear</a>';
            }
        }
        $h .= '<span class="d-count">' . $total . ' ' . ($total === 1 ? 'result' : 'results') . '</span></form>';

        if (!$rows) {
            $em = $c['empty'] ?? ['title' => 'Nothing here yet', 'text' => 'When items are added they will show up here.'];
            if ($q !== '' || $activeFilters) {
                $em = ['title' => 'No matches', 'text' => 'Try a different word or clear the filters.'];
            }
            $h .= '<div class="d-empty"><div class="d-empty-art" aria-hidden="true">' . icon('search', 30) . '</div><h3>' . e($em['title']) . '</h3><p>' . e($em['text']) . '</p>';
            if (!empty($em['cta']) && $q === '' && !$activeFilters) {
                $h .= '<a class="d-btn d-btn-primary" href="' . e($em['cta']['url']) . '">' . e($em['cta']['label']) . '</a>';
            }
            return $h . '</div></div>';
        }

        $h .= '<div class="d-table-wrap"><table class="d-table"><thead><tr>';
        foreach ($cols as $key => $col) {
            $label = e($col['label']);
            if (isset($col['sort'])) {
                $nd = ($sortKey === $key && $dir === 'asc') ? 'desc' : 'asc';
                $arrow = $sortKey === $key ? ($dir === 'asc' ? ' <span aria-hidden="true">↑</span>' : ' <span aria-hidden="true">↓</span>') : '';
                $h .= '<th class="' . e((string) ($col['class'] ?? '')) . '" aria-sort="' . ($sortKey === $key ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none') . '"><a href="' . e($keep(['sort' => $key, 'dir' => $nd, 'page' => ''])) . '">' . $label . $arrow . '</a></th>';
            } else {
                $h .= '<th class="' . e((string) ($col['class'] ?? '')) . '">' . $label . '</th>';
            }
        }
        if (!empty($c['actions'])) {
            $h .= '<th class="d-actions-h">Actions</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $h .= '<tr>';
            foreach ($cols as $key => $col) {
                $h .= '<td data-label="' . e($col['label']) . '" class="' . e((string) ($col['class'] ?? '')) . '">' . self::cell($row, $key, $col, $c) . '</td>';
            }
            if (!empty($c['actions'])) {
                $h .= '<td class="d-actions" data-label="Actions">';
                $vis = [];
                foreach ($c['actions'] as $a) {
                    if (isset($a['if']) && !$a['if']($row)) {
                        continue;
                    }
                    $vis[] = $a;
                }
                $draw = static function (array $a, array $row): string {
                    $url = (string) $a['url']($row);
                    $cls = 'd-act' . (!empty($a['danger']) ? ' is-danger' : '');
                    if (($a['method'] ?? 'get') === 'post') {
                        return '<form method="post" action="' . e($url) . '" class="d-inline"' . (!empty($a['confirm']) ? ' data-confirm="' . e($a['confirm']) . '"' : '') . '>' . csrf_field() . '<button class="' . $cls . '" type="submit" title="' . e($a['label']) . '">' . icon($a['icon'] ?? 'check', 16) . '<span>' . e($a['label']) . '</span></button></form>';
                    }
                    return '<a class="' . $cls . '" href="' . e($url) . '" title="' . e($a['label']) . '">' . icon($a['icon'] ?? 'eye', 16) . '<span>' . e($a['label']) . '</span></a>';
                };
                if (count($vis) <= 2) {
                    foreach ($vis as $a) {
                        $h .= $draw($a, $row);
                    }
                } else {
                    $h .= $draw($vis[0], $row);
                    $h .= $draw($vis[1], $row);
                    $h .= '<div class="d-more"><button type="button" class="d-act d-more-btn" aria-haspopup="true" aria-expanded="false" aria-label="More actions">' . icon('more', 16) . '</button><div class="d-more-menu" hidden>';
                    foreach (array_slice($vis, 2) as $a) {
                        $h .= $draw($a, $row);
                    }
                    $h .= '</div></div>';
                }
                $h .= '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';

        if ($pages > 1) {
            $h .= '<nav class="d-pager" aria-label="Pages"><span>Page ' . $page . ' of ' . $pages . '</span><div>';
            $h .= $page > 1 ? '<a class="d-btn d-btn-soft" href="' . e($keep(['page' => $page - 1])) . '">Previous</a>' : '<span class="d-btn d-btn-soft is-off">Previous</span>';
            $h .= $page < $pages ? '<a class="d-btn d-btn-soft" href="' . e($keep(['page' => $page + 1])) . '">Next</a>' : '<span class="d-btn d-btn-soft is-off">Next</span>';
            $h .= '</div></nav>';
        }
        return $h . '</div>';
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $col @param array<string,mixed> $c */
    private static function cell(array $row, string $key, array $col, array $c): string
    {
        if (isset($col['render'])) {
            return (string) $col['render']($row);
        }
        $v = $row[$key] ?? null;
        switch ($col['type'] ?? 'text') {
            case 'date':
                return e(dt($v === null ? null : (string) $v));
            case 'datetime':
                return e(dt($v === null ? null : (string) $v, 'd M Y, H:i'));
            case 'bool':
                return (int) $v === 1 ? '<span class="d-badge is-ok">Yes</span>' : '<span class="d-badge is-mute">No</span>';
            case 'money':
                return e(money((float) $v, (string) ($row['currency'] ?? Currency::platform())));
            case 'badge':
                $tone = $c['badges'][$key][(string) $v] ?? 'mute';
                return '<span class="d-badge is-' . e($tone) . '">' . e(ucfirst((string) $v)) . '</span>';
            case 'html':
                return (string) $v;
            default:
                $s = (string) $v;
                return $s === '' ? '<span class="d-mute">—</span>' : e(mb_strlen($s) > 90 ? mb_substr($s, 0, 87) . '…' : $s);
        }
    }
}
