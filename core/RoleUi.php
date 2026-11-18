<?php
declare(strict_types=1);

namespace Selfcare;

/** The "what can this role do" grid, shared by the vendor's Role access page and Self Care's role templates. */
final class RoleUi
{
    /** Module of the plan that a permission belongs to. */
    public const MODULE = ['manage_doctors' => 'doctors', 'manage_patients' => 'patients', 'manage_users' => 'staff', 'manage_settings' => 'website', 'manage_catalog' => 'catalog', 'manage_free_services' => 'free_services', 'view_messages' => 'messages', 'manage_access' => 'access', 'manage_invoices' => 'billing', 'manage_prescriptions' => 'documents', 'manage_medicines' => 'documents', 'manage_blood_bank' => 'documents', 'manage_reports' => 'reports', 'manage_accounts' => 'billing', 'manage_rx' => 'documents', 'view_rx' => 'documents'];

    /**
     * @param list<string> $perms        modules to show
     * @param list<string> $cur          modules the role has
     * @param array<string,list<string>> $acts actions per module
     * @param callable(string):?string $viewLock  reason why a module cannot be changed by this person, or null
     * @param callable(string,string):bool $actLock  true when this person may not change this action
     */
    public static function grid(Access $a, array $perms, array $cur, array $acts, callable $viewLock, callable $actLock, bool $readOnly = false): string
    {
        $h = '<div class="d-table-wrap"><table class="d-matrix" data-rolegrid><thead><tr><th>Module</th>';
        foreach (Access::ACTIONS as $lab) {
            $h .= '<th>' . e($lab) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        $info = $a->permissions();
        foreach ($perms as $perm) {
            if (!isset($info[$perm])) {
                continue;
            }
            $lock = $viewLock($perm);
            $has = in_array($perm, $cur, true);
            $h .= '<tr data-perm="' . e($perm) . '"><td><b>' . e($info[$perm][0]) . '</b><br><small class="d-mute">' . e($info[$perm][1]) . ' · ' . e($info[$perm][2]) . ($lock !== null ? ' (' . e($lock) . ')' : '') . '</small></td>';
            $h .= '<td data-label="View"><input type="checkbox" name="perm[]" value="' . e($perm) . '" data-act="view" aria-label="View ' . e($info[$perm][0]) . '"' . ($has ? ' checked' : '') . ($lock !== null || $readOnly ? ' disabled' : '') . '></td>';
            foreach (['add', 'edit', 'delete', 'print', 'export'] as $act) {
                if (!in_array($act, $a->permActions($perm), true)) {
                    $h .= '<td data-label="' . e(Access::ACTIONS[$act]) . '" class="d-mute">–</td>';
                    continue;
                }
                $on = $has && in_array($act, $acts[$perm] ?? [], true);
                $dis = $lock !== null || $readOnly || $actLock($perm, $act);
                $h .= '<td data-label="' . e(Access::ACTIONS[$act]) . '"><input type="checkbox" name="act[' . e($perm) . '][]" value="' . e($act) . '" data-act="' . e($act) . '" aria-label="' . e(Access::ACTIONS[$act] . ' ' . $info[$perm][0]) . '"' . ($on ? ' checked' : '') . ($dis ? ' disabled' : '') . '></td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div><p class="d-help" style="margin-top:8px">View lets the role open the module. Add, Edit, Delete, Print and Export are what it may do once inside. Greyed boxes are not in your plan or are above your own access.</p>' . self::script();
        return $h;
    }

    private static function script(): string
    {
        return '<script>document.querySelectorAll("[data-rolegrid]").forEach(function(t){if(t.dataset.bound)return;t.dataset.bound=1;t.addEventListener("change",function(e){var c=e.target;if(c.type!=="checkbox")return;var tr=c.closest("tr"),v=tr.querySelector("[data-act=view]");if(c.dataset.act==="view"&&!c.checked){tr.querySelectorAll("input[data-act]:not([data-act=view]):not(:disabled)").forEach(function(x){x.checked=false})}else if(c.dataset.act!=="view"&&c.checked&&v&&!v.disabled){v.checked=true}})});</script>';
    }

    /**
     * Reads the grid of a posted form. $before/$beforeActs are what the role had; boxes the person could not change keep their value.
     * @param list<string> $shown modules that were in the grid
     * @return array{0:list<string>,1:array<string,list<string>>}
     */
    public static function read(Access $a, array $shown, array $before, array $beforeActs, callable $viewLock, callable $actLock): array
    {
        $postView = array_map('strval', (array) ($_POST['perm'] ?? []));
        $postActs = (array) ($_POST['act'] ?? []);
        $perms = [];
        $acts = [];
        foreach (array_keys($a->permissions()) as $perm) {
            $isShown = in_array($perm, $shown, true);
            $has = in_array($perm, $before, true);
            if ($isShown && $viewLock($perm) === null) {
                $has = in_array($perm, $postView, true);
            }
            if (!$has) {
                continue;
            }
            $perms[] = $perm;
            $list = [];
            $posted = array_map('strval', (array) ($postActs[$perm] ?? []));
            foreach ($a->permActions($perm) as $act) {
                $now = in_array($act, $beforeActs[$perm] ?? [], true);
                if ($isShown && $viewLock($perm) === null && !$actLock($perm, $act)) {
                    $now = in_array($act, $posted, true);
                }
                if ($now) {
                    $list[] = $act;
                }
            }
            $acts[$perm] = $list;
        }
        return [$perms, $acts];
    }
}
