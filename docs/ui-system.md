# Self Care UI system (BoardUI kit)

Everything is plain PHP, CSS and JS. No library, no CDN, no web font. Icons are inline SVG from `icon()` in `core/helpers.php`.

| Part | File |
| --- | --- |
| Design tokens, light and dark theme, public site, footer | `public/css/selfcare-ui.css` |
| Dashboard shell, tables, forms, modals, printed sheet, vendor website | `public/css/selfcare-dash.css` |
| Theme toggle, side menu, confirm modal, print, count-up, reveal on scroll | `public/js/selfcare-app.js` |
| Invoice rows, totals, repeated form rows | `public/js/selfcare-docs.js` |
| Dashboard layout (side menu, top bar, flash, plan banner) | `templates/dash/layout.php` |
| Shared page renderer for portal and admin | `$app->dash` in `core/routes/portal.php` |
| Card, button and stat builders | `$app->ui` in `core/routes/portal.php` |

## Theme

Colours are CSS variables on `:root` and `[data-theme=dark]`. The pre-paint script in each layout reads `localStorage['sc-theme']` so there is no flash. All animation stops under `prefers-reduced-motion`.

## Page shell

```php
$app->dash($portal, 'billing', 'Invoices and bills', $html, ['sub' => 'One line under the title', 'actions' => $btn('New', url('portal/billing/new/opd_bill'))]);
```

`$portal` is a `Portal` (vendor pages). For the super admin pass `null` and give your own `nav`, `orgName`, `user`, `roleLabel` and `navHome` (see `core/routes/admin.php`).

Builders: `$card($title, $html)`, `$btn($label, $href, $class = 'd-btn-primary')`, `$stat($label, $value, $icon, $note)`.

## Listing component

`Selfcare\Listing::render($db, $config)` gives a table with search, sort, filters, pagination, row actions and an empty state. On a phone every row becomes a card with the column label in front of each value. It is used for vendors, doctors, patients, staff, requests, invoices, messages and free services.

### Add a listing in five steps

1. Pick the base query: `select` and `from` (joins are fine), and `where` plus `params` for what must always apply. **Always scope by tenant** (`'where' => ['t.tenant_id = ?'], 'params' => [$tid]`).
2. List the `columns`. Each is `key => ['label' => ..., 'sort' => 'sql expression', 'type' => ..., 'render' => fn($row) => html, 'class' => 'r']`. `type` is `text`, `date`, `datetime`, `bool`, `badge`, `money`, `html`. `render` must escape with `e()`.
3. Optional: `search` (columns searched by `?q=`), `filters` (`name => ['label', 'sql' => 'col = ?', 'options' => [value => label]]`, read from `?f_name=`), `sort` and `dir`, `per_page` (default 10).
4. Optional: `actions` as `['label', 'url' => fn($row) => url, 'method' => 'get|post', 'confirm' => 'text', 'icon', 'if' => fn($row) => bool, 'danger' => true]`. POST actions get the CSRF field automatically.
5. Add an `empty` state: `['title', 'text', 'cta' => ['label', 'url']]`, then echo the result inside a `$card('', ...)`.

```php
$html = Listing::render($app->db, [
    'id' => 'visits', 'select' => 'v.id, v.name, v.visit_date, v.status', 'from' => 'visits v',
    'where' => ['v.tenant_id = ?'], 'params' => [$p->tenantId],
    'search' => ['v.name'], 'search_hint' => 'Search by name',
    'filters' => ['status' => ['label' => 'State', 'sql' => 'v.status = ?', 'options' => ['open' => 'Open', 'done' => 'Done']]],
    'columns' => [
        'name' => ['label' => 'Name', 'sort' => 'v.name'],
        'visit_date' => ['label' => 'Date', 'sort' => 'v.visit_date', 'type' => 'date'],
        'status' => ['label' => 'State', 'type' => 'badge', 'sort' => 'v.status'],
    ],
    'badges' => ['status' => ['open' => 'warn', 'done' => 'ok']],
    'sort' => 'visit_date', 'dir' => 'desc',
    'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => fn ($r) => url('portal/visits/' . $r['id'])]],
    'empty' => ['title' => 'No visits yet', 'text' => 'They show up here when someone books.'],
]);
```

## Crud engine

For a simple table with a form, write a config array and mount it. `core/Crud.php` gives list, new, edit, save and delete with `scope` (always applied, `null` means `IS NULL`), `before_save`, `after_save` and `before_delete` hooks. Examples: departments, free services and website sections in `core/routes/portal.php`; plans, vendor types and website content in `core/routes/admin.php`.

Field types: `text`, `textarea`, `number`, `select`, `checks`, `toggle`, `image`, `date`, `time`, `datetime-local`, `email`, `tel`, `url`, `password`.



## Standing layout rules

These apply to every dashboard and website screen.

- **One primary action per view**, placed at the top right of the sticky page header (`.d-top-actions` in the portal, `.sc-nav-cta` on public and vendor sites). A second control in that cluster must be a soft button. If two primary classes are rendered, CSS demotes every primary after the first.
- **Row actions** sit in the right-hand column. If a row has more than two visible actions, the first two stay in the row and the rest open from a kebab menu (`.d-more`). Destructive actions stay danger-styled and still use the confirm dialog (`data-confirm`).
- **Long forms** use a sticky footer bar (`.d-form-actions-sticky` or `.sc-form-actions`): Cancel on the left, the primary save/create button on the right.
- **Options** (selects, filters, toggles, tabs, kebab) have a visible label and a sensible default. A select with more than 12 options gets `data-searchable` and a search box. Filters stay together in `.d-tools`.
- **Touch and fit**: interactive controls are at least 44px tall. Screens must fit 360–1440px in light and dark mode with no overlapping or off-screen controls. On a phone the header actions wrap onto their own row, and the form action bar stays sticky above the home indicator.

The first reviewed set is Clinic, Hospital, Pharmacy, the public home and the register page. Remaining screens are listed in `docs/CHECKLIST-uiux.md`.

## Wording rules

Short sentences, everyday words ("people", "bills", "Add the first one"), and every empty list says what to do next. Errors say what to change, not what failed internally.

## Responsive and print

Break points are 390 (phone) and 1440 (desktop) tested. Tables never scroll sideways on a phone: they stack. Printed documents use `.dx-sheet` with `@media print` rules, so the Print button gives a clean A4 page without the menu.
