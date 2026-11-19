<?php
// Selfcare original code. Who can do what in a vendor portal.
// Tables follow the existing pattern, see core/config/access.php.
declare(strict_types=1);

namespace Selfcare;

final class Access
{
    /** @var array<string,mixed> */
    private array $cfg;
    /** @var array<int,string> role id => name */
    private array $roleNames = [];
    /** @var array<string,list<string>> */
    private array $memo = [];

    public function __construct(private Database $db)
    {
        $this->cfg = require __DIR__ . '/config/access.php';
        $this->roleCfg = require __DIR__ . '/config/roles.php';
    }

    /** @var array<string,mixed> */
    private array $roleCfg = [];

    /** Owner and Admin always have every permission (and cannot be restricted). */
    public function full(string $role): bool
    {
        return $role === 'Admin' || $role === 'Owner';
    }

    /** Roles a vendor of this type uses: shipped enabled, plus what the admin switched on, minus what was switched off. @return list<string> */
    public function enabledRoles(string $tenantId, string $typeSlug): array
    {
        $over = array_column($this->db->all('SELECT role_id, is_enabled FROM tenant_roles WHERE tenant_id = ?', [$tenantId]), 'is_enabled', 'role_id');
        $out = [];
        foreach ($this->roles() as $id => $name) {
            if ($this->full($name)) {
                $out[] = $name;
                continue;
            }
            $ship = ($this->roleCfg['enabled'][$typeSlug][$name] ?? 0) === 1;
            $on = isset($over[$id]) ? (int) $over[$id] === 1 : $ship;
            if ($on) {
                $out[] = $name;
            }
        }
        foreach ($this->customRoles($tenantId) as $c) {
            $out[] = (string) $c['name'];
        }
        return $out;
    }

    /** Roles the type can offer at all (shipped enabled or available), with their shipped state. @return array<string,int> name => 1|0 */
    public function offeredRoles(string $typeSlug): array
    {
        $out = [];
        foreach ($this->roles() as $name) {
            $v = $this->roleCfg['enabled'][$typeSlug][$name] ?? 0;
            if ($v >= 0 || $this->full($name)) {
                $out[$name] = $this->full($name) ? 1 : $v;
            }
        }
        return $out;
    }

