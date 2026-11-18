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
        $rid = $this->roleId($role);
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
            foreach ($this->db->all("SELECT id, name FROM departments WHERE name <> 'Super Admin' AND is_active = 1 ORDER BY id") as $r) {
                $this->roleNames[(int) $r['id']] = (string) $r['name'];
            }
        }
        return $this->roleNames;
    }

    public function roleId(string $name): ?int
    {
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
        $rid = $this->roleId($role);
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
    public function save(string $tenantId, string $role, array $granted): void
    {
        $rid = $this->roleId($role);
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
    }

    /** Copies the default grants for every role into a new vendor. */
    public function seedTenant(string $tenantId, string $family): void
    {
        foreach ($this->roles() as $role) {
            if (!$this->full($role)) {
                $this->save($tenantId, $role, $this->defaultsFor($role, $family));
            }
        }
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
