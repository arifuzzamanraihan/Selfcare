<?php
// Selfcare original code. Doctors, patients and staff of one vendor (users + model_has_roles + the existing per-role tables).
declare(strict_types=1);

namespace Selfcare;

final class People
{
    public const SIDE_ROLES = ['Patient', 'Family Member', 'Referring Doctor', 'B2B Customer'];
    private const DOW = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function __construct(private App $app, private Portal $p)
    {
    }

    private function db(): Database
    {
        return $this->app->db;
    }

    // ------------------------------------------------------------------ listings

    public function listing(string $kind): string
    {
        $tid = $this->p->tenantId;
        $self = (int) $this->p->user['id'];
        $actions = [['label' => 'Edit', 'icon' => 'edit', 'url' => fn ($r) => url('portal/' . $kind . '/' . $r['id'])]];
        $status = static fn ($r) => (int) $r['status'] === 1 ? '<span class="d-badge is-ok">Active</span>' : '<span class="d-badge is-mute">Inactive</span>';
        $name = static fn ($r) => '<span class="d-person"><i style="background:hsl(' . tone((string) $r['email'])[0] . ' 55% 42%)">' . e(initials($r['first_name'] . ' ' . $r['last_name'])) . '</i><b>' . e(trim($r['first_name'] . ' ' . $r['last_name'])) . '</b></span>';
        if ($kind === 'doctors') {
            return Listing::render($this->db(), [
                'id' => 'doctors', 'select' => 'd.id, u.first_name, u.last_name, u.email, u.phone, u.status, d.specialist, d.appointment_charge, dd.title AS dept', 'from' => 'doctors d JOIN users u ON u.id = d.user_id LEFT JOIN doctor_departments dd ON dd.id = d.doctor_department_id',
                'where' => ['d.tenant_id = ?'], 'params' => [$tid], 'search' => ['u.first_name', 'u.last_name', 'u.email', 'd.specialist', 'dd.title'], 'search_hint' => 'Search by name, email or speciality',
                'filters' => ['status' => ['label' => 'Status', 'sql' => 'u.status = ?', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
                'columns' => ['first_name' => ['label' => 'Name', 'sort' => 'u.first_name', 'render' => $name], 'specialist' => ['label' => 'Speciality', 'sort' => 'd.specialist'], 'dept' => ['label' => 'Department', 'sort' => 'dd.title'], 'appointment_charge' => ['label' => 'Fee', 'sort' => 'd.appointment_charge', 'render' => fn ($r) => e(money((float) $r['appointment_charge']))], 'phone' => ['label' => 'Phone'], 'status' => ['label' => 'Status', 'sort' => 'u.status', 'render' => $status]],
                'sort' => 'first_name', 'actions' => $actions, 'empty' => ['title' => 'No ' . strtolower($this->p->label('team', 'doctors')) . ' yet', 'text' => 'Add the first one and they will show on your website.', 'cta' => ['label' => 'Add one', 'url' => url('portal/doctors/new')]],
            ]);
        }
        if ($kind === 'patients') {
            return Listing::render($this->db(), [
                'id' => 'patients', 'select' => 'p.id, p.patient_unique_id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.city, u.status, p.created_at', 'from' => 'patients p JOIN users u ON u.id = p.user_id',
                'where' => ['p.tenant_id = ?'], 'params' => [$tid], 'search' => ['u.first_name', 'u.last_name', 'u.email', 'u.phone', 'p.patient_unique_id'], 'search_hint' => 'Search by name, phone or ID',
                'filters' => ['gender' => ['label' => 'Sex', 'sql' => 'u.gender = ?', 'options' => ['0' => 'Male', '1' => 'Female', '2' => 'Other']]],
                'columns' => ['patient_unique_id' => ['label' => 'ID', 'sort' => 'p.patient_unique_id'], 'first_name' => ['label' => 'Name', 'sort' => 'u.first_name', 'render' => $name], 'phone' => ['label' => 'Phone'], 'gender' => ['label' => 'Sex', 'sort' => 'u.gender', 'render' => fn ($r) => e(['Male', 'Female', 'Other'][(int) $r['gender']] ?? '')], 'created_at' => ['label' => 'Registered', 'sort' => 'p.created_at', 'type' => 'date']],
                'sort' => 'created_at', 'dir' => 'desc', 'actions' => $actions, 'empty' => ['title' => 'No ' . strtolower((string) $this->p->label('people', 'patients')) . ' yet', 'text' => 'Register the first one from here.', 'cta' => ['label' => 'Register one', 'url' => url('portal/patients/new')]],
            ]);
        }
        // staff
        return Listing::render($this->db(), [
            'id' => 'staff', 'select' => "u.id, u.first_name, u.last_name, u.email, u.phone, u.status, d.name AS role, w.valid_until", 'from' => "users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = '" . Auth::ROLE_MODEL . "' JOIN departments d ON d.id = m.role_id LEFT JOIN staff_access_windows w ON w.user_id = u.id",
            'where' => ['u.tenant_id = ?', "d.name NOT IN ('Doctor', 'Patient', 'Family Member', 'Referring Doctor', 'B2B Customer')"], 'params' => [$tid], 'search' => ['u.first_name', 'u.last_name', 'u.email', 'd.name'], 'search_hint' => 'Search by name, email or role',
            'filters' => ['role' => ['label' => 'Role', 'sql' => 'd.name = ?', 'options' => array_combine($this->staffRoles(), $this->staffRoles())], 'status' => ['label' => 'Status', 'sql' => 'u.status = ?', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
            'columns' => ['first_name' => ['label' => 'Name', 'sort' => 'u.first_name', 'render' => $name], 'role' => ['label' => 'Role', 'sort' => 'd.name', 'render' => fn ($r) => e($this->app->access->roleLabel((string) $r['role'], $this->p->family))], 'email' => ['label' => 'Email', 'sort' => 'u.email'], 'valid_until' => ['label' => 'Access until', 'sort' => 'w.valid_until', 'render' => fn ($r) => $r['valid_until'] ? e(dt((string) $r['valid_until'])) : '<span class="d-mute">No end date</span>'], 'status' => ['label' => 'Status', 'sort' => 'u.status', 'render' => $status]],
            'sort' => 'first_name', 'actions' => $actions, 'empty' => ['title' => 'No staff accounts yet', 'text' => 'Add your team. Each person gets only the access their role allows.', 'cta' => ['label' => 'Add staff', 'url' => url('portal/staff/new')]],
        ]);
    }

    /** Roles this vendor can give to staff (enabled, not the patient side, not Owner / Doctor). @return list<string> */
    public function staffRoles(): array
    {
        $roles = $this->app->access->enabledRoles($this->p->tenantId, (string) $this->p->type['slug']);
        return array_values(array_filter($roles, static fn ($r) => !in_array($r, array_merge(self::SIDE_ROLES, ['Owner', 'Doctor']), true)));
    }

    // ------------------------------------------------------------------ forms

    /** @return array<string,array<string,mixed>> */
    private function fields(string $kind, ?int $id): array
    {
        $f = [
            'first_name' => ['label' => 'First name', 'required' => true, 'max' => 160],
            'last_name' => ['label' => 'Last name', 'max' => 191],
            'email' => ['label' => 'Email (used to sign in)', 'type' => 'email', 'required' => true, 'max' => 191],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'gender' => ['label' => 'Sex', 'type' => 'select', 'options' => ['0' => 'Male', '1' => 'Female', '2' => 'Other'], 'required' => true],
        ];
        if ($kind === 'doctors') {
            $depts = array_column($this->db()->all('SELECT id, title FROM doctor_departments WHERE tenant_id = ? ORDER BY title', [$this->p->tenantId]), 'title', 'id');
            $f += ['specialist' => ['label' => 'Speciality', 'required' => true, 'max' => 191], 'qualification' => ['label' => 'Qualifications', 'max' => 191]];
            if ($depts) {
                $f['doctor_department_id'] = ['label' => 'Department', 'type' => 'select', 'options' => $depts, 'required' => true];
            }
            $f += ['appointment_charge' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0], 'description' => ['label' => 'About (shown on the website)', 'type' => 'textarea', 'max' => 2000]];
        } elseif ($kind === 'patients') {
            $f += ['dob' => ['label' => 'Date of birth', 'type' => 'date'], 'blood_group' => ['label' => 'Blood group', 'type' => 'select', 'options' => array_combine(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'], ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])], 'city' => ['label' => 'City', 'max' => 191]];
        } else {
            $roles = $this->staffRoles();
            $f += ['role' => ['label' => 'Role', 'type' => 'select', 'options' => array_combine($roles, array_map(fn ($r) => $this->app->access->roleLabel($r, $this->p->family), $roles)), 'required' => true, 'help' => 'The role decides what this person can see. You can change what each role may do under Role access.']];
        }
        $f['password'] = ['label' => $id ? 'New password (leave empty to keep)' : 'Password', 'type' => 'password', 'max' => 100, 'required' => !$id, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters.'];
        $f['status'] = ['label' => 'Account is active', 'type' => 'toggle', 'default' => 1];
        return $f;
    }

    /** @return array<string,mixed>|null */
    public function find(string $kind, int $id): ?array
    {
        $tid = $this->p->tenantId;
        return match ($kind) {
            'doctors' => $this->db()->one('SELECT d.id AS rid, d.specialist, d.description, d.doctor_department_id, d.appointment_charge, u.* FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ? AND d.tenant_id = ?', [$id, $tid]),
            'patients' => $this->db()->one('SELECT p.id AS rid, u.* FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ?', [$id, $tid]),
            default => $this->db()->one("SELECT u.*, d.name AS role, u.id AS rid FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.id = ? AND u.tenant_id = ?", [Auth::ROLE_MODEL, $id, $tid]),
        };
    }

    public function form(string $kind, ?int $id): string
    {
        $row = $id ? $this->find($kind, $id) : null;
        $fields = $this->fields($kind, $id);
        $h = '<form class="d-form" method="post" action="' . e(url('portal/' . $kind . '/save')) . '" autocomplete="off">' . csrf_field() . ($id ? '<input type="hidden" name="id" value="' . $id . '">' : '') . '<div class="d-form-grid">';
        foreach ($fields as $n => $f) {
            $val = $n === 'password' ? '' : (string) ($row[$n] ?? ($f['default'] ?? ''));
            $h .= Crud::field($n, $f, old($n, $val));
        }
        $h .= '</div>';
        if ($kind === 'staff') {
            $uid = $id ?? 0;
            $w = $uid ? $this->app->access->window($this->p->tenantId, $uid) : null;
            $shifts = $uid ? $this->app->access->shifts($this->p->tenantId, $uid) : [];
            $h .= '<fieldset class="d-fieldset"><legend>When can this person sign in?</legend><p class="d-help">Leave everything empty for normal access at any time. Use the dates for a season, a locum or a trial. Use shifts to allow sign in only during working hours. Admins are never blocked.</p><div class="d-form-grid">';
            $h .= Crud::field('valid_from', ['label' => 'Access starts', 'type' => 'datetime-local'], old('valid_from', $w && $w['valid_from'] ? date('Y-m-d\TH:i', strtotime((string) $w['valid_from'])) : ''));
            $h .= Crud::field('valid_until', ['label' => 'Access ends', 'type' => 'datetime-local'], old('valid_until', $w && $w['valid_until'] ? date('Y-m-d\TH:i', strtotime((string) $w['valid_until'])) : ''));
            $h .= Crud::field('window_note', ['label' => 'Reason (for your records)', 'max' => 190, 'wide' => true], old('window_note', (string) ($w['note'] ?? '')));
            $h .= '</div><div class="d-shifts"><div class="d-shift-head"><span>Day</span><span>From</span><span>To</span></div>';
            $by = [];
            foreach ($shifts as $s) {
                $by[(int) $s['day_of_week']] = $s;
            }
            foreach (self::DOW as $i => $day) {
                $s = $by[$i] ?? null;
                $h .= '<div class="d-shift"><label class="d-check"><input type="checkbox" name="shift_on[' . $i . ']" value="1"' . ($s ? ' checked' : '') . '><span>' . $day . '</span></label><input type="time" name="shift_from[' . $i . ']" value="' . e($s ? substr((string) $s['start_time'], 0, 5) : '08:00') . '"><input type="time" name="shift_to[' . $i . ']" value="' . e($s ? substr((string) $s['end_time'], 0, 5) : '16:00') . '"></div>';
            }
            $h .= '</div></fieldset>';
        }
        $h .= '<div class="d-form-actions"><button class="d-btn d-btn-primary" type="submit">' . ($id ? 'Save changes' : 'Add') . '</button><a class="d-btn d-btn-soft" href="' . e(url('portal/' . $kind)) . '">Cancel</a></div></form>';
        clear_old();
        return $h;
    }

    // ------------------------------------------------------------------ save

    /** Returns an error text, or null when saved. */
    public function save(string $kind): ?string
    {
        $tid = $this->p->tenantId;
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id ? $this->find($kind, $id) : null;
        if ($id && $row === null) {
            return 'That person was not found.';
        }
        if (!$id) {
            $lim = $this->app->plans->limitError($tid, $kind === 'patients' ? 'patients' : 'staff');
            if ($lim !== null) {
                return $lim;
            }
        }
        $fields = $this->fields($kind, $id ?: null);
        [$d, $err] = Crud::collect($fields);
        if ($err !== null) {
            keep_old($_POST);
            return $err;
        }
        $email = strtolower((string) $d['email']);
        $d['email'] = $email;
        $pw = (string) ($_POST['password'] ?? '');
        if ($pw !== '' && strlen($pw) < 8) {
            keep_old($_POST);
            return 'Use at least 8 characters for the password.';
        }
        if ((int) $this->db()->val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $row['id'] ?? 0]) > 0) {
            keep_old($_POST);
            return 'That email already has an account.';
        }
        $role = $kind === 'doctors' ? 'Doctor' : ($kind === 'patients' ? 'Patient' : (string) $d['role']);
        $actor = (int) $this->p->user['id'];
        $roleId = null;
        if ($kind === 'staff') {
            if (!in_array($role, $this->staffRoles(), true)) {
                keep_old($_POST);
                return 'That role is not switched on for your organisation.';
            }
            $roleId = $this->app->access->roleId($role);
            // grantor ceiling: only roles whose permissions you hold yourself
            if (!$this->app->access->full($this->p->role)) {
                $mine = $this->p->perms;
                foreach ($this->app->access->effective($tid, $role, $this->p->family) as $perm) {
                    if (!in_array($perm, $mine, true)) {
                        keep_old($_POST);
                        return 'You can only give roles that do not go beyond your own access.';
                    }
                }
            }
            if ($id === $actor && $role !== $row['role']) {
                keep_old($_POST);
                return 'You cannot change your own role. Ask another admin.';
            }
            if ($row !== null && $this->app->access->full((string) $row['role']) && ($role !== $row['role'] || (int) $d['status'] === 0) && $this->activeAdmins() <= 1) {
                keep_old($_POST);
                return 'This is the last active admin. Make another admin first.';
            }
        } else {
            $roleId = $this->app->access->roleId($role) ?? 0;
        }
        $this->db()->begin();
        try {
            $now = date('Y-m-d H:i:s');
            $u = ['first_name' => $d['first_name'], 'last_name' => $d['last_name'] ?? '', 'email' => $email, 'phone' => $d['phone'] ?? null, 'gender' => (int) $d['gender'], 'status' => (int) ($d['status'] ?? 1), 'updated_at' => $now];
            foreach (['dob', 'blood_group', 'city', 'qualification'] as $c) {
                if (array_key_exists($c, $d)) {
                    $u[$c] = $d[$c] === '' ? null : $d[$c];
                }
            }
            if ($pw !== '') {
                $u['password'] = password_hash($pw, PASSWORD_BCRYPT);
            }
            if ($row !== null) {
                $uid = (int) $row['id'];
                $this->db()->update('users', $u, 'id = ?', [$uid]);
            } else {
                $u += ['department_id' => $roleId, 'hospital_name' => (string) $this->p->vendor['name'], 'tenant_id' => $tid, 'language' => 'en', 'created_at' => $now, 'password' => $u['password'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT)];
                $uid = $this->db()->insert('users', $u);
                $this->db()->insert('model_has_roles', ['role_id' => $roleId, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
                $table = $this->app->access->staffTable($role);
                if ($table !== null && !in_array($table, ['doctors', 'patients'], true)) {
                    $this->db()->insert($table, ['user_id' => $uid, 'tenant_id' => $tid, 'created_at' => $now, 'updated_at' => $now]);
                }
                $this->app->access->log($tid, $actor, $uid, $role, 'staff_created', null, ['email' => $email, 'role' => $role]);
            }
            if ($kind === 'doctors') {
                $dd = ['specialist' => $d['specialist'], 'description' => $d['description'] ?? null, 'appointment_charge' => $d['appointment_charge'] ?? 0, 'updated_at' => $now];
                if (isset($d['doctor_department_id'])) {
                    $dd['doctor_department_id'] = (int) $d['doctor_department_id'];
                } elseif ($row === null) {
                    $dd['doctor_department_id'] = (int) ($this->db()->val('SELECT id FROM doctor_departments WHERE tenant_id = ? ORDER BY id LIMIT 1', [$tid]) ?: $this->db()->insert('doctor_departments', ['title' => 'General', 'tenant_id' => $tid, 'created_at' => $now, 'updated_at' => $now]));
                }
                if ($row !== null) {
                    $this->db()->update('doctors', $dd, 'id = ?', [(int) $row['rid']]);
                } else {
                    $this->db()->insert('doctors', $dd + ['user_id' => $uid, 'tenant_id' => $tid, 'created_at' => $now]);
                }
            } elseif ($kind === 'patients' && $row === null) {
                $this->db()->insert('patients', ['user_id' => $uid, 'tenant_id' => $tid, 'patient_unique_id' => strtoupper(substr($tid, 0, 3)) . '-' . strtoupper(bin2hex(random_bytes(3))), 'created_at' => $now, 'updated_at' => $now]);
            } elseif ($kind === 'staff') {
                if ($row !== null && $role !== $row['role']) {
                    $this->db()->run('UPDATE model_has_roles SET role_id = ? WHERE model_id = ? AND model_type = ?', [$roleId, $uid, Auth::ROLE_MODEL]);
                    $this->db()->run('UPDATE users SET department_id = ? WHERE id = ?', [$roleId, $uid]);
                    $this->app->access->log($tid, $actor, $uid, $role, 'role_changed', $row['role'], $role);
                }
                $this->saveTime($uid);
            }
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
        return null;
    }

    private function activeAdmins(): int
    {
        return (int) $this->db()->val("SELECT COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND u.status = 1 AND d.name IN ('Admin', 'Owner')", [Auth::ROLE_MODEL, $this->p->tenantId]);
    }

    /** Saves the access window and the weekly shifts. */
    private function saveTime(int $uid): void
    {
        $tid = $this->p->tenantId;
        $now = date('Y-m-d H:i:s');
        $from = (string) ($_POST['valid_from'] ?? '');
        $until = (string) ($_POST['valid_until'] ?? '');
        $from = $from !== '' && strtotime($from) ? date('Y-m-d H:i:s', strtotime($from)) : null;
        $until = $until !== '' && strtotime($until) ? date('Y-m-d H:i:s', strtotime($until)) : null;
        $this->db()->run('DELETE FROM staff_access_windows WHERE user_id = ? AND tenant_id = ?', [$uid, $tid]);
        if ($from !== null || $until !== null) {
            $this->db()->insert('staff_access_windows', ['tenant_id' => $tid, 'user_id' => $uid, 'valid_from' => $from, 'valid_until' => $until, 'is_active' => 1, 'note' => mb_substr(trim((string) ($_POST['window_note'] ?? '')), 0, 190) ?: null, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db()->run('DELETE FROM staff_shifts WHERE user_id = ? AND tenant_id = ?', [$uid, $tid]);
        foreach ((array) ($_POST['shift_on'] ?? []) as $d => $v) {
            $d = (int) $d;
            $a = (string) ($_POST['shift_from'][$d] ?? '');
            $b = (string) ($_POST['shift_to'][$d] ?? '');
            if ($d >= 0 && $d <= 6 && preg_match('/^\d{2}:\d{2}$/', $a) && preg_match('/^\d{2}:\d{2}$/', $b)) {
                $this->db()->insert('staff_shifts', ['tenant_id' => $tid, 'user_id' => $uid, 'day_of_week' => $d, 'start_time' => $a . ':00', 'end_time' => $b . ':00', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $this->app->access->log($tid, (int) $this->p->user['id'], $uid, null, 'time_access_saved', null, ['from' => $from, 'until' => $until]);
    }
}