    public function setRoleEnabled(string $tenantId, string $role, bool $on): void
    {
        $rid = $this->roleId($role, $tenantId);
        if ($rid === null || $this->full($role)) {
            return;
        }
        $this->db->run('DELETE FROM tenant_roles WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid]);
        $this->db->insert('tenant_roles', ['tenant_id' => $tenantId, 'role_id' => $rid, 'is_enabled' => $on ? 1 : 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Writes one row of the permission change log. */
    public function log(string $tenantId, ?int $actor, ?int $target, ?string $role, string $action, mixed $before, mixed $after, ?string $reason = null): void
    {
        $this->db->insert('permission_change_log', [
            'tenant_id' => $tenantId, 'actor_id' => $actor, 'target_user_id' => $target, 'role_name' => $role, 'action' => $action,
            'before_value' => json_encode($before), 'after_value' => json_encode($after), 'reason' => $reason ? mb_substr($reason, 0, 250) : null,
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string,array{0:string,1:string,2:string,3:bool}> */
    public function permissions(): array
    {
        return $this->cfg['permissions'];
    }

    /** Roles that a vendor can use: every row of "departments" except Super Admin. @return array<int,string> */
    public function roles(): array
    {
        if (!$this->roleNames) {
            foreach ($this->db->all("SELECT id, name FROM departments WHERE name <> 'Super Admin' AND is_active = 1 AND tenant_id IS NULL ORDER BY id") as $r) {
                $this->roleNames[(int) $r['id']] = (string) $r['name'];
            }
        }
        return $this->roleNames;
    }

    public function roleId(string $name, ?string $tenant = null): ?int
    {
        if ($tenant !== null && isset($this->customIds($tenant)[$name])) {
            return $this->customIds($tenant)[$name];
        }
        $id = array_search($name, $this->roles(), true);
        return $id === false ? null : (int) $id;
    }

    public function roleLabel(string $role, string $family): string
    {
        return $this->cfg['role_labels'][$family][$role] ?? $role;
    }

    public function roleHelp(string $role): string
    {
        return $this->cfg['role_help'][$role] ?? ('Role from the Self Care role catalogue (' . ($this->roleCfg['roles'][$role]['category'] ?? 'staff') . ').');
    }

    public function staffTable(string $role): ?string
    {
        return $this->cfg['staff_tables'][$role] ?? null;
    }

    /** @return array<string,int> permission name => id */
    private function permIds(): array
    {
        static $ids = null;
        return $ids ??= array_column($this->db->all('SELECT id, name FROM permissions'), 'id', 'name');
    }

    /** Default grants for a role in a vendor family (names). @return list<string> */
    public function defaultsFor(string $role, string $family): array
    {
        if ($this->full($role)) {
            return array_keys($this->cfg['permissions']);
        }
        $set = $this->cfg['defaults'][$role] ?? [];
        foreach ($this->cfg['family_defaults'][$family][$role] ?? [] as $p) {
            $set[] = $p;
        }
        return array_values(array_intersect(array_keys($this->cfg['permissions']), array_unique($set)));
    }

    /** Permission names in force for a role. Admin always has all. @return list<string> */
    public function effective(string $tenantId, string $role, string $family): array
    {
        if ($this->full($role)) {
            return array_keys($this->cfg['permissions']);
        }
        $k = $tenantId . '|' . $role;
        if (isset($this->memo[$k])) {
            return $this->memo[$k];
        }
        $rid = $this->roleId($role, $tenantId);
        $names = [];
        if ($rid !== null) {
            $count = (int) $this->db->val('SELECT COUNT(*) FROM tenant_role_has_permissions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid]);
            if ($count > 0) {
                $rows = $this->db->all('SELECT p.name FROM tenant_role_has_permissions t JOIN permissions p ON p.id = t.permission_id WHERE t.tenant_id = ? AND t.role_id = ?', [$tenantId, $rid]);
            } else {
                $rows = [];
                foreach ($this->defaultsFor($role, $family) as $n) {
                    $rows[] = ['name' => $n];
                }
            }
            $names = array_column($rows, 'name');
        }
        return $this->memo[$k] = array_values(array_intersect(array_keys($this->cfg['permissions']), $names));
    }

    public function can(string $tenantId, string $role, string $family, string $perm): bool
    {
        return in_array($perm, $this->effective($tenantId, $role, $family), true);
    }

    /** Writes a role's grants for one vendor. @param list<string> $granted */
    public function save(string $tenantId, string $role, array $granted, ?array $actions = null): void
    {
        $rid = $this->roleId($role, $tenantId);
        if ($rid === null || $this->full($role)) {
            return;
        }
        $ids = $this->permIds();
        $this->db->begin();
        try {
            $this->db->run('DELETE FROM tenant_role_has_permissions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid]);
            foreach ($granted as $name) {
                if (isset($ids[$name], $this->cfg['permissions'][$name])) {
                    $this->db->insert('tenant_role_has_permissions', ['tenant_id' => $tenantId, 'role_id' => $rid, 'permission_id' => (int) $ids[$name]]);
                }
            }
            if (!$this->db->val('SELECT COUNT(*) FROM tenant_role_has_permissions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid])) {
                // permission_id 0 = "this role was saved with no permissions", so the defaults are not used again
                $this->db->insert('tenant_role_has_permissions', ['tenant_id' => $tenantId, 'role_id' => $rid, 'permission_id' => 0]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        unset($this->memo[$tenantId . '|' . $role]);
        if ($actions !== null) {
            $this->saveActions($tenantId, $role, $actions);
        }
    }

    /** Copies the role template of the vendor type (built in, or edited by Self Care) into a new vendor. */
    public function seedTenant(string $tenantId, string $family, string $type = ''): void
    {
        foreach ($this->roles() as $role) {
            if ($this->full($role)) {
                continue;
            }
            $t = $this->templateFor($type, $family, $role);
            $this->save($tenantId, $role, $t['perms'], $t['custom_actions'] ? $t['actions'] : null);
            $ship = ($this->roleCfg['enabled'][$type][$role] ?? 0) === 1;
            if ($t['enabled'] !== $ship) {
                $this->setRoleEnabled($tenantId, $role, $t['enabled']);
            }
        }
        foreach ($this->templateCustomRoles($type) as $r) {
            $this->createFromTemplate($tenantId, $r);
        }
    }


    // ------------------------------------------------------------------ what a role may do inside a module

    public const ACTIONS = ['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'print' => 'Print', 'export' => 'Export'];
    /** When an action does not exist for a module it falls back to the nearest one. */
    private const FALLBACK = ['add' => 'edit', 'delete' => 'edit', 'print' => 'view', 'export' => 'view'];

    /** @var array<string,array<string,int>> */
    private array $customMemo = [];
    /** @var array<string,array<string,string>> */
    private array $actionMemo = [];

    /** Actions that exist for a module, besides "view". @return list<string> */
    public function permActions(string $perm): array
    {
        return $this->cfg['perm_actions'][$perm] ?? ['add', 'edit', 'delete', 'print', 'export'];
    }

    /** Actions a role gets in every module it has until the vendor changes it. @return list<string> */
    public function profile(string $role): array
    {
        if ($this->full($role)) {
            return ['add', 'edit', 'delete', 'print', 'export'];
        }
        return $this->cfg['action_profiles'][$role] ?? $this->cfg['action_profiles']['default'];
    }

    /** Actions a role may do inside one module (besides view). @return list<string> */
    public function actions(string $tenantId, string $role, string $perm): array
    {
        $app = $this->permActions($perm);
        if ($this->full($role)) {
            return $app;
        }
        $rid = $this->roleId($role, $tenantId);
        $k = $tenantId . '|' . (int) $rid;
        if (!isset($this->actionMemo[$k])) {
            $this->actionMemo[$k] = $rid === null ? [] : array_column($this->db->all('SELECT permission, actions FROM sc_role_actions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid]), 'actions', 'permission');
        }
        $list = isset($this->actionMemo[$k][$perm]) ? array_filter(explode(',', $this->actionMemo[$k][$perm])) : $this->profile($role);
        return array_values(array_intersect($app, $list));
    }

    /** May this role do this action? Needs the module itself first. */
    public function canDo(string $tenantId, string $role, string $family, string $perm, string $action): bool
    {
        if (!$this->can($tenantId, $role, $family, $perm)) {
            return false;
        }
        if ($action === 'view' || $this->full($role)) {
            return true;
        }
        if (!in_array($action, $this->permActions($perm), true)) {
            $action = self::FALLBACK[$action] ?? 'view';
            if ($action === 'view') {
                return true;
            }
            if (!in_array($action, $this->permActions($perm), true)) {
                return true; // the module has nothing to edit (view only)
            }
        }
        return in_array($action, $this->actions($tenantId, $role, $perm), true);
    }

    /** Writes the actions of a role for one vendor. @param array<string,list<string>> $map permission => actions */
    public function saveActions(string $tenantId, string $role, array $map): void
    {
        $rid = $this->roleId($role, $tenantId);
        if ($rid === null || $this->full($role)) {
            return;
        }
        $this->db->run('DELETE FROM sc_role_actions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $rid]);
        foreach ($map as $perm => $list) {
            if (!isset($this->cfg['permissions'][$perm])) {
                continue;
            }
            $ok = array_values(array_intersect($this->permActions($perm), array_map('strval', $list)));
            $this->db->insert('sc_role_actions', ['tenant_id' => $tenantId, 'role_id' => $rid, 'permission' => $perm, 'actions' => implode(',', $ok)]);
        }
        unset($this->actionMemo[$tenantId . '|' . $rid]);
    }

    /** Effective actions of a role for every module it has, as a map. @return array<string,list<string>> */
    public function actionMap(string $tenantId, string $role, string $family): array
    {
        $out = [];
        foreach ($this->effective($tenantId, $role, $family) as $perm) {
            $out[$perm] = $this->actions($tenantId, $role, $perm);
        }
        return $out;
    }

    /** Modules (permissions) that make sense for a kind of vendor. @return list<string> */
    public function permsForType(string $typeSlug, string $family, array $tpl): array
    {
        $docs = require __DIR__ . '/config/documents.php';
        $used = [];
        foreach (($docs['by_family'][$family]['forms'] ?? []) as $fk) {
            $f = $docs['forms'][$fk] ?? null;
            if ($f !== null && (empty($f['types']) || in_array($typeSlug, $f['types'], true))) {
                $used[(string) ($f['perm'] ?? '')] = true;
            }
        }
        foreach (Ops::all() as $m) {
            if (in_array($typeSlug, $m['types'], true)) {
                $used[(string) $m['perm']] = true;
            }
        }
        $kinds = array_column($tpl['sections'] ?? [], 'kind');
        $out = [];
        foreach (array_keys($this->cfg['permissions']) as $perm) {
            $ok = match ($perm) {
                'manage_doctors' => in_array('team', $kinds, true),
                'manage_patients' => !empty($tpl['people']),
                'manage_doctor_departments' => in_array('departments', $kinds, true),
                'manage_catalog' => in_array('cards', $kinds, true),
                'manage_notice_board' => in_array('news', $kinds, true),
                'manage_rx', 'view_rx' => Rx::forType($typeSlug) !== null,
                'manage_medicines' => isset($used[$perm]) || in_array($family, ['pharmacy', 'bloodbank', 'equipment'], true),
                'manage_blood_bank', 'manage_prescriptions', 'manage_ipd' => isset($used[$perm]),
                'lab_booking', 'lab_samples', 'lab_results', 'lab_approve', 'lab_catalogue', 'lab_referrers', 'lab_commission', 'lab_finance', 'lab_inventory', 'lab_staff', 'lab_attendance', 'lab_salary', 'lab_share', 'lab_records', 'lab_branches', 'lab_reports' => Lab::supports($typeSlug),
                default => true,
            };
            if ($ok) {
                $out[] = $perm;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ roles a vendor makes itself

    /** @return array<string,int> role name => id */
    private function customIds(string $tenantId): array
    {
        return $this->customMemo[$tenantId] ??= array_map('intval', array_column($this->db->all('SELECT id, name FROM departments WHERE tenant_id = ? AND is_active = 1', [$tenantId]), 'id', 'name'));
    }

    /** @return list<array<string,mixed>> */
    public function customRoles(string $tenantId): array
    {
        return $this->db->all('SELECT id, name, description, created_at FROM departments WHERE tenant_id = ? AND is_active = 1 ORDER BY name', [$tenantId]);
    }

    public function isCustom(string $tenantId, string $role): bool
    {
        return isset($this->customIds($tenantId)[$role]);
    }

    /** Why a role name cannot be used, or null. */
    public function nameError(string $tenantId, string $name, ?string $except = null): ?string
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40 || !preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} &'().\\/+-]*$/u", $name)) {
            return 'Give the role a name of 2 to 40 letters or numbers.';
        }
        foreach (array_merge($this->roles(), ['Super Admin']) as $g) {
            if (mb_strtolower($g) === mb_strtolower($name)) {
                return 'That name is already used by a standard role. Pick another name.';
            }
        }
        foreach (array_keys($this->customIds($tenantId)) as $c) {
            if ($c !== $except && mb_strtolower($c) === mb_strtolower($name)) {
                return 'You already have a role with that name.';
            }
        }
        return null;
    }

    /** Creates a role for one vendor. @param list<string> $perms @param array<string,list<string>> $actions */
    public function createRole(string $tenantId, string $name, string $description, array $perms, array $actions): int
    {
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('departments', ['name' => $name, 'is_active' => 1, 'guard_name' => 'web', 'tenant_id' => $tenantId, 'description' => mb_substr($description, 0, 250) ?: null, 'created_at' => $now, 'updated_at' => $now]);
        unset($this->customMemo[$tenantId]);
        $this->save($tenantId, $name, $perms, $actions);
        return $id;
    }

    public function renameRole(string $tenantId, string $role, string $name, string $description): void
    {
        $id = $this->customIds($tenantId)[$role] ?? null;
        if ($id !== null) {
            $this->db->run('UPDATE departments SET name = ?, description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [$name, mb_substr($description, 0, 250) ?: null, $id, $tenantId]);
            unset($this->customMemo[$tenantId]);
            foreach (array_keys($this->memo) as $k) {
                if (str_starts_with($k, $tenantId . '|')) {
                    unset($this->memo[$k]);
                }
            }
        }
    }

    /** Deletes a role of this vendor. Refuses while people still hold it. Returns an error or null. */
    public function deleteRole(string $tenantId, string $role): ?string
    {
        $id = $this->customIds($tenantId)[$role] ?? null;
        if ($id === null) {
            return 'That role cannot be deleted.';
        }
        $n = (int) $this->db->val('SELECT COUNT(*) FROM model_has_roles m JOIN users u ON u.id = m.model_id WHERE m.role_id = ? AND m.model_type = ? AND u.tenant_id = ?', [$id, Auth::ROLE_MODEL, $tenantId]);
        if ($n > 0) {
            return $n . ($n === 1 ? ' person still has' : ' people still have') . ' this role. Give them another role first.';
        }
        $this->db->run('DELETE FROM sc_role_actions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $id]);
        $this->db->run('DELETE FROM tenant_role_has_permissions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $id]);
        $this->db->run('DELETE FROM tenant_roles WHERE tenant_id = ? AND role_id = ?', [$tenantId, $id]);
        $this->db->run('DELETE FROM departments WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        unset($this->customMemo[$tenantId]);
        return null;
    }

    // ------------------------------------------------------------------ role templates per kind of vendor (edited by Self Care)

    /** @return array<string,mixed>|null */
    private function templateRow(string $type, string $role): ?array
    {
        return $type === '' ? null : $this->db->one('SELECT * FROM sc_role_templates WHERE type_slug = ? AND role_name = ?', [$type, $role]);
    }

    /** The starting point of a role for a kind of vendor. @return array{enabled:bool,perms:list<string>,actions:array<string,list<string>>,custom:bool,custom_actions:bool} */
    public function templateFor(string $type, string $family, string $role): array
    {
        $row = $this->templateRow($type, $role);
        $perms = $this->defaultsFor($role, $family);
        $ship = ($this->roleCfg['enabled'][$type][$role] ?? 0) === 1;
        if ($row === null) {
            $map = [];
            foreach ($perms as $pm) {
                $map[$pm] = array_values(array_intersect($this->permActions($pm), $this->profile($role)));
            }
            return ['enabled' => $ship, 'perms' => $perms, 'actions' => $map, 'custom' => false, 'custom_actions' => false];
        }
        $perms = array_values(array_intersect(array_keys($this->cfg['permissions']), (array) json_decode((string) $row['perms'], true)));
        $map = [];
        $stored = (array) json_decode((string) $row['actions'], true);
        foreach ($perms as $pm) {
            $map[$pm] = array_values(array_intersect($this->permActions($pm), (array) ($stored[$pm] ?? $this->profile($role))));
        }
        return ['enabled' => (int) $row['enabled'] === 1, 'perms' => $perms, 'actions' => $map, 'custom' => true, 'custom_actions' => true];
    }

    /** @param list<string> $perms @param array<string,list<string>> $actions */
    public function saveTemplate(string $type, string $role, bool $enabled, array $perms, array $actions, ?int $by, bool $isCustom = false, string $description = ''): void
    {
        $perms = array_values(array_intersect(array_keys($this->cfg['permissions']), $perms));
        $map = [];
        foreach ($perms as $pm) {
            $map[$pm] = array_values(array_intersect($this->permActions($pm), (array) ($actions[$pm] ?? [])));
        }
        $this->db->run('DELETE FROM sc_role_templates WHERE type_slug = ? AND role_name = ?', [$type, $role]);
        $this->db->insert('sc_role_templates', ['type_slug' => $type, 'role_name' => $role, 'is_custom' => $isCustom ? 1 : 0, 'enabled' => $enabled ? 1 : 0, 'description' => mb_substr($description, 0, 250) ?: null, 'perms' => json_encode($perms), 'actions' => json_encode($map), 'updated_by' => $by, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function resetTemplate(string $type, string $role): void
    {
        $this->db->run('DELETE FROM sc_role_templates WHERE type_slug = ? AND role_name = ?', [$type, $role]);
    }

    /** @return list<array<string,mixed>> */
    public function templateCustomRoles(string $type): array
    {
        return $type === '' ? [] : $this->db->all('SELECT * FROM sc_role_templates WHERE type_slug = ? AND is_custom = 1 ORDER BY role_name', [$type]);
    }

    /** Makes a template role (made by Self Care) for a vendor unless it has one with that name. @param array<string,mixed> $r */
    public function createFromTemplate(string $tenantId, array $r): void
    {
        $name = (string) $r['role_name'];
        if ($this->isCustom($tenantId, $name) || $this->nameError($tenantId, $name) !== null) {
            return;
        }
        $this->createRole($tenantId, $name, (string) ($r['description'] ?? ''), (array) json_decode((string) $r['perms'], true), (array) json_decode((string) $r['actions'], true));
    }

    /** Puts the template of the vendor type onto one vendor again (roles of the catalogue, and roles made by Self Care). Returns how many roles changed. */
    public function applyTemplate(string $tenantId, string $family, string $type): int
    {
        $n = 0;
        foreach ($this->roles() as $role) {
            if ($this->full($role)) {
                continue;
            }
            $t = $this->templateFor($type, $family, $role);
            $this->save($tenantId, $role, $t['perms'], $t['actions']);
            $this->setRoleEnabled($tenantId, $role, $t['enabled']);
            $n++;
        }
        foreach ($this->templateCustomRoles($type) as $r) {
            if ($this->isCustom($tenantId, (string) $r['role_name'])) {
                $this->save($tenantId, (string) $r['role_name'], (array) json_decode((string) $r['perms'], true), (array) json_decode((string) $r['actions'], true));
            } else {
                $this->createFromTemplate($tenantId, $r);
            }
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------------ which module and action a web address is

    /**
     * Action of a request: view, add, edit, delete, print or export. Looks at the method and the last part of the address.
     * @param array<string,mixed> $post
     */
    public static function classify(string $method, string $path, array $query = [], array $post = []): string
    {
        $path = '/' . trim($path, '/');
        $last = basename($path);
        $fmt = strtolower((string) ($query['format'] ?? ''));
        if ($fmt === 'csv' || $fmt === 'xlsx' || $last === 'export' || str_contains($path, '/export/')) {
            return 'export';
        }
        if ($fmt === 'pdf' || in_array($last, ['pdf', 'print', 'blank'], true) || isset($query['print'])) {
            return 'print';
        }
        if (in_array($last, ['delete', 'remove', 'purge', 'void'], true)) {
            return 'delete';
        }
        if (in_array($last, ['new', 'create', 'add', 'copy'], true) || preg_match('#/billing/new/#', $path)) {
            return 'add';
        }
        if ($last === 'save') {
            return empty($post['id']) ? 'add' : 'edit';
        }
        if (in_array($last, ['edit', 'restore', 'adjust', 'issue', 'pay', 'opening', 'verify'], true) || preg_match('#/do/[^/]+$#', $path)) {
            return 'edit';
        }
        return strtoupper($method) === 'POST' ? 'edit' : 'view';
    }

    /** Module (permission) behind a portal address, or null when it is open to everyone signed in or unknown. */
    public function permForPath(string $path, string $typeSlug, string $family, array $tpl, bool $canManageRx = true): ?string
    {
        $seg = explode('/', trim($path, '/'));
        if (($seg[0] ?? '') !== 'portal') {
            return null;
        }
        $a = $seg[1] ?? '';
        $simple = [
            'requests' => 'manage_appointments', 'doctors' => 'manage_doctors', 'patients' => 'manage_patients', 'staff' => 'manage_users', 'website' => 'manage_settings',
            'domains' => 'manage_settings', 'settings' => 'manage_settings', 'messages' => 'view_messages', 'access' => 'manage_access', 'plan' => 'view_plan',
            'billing' => 'manage_invoices', 'accounts' => 'manage_accounts', 'reports' => 'manage_reports', 'stock' => 'manage_medicines',
        ];
        if (isset($simple[$a])) {
            return $simple[$a];
        }
        if ($a === 'lab' && Lab::supports($typeSlug)) {
            return Lab::SECTION_PERM[$seg[2] ?? 'dashboard'] ?? 'lab_reports';
        }
        if ($a === 'workspace') {
            return 'manage_rx';
        }
        if ($a === 'medicines') {
            return 'manage_rx';
        }
        if ($a === 'prescriptions') {
            return $canManageRx ? 'manage_rx' : 'view_rx';
        }
        if ($a === 'ops' && isset($seg[2])) {
            $m = Ops::all()[$seg[2]] ?? null;
            return $m === null ? null : (string) $m['perm'];
        }
        if ($a === 'documents' && isset($seg[2]) && !in_array($seg[2], ['', 'blank'], true)) {
            $docs = require __DIR__ . '/config/documents.php';
            $f = $docs['forms'][$seg[2]] ?? $docs['invoices'][$seg[2]] ?? null;
            return $f === null ? null : (string) ($f['perm'] ?? null);
        }
        if ($a === 'c' && isset($seg[2])) {
            if ($seg[2] === 'free-services') {
                return 'manage_free_services';
            }
            foreach ($tpl['sections'] ?? [] as $s) {
                if ($s['key'] === $seg[2]) {
                    return match ($s['kind']) { 'cards' => 'manage_catalog', 'news' => 'manage_notice_board', 'departments' => 'manage_doctor_departments', default => 'manage_settings' };
                }
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ time based access

    /** @return array<string,mixed>|null */
    public function window(string $tenantId, int $userId): ?array
    {
        return $this->db->one('SELECT * FROM staff_access_windows WHERE tenant_id = ? AND user_id = ? LIMIT 1', [$tenantId, $userId]);
    }

    /** @return list<array<string,mixed>> */
    public function shifts(string $tenantId, int $userId): array
    {
        return $this->db->all('SELECT * FROM staff_shifts WHERE tenant_id = ? AND user_id = ? ORDER BY day_of_week, start_time', [$tenantId, $userId]);
    }

    /**
     * Is this staff member allowed in right now? Returns null when allowed, or a plain reason.
     * Admin accounts are never blocked. People without a window or shifts have normal access.
     */
    public function timeBlock(string $tenantId, int $userId, string $role, ?\DateTimeImmutable $now = null): ?string
    {
        if ($this->full($role)) {
            return null;
        }
        $now ??= new \DateTimeImmutable('now');
        $w = $this->window($tenantId, $userId);
        if ($w !== null) {
            if ((int) $w['is_active'] === 0) {
                return 'Your access has been paused. Please ask your administrator.';
            }
            if ($w['valid_from'] !== null && $now < new \DateTimeImmutable((string) $w['valid_from'])) {
                return 'Your access starts on ' . dt((string) $w['valid_from'], 'd M Y, H:i') . '.';
            }
            if ($w['valid_until'] !== null && $now > new \DateTimeImmutable((string) $w['valid_until'])) {
                return 'Your access ended on ' . dt((string) $w['valid_until'], 'd M Y, H:i') . '. Please ask your administrator.';
            }
        }
        $shifts = $this->shifts($tenantId, $userId);
        if ($shifts) {
            $dow = (int) $now->format('w');
            $prev = ($dow + 6) % 7;
            $t = $now->format('H:i:s');
            foreach ($shifts as $s) {
                $a = (string) $s['start_time'];
                $b = (string) $s['end_time'];
                $d = (int) $s['day_of_week'];
                if ($a <= $b) {
                    if ($d === $dow && $t >= $a && $t <= $b) {
                        return null;
                    }
                } else { // shift that passes midnight
                    if (($d === $dow && $t >= $a) || ($d === $prev && $t <= $b)) {
                        return null;
                    }
                }
            }
            return 'You are outside your shift. Sign in during your scheduled hours.';
        }
        return null;
    }
}
