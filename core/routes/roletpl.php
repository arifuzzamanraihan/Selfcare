<?php
// Selfcare original routes: super admin "Role templates". What each role starts with, per kind of vendor, for new vendors and (on request) for existing ones.
// /admin/role-templates?type=slug[&role=name]   save / reset / new / delete / apply (POST)
declare(strict_types=1);

use Selfcare\{RoleUi, Vendors};
use function Selfcare\{csrf_field, e, flash, icon, redirect, url};

$dash = $app->dash;
['card' => $card] = $app->ui;

$tplAdmin = static function () use ($app): array {
    $u = $app->auth->user();
    if ($u === null) {
        flash('Please sign in to continue.', 'info');
        redirect(url('login'));
    }
    if ($u['role'] !== 'Super Admin') {
        $app->error(403, 'This area is for Self Care administrators.', 'No access');
    }
    if ($app->hostVendor !== null) {
        redirect($app->tenant->mainUrl('admin/role-templates'));
    }
    return $u;
};
$tplPage = static function (array $u, string $title, string $html, string $sub = '') use ($app, $dash): void {
    ($app->adminPage)($u, 'roletpl', $title, $html, ['sub' => $sub]);
};
$typeOf = static function () use ($app): ?array {
    $slug = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
    return $slug === '' ? null : ($app->db->one('SELECT slug, name, family FROM sc_vendor_types WHERE slug = ?', [$slug]) ?: null);
};

$app->router->get('/admin/role-templates', static function () use ($app, $tplAdmin, $tplPage, $card, $typeOf): void {
    $u = $tplAdmin();
    $a = $app->access;
    $type = $typeOf();
    if ($type === null) {
        $h = '<p class="d-help">Pick a kind of vendor. Its role template decides which roles a new vendor starts with, and what each can view, add, edit, delete, print and export. You can also push a template to the vendors that already exist.</p><div class="d-table-wrap"><table class="d-table"><thead><tr><th>Vendor type</th><th>Vendors</th><th>Edited roles</th><th></th></tr></thead><tbody>';
        foreach ($app->db->all('SELECT slug, name FROM sc_vendor_types ORDER BY name') as $r) {
            $n = (int) $app->db->val("SELECT COUNT(*) FROM sc_vendors WHERE type_slug = ? AND deleted_at IS NULL", [$r['slug']]);
            $e = (int) $app->db->val('SELECT COUNT(*) FROM sc_role_templates WHERE type_slug = ?', [$r['slug']]);
            $h .= '<tr><td data-label="Vendor type"><b>' . e((string) $r['name']) . '</b></td><td data-label="Vendors">' . $n . '</td><td data-label="Edited roles">' . $e . '</td><td class="d-actions"><a class="d-act" href="' . e(url('admin/role-templates', ['type' => $r['slug']])) . '">' . icon('edit', 14) . '<span>Open</span></a></td></tr>';
        }
        $tplPage($u, 'Role templates', $card('Vendor types', $h . '</tbody></table></div>'), 'Default roles and permissions for every kind of vendor.');
        return;
    }
    $slug = (string) $type['slug'];
    $fam = (string) $type['family'];
    $sel = (string) ($_GET['role'] ?? '');
    $offered = $a->offeredRoles($slug);
    $customT = array_column($a->templateCustomRoles($slug), null, 'role_name');
    if (!isset($offered[$sel]) && !isset($customT[$sel])) {
        $sel = '';
    }
    $h = '<div class="d-table-wrap"><table class="d-table"><thead><tr><th>Role</th><th>Starts</th><th>Modules</th><th>Source</th><th></th></tr></thead><tbody>';
    foreach ($offered + array_fill_keys(array_keys($customT), -2) as $role => $_) {
        $t = $a->templateFor($slug, $fam, $role);
        $isC = isset($customT[$role]);
        $h .= '<tr' . ($sel === $role ? ' style="background:var(--soft)"' : '') . '><td data-label="Role"><b>' . e($a->roleLabel($role, $fam)) . '</b></td><td data-label="Starts">' . ($a->full($role) || $t['enabled'] ? '<span class="d-pill is-on">On</span>' : '<span class="d-pill">Off</span>') . '</td><td data-label="Modules">' . ($a->full($role) ? 'All' : count($t['perms'])) . '</td><td data-label="Source">' . ($isC ? 'Added by Self Care' : ($t['custom'] ? 'Edited' : 'Built in')) . '</td><td class="d-actions">' . ($a->full($role) ? '<span class="d-mute">Always full</span>' : '<a class="d-act" href="' . e(url('admin/role-templates', ['type' => $slug, 'role' => $role])) . '#matrix">' . icon('edit', 14) . '<span>Edit</span></a>') . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    $out = $card($type['name'] . ': roles', $h);
    $new = '<form class="d-form" method="post" action="' . e(url('admin/role-templates/new')) . '">' . csrf_field() . '<input type="hidden" name="type" value="' . e($slug) . '"><div class="d-grid2"><div class="d-field"><label for="tn">Role name</label><input id="tn" name="name" maxlength="40" required placeholder="e.g. Cashier"></div><div class="d-field"><label for="td">What is it for?</label><input id="td" name="description" maxlength="250"></div></div><div class="d-form-actions d-form-actions-sticky"><button class="d-btn d-btn-primary" type="submit">Add role to this template</button></div></form>';
    $out .= $card('Add a role that every new ' . $type['name'] . ' vendor gets', $new);
    if ($sel !== '' && !$a->full($sel)) {
        $t = $a->templateFor($slug, $fam, $sel);
        $isC = isset($customT[$sel]);
        $shown = $a->permsForType($slug, $fam, \Selfcare\VendorTypes::template($app->typeRow($slug)));
        $m = '<form method="post" action="' . e(url('admin/role-templates/save')) . '">' . csrf_field() . '<input type="hidden" name="type" value="' . e($slug) . '"><input type="hidden" name="role" value="' . e($sel) . '">';
        $m .= '<div class="d-field"><label><input type="checkbox" name="enabled" value="1"' . ($t['enabled'] ? ' checked' : '') . '> Switched on for new vendors of this type</label></div>';
        if ($isC) {
            $m .= '<div class="d-field"><label for="td2">What is it for?</label><input id="td2" name="description" maxlength="250" value="' . e((string) ($customT[$sel]['description'] ?? '')) . '"></div>';
        }
        $m .= RoleUi::grid($a, $shown, $t['perms'], $t['actions'], static fn () => null, static fn () => false);
        $m .= '<div class="d-form-actions d-form-actions-sticky"><button class="d-btn d-btn-primary" type="submit">Save template</button></div></form>';
        $m .= '<div class="d-form-actions d-form-actions-sticky"><form class="d-inline" method="post" action="' . e(url('admin/role-templates/' . ($isC ? 'delete' : 'reset'))) . '" data-confirm="' . ($isC ? 'Remove this role from the template? Vendors that already have it keep it.' : 'Go back to the built-in setting for this role?') . '">' . csrf_field() . '<input type="hidden" name="type" value="' . e($slug) . '"><input type="hidden" name="role" value="' . e($sel) . '"><button class="d-btn" type="submit">' . ($isC ? 'Remove from template' : 'Reset to built-in') . '</button></form></div>';
        $out .= '<div id="matrix">' . $card('Template of "' . e($a->roleLabel($sel, $fam)) . '" for ' . e((string) $type['name']), $m) . '</div>';
    }
    $n = (int) $app->db->val("SELECT COUNT(*) FROM sc_vendors WHERE type_slug = ? AND deleted_at IS NULL", [$slug]);
    $ap = '<p class="d-help">Changing a template only affects new vendors. To put this template on the ' . $n . ' existing vendor' . ($n === 1 ? '' : 's') . ' of this type, press the button. Their own roles are replaced by the template; roles they made themselves are kept. People keep their current role.</p><form method="post" action="' . e(url('admin/role-templates/apply')) . '" data-confirm="Replace the role settings of all ' . $n . ' ' . e((string) $type['name']) . ' vendors with this template?">' . csrf_field() . '<input type="hidden" name="type" value="' . e($slug) . '"><button class="d-btn d-btn-primary" type="submit">Apply to existing vendors</button></form>';
    $out .= $card('Existing vendors', $ap);
    $tplPage($u, 'Role templates: ' . $type['name'], $out, 'What each role starts with for this kind of vendor.');
});

$tplBack = static fn (string $slug, string $role = ''): string => url('admin/role-templates', array_filter(['type' => $slug, 'role' => $role]));

$app->router->post('/admin/role-templates/save', static function () use ($app, $tplAdmin, $typeOf, $tplBack): void {
    $u = $tplAdmin();
    $type = $typeOf() ?? $app->error(404, 'Unknown vendor type.');
    $a = $app->access;
    $slug = (string) $type['slug'];
    $role = (string) ($_POST['role'] ?? '');
    $isC = isset(array_column($a->templateCustomRoles($slug), null, 'role_name')[$role]);
    if ((!isset($a->offeredRoles($slug)[$role]) && !$isC) || $a->full($role)) {
        flash('That role cannot be changed.', 'bad');
        redirect($tplBack($slug));
    }
    $shown = $a->permsForType($slug, (string) $type['family'], \Selfcare\VendorTypes::template($app->typeRow($slug)));
    $t = $a->templateFor($slug, (string) $type['family'], $role);
    [$perms, $acts] = RoleUi::read($a, $shown, $t['perms'], $t['actions'], static fn () => null, static fn () => false);
    $a->saveTemplate($slug, $role, ($_POST['enabled'] ?? '') === '1', $perms, $acts, (int) $u['id'], $isC, (string) ($_POST['description'] ?? ''));
    flash('Template saved. New vendors of this type get it.', 'ok');
    redirect($tplBack($slug, $role));
});
$app->router->post('/admin/role-templates/reset', static function () use ($app, $tplAdmin, $typeOf, $tplBack): void {
    $tplAdmin();
    $type = $typeOf() ?? $app->error(404, 'Unknown vendor type.');
    $app->access->resetTemplate((string) $type['slug'], (string) ($_POST['role'] ?? ''));
    flash('Back to the built-in setting.', 'ok');
    redirect($tplBack((string) $type['slug'], (string) ($_POST['role'] ?? '')));
});
$app->router->post('/admin/role-templates/delete', static function () use ($app, $tplAdmin, $typeOf, $tplBack): void {
    $tplAdmin();
    $type = $typeOf() ?? $app->error(404, 'Unknown vendor type.');
    $role = (string) ($_POST['role'] ?? '');
    $app->db->run('DELETE FROM sc_role_templates WHERE type_slug = ? AND role_name = ? AND is_custom = 1', [(string) $type['slug'], $role]);
    flash('Removed from the template.', 'ok');
    redirect($tplBack((string) $type['slug']));
});
$app->router->post('/admin/role-templates/new', static function () use ($app, $tplAdmin, $typeOf, $tplBack): void {
    $u = $tplAdmin();
    $type = $typeOf() ?? $app->error(404, 'Unknown vendor type.');
    $a = $app->access;
    $slug = (string) $type['slug'];
    $name = trim((string) ($_POST['name'] ?? ''));
    $err = $a->nameError('', $name);
    if ($err === null && $app->db->val('SELECT COUNT(*) FROM sc_role_templates WHERE type_slug = ? AND role_name = ?', [$slug, $name]) > 0) {
        $err = 'The template already has that role.';
    }
    if ($err !== null) {
        flash($err, 'bad');
        redirect($tplBack($slug));
    }
    $a->saveTemplate($slug, $name, true, [], [], (int) $u['id'], true, (string) ($_POST['description'] ?? ''));
    flash('Role added. Choose what it can do.', 'ok');
    redirect($tplBack($slug, $name) . '#matrix');
});
$app->router->post('/admin/role-templates/apply', static function () use ($app, $tplAdmin, $typeOf, $tplBack): void {
    $u = $tplAdmin();
    $type = $typeOf() ?? $app->error(404, 'Unknown vendor type.');
    $slug = (string) $type['slug'];
    $n = 0;
    foreach ($app->db->all('SELECT tenant_id FROM sc_vendors WHERE type_slug = ? AND deleted_at IS NULL', [$slug]) as $v) {
        $app->access->applyTemplate((string) $v['tenant_id'], (string) $type['family'], $slug);
        $app->access->log((string) $v['tenant_id'], (int) $u['id'], null, null, 'template_applied', null, ['type' => $slug]);
        $n++;
    }
    flash('Template applied to ' . $n . ' vendor' . ($n === 1 ? '' : 's') . '.', 'ok');
    redirect($tplBack($slug));
});
