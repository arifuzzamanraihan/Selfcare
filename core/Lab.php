<?php
// Selfcare original code. Clinic laboratory suite: branches and branch isolation, user level permissions, patients (MR numbers),
// test catalogue, orders billed on the normal invoices, samples, results and approval, referrer commission, finance, staff, attendance, salary.
// It extends the clinic portal: patients are `patients`, bills are `sc_invoices`, expenses are `sc_ledger`, stock is `sc_stock_items`, staff are `users`.
// Routes: core/routes/lab.php and core/routes/lab2.php. Documents: core/LabDocs.php.
declare(strict_types=1);

namespace Selfcare;

final class Lab
{
    /** Vendor types that get the laboratory suite. */
    public const TYPES = ['clinic'];

    /** URL section => permission (module) that guards it. */
    public const SECTION_PERM = [
        'book' => 'lab_booking', 'orders' => 'lab_booking', 'order' => 'lab_booking', 'patients' => 'lab_booking',
        'samples' => 'lab_samples', 'home' => 'lab_samples', 'results' => 'lab_results', 'approve' => 'lab_approve', 'report' => 'lab_results',
        'catalogue' => 'lab_catalogue', 'referrers' => 'lab_referrers', 'commission' => 'lab_commission', 'finance' => 'lab_finance', 'expenses' => 'lab_finance',
        'employees' => 'lab_staff', 'attendance' => 'lab_attendance', 'salary' => 'lab_salary', 'share' => 'lab_share', 'history' => 'lab_records',
        'branches' => 'lab_branches', 'settings' => 'lab_branches', 'backups' => 'lab_branches', 'access' => 'lab_branches', 'reports' => 'lab_reports', 'dashboard' => 'lab_reports',
    ];
    public const PERM_LIST = ['lab_booking', 'lab_samples', 'lab_results', 'lab_approve', 'lab_catalogue', 'lab_referrers', 'lab_commission', 'lab_finance', 'lab_inventory', 'lab_staff', 'lab_attendance', 'lab_salary', 'lab_share', 'lab_records', 'lab_branches', 'lab_reports'];
    public const SPECIMENS = ['Blood', 'Serum', 'Plasma', 'Urine', 'Stool', 'Swab', 'Sputum', 'Tissue', 'Fluid', 'Other'];
    public const METHODS = ['Cash', 'Bank transfer', 'Card', 'Mobile wallet', 'Cheque', 'Other'];
    public const EXTRA_EXPENSE = ['Doctor commissions', 'Partner laboratory commissions', 'Stock purchases'];
    public const SYSTEM_EXPENSE = ['Salaries and wages', 'Doctor commissions', 'Partner laboratory commissions', 'Stock purchases'];

    /** @var array<string,self> */
    private static array $inst = [];
    /** @var ?array<int,array<string,mixed>> */
    private ?array $branchCache = null;

    public readonly string $t;
    public readonly Database $db;

    private function __construct(public readonly Portal $p)
    {
        $this->t = $p->tenantId;
        $this->db = app()->db;
        $this->ensure();
    }

    public static function supports(string $typeSlug): bool
    {
        return in_array($typeSlug, self::TYPES, true);
    }

    public static function for(Portal $p): self
    {
        $i = self::$inst[$p->tenantId . '|' . $p->user['id']] ??= new self($p);
        $i->applyContext();
        return $i;
    }

    /** Branch screens use the branch currency and time zone; the all branches view uses the vendor's (with the vendor's own exchange rates for totals). */
    public function applyContext(): void
    {
        Currency::loadVendorFx($this->t);
        $c = $this->current();
        $cur = $this->reportCurrency();
        Currency::$context = $cur;
        $tz = $c > 0 ? (string) ($this->branch($c)['timezone'] ?? '') : '';
        if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true) && $tz !== date_default_timezone_get()) {
            date_default_timezone_set($tz);
            $this->db->run('SET time_zone = ?', [date('P')]);
        }
    }

    /** Currency of one branch (its own, else the vendor's). */
    public function branchCurrency(int $branch): string
    {
        return Currency::forBranch($this->t, $branch ?: $this->mainId());
    }

    /** Currency the screen and reports are written in: the branch's when one branch is open, else the vendor's. */
    public function reportCurrency(): string
    {
        $ids = $this->force !== null ? $this->force : ($this->current() > 0 ? [$this->current()] : []);
        if (count($ids) === 1) {
            return $this->branchCurrency((int) $ids[0]);
        }
        return Currency::forTenant($this->t);
    }

    /** True when the branches in view do not all use one currency. */
    public function mixedCurrencies(): bool
    {
        $set = [];
        foreach ($this->force !== null ? $this->force : $this->viewIds() as $b) {
            $set[$this->branchCurrency((int) $b)] = true;
        }
        return count($set) > 1;
    }

    /** True once the branch has money records (bills, payments, ledger, salary, stock). Its currency is then fixed so old amounts never change meaning. */
    public function branchHasMoney(int $branch): bool
    {
        $m = $this->mainId();
        $w = $branch === $m ? '(branch_id = ? OR branch_id IS NULL)' : 'branch_id = ?';
        foreach (['sc_lab_orders', 'sc_ledger', 'sc_salary', 'sc_stock_items', 'sc_invoices'] as $t) {
            $extra = $t === 'sc_invoices' ? " AND issuer = 'vendor'" : '';
            if ((int) $this->db->val("SELECT COUNT(*) FROM $t WHERE tenant_id = ? AND $w$extra", [$this->t, $branch]) > 0) {
                return true;
            }
        }
        return false;
    }

    /** An amount written in the vendor's currency, in the currency of a branch (central prices, fixed commissions). */
    public function toBranch(float $amount, int $branch): float
    {
        $v = Currency::forTenant($this->t);
        $b = $this->branchCurrency($branch);
        return $v === $b ? $amount : Currency::convert($amount, $v, $b);
    }

    /** The laboratory service of a vendor, run as its main admin (used for patient downloads and public links). */
    public static function forTenant(App $app, string $tenant): ?self
    {
        $uid = (int) $app->db->val('SELECT id FROM users WHERE tenant_id = ? AND is_admin_default = 1 AND status = 1 ORDER BY id LIMIT 1', [$tenant]);
        $p = $uid ? Portal::forUser($app, $uid) : null;
        return $p !== null && self::enabled($p) ? self::for($p) : null;
    }

    public static function enabled(Portal $p): bool
    {
        return !$p->isSide() && self::supports((string) $p->type['slug']);
    }

    // ------------------------------------------------------------------ setup

    private function ensure(): void
    {
        if ((int) $this->db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ?', [$this->t]) === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->insert('sc_branches', ['tenant_id' => $this->t, 'code' => 'MAIN', 'name' => 'Main Branch', 'is_main' => 1, 'active' => 1, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }
        if ((int) $this->db->val('SELECT COUNT(*) FROM sc_lab_departments WHERE tenant_id = ?', [$this->t]) === 0) {
            foreach (['Hematology', 'Biochemistry', 'Microbiology', 'Serology', 'Histopathology', 'Immunology', 'Urine / Stool', 'Hormones', 'Autoimmune', 'Tumour markers', 'Parasitology'] as $i => $d) {
                $this->db->run('INSERT IGNORE INTO sc_lab_departments (tenant_id, name, sort_order) VALUES (?, ?, ?)', [$this->t, $d, $i]);
            }
        }
        if ((int) $this->db->val('SELECT COUNT(*) FROM sc_lab_signatories WHERE tenant_id = ?', [$this->t]) === 0) {
            $this->db->insert('sc_lab_signatories', ['tenant_id' => $this->t, 'name' => 'Authorised pathologist', 'title' => 'Consultant Pathologist', 'is_default' => 1, 'active' => 1]);
        }
    }

    // ------------------------------------------------------------------ settings and numbers

    public function setting(string $k, string $d = ''): string
    {
        return (new Billing($this->db))->setting($this->t, 'lab_' . $k, $d);
    }

    public function setSetting(string $k, string $v): void
    {
        (new Billing($this->db))->setSetting($this->t, 'lab_' . $k, $v);
    }

    /** Extra expense categories and the switched off ones are kept as settings. @return array{extra:list<string>,off:list<string>} */
    public function expenseCats(): array
    {
        $x = json_decode($this->setting('expense_cats', '{}'), true);
        return ['extra' => array_values((array) ($x['extra'] ?? [])), 'off' => array_values((array) ($x['off'] ?? []))];
    }

    /** Expense categories in use, with the module categories always present. @return list<string> */
    public function expenseList(bool $withOff = false): array
    {
        $c = $this->expenseCats();
        $all = array_values(array_unique(array_merge(Accounts::EXPENSE, self::EXTRA_EXPENSE, $c['extra'])));
        return $withOff ? $all : array_values(array_filter($all, static fn ($n) => !in_array($n, $c['off'], true) || in_array($n, self::SYSTEM_EXPENSE, true)));
    }

    public function seq(string $type): int
    {
        $this->db->run('INSERT INTO sc_number_sequences (tenant_id, doc_type, last_no) VALUES (?, ?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)', [$this->t, $type]);
        return (int) $this->db->val('SELECT LAST_INSERT_ID()');
    }

    /** Document number with the branch code in it, for example ORD-MAIN-000012. */
    public function docNo(string $prefix, int $branch, int $pad = 6): string
    {
        $code = (string) ($this->branch($branch)['code'] ?? 'MAIN');
        return $prefix . '-' . $code . '-' . str_pad((string) $this->seq('lab_' . strtolower($prefix)), $pad, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ branches and who may see them

    /** @return array<int,array<string,mixed>> id => row, ordered main first */
    public function branches(bool $activeOnly = false): array
    {
        if ($this->branchCache === null) {
            $this->branchCache = [];
            foreach ($this->db->all('SELECT * FROM sc_branches WHERE tenant_id = ? ORDER BY is_main DESC, sort_order, id', [$this->t]) as $r) {
                $this->branchCache[(int) $r['id']] = $r;
            }
        }
        return $activeOnly ? array_filter($this->branchCache, static fn ($b) => (int) $b['active'] === 1) : $this->branchCache;
    }

    public function branch(int $id): ?array
    {
        return $this->branches()[$id] ?? null;
    }

    public function mainId(): int
    {
        foreach ($this->branches() as $b) {
            if ((int) $b['is_main'] === 1) {
                return (int) $b['id'];
            }
        }
        return (int) array_key_first($this->branches());
    }

    public function flushBranches(): void
    {
        $this->branchCache = null;
    }

    /** Central staff see every branch: admins and anyone given "all branches". */
    public function central(): bool
    {
        if (app()->access->full($this->p->role)) {
            return true;
        }
        return (int) $this->db->val('SELECT COUNT(*) FROM sc_user_branches WHERE tenant_id = ? AND user_id = ? AND branch_id = 0', [$this->t, (int) $this->p->user['id']]) > 0;
    }

    /** Branch ids this user may open. Staff without an assignment get the main branch. @return list<int> */
    public function allowed(): array
    {
        $active = array_keys($this->branches(true));
        if ($this->central()) {
            return $active;
        }
        $ids = array_map('intval', array_column($this->db->all('SELECT branch_id FROM sc_user_branches WHERE tenant_id = ? AND user_id = ?', [$this->t, (int) $this->p->user['id']]), 'branch_id'));
        $ids = array_values(array_intersect($ids, $active));
        return $ids ?: [$this->mainId()];
    }

    public function may(int $branch): bool
    {
        return in_array($branch, $this->allowed(), true);
    }

    /** The branch chosen in the switcher. 0 means every branch the user may open (consolidated). */
    public function current(): int
    {
        $c = (int) ($_SESSION['lab_branch'][$this->t] ?? -1);
        $al = $this->allowed();
        if ($c === 0 && count($al) > 1) {
            return 0;
        }
        if ($c > 0 && $this->may($c)) {
            return $c;
        }
        return count($al) > 1 && $this->central() ? 0 : $al[0];
    }

    public function switchTo(int $branch): void
    {
        if ($branch === 0 ? count($this->allowed()) > 1 : $this->may($branch)) {
            $_SESSION['lab_branch'][$this->t] = $branch;
            $this->log('branch', $branch, 'viewed', null, ['switch' => $branch === 0 ? 'all branches' : (string) ($this->branch($branch)['name'] ?? '')], null, $branch);
        }
    }

    /** Ids the current switcher choice covers. @return list<int> */
    public function viewIds(): array
    {
        $c = $this->current();
        return $c === 0 ? $this->allowed() : [$c];
    }

    /** SQL condition limiting a branch column to what this user may see now. NULL counts as the main branch. */
    /** @var ?list<int> Branch ids to report on, set by branch by branch reports. */
    public ?array $force = null;

    public function scope(string $col): string
    {
        if ($this->force !== null) {
            return 'COALESCE(' . $col . ', ' . $this->mainId() . ') IN (' . implode(',', array_map('intval', $this->force ?: [0])) . ')';
        }
        if ($this->central() && $this->current() === 0) {
            return '1 = 1';
        }
        return 'COALESCE(' . $col . ', ' . $this->mainId() . ') IN (' . implode(',', array_map('intval', $this->viewIds() ?: [0])) . ')';
    }

    /** Branch to write a new record into: the form value when allowed, else the current one. */
    public function writeBranch(?int $asked = null): int
    {
        $b = $asked ?? (int) ($_POST['branch_id'] ?? 0);
        if ($b > 0 && $this->may($b)) {
            return $b;
        }
        $c = $this->current();
        return $c > 0 ? $c : ($this->may($this->mainId()) ? $this->mainId() : $this->allowed()[0]);
    }

    public function branchName(?int $id): string
    {
        return (string) ($this->branch((int) ($id ?: $this->mainId()))['name'] ?? '');
    }

    // ------------------------------------------------------------------ permissions

    public function can(string $perm, string $action = 'view'): bool
    {
        return $this->p->canDo($perm, $action);
    }

    public function need(string $perm, string $action = 'view'): void
    {
        if (!$this->can($perm, $action)) {
            app()->error(403, 'Your access does not include this. Ask your administrator to give you the lab permission.', 'No access');
        }
    }

    /** Branch switcher put above lab pages. */
    public function switcher(): string
    {
        $br = array_intersect_key($this->branches(true), array_flip($this->allowed()));
        if (count($br) < 1) {
            return '';
        }
        $cur = $this->current();
        $h = '<form class="d-queue-toolbar" method="post" action="' . e(url('portal/lab/branch')) . '" data-branch-switch>' . csrf_field() . '<label class="d-field" style="margin:0"><span class="d-help">Branch</span><select name="branch" onchange="this.form.submit()">';
        if (count($br) > 1) {
            $h .= '<option value="0"' . ($cur === 0 ? ' selected' : '') . '>All branches (consolidated)</option>';
        }
        foreach ($br as $b) {
            $h .= '<option value="' . (int) $b['id'] . '"' . ($cur === (int) $b['id'] ? ' selected' : '') . '>' . e((string) $b['name']) . '</option>';
        }
        return $h . '</select></label><noscript><button class="d-btn d-btn-soft" type="submit">Switch</button></noscript></form>';
    }

    // ------------------------------------------------------------------ record history

    public function log(string $entity, int $id, string $action, mixed $before = null, mixed $after = null, ?string $reason = null, ?int $branch = null): void
    {
        $this->db->insert('sc_record_history', [
            'tenant_id' => $this->t, 'branch_id' => (int) ($branch ?? $this->current()), 'entity' => $entity, 'entity_id' => $id, 'action' => $action,
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE), 'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null, 'user_id' => (int) $this->p->user['id'], 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Only the fields that changed. @return array{0:array<string,mixed>,1:array<string,mixed>} */
    public static function diff(array $old, array $new): array
    {
        $b = [];
        $a = [];
        foreach ($new as $k => $v) {
            if ((string) ($old[$k] ?? '') !== (string) $v) {
                $b[$k] = $old[$k] ?? null;
                $a[$k] = $v;
            }
        }
        return [$b, $a];
    }

    // ------------------------------------------------------------------ patients (the clinic's own patients table)

    public function mrPrefix(): string
    {
        $p = $this->setting('mr_prefix');
        if ($p === '') {
            $p = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->p->vendor['name']) ?: 'MR', 0, 3));
        }
        return $p;
    }

    public function nextMr(): string
    {
        do {
            $mr = $this->mrPrefix() . '-' . str_pad((string) $this->seq('lab_mr'), 6, '0', STR_PAD_LEFT);
        } while ((int) $this->db->val('SELECT COUNT(*) FROM patients WHERE patient_unique_id = ?', [$mr]) > 0);
        return $mr;
    }

    /** @return ?array<string,mixed> */
    public function patient(int $id): ?array
    {
        return $this->db->one("SELECT p.id, p.user_id, p.patient_unique_id mr, p.guardian, p.age_value, p.age_unit, p.cnic, p.address, p.whatsapp, p.reg_notes, p.branch_id, p.allergies, p.conditions, u.first_name, u.last_name, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) name, u.email, u.phone, u.gender, u.dob, u.status, u.city
            FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.tenant_id = ? AND u.deleted_at IS NULL", [$id, $this->t]);
    }

    public static function sexLetter(mixed $g): string
    {
        return [0 => 'M', 1 => 'F'][(int) $g] ?? 'A';
    }

    public static function sexName(mixed $g): string
    {
        return [0 => 'Male', 1 => 'Female', 2 => 'Other'][(int) $g] ?? '';
    }

    /** Age in years (decimal) from date of birth, or the age the patient was registered with. */
    public static function ageYears(array $pt): ?float
    {
        if (!empty($pt['dob'])) {
            return round((time() - strtotime((string) $pt['dob'])) / 31557600, 2);
        }
        if ($pt['age_value'] !== null && $pt['age_value'] !== '') {
            $v = (float) $pt['age_value'];
            return match ((string) ($pt['age_unit'] ?? 'years')) { 'months' => round($v / 12, 2), 'days' => round($v / 365, 2), default => $v };
        }
        return null;
    }

    public static function ageText(array $pt): string
    {
        if ($pt['age_value'] !== null && $pt['age_value'] !== '' && empty($pt['dob'])) {
            return rtrim(rtrim(number_format((float) $pt['age_value'], 1, '.', ''), '0'), '.') . ' ' . ($pt['age_unit'] ?: 'years');
        }
        $y = self::ageYears($pt);
        return $y === null ? '' : ($y < 1 ? (int) round($y * 12) . ' months' : (int) $y . ' years');
    }

    /** Registers a patient (user with the Patient role, patients row, MR number). @return int|string patients.id or an error text */
    public function registerPatient(array $d, int $branch): int|string
    {
        $first = trim((string) ($d['first_name'] ?? ''));
        if ($first === '') {
            return 'Please enter the patient name.';
        }
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'That email address does not look right.';
        }
        if ($email !== '' && (int) $this->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            return 'That email already has an account. Search for the patient instead.';
        }
        if ($email === '') {
            $email = 'pt' . bin2hex(random_bytes(5)) . '@no-login.invalid';
        }
        $role = app()->access->roleId('Patient', $this->t) ?? 0;
        $now = date('Y-m-d H:i:s');
        $this->db->begin();
        try {
            $uid = $this->db->insert('users', [
                'department_id' => $role, 'first_name' => mb_substr($first, 0, 160), 'last_name' => mb_substr(trim((string) ($d['last_name'] ?? '')), 0, 191), 'email' => $email,
                'password' => password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT), 'phone' => mb_substr(trim((string) ($d['phone'] ?? '')), 0, 40) ?: null,
                'gender' => max(0, min(2, (int) ($d['gender'] ?? 0))), 'dob' => ($d['dob'] ?? '') !== '' ? $d['dob'] : null, 'status' => 1, 'language' => 'en',
                'hospital_name' => (string) $this->p->vendor['name'], 'tenant_id' => $this->t, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->insert('model_has_roles', ['role_id' => $role, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
            $pid = $this->db->insert('patients', ['user_id' => $uid, 'tenant_id' => $this->t, 'patient_unique_id' => $this->nextMr(), 'created_at' => $now, 'updated_at' => $now, 'branch_id' => $branch] + $this->extraCols($d));
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->log('patient', $pid, 'created', null, ['name' => $first, 'mr' => $this->db->val('SELECT patient_unique_id FROM patients WHERE id = ?', [$pid])], null, $branch);
        return $pid;
    }

    /** @return array<string,mixed> the lab columns of the patients table */
    public function extraCols(array $d): array
    {
        $o = [];
        foreach (['guardian' => 160, 'cnic' => 40, 'address' => 255, 'whatsapp' => 40, 'reg_notes' => 500] as $k => $max) {
            if (array_key_exists($k, $d)) {
                $o[$k] = mb_substr(trim((string) $d[$k]), 0, $max) ?: null;
            }
        }
        if (array_key_exists('age_value', $d)) {
            $o['age_value'] = $d['age_value'] === '' || $d['age_value'] === null ? null : max(0, min(150, (float) $d['age_value']));
        }
        if (array_key_exists('age_unit', $d)) {
            $o['age_unit'] = in_array((string) $d['age_unit'], ['years', 'months', 'days'], true) ? (string) $d['age_unit'] : 'years';
        }
        return $o;
    }

    /** Patient contact fix before sharing, with the old values kept in the history. */
    public function updateContact(int $pid, array $d): void
    {
        $old = $this->patient($pid);
        if ($old === null) {
            return;
        }
        $new = [];
        if (isset($d['phone'])) {
            $new['phone'] = mb_substr(trim((string) $d['phone']), 0, 40) ?: null;
            $this->db->update('users', ['phone' => $new['phone'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $old['user_id']]);
        }
        if (isset($d['whatsapp'])) {
            $new['whatsapp'] = mb_substr(trim((string) $d['whatsapp']), 0, 40) ?: null;
            $this->db->update('patients', ['whatsapp' => $new['whatsapp']], 'id = ?', [$pid]);
        }
        $em = strtolower(trim((string) ($d['email'] ?? '')));
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && $em !== (string) $old['email'] && (int) $this->db->val('SELECT COUNT(*) FROM users WHERE email = ?', [$em]) === 0) {
            $new['email'] = $em;
            $this->db->update('users', ['email' => $em, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $old['user_id']]);
        }
        [$b, $a] = self::diff($old, $new);
        if ($b || $a) {
            $this->log('patient', $pid, 'edited', $b, $a, 'Contact corrected before sharing', (int) ($old['branch_id'] ?: $this->mainId()));
        }
    }

    /** The address to email a patient: the real one only. */
    public static function realEmail(array $pt): string
    {
        return str_ends_with((string) $pt['email'], '@no-login.invalid') ? '' : (string) $pt['email'];
    }

    /** Digits only phone for wa.me. Numbers starting 0 become 92 (Pakistan). */
    public static function waNumber(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = '92' . substr($d, 1);
        }
        return $d;
    }

    // ------------------------------------------------------------------ catalogue and reference ranges

    /** Price of a test in a branch. @return array{price:float,available:bool} */
    public function priceIn(array $test, int $branch): array
    {
        $o = $this->db->one('SELECT price, available FROM sc_lab_branch_tests WHERE tenant_id = ? AND branch_id = ? AND test_id = ?', [$this->t, $branch, (int) $test['id']]);
        return ['price' => $o && $o['price'] !== null ? (float) $o['price'] : $this->toBranch((float) $test['price'], $branch), 'available' => $o ? (int) $o['available'] === 1 : true];
    }

    /** Active tests for a branch with the branch price. @return list<array<string,mixed>> */
    public function testsFor(int $branch): array
    {
        $out = [];
        foreach ($this->db->all('SELECT t.*, d.name dept FROM sc_lab_tests t LEFT JOIN sc_lab_departments d ON d.id = t.dept_id WHERE t.tenant_id = ? AND t.active = 1 ORDER BY d.sort_order, t.name', [$this->t]) as $r) {
            $pr = $this->priceIn($r, $branch);
            if ($pr['available']) {
                $out[] = $r + ['eff_price' => $pr['price']];
            }
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function params(int $testId): array
    {
        return $this->db->all('SELECT * FROM sc_lab_test_params WHERE tenant_id = ? AND test_id = ? AND active = 1 ORDER BY sort_order, id', [$this->t, $testId]);
    }

    /** Reference range for a parameter and patient: the most specific row. @return ?array<string,mixed> */
    public function range(int $paramId, ?string $sex, ?float $ageYears): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($this->db->all('SELECT * FROM sc_lab_ref_ranges WHERE tenant_id = ? AND param_id = ?', [$this->t, $paramId]) as $r) {
            if ($r['sex'] !== 'A' && ($sex === null || $r['sex'] !== $sex)) {
                continue;
            }
            if ($ageYears !== null && ($ageYears < (float) $r['age_min'] || $ageYears > (float) $r['age_max'])) {
                continue;
            }
            $score = ($r['sex'] !== 'A' ? 2 : 0) + ((float) $r['age_min'] > 0 || (float) $r['age_max'] < 150 ? 1 : 0);
            if ($score > $bestScore) {
                $best = $r;
                $bestScore = $score;
            }
        }
        return $best;
    }

    public static function rangeText(?array $r): string
    {
        if ($r === null) {
            return '';
        }
        if (!empty($r['text'])) {
            return (string) $r['text'];
        }
        $n = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        if ($r['low'] !== null && $r['high'] !== null) {
            return $n($r['low']) . ' to ' . $n($r['high']);
        }
        return $r['low'] !== null ? 'above ' . $n($r['low']) : ($r['high'] !== null ? 'below ' . $n($r['high']) : '');
    }

    /** N, L or H for a value against a range, empty when it cannot be judged. */
    public static function flag(string $value, ?array $r): string
    {
        $v = trim($value);
        if ($v === '' || $r === null || !is_numeric($v)) {
            return '';
        }
        $x = (float) $v;
        if ($r['low'] !== null && $x < (float) $r['low']) {
            return 'L';
        }
        if ($r['high'] !== null && $x > (float) $r['high']) {
            return 'H';
        }
        return ($r['low'] !== null || $r['high'] !== null) ? 'N' : '';
    }

    // ------------------------------------------------------------------ referrers and commission

    /** Makes sure every doctor on the clinic team has a referrer row. */
    public function syncDoctors(): void
    {
        $rows = $this->db->all("SELECT d.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, d.specialist, u.phone, u.email FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? AND u.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM sc_referrers r WHERE r.tenant_id = d.tenant_id AND r.doctor_id = d.id)", [$this->t]);
        foreach ($rows as $r) {
            $this->db->insert('sc_referrers', ['tenant_id' => $this->t, 'kind' => 'doctor', 'doctor_id' => (int) $r['id'], 'name' => $r['n'], 'speciality' => $r['specialist'], 'org' => (string) $this->p->vendor['name'], 'phone' => $r['phone'], 'email' => $r['email'], 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function referrers(?string $kind = null, bool $activeOnly = true): array
    {
        $this->syncDoctors();
        return $this->db->all('SELECT * FROM sc_referrers WHERE tenant_id = ?' . ($kind ? ' AND kind = ' . $this->db->quote($kind) : '') . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY kind, name', [$this->t]);
    }

    /** Rule for one item: a test rule, else a department rule, else the default per test rule. @return ?array<string,mixed> */
    private function itemRule(array $rules, array $item): ?array
    {
        foreach ($rules as $r) {
            if ($r['scope'] === 'test' && (int) $r['test_id'] === (int) $item['test_id']) {
                return $r;
            }
        }
        foreach ($rules as $r) {
            if ($r['scope'] === 'dept' && $item['dept_id'] && (int) $r['dept_id'] === (int) $item['dept_id']) {
                return $r;
            }
        }
        foreach ($rules as $r) {
            if ($r['scope'] === 'default' && $r['basis'] === 'test') {
                return $r;
            }
        }
        return null;
    }

    /** Commission an order should carry now. Cancelled orders and refunded money take it down. @return array<string,array{amount:float,base:float,rule:string}> */
    public function commissionWanted(array $order): array
    {
        $out = [];
        if ($order['status'] === 'cancelled' || $order['referral_type'] === 'none' || empty($order['referrer_id'])) {
            return $out;
        }
        $rules = $this->db->all('SELECT * FROM sc_comm_rules WHERE tenant_id = ? AND referrer_id = ? AND active = 1', [$this->t, (int) $order['referrer_id']]);
        if (!$rules) {
            return $out;
        }
        $items = $this->db->all("SELECT * FROM sc_lab_order_items WHERE tenant_id = ? AND order_id = ? AND status <> 'cancelled'", [$this->t, (int) $order['id']]);
        $factor = $this->refundFactor($order);
        $patientRule = null;
        foreach ($rules as $r) {
            if ($r['scope'] === 'default' && $r['basis'] === 'patient') {
                $patientRule = $r;
            }
        }
        $left = 0.0;
        $anyLeft = false;
        foreach ($items as $it) {
            $r = $this->itemRule($rules, $it);
            $net = (float) $it['net'];
            if ($r === null || ($r['scope'] === 'default' && $patientRule !== null)) {
                if ($patientRule !== null) {
                    $left += $net;
                    $anyLeft = true;
                }
                continue;
            }
            $amt = $r['vtype'] === 'pct' ? $net * (float) $r['value'] / 100 : $this->toBranch((float) $r['value'], (int) $order['branch_id']);
            $out['i' . $it['id']] = ['amount' => round($amt * $factor, 2), 'base' => $net, 'rule' => ($r['vtype'] === 'pct' ? rtrim(rtrim((string) $r['value'], '0'), '.') . '% per test' : 'fixed per test') . ' (' . $r['scope'] . ')'];
        }
        if ($patientRule !== null && $anyLeft) {
            $amt = $patientRule['vtype'] === 'pct' ? $left * (float) $patientRule['value'] / 100 : $this->toBranch((float) $patientRule['value'], (int) $order['branch_id']);
            $out['o'] = ['amount' => round($amt * $factor, 2), 'base' => $left, 'rule' => $patientRule['vtype'] === 'pct' ? rtrim(rtrim((string) $patientRule['value'], '0'), '.') . '% of the patient bill' : 'fixed per patient'];
        }
        return $out;
    }

    /** Share of the bill that was not refunded: 1 when nothing was refunded. */
    public function refundFactor(array $order): float
    {
        if (empty($order['invoice_id'])) {
            return 1.0;
        }
        $tot = (float) $this->db->val('SELECT total FROM sc_invoices WHERE id = ? AND tenant_id = ?', [(int) $order['invoice_id'], $this->t]);
        return $tot > 0 ? max(0.0, 1 - $this->refunded((int) $order['invoice_id']) / $tot) : 1.0;
    }

    public function refunded(int $invoiceId): float
    {
        return (float) $this->db->val("SELECT COALESCE(SUM(total), 0) FROM sc_invoices WHERE tenant_id = ? AND related_id = ? AND doc_type = 'credit_note' AND status <> 'void'", [$this->t, $invoiceId]);
    }

    /** Brings the commission rows of an order in line with the rules, refunds, cancellations and discounts. Every change is a new row, so the history stays. */
    public function recalcCommission(int $orderId, string $why = ''): void
    {
        $o = $this->db->one('SELECT * FROM sc_lab_orders WHERE id = ? AND tenant_id = ?', [$orderId, $this->t]);
        if ($o === null) {
            return;
        }
        $want = $this->commissionWanted($o);
        $have = [];
        foreach ($this->db->all('SELECT referrer_id, item_id, SUM(amount) s FROM sc_commissions WHERE tenant_id = ? AND order_id = ? GROUP BY referrer_id, item_id', [$this->t, $orderId]) as $r) {
            $have[(int) $r['referrer_id'] . ':' . ($r['item_id'] === null ? 'o' : 'i' . $r['item_id'])] = (float) $r['s'];
        }
        $keys = array_map(static fn ($k) => (int) $o['referrer_id'] . ':' . $k, array_keys($want));
        foreach (array_unique(array_merge($keys, array_keys($have))) as $k) {
            [$rid, $kk] = explode(':', $k, 2);
            $w = $want[$kk] ?? null;
            $delta = round(($w ? (float) $w['amount'] : 0.0) - ($have[$k] ?? 0.0), 2);
            if (abs($delta) < 0.005) {
                continue;
            }
            $first = !isset($have[$k]);
            $this->db->insert('sc_commissions', [
                'tenant_id' => $this->t, 'branch_id' => (int) $o['branch_id'], 'referrer_id' => (int) $rid, 'order_id' => $orderId, 'item_id' => $kk === 'o' ? null : (int) substr($kk, 1),
                'kind' => $first ? 'accrual' : 'adjustment', 'amount' => $delta, 'base' => $w ? (float) $w['base'] : 0, 'rule' => $w['rule'] ?? 'removed', 'note' => $first ? null : ($why ?: 'Order changed'), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** Totals for one referrer: referred patients, orders and tests, earned, paid, outstanding. @return array<string,float|int> */
    public function commissionTotals(int $referrerId, ?string $from = null, ?string $to = null): array
    {
        $q = fn (string $s) => $this->db->quote($s);
        $cur = $this->reportCurrency();
        $cw = ($from && $to) ? ' AND DATE(created_at) BETWEEN ' . $q($from) . ' AND ' . $q($to) : '';
        $lw = ($from && $to) ? ' AND entry_date BETWEEN ' . $q($from) . ' AND ' . $q($to) : '';
        $ow = ($from && $to) ? ' AND DATE(o.order_date) BETWEEN ' . $q($from) . ' AND ' . $q($to) : '';
        $earned = (float) $this->db->val('SELECT COALESCE(SUM(' . Currency::sql('amount', 'currency', $cur) . '), 0) FROM sc_commissions WHERE tenant_id = ? AND referrer_id = ? AND ' . $this->scope('branch_id') . $cw, [$this->t, $referrerId]);
        $paid = (float) $this->db->val("SELECT COALESCE(SUM(" . Currency::sql('amount', 'currency', $cur) . "), 0) FROM sc_ledger WHERE tenant_id = ? AND source = 'commission' AND referrer_id = ? AND status = 'paid' AND deleted_at IS NULL AND " . $this->scope('branch_id') . $lw, [$this->t, $referrerId]);
        $base = "FROM sc_lab_orders o WHERE o.tenant_id = ? AND o.referrer_id = ? AND o.status <> 'cancelled' AND " . $this->scope('o.branch_id') . $ow;
        $pat = (int) $this->db->val('SELECT COUNT(DISTINCT o.patient_id) ' . $base, [$this->t, $referrerId]);
        $ord = (int) $this->db->val('SELECT COUNT(*) ' . $base, [$this->t, $referrerId]);
        $tests = (int) $this->db->val("SELECT COUNT(*) FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id WHERE o.tenant_id = ? AND o.referrer_id = ? AND o.status <> 'cancelled' AND i.status <> 'cancelled' AND " . $this->scope('o.branch_id') . $ow, [$this->t, $referrerId]);
        return ['patients' => $pat, 'orders' => $ord, 'tests' => $tests, 'earned' => round($earned, 2), 'paid' => round($paid, 2), 'outstanding' => round($earned - $paid, 2)];
    }

    // ------------------------------------------------------------------ orders: booking, billing, samples

    public function order(int $id): ?array
    {
        return $this->db->one('SELECT * FROM sc_lab_orders WHERE id = ? AND tenant_id = ?', [$id, $this->t]);
    }

    /** Order the user may open (branch isolation). */
    public function orderFor(int $id): ?array
    {
        $o = $this->order($id);
        return $o !== null && $this->may((int) $o['branch_id']) ? $o : null;
    }

    /** @return list<array<string,mixed>> */
    public function items(int $orderId): array
    {
        return $this->db->all('SELECT * FROM sc_lab_order_items WHERE tenant_id = ? AND order_id = ? ORDER BY id', [$this->t, $orderId]);
    }

    /**
     * Creates an order with its bill (a normal sc_invoices test invoice), samples and commission.
     * @param array<string,mixed> $in
     * @return int|string order id, or an error text
     */
    public function createOrder(array $in): int|string
    {
        $branch = $this->writeBranch((int) ($in['branch_id'] ?? 0));
        $pt = $this->patient((int) ($in['patient_id'] ?? 0));
        if ($pt === null) {
            return 'Choose a patient or register a new one.';
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['test_ids'] ?? [])))));
        if (!$ids) {
            return 'Add at least one test.';
        }
        $tests = [];
        foreach ($ids as $id) {
            $t = $this->db->one('SELECT t.*, d.name dept FROM sc_lab_tests t LEFT JOIN sc_lab_departments d ON d.id = t.dept_id WHERE t.id = ? AND t.tenant_id = ? AND t.active = 1', [$id, $this->t]);
            if ($t === null) {
                return 'One of the tests is no longer in the catalogue.';
            }
            $pr = $this->priceIn($t, $branch);
            if (!$pr['available']) {
                return $t['name'] . ' is not available in this branch.';
            }
            $tests[] = $t + ['eff_price' => $pr['price']];
        }
        $refType = in_array((string) ($in['referral_type'] ?? 'none'), ['none', 'doctor', 'partner'], true) ? (string) $in['referral_type'] : 'none';
        $refId = $refType === 'none' ? null : (int) ($in['referrer_id'] ?? 0);
        $ref = null;
        if ($refType !== 'none') {
            $ref = $refId ? $this->db->one('SELECT * FROM sc_referrers WHERE id = ? AND tenant_id = ? AND active = 1 AND kind = ?', [$refId, $this->t, $refType]) : null;
            if ($ref === null) {
                return 'Choose the referring ' . ($refType === 'doctor' ? 'doctor' : 'partner laboratory') . ', or set the referral to none.';
            }
        }
        $home = ($in['collection'] ?? 'lab') === 'home';
        $homeCharge = $home ? max(0.0, round((float) ($in['home_charge'] ?? 0), 2)) : 0.0;
        $lines = [];
        foreach ($tests as $t) {
            $lines[] = ['code' => $t['code'], 'item_name' => $t['name'], 'category' => $t['dept'] ?: 'Laboratory', 'qty' => 1, 'price' => $t['eff_price']];
        }
        if ($homeCharge > 0) {
            $lines[] = ['code' => 'HOME', 'item_name' => 'Home collection charge', 'category' => 'Home collection', 'qty' => 1, 'price' => $homeCharge];
        }
        $subtests = (float) array_sum(array_column($tests, 'eff_price'));
        $discount = max(0.0, min($subtests, round((float) ($in['discount'] ?? 0), 2)));
        $total = round($subtests - $discount + $homeCharge, 2);
        $paid = max(0.0, min($total, round((float) ($in['paid_now'] ?? 0), 2)));
        $b = new Billing($this->db);
        $this->db->begin();
        try {
            $orderNo = $this->docNo('ORD', $branch);
            $iid = $b->create($this->t, 'vendor', 'test_invoice', [
                'party_name' => $pt['name'], 'party_phone' => $pt['phone'], 'party_address' => $pt['address'], 'patient_id' => (int) $pt['id'], 'invoice_date' => date('Y-m-d'),
                'discount' => $discount, 'paid_now' => $paid, 'payment_method' => (string) ($in['method'] ?? 'Cash'), 'payment_ref' => (string) ($in['reference'] ?? ''),
                'x_referred_by' => $ref ? (string) $ref['name'] : '', 'x_sample_id' => $orderNo,
            ], $lines, (int) $this->p->user['id'], $this->branchCurrency($branch));
            $this->db->run('UPDATE sc_invoices SET branch_id = ? WHERE id = ?', [$branch, $iid]);
            $payId = (int) $this->db->val('SELECT id FROM sc_invoice_payments WHERE invoice_id = ? ORDER BY id LIMIT 1', [$iid]);
            if ($payId) {
                $this->db->run('UPDATE sc_invoice_payments SET branch_id = ?, receipt_no = ? WHERE id = ?', [$branch, $this->docNo('RCT', $branch), $payId]);
            }
            $invNo = (string) $this->db->val('SELECT invoice_no FROM sc_invoices WHERE id = ?', [$iid]);
            $now = date('Y-m-d H:i:s');
            $oid = $this->db->insert('sc_lab_orders', [
                'tenant_id' => $this->t, 'branch_id' => $branch, 'order_no' => $orderNo, 'invoice_id' => $iid, 'invoice_no' => $invNo, 'patient_id' => (int) $pt['id'], 'order_date' => $now,
                'booked_by' => (int) $this->p->user['id'], 'referral_type' => $refType, 'referrer_id' => $refId, 'collection' => $home ? 'home' : 'lab', 'home_id' => !empty($in['home_id']) ? (int) $in['home_id'] : null,
                'token_id' => !empty($in['token_id']) ? (int) $in['token_id'] : null, 'rx_id' => !empty($in['rx_id']) ? (int) $in['rx_id'] : null, 'ordered_by_doctor' => !empty($in['ordered_by_doctor']) ? (int) $in['ordered_by_doctor'] : null,
                'subtotal' => $subtests, 'discount' => $discount, 'discount_reason' => mb_substr(trim((string) ($in['discount_reason'] ?? '')), 0, 190) ?: null, 'home_charge' => $homeCharge,
                'total' => $total, 'status' => 'booked', 'clinical_note' => mb_substr(trim((string) ($in['clinical_note'] ?? '')), 0, 500) ?: null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->addItems($oid, $branch, $tests, $discount, $subtests, $home);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Could not save the booking: ' . $e->getMessage();
        }
        $this->recalcCommission($oid, 'Booked');
        $this->log('order', $oid, 'created', null, ['order_no' => $orderNo, 'tests' => count($tests), 'total' => $total], null, $branch);
        return $oid;
    }

    /** Adds test lines to an order with their samples (one sample per specimen type). @param list<array<string,mixed>> $tests */
    public function addItems(int $oid, int $branch, array $tests, float $discount, float $subtotal, bool $home): void
    {
        $samples = [];
        foreach ($this->db->all("SELECT id, specimen FROM sc_lab_samples WHERE tenant_id = ? AND order_id = ? AND status <> 'rejected'", [$this->t, $oid]) as $s) {
            $samples[(string) $s['specimen']] = (int) $s['id'];
        }
        foreach ($tests as $t) {
            $spec = (string) ($t['specimen'] ?: 'Blood');
            if (!isset($samples[$spec])) {
                $samples[$spec] = $this->db->insert('sc_lab_samples', ['tenant_id' => $this->t, 'branch_id' => $branch, 'order_id' => $oid, 'barcode' => $this->docNo('SMP', $branch, 7), 'specimen' => $spec, 'collection' => $home ? 'home' : 'lab', 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);
            }
            $price = (float) $t['eff_price'];
            $dis = $subtotal > 0 ? round($discount * $price / $subtotal, 2) : 0.0;
            $this->db->insert('sc_lab_order_items', ['tenant_id' => $this->t, 'branch_id' => $branch, 'order_id' => $oid, 'test_id' => (int) $t['id'], 'test_name' => $t['name'], 'dept_id' => $t['dept_id'], 'dept_name' => $t['dept'] ?? null, 'specimen' => $spec, 'price' => $price, 'discount' => $dis, 'net' => $price - $dis, 'sample_id' => $samples[$spec], 'status' => 'pending', 'report_status' => 'none']);
        }
    }

    /** Re-spreads the discount over the active items, rewrites the bill lines and totals, and refreshes commission. */
    public function resync(int $oid, string $why = ''): void
    {
        $o = $this->order($oid);
        if ($o === null) {
            return;
        }
        $items = $this->db->all("SELECT * FROM sc_lab_order_items WHERE tenant_id = ? AND order_id = ? AND status <> 'cancelled' ORDER BY id", [$this->t, $oid]);
        $sub = (float) array_sum(array_column($items, 'price'));
        $disc = max(0.0, min($sub, (float) $o['discount']));
        foreach ($items as $it) {
            $d = $sub > 0 ? round($disc * (float) $it['price'] / $sub, 2) : 0.0;
            $this->db->run('UPDATE sc_lab_order_items SET discount = ?, net = ? WHERE id = ?', [$d, (float) $it['price'] - $d, (int) $it['id']]);
        }
        $home = (float) $o['home_charge'];
        $total = round($sub - $disc + $home, 2);
        $this->db->run('UPDATE sc_lab_orders SET subtotal = ?, discount = ?, total = ?, updated_at = NOW() WHERE id = ?', [$sub, $disc, $total, $oid]);
        if ($o['invoice_id']) {
            $iid = (int) $o['invoice_id'];
            $this->db->run('DELETE FROM sc_invoice_items WHERE invoice_id = ?', [$iid]);
            $pos = 0;
            $now = date('Y-m-d H:i:s');
            foreach ($items as $it) {
                $this->db->insert('sc_invoice_items', ['invoice_id' => $iid, 'position' => $pos++, 'item_name' => $it['test_name'], 'category' => $it['dept_name'] ?: 'Laboratory', 'qty' => 1, 'price' => $it['price'], 'tax_pct' => 0, 'amount' => $it['price'], 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($home > 0) {
                $this->db->insert('sc_invoice_items', ['invoice_id' => $iid, 'position' => $pos, 'code' => 'HOME', 'item_name' => 'Home collection charge', 'category' => 'Home collection', 'qty' => 1, 'price' => $home, 'tax_pct' => 0, 'amount' => $home, 'created_at' => $now, 'updated_at' => $now]);
            }
            $this->db->run('UPDATE sc_invoices SET subtotal = ?, discount = ?, total = ?, updated_at = NOW() WHERE id = ?', [$sub + $home, $disc, $total, $iid]);
            (new Billing($this->db))->recalc($iid);
        }
        $this->recalcCommission($oid, $why ?: 'Order changed');
    }

    /** Money position of an order from its bill. @return array<string,float> */
    public function money(array $o): array
    {
        $inv = $o['invoice_id'] ? $this->db->one('SELECT total, paid, balance FROM sc_invoices WHERE id = ?', [(int) $o['invoice_id']]) : null;
        $ref = $o['invoice_id'] ? $this->refunded((int) $o['invoice_id']) : 0.0;
        $paid = $o['invoice_id'] ? (float) $this->db->val("SELECT COALESCE(SUM(amount), 0) FROM sc_invoice_payments WHERE invoice_id = ? AND (note IS NULL OR note NOT LIKE 'Credit note%')", [(int) $o['invoice_id']]) : 0.0;
        $total = (float) ($o['total'] ?? 0);
        return ['cur' => (string) (($o['currency'] ?? '') ?: $this->branchCurrency((int) ($o['branch_id'] ?? 0))), 'total' => $total, 'paid' => $paid, 'refunded' => $ref, 'balance' => $o['status'] === 'cancelled' ? 0.0 : (float) ($inv['balance'] ?? $total), 'refund_due' => max(0.0, round($paid - $ref - $total, 2))];
    }

    /** Receipt number of the latest payment on an order, if any. */
    public function lastReceipt(array $o): ?array
    {
        return $o['invoice_id'] ? $this->db->one("SELECT * FROM sc_invoice_payments WHERE invoice_id = ? AND (note IS NULL OR note NOT LIKE 'Credit note%') ORDER BY id DESC LIMIT 1", [(int) $o['invoice_id']]) : null;
    }

    /** Records a payment on the order bill (the normal invoice payment) and stamps receipt number and branch. @return ?string error */
    public function pay(array $o, float $amount, string $method, ?string $ref): ?string
    {
        if ($o['status'] === 'cancelled') {
            return 'This order is cancelled.';
        }
        $err = (new Billing($this->db))->addPayment($this->t, (int) $o['invoice_id'], $amount, $method, $ref, (int) $this->p->user['id']);
        if ($err !== null) {
            return $err;
        }
        $pid = (int) $this->db->val('SELECT id FROM sc_invoice_payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1', [(int) $o['invoice_id']]);
        $this->db->run('UPDATE sc_invoice_payments SET branch_id = ?, receipt_no = ? WHERE id = ?', [(int) $o['branch_id'], $this->docNo('RCT', (int) $o['branch_id']), $pid]);
        $this->log('order', (int) $o['id'], 'edited', null, ['payment' => $amount, 'method' => $method], 'Payment received', (int) $o['branch_id']);
        return null;
    }

    /** Refund through a credit note on the bill. Commission follows. @return ?string error */
    public function refund(array $o, float $amount, string $method, string $reason): ?string
    {
        $m = $this->money($o);
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > $m['paid'] - $m['refunded'] + 0.001) {
            return 'Refund must be above zero and not more than what was received and not yet refunded (' . money($m['paid'] - $m['refunded']) . ').';
        }
        if (trim($reason) === '') {
            return 'Please write the reason for the refund.';
        }
        try {
            $cid = (new Billing($this->db))->create($this->t, 'vendor', 'credit_note', ['party_name' => (string) $this->patient((int) $o['patient_id'])['name'], 'patient_id' => (int) $o['patient_id'], 'invoice_date' => date('Y-m-d'), 'related_id' => (int) $o['invoice_id'], 'payment_method' => $method, 'note' => 'Refund: ' . $reason, 'reference' => $o['invoice_no']], [['item_name' => 'Refund on ' . $o['order_no'], 'category' => 'Refund', 'qty' => 1, 'price' => $amount]], (int) $this->p->user['id'], (string) ($o['currency'] ?: $this->branchCurrency((int) $o['branch_id'])));
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->db->run('UPDATE sc_invoices SET branch_id = ? WHERE id = ?', [(int) $o['branch_id'], $cid]);
        $this->db->run("UPDATE sc_invoice_payments SET branch_id = ? WHERE invoice_id = ?", [(int) $o['branch_id'], $cid]);
        $this->log('order', (int) $o['id'], 'edited', null, ['refund' => $amount], $reason, (int) $o['branch_id']);
        $this->recalcCommission((int) $o['id'], 'Refund of ' . money($amount));
        return null;
    }

    /** Cancels an order with a reason: refunds what was received, closes the bill, drops commission. @return ?string error */
    public function cancelOrder(array $o, string $reason, string $method = 'Cash'): ?string
    {
        if (trim($reason) === '') {
            return 'Please write the reason for cancelling.';
        }
        if ($o['status'] === 'cancelled') {
            return 'Already cancelled.';
        }
        if ((int) $this->db->val("SELECT COUNT(*) FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered')", [(int) $o['id']]) > 0) {
            return 'A report was already approved for this order, so it cannot be cancelled. Refund or amend it instead.';
        }
        $m = $this->money($o);
        $open = round($m['paid'] - $m['refunded'], 2);
        if ($open > 0) {
            $err = $this->refund($o, $open, $method, 'Order cancelled: ' . $reason);
            if ($err !== null) {
                return $err;
            }
        }
        $this->db->run("UPDATE sc_lab_orders SET status = 'cancelled', cancel_reason = ?, updated_at = NOW() WHERE id = ?", [mb_substr($reason, 0, 255), (int) $o['id']]);
        $this->db->run("UPDATE sc_lab_order_items SET status = 'cancelled' WHERE order_id = ?", [(int) $o['id']]);
        $this->db->run("UPDATE sc_lab_samples SET status = 'rejected', reason = 'Order cancelled' WHERE order_id = ? AND status <> 'completed'", [(int) $o['id']]);
        if ($o['invoice_id']) {
            $this->db->run("UPDATE sc_invoices SET balance = 0, status = 'paid', note = ?, updated_at = NOW() WHERE id = ?", [mb_substr('CANCELLED: ' . $reason, 0, 500), (int) $o['invoice_id']]);
        }
        $this->recalcCommission((int) $o['id'], 'Order cancelled');
        $this->log('order', (int) $o['id'], 'voided', ['status' => $o['status']], ['status' => 'cancelled'], $reason, (int) $o['branch_id']);
        return null;
    }

    // ------------------------------------------------------------------ samples

    /** Moves a sample along. Its tests follow it. @return ?string error */
    public function sampleStep(array $s, string $act, string $reason = ''): ?string
    {
        $now = date('Y-m-d H:i:s');
        $uid = (int) $this->p->user['id'];
        $from = (string) $s['status'];
        switch ($act) {
            case 'collect':
                if (!in_array($from, ['pending', 'recollect'], true)) {
                    return 'This sample is not waiting for collection.';
                }
                $this->db->run("UPDATE sc_lab_samples SET status = 'collected', collected_at = ?, collected_by = ?, reason = NULL WHERE id = ?", [$now, $uid, (int) $s['id']]);
                $to = 'collected';
                break;
            case 'receive':
                if ($from !== 'collected') {
                    return 'Only a collected sample can be received.';
                }
                $this->db->run("UPDATE sc_lab_samples SET status = 'received', received_at = ?, received_by = ? WHERE id = ?", [$now, $uid, (int) $s['id']]);
                $to = 'received';
                break;
            case 'process':
                if ($from !== 'received') {
                    return 'Receive the sample first.';
                }
                $this->db->run("UPDATE sc_lab_samples SET status = 'processing' WHERE id = ?", [(int) $s['id']]);
                $to = 'processing';
                break;
            case 'reject':
            case 'recollect':
                if (trim($reason) === '') {
                    return 'Please write the reason.';
                }
                if (in_array($from, ['completed', 'rejected'], true)) {
                    return 'This sample is already closed.';
                }
                $to = $act === 'reject' ? 'rejected' : 'recollect';
                $this->db->run('UPDATE sc_lab_samples SET status = ?, reason = ? WHERE id = ?', [$to, mb_substr($reason, 0, 255), (int) $s['id']]);
                break;
            default:
                return 'Unknown step.';
        }
        $this->db->run("UPDATE sc_lab_order_items SET status = ? WHERE sample_id = ? AND status NOT IN ('completed','cancelled')", [$to, (int) $s['id']]);
        $this->log('sample', (int) $s['id'], 'edited', ['status' => $from], ['status' => $to], $reason ?: null, (int) $s['branch_id']);
        return null;
    }

    public function completeSample(int $sid): void
    {
        if ((int) $this->db->val("SELECT COUNT(*) FROM sc_lab_order_items WHERE sample_id = ? AND status NOT IN ('completed','cancelled')", [$sid]) === 0) {
            $this->db->run("UPDATE sc_lab_samples SET status = 'completed' WHERE id = ? AND status <> 'rejected'", [$sid]);
        }
    }

    // ------------------------------------------------------------------ results, review, approval

    /** Rows to enter or show for one test line: each parameter with its range for this patient and the saved value. @return list<array<string,mixed>> */
    public function resultRows(array $item): array
    {
        $o = $this->order((int) $item['order_id']);
        $pt = $o ? $this->patient((int) $o['patient_id']) : null;
        $sex = $pt ? self::sexLetter($pt['gender']) : null;
        $age = $pt ? self::ageYears($pt) : null;
        $saved = [];
        foreach ($this->db->all('SELECT * FROM sc_lab_results WHERE item_id = ?', [(int) $item['id']]) as $r) {
            $saved[(int) $r['param_id']] = $r;
        }
        $out = [];
        foreach ($this->params((int) $item['test_id']) as $p) {
            $rg = $this->range((int) $p['id'], $sex === 'A' ? null : $sex, $age);
            $s = $saved[(int) $p['id']] ?? null;
            $out[] = ['param_id' => (int) $p['id'], 'name' => $p['name'], 'unit' => $p['unit'], 'ref' => $s['ref_text'] ?? self::rangeText($rg), 'range' => $rg, 'value' => $s['value'] ?? '', 'flag' => $s['flag'] ?? '', 'comment' => $s['comment'] ?? '', 'kind' => $p['kind'] ?? 'number'];
        }
        return $out;
    }

    /** Saves typed results. Allowed while the report is none or draft, or returned. @return ?string error */
    public function saveResults(array $item, array $values, array $comments = []): ?string
    {
        if (!in_array($item['report_status'], ['none', 'draft'], true)) {
            return 'This report is locked. Ask the approver to return it, or amend it after approval.';
        }
        if (!in_array($item['status'], ['received', 'processing', 'completed'], true)) {
            return 'The sample must be received in the lab before results are entered.';
        }
        $now = date('Y-m-d H:i:s');
        $uid = (int) $this->p->user['id'];
        foreach ($this->resultRows($item) as $r) {
            $v = trim((string) ($values[$r['param_id']] ?? ''));
            $flag = self::flag($v, $r['range']);
            $this->db->run('INSERT INTO sc_lab_results (tenant_id, branch_id, item_id, param_id, param_name, value, unit, ref_text, flag, comment, entered_by, entered_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value), unit = VALUES(unit), ref_text = VALUES(ref_text), flag = VALUES(flag), comment = VALUES(comment), entered_by = VALUES(entered_by), entered_at = VALUES(entered_at)', [$this->t, (int) $item['branch_id'], (int) $item['id'], $r['param_id'], $r['name'], $v, $r['unit'], $r['ref'], $flag, mb_substr(trim((string) ($comments[$r['param_id']] ?? '')), 0, 255) ?: null, $uid, $now]);
        }
        $this->db->run("UPDATE sc_lab_order_items SET report_status = 'draft', status = IF(status = 'received', 'processing', status), entered_by = ? WHERE id = ?", [$uid, (int) $item['id']]);
        if ($item['status'] === 'received') {
            $this->db->run("UPDATE sc_lab_samples SET status = 'processing' WHERE id = ? AND status = 'received'", [(int) $item['sample_id']]);
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function results(int $itemId): array
    {
        return $this->db->all('SELECT * FROM sc_lab_results WHERE item_id = ? ORDER BY id', [$itemId]);
    }

    /** Frozen copy of a report for the version log. */
    public function snapshot(int $itemId): string
    {
        return json_encode(array_map(static fn ($r) => ['p' => $r['param_name'], 'v' => $r['value'], 'u' => $r['unit'], 'r' => $r['ref_text'], 'f' => $r['flag'], 'c' => $r['comment']], $this->results($itemId)), JSON_UNESCAPED_UNICODE);
    }

    private function reportLog(array $it, string $action, ?string $reason): void
    {
        $this->db->insert('sc_lab_report_log', ['tenant_id' => $this->t, 'branch_id' => (int) $it['branch_id'], 'item_id' => (int) $it['id'], 'version' => (int) $it['version'], 'action' => $action, 'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null, 'snapshot' => $this->snapshot((int) $it['id']), 'user_id' => (int) $this->p->user['id'], 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** submit | approve | return | deliver. @return ?string error */
    public function reportStep(array $it, string $act, string $reason = '', ?int $signatory = null): ?string
    {
        $uid = (int) $this->p->user['id'];
        $st = (string) $it['report_status'];
        $now = date('Y-m-d H:i:s');
        switch ($act) {
            case 'submit':
                if ($st !== 'draft') {
                    return 'Save the results first.';
                }
                if ((int) $this->db->val("SELECT COUNT(*) FROM sc_lab_results WHERE item_id = ? AND value <> ''", [(int) $it['id']]) === 0) {
                    return 'Enter at least one result before sending for review.';
                }
                $this->db->run("UPDATE sc_lab_order_items SET report_status = 'pending_review' WHERE id = ?", [(int) $it['id']]);
                $this->reportLog($it, 'submit', null);
                break;
            case 'approve':
                if ($st !== 'pending_review') {
                    return 'Only a report waiting for review can be approved.';
                }
                if ((int) $it['entered_by'] === $uid && $this->setting('self_approve', '0') !== '1') {
                    return 'The person who entered the results cannot approve them. Ask another approver.';
                }
                $sg = $signatory ?: (int) $this->db->val('SELECT id FROM sc_lab_signatories WHERE tenant_id = ? AND active = 1 ORDER BY is_default DESC, id LIMIT 1', [$this->t]);
                $no = $it['report_no'] ?: $this->docNo('RPT', (int) $it['branch_id']);
                $this->db->run("UPDATE sc_lab_order_items SET report_status = 'approved', status = 'completed', approved_by = ?, approved_at = ?, signatory_id = ?, report_no = ? WHERE id = ?", [$uid, $now, $sg ?: null, $no, (int) $it['id']]);
                $this->completeSample((int) $it['sample_id']);
                $this->reportLog($it, 'approve', null);
                break;
            case 'return':
                if ($st !== 'pending_review') {
                    return 'Only a report waiting for review can be returned.';
                }
                if (trim($reason) === '') {
                    return 'Please write what must be corrected.';
                }
                $this->db->run("UPDATE sc_lab_order_items SET report_status = 'draft' WHERE id = ?", [(int) $it['id']]);
                $this->reportLog($it, 'reject', $reason);
                break;
            case 'deliver':
                if (!in_array($st, ['approved', 'delivered'], true)) {
                    return 'Only an approved report can be delivered.';
                }
                $this->db->run("UPDATE sc_lab_order_items SET report_status = 'delivered', delivered_at = COALESCE(delivered_at, ?) WHERE id = ?", [$now, (int) $it['id']]);
                break;
            default:
                return 'Unknown step.';
        }
        $this->log('report', (int) $it['id'], $act === 'approve' ? 'edited' : 'edited', ['report_status' => $st], ['report_status' => $act], $reason ?: null, (int) $it['branch_id']);
        return null;
    }

    /** Opens an approved report for correction. The old version stays in the log. @return ?string error */
    public function amend(array $it, string $reason): ?string
    {
        if (!in_array($it['report_status'], ['approved', 'delivered'], true)) {
            return 'Only an approved report can be amended.';
        }
        if (trim($reason) === '') {
            return 'Please write why the report is being amended.';
        }
        $this->reportLog($it, 'amend', $reason);
        $this->db->run("UPDATE sc_lab_order_items SET report_status = 'draft', version = version + 1, status = 'processing', approved_by = NULL, approved_at = NULL, delivered_at = NULL WHERE id = ?", [(int) $it['id']]);
        $this->db->run("UPDATE sc_lab_samples SET status = 'processing' WHERE id = ?", [(int) $it['sample_id']]);
        $this->log('report', (int) $it['id'], 'edited', ['version' => (int) $it['version']], ['version' => (int) $it['version'] + 1], $reason, (int) $it['branch_id']);
        return null;
    }

    // ------------------------------------------------------------------ finance

    /** @return array{0:string,1:string,2:string} from, to, label */
    public function period(string $key, ?string $f = null, ?string $t = null): array
    {
        $today = date('Y-m-d');
        return match ($key) {
            'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), 'Yesterday'],
            'month' => [date('Y-m-01'), $today, 'This month'],
            'lastmonth' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month')), 'Last month'],
            'custom' => [preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f) ? (string) $f : $today, preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $t) ? (string) $t : $today, 'Custom'],
            default => [$today, $today, 'Today'],
        };
    }

    /** Income, refunds, discounts, expenses, net and payables for a period in the current branch scope. @return array<string,mixed> */
    public function finance(string $from, string $to): array
    {
        $cur = $this->reportCurrency();
        $q = fn (string $s) => $this->db->quote($s);
        $credit = implode(',', array_map($q, (new Billing($this->db))->creditTypes()));
        $amt = Currency::sql('p.amount', 'i.currency', $cur);
        $pay = "FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND DATE(p.paid_at) BETWEEN ? AND ? AND " . $this->scope('COALESCE(p.branch_id, i.branch_id)');
        $args = [$this->t, $from, $to];
        $tests = (float) $this->db->val("SELECT COALESCE(SUM($amt), 0) $pay AND i.doc_type = 'test_invoice' AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%')", $args);
        $bills = (float) $this->db->val("SELECT COALESCE(SUM($amt), 0) $pay AND i.doc_type NOT IN ($credit, 'supplier_invoice', 'test_invoice') AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%')", $args);
        $refunds = (float) $this->db->val("SELECT COALESCE(SUM($amt), 0) $pay AND i.doc_type IN ($credit) AND p.note = 'Refund paid'", $args);
        $lamt = Currency::sql('l.amount', 'l.currency', $cur);
        $led = "FROM sc_ledger l WHERE l.tenant_id = ? AND l.deleted_at IS NULL AND l.status = 'paid' AND DATE(l.paid_at) BETWEEN ? AND ? AND " . $this->scope('l.branch_id');
        $other = (float) $this->db->val("SELECT COALESCE(SUM($lamt), 0) $led AND l.kind = 'income'", $args);
        $exp = [];
        foreach ($this->db->all("SELECT l.category c, COALESCE(SUM($lamt), 0) s $led AND l.kind = 'expense' GROUP BY l.category ORDER BY s DESC", $args) as $r) {
            $exp[(string) $r['c']] = round((float) $r['s'], 2);
        }
        $home = (float) $this->db->val("SELECT COALESCE(SUM(" . Currency::sql('home_charge', 'currency', $cur) . "), 0) FROM sc_lab_orders WHERE tenant_id = ? AND status <> 'cancelled' AND DATE(order_date) BETWEEN ? AND ? AND " . $this->scope('branch_id'), $args);
        $disc = (float) $this->db->val("SELECT COALESCE(SUM(" . Currency::sql('discount', 'currency', $cur) . "), 0) FROM sc_lab_orders WHERE tenant_id = ? AND status <> 'cancelled' AND DATE(order_date) BETWEEN ? AND ? AND " . $this->scope('branch_id'), $args);
        $due = (float) $this->db->val("SELECT COALESCE(SUM($lamt), 0) FROM sc_ledger l WHERE l.tenant_id = ? AND l.deleted_at IS NULL AND l.kind = 'expense' AND l.status = 'due' AND " . $this->scope('l.branch_id'), [$this->t]);
        $commOut = (float) $this->db->val('SELECT COALESCE(SUM(' . Currency::sql('amount', 'currency', $cur) . '), 0) FROM sc_commissions WHERE tenant_id = ? AND ' . $this->scope('branch_id'), [$this->t])
            - (float) $this->db->val("SELECT COALESCE(SUM(" . Currency::sql('amount', 'currency', $cur) . "), 0) FROM sc_ledger WHERE tenant_id = ? AND source = 'commission' AND status = 'paid' AND deleted_at IS NULL AND " . $this->scope('branch_id'), [$this->t]);
        $salOut = (float) $this->db->val('SELECT COALESCE(SUM(' . Currency::sql('(net - paid)', 'currency', $cur) . '), 0) FROM sc_salary WHERE tenant_id = ? AND ' . $this->scope('branch_id'), [$this->t]);
        $recv = (float) $this->db->val("SELECT COALESCE(SUM(" . Currency::sql('balance', 'currency', $cur) . "), 0) FROM sc_invoices WHERE tenant_id = ? AND issuer = 'vendor' AND status NOT IN ('void') AND doc_type = 'test_invoice' AND " . $this->scope('branch_id'), [$this->t]);
        $income = round($tests + $bills + $other, 2);
        $expTotal = round(array_sum($exp), 2);
        return [
            'from' => $from, 'to' => $to, 'tests' => round($tests, 2), 'bills' => round($bills, 2), 'other' => round($other, 2), 'income' => $income, 'refunds' => round($refunds, 2), 'discounts' => round($disc, 2), 'home' => round($home, 2),
            'expenses' => $exp, 'expense_total' => $expTotal, 'net' => round($income - $refunds - $expTotal, 2),
            'salary' => (float) ($exp['Salaries and wages'] ?? 0), 'doctor_comm' => (float) ($exp['Doctor commissions'] ?? 0), 'partner_comm' => (float) ($exp['Partner laboratory commissions'] ?? 0), 'stock' => (float) ($exp['Stock purchases'] ?? 0),
            'payable_bills' => round($due, 2), 'payable_commission' => round(max(0, $commOut), 2), 'payable_salary' => round(max(0, $salOut), 2), 'receivable' => round($recv, 2), 'currency' => $cur,
        ];
    }

    /** Income, refunds and expenses of the period kept apart per currency, never mixed. @return array<string,array{income:float,refunds:float,expenses:float,net:float}> */
    public function currencyTotals(string $from, string $to): array
    {
        $credit = implode(',', array_map(fn (string $x) => $this->db->quote($x), (new Billing($this->db))->creditTypes()));
        $out = [];
        $add = static function (array &$o, string $c, string $k, float $v): void {
            $o[$c] ??= ['income' => 0.0, 'refunds' => 0.0, 'expenses' => 0.0, 'net' => 0.0];
            $o[$c][$k] += $v;
        };
        $pay = "FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND DATE(p.paid_at) BETWEEN ? AND ? AND " . $this->scope('COALESCE(p.branch_id, i.branch_id)');
        $args = [$this->t, $from, $to];
        foreach ($this->db->all("SELECT i.currency c, SUM(p.amount) s $pay AND i.doc_type NOT IN ($credit, 'supplier_invoice') AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%') GROUP BY i.currency", $args) as $r) {
            $add($out, (string) $r['c'], 'income', (float) $r['s']);
        }
        foreach ($this->db->all("SELECT i.currency c, SUM(p.amount) s $pay AND i.doc_type IN ($credit) AND p.note = 'Refund paid' GROUP BY i.currency", $args) as $r) {
            $add($out, (string) $r['c'], 'refunds', (float) $r['s']);
        }
        $led = 'FROM sc_ledger l WHERE l.tenant_id = ? AND l.deleted_at IS NULL AND l.status = \'paid\' AND DATE(l.paid_at) BETWEEN ? AND ? AND ' . $this->scope('l.branch_id');
        foreach ($this->db->all("SELECT COALESCE(l.currency, ?) c, l.kind k, SUM(l.amount) s $led GROUP BY COALESCE(l.currency, ?), l.kind", array_merge([Currency::forTenant($this->t)], $args, [Currency::forTenant($this->t)])) as $r) {
            $add($out, (string) $r['c'], $r['k'] === 'income' ? 'income' : 'expenses', (float) $r['s']);
        }
        foreach ($out as $c => &$v) {
            $v['net'] = round($v['income'] - $v['refunds'] - $v['expenses'], 2);
        }
        unset($v);
        ksort($out);
        return $out;
    }

    /** Every cash movement in the scope, newest first, for drill down. @return list<array<string,mixed>> */
    public function transactions(string $from, string $to, ?string $kind = null): array
    {
        $rows = (new Accounts($this->db, new Billing($this->db)))->movements($this->t, $from, $to, $this->reportCurrency(), [$this->scope('COALESCE(p.branch_id, i.branch_id)'), $this->scope('l.branch_id')]);
        $out = [];
        foreach ($rows as $m) {
            if ($kind !== null && $kind !== '' && !($m['cat'] === $kind || $m['src'] === $kind)) {
                continue;
            }
            $out[] = $m;
        }
        return array_reverse($out);
    }

    /** Posts a paid or due expense into the clinic ledger (the same cash book the Accounts screen reads). Returns the ledger id. */
    public function postExpense(string $category, float $amount, string $date, string $party, string $desc, string $method, string $status, int $branch, ?string $source = null, ?int $sourceId = null, ?int $referrerId = null, ?int $userId = null, ?string $ref = null): int
    {
        $acc = new Accounts($this->db, new Billing($this->db));
        $now = date('Y-m-d H:i:s');
        $row = Currency::stamp('sc_ledger', [
            'tenant_id' => $this->t, 'kind' => 'expense', 'code' => $acc->code($this->t, 'expense'), 'entry_date' => $date, 'category' => $category, 'party' => mb_substr($party, 0, 190) ?: null, 'description' => mb_substr($desc, 0, 255) ?: null,
            'amount' => round($amount, 2), 'method' => $method ?: 'Cash', 'reference' => $ref, 'status' => $status === 'due' ? 'due' : 'paid', 'due_date' => null, 'paid_at' => $status === 'due' ? null : $date . ' ' . ($date === date('Y-m-d') ? date('H:i:s') : '12:00:00'),
            'user_id' => $userId, 'created_by' => (int) $this->p->user['id'], 'created_at' => $now, 'updated_at' => $now, 'branch_id' => $branch, 'source' => $source, 'source_id' => $sourceId, 'referrer_id' => $referrerId,
        ]);
        return $this->db->insert('sc_ledger', $row);
    }

    // ------------------------------------------------------------------ sharing and mail

    /** The mailer with this clinic's own SMTP account when one is saved, else the platform one. */
    public function mailer(): Mailer
    {
        $m = clone app()->mailer;
        if ($this->setting('smtp_host') !== '') {
            $m->override = ['smtp_host' => $this->setting('smtp_host'), 'smtp_port' => $this->setting('smtp_port', '587'), 'smtp_user' => $this->setting('smtp_user'), 'smtp_password' => $this->secret('smtp_password'), 'smtp_enc' => $this->setting('smtp_enc', 'tls'), 'from_address' => $this->setting('from_address'), 'from_name' => $this->setting('from_name', (string) $this->p->vendor['name'])];
        }
        return $m;
    }

    private function key(): string
    {
        return hash('sha256', 'lab|' . (string) (app()->env->get('DB_PASS', '') ?: app()->env->get('APP_KEY', 'selfcare')), true);
    }

    public function setSecret(string $k, string $v): void
    {
        $iv = random_bytes(16);
        $this->setSetting($k, base64_encode($iv . openssl_encrypt($v, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv)));
    }

    public function secret(string $k): string
    {
        $raw = base64_decode($this->setting($k), true);
        return $raw === false || strlen($raw) < 17 ? '' : (string) openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    }

    /** Document model for a share: report (all approved tests) or receipt. @return ?array<string,mixed> */
    public function shareModel(array $o, string $doc): ?array
    {
        $D = new LabDocs($this);
        if ($doc === 'receipt') {
            return $D->receipt($o, $this->lastReceipt($o), '');
        }
        $items = $this->db->all("SELECT * FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered') ORDER BY id", [(int) $o['id']]);
        return $items ? $D->report($o, $items, '') : null;
    }

    public function publicUrl(string $token): string
    {
        return rtrim((string) app()->env->get('APP_URL', 'https://selfcare.easybzi.com'), '/') . '/lab-doc/' . $token;
    }

    /**
     * Records a share and, for email, sends it. WhatsApp opens a wa.me link from the browser (no provider yet).
     * @return array{ok:bool,id:int,msg:string,link:string}
     */
    public function share(array $o, string $doc, string $channel, string $to, string $name, string $relation): array
    {
        $pt = $this->patient((int) $o['patient_id']);
        if ($doc === 'report' && (int) $this->db->val("SELECT COUNT(*) FROM sc_lab_order_items WHERE order_id = ? AND report_status IN ('approved','delivered')", [(int) $o['id']]) === 0) {
            return ['ok' => false, 'id' => 0, 'msg' => 'Only an approved report can be shared.', 'link' => ''];
        }
        $to = trim($to);
        if ($channel === 'email' ? !filter_var($to, FILTER_VALIDATE_EMAIL) : strlen(self::waNumber($to)) < 9) {
            return ['ok' => false, 'id' => 0, 'msg' => $channel === 'email' ? 'Enter a valid email address.' : 'Enter a valid WhatsApp number with the country code.', 'link' => ''];
        }
        $token = bin2hex(random_bytes(20));
        $days = max(1, (int) $this->setting('link_days', '7'));
        $docNo = $doc === 'receipt' ? (string) ($this->lastReceipt($o)['receipt_no'] ?? $o['order_no']) : (string) $o['order_no'];
        $link = $this->publicUrl($token);
        $text = strtr($this->setting('wa_text', 'Dear {name}, your {doc} {no} from {lab} is ready. Open it here: {link} . The link works for {days} days.'), ['{name}' => $pt['name'], '{doc}' => $doc === 'receipt' ? 'receipt' : 'laboratory report', '{no}' => $docNo, '{lab}' => (string) $this->p->vendor['name'], '{link}' => $link, '{days}' => (string) $days]);
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('sc_lab_delivery_log', ['tenant_id' => $this->t, 'branch_id' => (int) $o['branch_id'], 'order_id' => (int) $o['id'], 'doc_type' => $doc, 'doc_ref' => $docNo, 'channel' => $channel, 'recipient' => $to, 'recipient_name' => mb_substr($name, 0, 160), 'relation' => $relation, 'message' => mb_substr($text, 0, 1000), 'status' => 'queued', 'tries' => 0, 'token' => $token, 'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400), 'user_id' => (int) $this->p->user['id'], 'created_at' => $now, 'updated_at' => $now]);
        $this->log('order', (int) $o['id'], 'shared', null, ['channel' => $channel, 'to' => $to, 'doc' => $doc], null, (int) $o['branch_id']);
        if ($channel === 'whatsapp') {
            $this->db->run("UPDATE sc_lab_delivery_log SET status = 'sent', error = 'Opened in WhatsApp. Delivery is not confirmed.', tries = 1 WHERE id = ?", [$id]);
            return ['ok' => true, 'id' => $id, 'msg' => 'WhatsApp is ready.', 'link' => 'https://wa.me/' . self::waNumber($to) . '?text=' . rawurlencode($text)];
        }
        $r = $this->sendEmail($id);
        return ['ok' => $r === null, 'id' => $id, 'msg' => $r ?? 'Email sent.', 'link' => ''];
    }

    /** Sends (or resends) a queued email share. @return ?string error text, null when sent */
    public function sendEmail(int $logId): ?string
    {
        $r = $this->db->one("SELECT * FROM sc_lab_delivery_log WHERE id = ? AND tenant_id = ? AND channel = 'email'", [$logId, $this->t]);
        $o = $r ? $this->order((int) $r['order_id']) : null;
        if ($r === null || $o === null) {
            return 'Not found.';
        }
        $m = $this->shareModel($o, (string) $r['doc_type']);
        if ($m === null) {
            return 'The report is not approved.';
        }
        $pdf = Documents::pdf($m)->render();
        $body = (string) $r['message'] . "\n\nThe PDF is attached.";
        $ml = $this->mailer();
        $ok = $ml->send((string) $r['recipient'], ((string) $this->p->vendor['name']) . ': your ' . ($r['doc_type'] === 'receipt' ? 'receipt ' : 'laboratory report ') . $r['doc_ref'], $body, [[$r['doc_ref'] . '.pdf', 'application/pdf', $pdf]]);
        $this->db->run('UPDATE sc_lab_delivery_log SET status = ?, error = ?, tries = tries + 1, updated_at = NOW() WHERE id = ?', [$ok ? 'sent' : 'failed', $ok ? null : mb_substr($ml->lastError ?: 'Could not send', 0, 255), $logId]);
        if ($ok && $r['doc_type'] === 'report') {
            $this->db->run("UPDATE sc_lab_order_items SET report_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW()) WHERE order_id = ? AND report_status = 'approved'", [(int) $o['id']]);
        }
        return $ok ? null : ($ml->lastError ?: 'Could not send');
    }

    // ------------------------------------------------------------------ reports (screen, PDF, Excel, scheduled email)

    public const REPORTS = ['summary' => 'Management summary', 'patients' => 'Patients', 'tests' => 'Tests and departments', 'income' => 'Income', 'expenses' => 'Expenses', 'commission' => 'Commission', 'salary' => 'Salary', 'inventory' => 'Inventory', 'branches' => 'Branch comparison'];

    /** @return array{title:string,cols:list<array{0:string,1:float,2?:string}>,rows:list<list<string>>,totals:list<array{0:string,1:string,2?:bool}>} */
    public function reportData(string $type, string $from, string $to): array
    {
        $q = fn (string $s) => $this->db->quote($s);
        $t = [$this->t];
        $cols = [];
        $rows = [];
        $tot = [];
        $title = self::REPORTS[$type] ?? 'Report';
        switch ($type) {
            case 'patients':
                $cols = [['MR', 70], ['Patient', 150], ['Branch', 80], ['Orders', 45, 'R'], ['Billed', 70, 'R'], ['Received', 70, 'R']];
                foreach ($this->db->all("SELECT pt.patient_unique_id mr, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n, o.branch_id, COUNT(*) c, SUM(" . Currency::sql('o.total', 'o.currency', $this->reportCurrency()) . ") tot, (SELECT COALESCE(SUM(" . Currency::sql('p.amount', 'pi.currency', $this->reportCurrency()) . "), 0) FROM sc_invoice_payments p JOIN sc_invoices pi ON pi.id = p.invoice_id WHERE p.invoice_id IN (SELECT invoice_id FROM sc_lab_orders x WHERE x.patient_id = pt.id AND x.tenant_id = pt.tenant_id AND DATE(x.order_date) BETWEEN " . $q($from) . ' AND ' . $q($to) . ") AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%')) pd FROM sc_lab_orders o JOIN patients pt ON pt.id = o.patient_id JOIN users u ON u.id = pt.user_id WHERE o.tenant_id = ? AND o.status <> 'cancelled' AND DATE(o.order_date) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('o.branch_id') . ' GROUP BY pt.id, o.branch_id ORDER BY tot DESC', $t) as $r) {
                    $rows[] = [$r['mr'], $r['n'], $this->branchName((int) $r['branch_id']), (string) $r['c'], number_format((float) $r['tot'], 2), number_format((float) $r['pd'], 2)];
                }
                $tot[] = ['Patients', (string) count($rows), true];
                break;
            case 'tests':
                $cols = [['Department', 110], ['Test', 190], ['Count', 50, 'R'], ['Revenue', 80, 'R']];
                foreach ($this->db->all("SELECT COALESCE(i.dept_name, 'Other') d, i.test_name, COUNT(*) c, SUM(" . Currency::sql('i.net', 'o.currency', $this->reportCurrency()) . ") r FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id WHERE i.tenant_id = ? AND i.status <> 'cancelled' AND o.status <> 'cancelled' AND DATE(o.order_date) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('o.branch_id') . ' GROUP BY d, i.test_name ORDER BY d, c DESC', $t) as $r) {
                    $rows[] = [$r['d'], $r['test_name'], (string) $r['c'], number_format((float) $r['r'], 2)];
                }
                $tot[] = ['Tests done', (string) array_sum(array_map(static fn ($r) => (int) $r[2], $rows)), true];
                break;
            case 'income':
                $cols = [['Date', 60], ['Receipt or bill', 100], ['Patient', 130], ['Branch', 70], ['Method', 60], ['Amount', 70, 'R']];
                $cur = $this->reportCurrency();
                $sum = 0.0;
                foreach ($this->db->all("SELECT p.paid_at, COALESCE(p.receipt_no, i.invoice_no) no, i.party_name, COALESCE(p.branch_id, i.branch_id) b, p.method, " . Currency::sql('p.amount', 'i.currency', $cur) . " amt FROM sc_invoice_payments p JOIN sc_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND i.issuer = 'vendor' AND i.status <> 'void' AND i.doc_type NOT IN ('credit_note','supplier_invoice') AND (p.note IS NULL OR p.note NOT LIKE 'Credit note%') AND DATE(p.paid_at) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('COALESCE(p.branch_id, i.branch_id)') . ' ORDER BY p.paid_at', $t) as $r) {
                    $sum += (float) $r['amt'];
                    $rows[] = [dt((string) $r['paid_at']), (string) $r['no'], (string) $r['party_name'], $this->branchName((int) $r['b']), (string) $r['method'], number_format((float) $r['amt'], 2)];
                }
                $tot[] = ['Total received', number_format($sum, 2), true];
                break;
            case 'expenses':
                $cols = [['Date', 60], ['No', 60], ['Category', 120], ['Paid to', 120], ['Branch', 70], ['Amount', 70, 'R']];
                $cur = $this->reportCurrency();
                $sum = 0.0;
                foreach ($this->db->all("SELECT l.*, " . Currency::sql('l.amount', 'l.currency', $cur) . " amt FROM sc_ledger l WHERE l.tenant_id = ? AND l.kind = 'expense' AND l.deleted_at IS NULL AND l.status = 'paid' AND DATE(l.paid_at) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('l.branch_id') . ' ORDER BY l.paid_at', $t) as $r) {
                    $sum += (float) $r['amt'];
                    $rows[] = [dt((string) $r['entry_date']), (string) $r['code'], (string) $r['category'], (string) $r['party'], $this->branchName((int) $r['branch_id']), number_format((float) $r['amt'], 2)];
                }
                $tot[] = ['Total paid out', number_format($sum, 2), true];
                break;
            case 'commission':
                $cols = [['Referrer', 150], ['Type', 60], ['Patients', 50, 'R'], ['Tests', 45, 'R'], ['Earned', 70, 'R'], ['Paid', 70, 'R'], ['Outstanding', 70, 'R']];
                foreach ($this->referrers(null, false) as $r) {
                    $T = $this->commissionTotals((int) $r['id'], $from, $to);
                    $all = $this->commissionTotals((int) $r['id']);
                    if ($T['orders'] || $T['earned'] || $all['outstanding']) {
                        $rows[] = [(string) $r['name'], ucfirst((string) $r['kind']), (string) $T['patients'], (string) $T['tests'], number_format((float) $T['earned'], 2), number_format((float) $T['paid'], 2), number_format((float) $all['outstanding'], 2)];
                    }
                }
                break;
            case 'salary':
                $cols = [['Employee', 140], ['Month', 50], ['Branch', 70], ['Basic', 65, 'R'], ['Net', 65, 'R'], ['Paid', 65, 'R'], ['Status', 50]];
                foreach ($this->db->all("SELECT s.*, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) n FROM sc_salary s JOIN users u ON u.id = s.user_id WHERE s.tenant_id = ? AND s.period BETWEEN " . $q(substr($from, 0, 7)) . ' AND ' . $q(substr($to, 0, 7)) . ' AND ' . $this->scope('s.branch_id') . ' ORDER BY s.period, u.first_name', $t) as $r) {
                    $rows[] = [$r['n'], (string) $r['period'], $this->branchName((int) $r['branch_id']), number_format((float) $r['base'], 2), number_format((float) $r['net'], 2), number_format((float) $r['paid'], 2), ucfirst((string) $r['status'])];
                }
                break;
            case 'inventory':
                $cols = [['Item', 150], ['Branch', 70], ['Batch', 60], ['In stock', 55, 'R'], ['Cost', 55, 'R'], ['Value', 65, 'R'], ['Expiry', 60]];
                $sum = 0.0;
                foreach ($this->db->all('SELECT * FROM sc_stock_items WHERE tenant_id = ? AND ' . $this->scope('branch_id') . ' ORDER BY name', $t) as $r) {
                    $v = (float) $r['quantity'] * (float) $r['cost_price'];
                    $sum += $v;
                    $rows[] = [(string) $r['name'], $this->branchName((int) $r['branch_id']), (string) $r['batch_no'], rtrim(rtrim((string) $r['quantity'], '0'), '.'), number_format((float) $r['cost_price'], 2), number_format($v, 2), $r['expiry_date'] ? dt((string) $r['expiry_date']) : ''];
                }
                $tot[] = ['Stock value', number_format($sum, 2), true];
                break;
            case 'branches':
                $cols = [['Branch', 110], ['Currency', 50], ['Orders', 50, 'R'], ['Income', 75, 'R'], ['Refunds', 65, 'R'], ['Expenses', 75, 'R'], ['Net', 75, 'R']];
                $keep = $this->force;
                foreach (array_intersect_key($this->branches(true), array_flip($this->allowed())) as $b) {
                    $this->force = [(int) $b['id']];
                    $F = $this->finance($from, $to);
                    $n = (int) $this->db->val("SELECT COUNT(*) FROM sc_lab_orders WHERE tenant_id = ? AND status <> 'cancelled' AND DATE(order_date) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('branch_id'), $t);
                    $rows[] = [(string) $b['name'], $this->branchCurrency((int) $b['id']), (string) $n, number_format($F['income'], 2), number_format($F['refunds'], 2), number_format($F['expense_total'], 2), number_format($F['net'], 2)];
                }
                $this->force = $keep;
                break;
            default:
                $F = $this->finance($from, $to);
                $n = static fn (float $v): string => number_format($v, 2);
                $cnt = fn (string $sql) => (int) $this->db->val($sql, $t);
                $w = " AND DATE(order_date) BETWEEN " . $q($from) . ' AND ' . $q($to) . ' AND ' . $this->scope('branch_id');
                $cols = [['Measure', 260], ['Value', 120, 'R']];
                $rows = [['Orders', (string) $cnt("SELECT COUNT(*) FROM sc_lab_orders WHERE tenant_id = ? AND status <> 'cancelled'" . $w)], ['Patients served', (string) $cnt("SELECT COUNT(DISTINCT patient_id) FROM sc_lab_orders WHERE tenant_id = ? AND status <> 'cancelled'" . $w)], ['Tests done', (string) $cnt("SELECT COUNT(*) FROM sc_lab_order_items i JOIN sc_lab_orders o ON o.id = i.order_id WHERE i.tenant_id = ? AND i.status <> 'cancelled' AND o.status <> 'cancelled' " . str_replace(['order_date', 'branch_id'], ['o.order_date', 'o.branch_id'], $w))], ['Cancelled orders', (string) $cnt("SELECT COUNT(*) FROM sc_lab_orders WHERE tenant_id = ? AND status = 'cancelled'" . $w)],
                    ['Test income', $n($F['tests'])], ['Other bill income', $n($F['bills'])], ['Other income', $n($F['other'])], ['Total income', $n($F['income'])], ['Refunds', $n($F['refunds'])], ['Discounts given (not in net)', $n($F['discounts'])]];
                foreach ($F['expenses'] as $c => $v) {
                    $rows[] = ['Expense: ' . $c, $n($v)];
                }
                $rows = array_merge($rows, [['Total expenses', $n($F['expense_total'])], ['Commission still to pay', $n($F['payable_commission'])], ['Salary still to pay', $n($F['payable_salary'])], ['Bills to pay later', $n($F['payable_bills'])], ['Patients owe', $n($F['receivable'])]]);
                $tot[] = ['Net income', $n($F['net']), true];
        }
        return ['title' => $title, 'cols' => $cols, 'rows' => $rows, 'totals' => $tot];
    }

    /** Excel workbook of the daily or monthly pack: summary plus every report. */
    public function workbook(string $from, string $to): string
    {
        $sheets = [];
        foreach (array_keys(self::REPORTS) as $k) {
            $d = $this->reportData($k, $from, $to);
            $sheets[$d['title']] = array_merge([array_map(static fn ($c) => $c[0], $d['cols'])], $d['rows'], array_map(static fn ($t) => [$t[0], $t[1]], $d['totals']));
        }
        return LabDocs::xlsx($sheets);
    }

    // ------------------------------------------------------------------ backups of the laboratory data of this clinic

    public const BACKUP_TABLES = ['sc_branches', 'sc_user_branches', 'sc_user_perms', 'sc_lab_departments', 'sc_lab_tests', 'sc_lab_test_params', 'sc_lab_ref_ranges', 'sc_lab_branch_tests', 'sc_referrers', 'sc_comm_rules', 'sc_commissions', 'sc_lab_orders', 'sc_lab_order_items', 'sc_lab_samples', 'sc_lab_results', 'sc_lab_report_log', 'sc_lab_signatories', 'sc_lab_home', 'sc_lab_delivery_log', 'sc_record_history', 'sc_staff_profiles', 'sc_attendance', 'sc_salary', 'sc_salary_adj'];

    public function backupDir(): string
    {
        $d = dirname(__DIR__) . '/storage/lab-backups/' . preg_replace('/[^A-Za-z0-9_-]/', '', $this->t);
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    /** Writes a zip with one JSON file per table and a manifest with checksums. @return array{ok:bool,id:int,msg:string} */
    public function makeBackup(string $kind = 'manual', string $note = ''): array
    {
        $dir = $this->backupDir();
        $file = 'lab-' . date('Ymd-His') . '-' . $kind . '.zip';
        $z = new \ZipArchive();
        if (!is_dir($dir) || $z->open($dir . '/' . $file, \ZipArchive::CREATE) !== true) {
            return ['ok' => false, 'id' => 0, 'msg' => 'Could not create the backup file.'];
        }
        $man = ['made' => date('c'), 'tenant' => $this->t, 'tables' => []];
        foreach (self::BACKUP_TABLES as $tb) {
            $rows = $this->db->all("SELECT * FROM $tb WHERE tenant_id = ?", [$this->t]);
            $j = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $z->addFromString($tb . '.json', (string) $j);
            $man['tables'][$tb] = ['rows' => count($rows), 'sha256' => hash('sha256', (string) $j)];
        }
        $z->addFromString('manifest.json', json_encode($man, JSON_PRETTY_PRINT));
        $z->close();
        $sha = hash_file('sha256', $dir . '/' . $file);
        $id = $this->db->insert('sc_lab_backups', ['tenant_id' => $this->t, 'file' => $file, 'size' => filesize($dir . '/' . $file), 'sha256' => $sha, 'kind' => $kind, 'verified' => 0, 'action' => 'backup', 'status' => 'ok', 'note' => mb_substr($note, 0, 255) ?: null, 'user_id' => (int) ($this->p->user['id'] ?? 0) ?: null, 'created_at' => date('Y-m-d H:i:s')]);
        $keep = max(1, (int) $this->setting('backup_keep', '14'));
        foreach ($this->db->all("SELECT id, file FROM sc_lab_backups WHERE tenant_id = ? AND action = 'backup' ORDER BY id DESC LIMIT 1000 OFFSET " . $keep, [$this->t]) as $old) {
            @unlink($dir . '/' . $old['file']);
            $this->db->run('DELETE FROM sc_lab_backups WHERE id = ?', [(int) $old['id']]);
        }
        $copy = trim($this->setting('backup_copy_dir'));
        if ($copy !== '' && is_dir($copy) && is_writable($copy)) {
            @copy($dir . '/' . $file, rtrim($copy, '/') . '/' . $this->t . '-' . $file);
        }
        return ['ok' => true, 'id' => $id, 'msg' => 'Backup ' . $file . ' created.'];
    }

    /** Integrity check: checksum of the zip and of every table file. @return ?string error */
    public function verifyBackup(int $id): ?string
    {
        $b = $this->db->one('SELECT * FROM sc_lab_backups WHERE id = ? AND tenant_id = ?', [$id, $this->t]);
        $path = $b ? $this->backupDir() . '/' . $b['file'] : '';
        if ($b === null || !is_file($path)) {
            return 'The backup file is missing.';
        }
        if (hash_file('sha256', $path) !== $b['sha256']) {
            return 'The file does not match its saved checksum. It was changed or damaged.';
        }
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            return 'The file cannot be opened.';
        }
        $man = json_decode((string) $z->getFromName('manifest.json'), true);
        foreach ((array) ($man['tables'] ?? []) as $tb => $m) {
            if (hash('sha256', (string) $z->getFromName($tb . '.json')) !== $m['sha256']) {
                $z->close();
                return 'Table ' . $tb . ' inside the backup is damaged.';
            }
        }
        $z->close();
        $this->db->run('UPDATE sc_lab_backups SET verified = 1 WHERE id = ?', [$id]);
        return null;
    }

    /** Puts the laboratory tables of this clinic back as they were in the backup. A safety backup is made first. @return ?string error */
    public function restoreBackup(int $id): ?string
    {
        $err = $this->verifyBackup($id);
        if ($err !== null) {
            return $err;
        }
        $b = $this->db->one('SELECT * FROM sc_lab_backups WHERE id = ?', [$id]);
        $safe = $this->makeBackup('prerestore', 'Safety copy before restoring ' . $b['file']);
        if (!$safe['ok']) {
            return 'Could not make the safety backup, so nothing was restored.';
        }
        $z = new \ZipArchive();
        $z->open($this->backupDir() . '/' . $b['file']);
        $this->db->begin();
        try {
            foreach (array_reverse(self::BACKUP_TABLES) as $tb) {
                $this->db->run("DELETE FROM $tb WHERE tenant_id = ?", [$this->t]);
            }
            foreach (self::BACKUP_TABLES as $tb) {
                foreach ((array) json_decode((string) $z->getFromName($tb . '.json'), true) as $row) {
                    $this->db->insert($tb, $row);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $z->close();
            return 'Restore failed and was rolled back: ' . $e->getMessage();
        }
        $z->close();
        $this->branchCache = null;
        $this->db->insert('sc_lab_backups', ['tenant_id' => $this->t, 'file' => $b['file'], 'size' => (int) $b['size'], 'sha256' => $b['sha256'], 'kind' => 'restore', 'verified' => 1, 'action' => 'restore', 'status' => 'ok', 'note' => 'Restored', 'user_id' => (int) ($this->p->user['id'] ?? 0) ?: null, 'created_at' => date('Y-m-d H:i:s')]);
        return null;
    }

    /** Emails the daily Excel workbook and the PDF management summary. Logs the result; a failed run can be retried. @return ?string error */
    public function sendDailyReport(string $date, ?int $retryId = null): ?string
    {
        $to = array_filter(array_map('trim', explode(',', $this->setting('report_to', 'haiderlab09@gmail.com'))));
        if (!$to) {
            return 'No recipient is set. Add one under Schedules.';
        }
        $this->force = array_keys($this->branches(true));
        try {
            $xlsx = $this->workbook($date, $date);
            $d = $this->reportData('summary', $date, $date);
            $D = new LabDocs($this);
            $pdf = Documents::pdf($D->table('Daily management summary', 'DAY-' . str_replace('-', '', $date), [['Date', dt($date)], ['Branches', 'All branches combined']], $d['cols'], $d['rows'], $d['totals']))->render();
        } finally {
            $this->force = null;
        }
        $subject = ((string) $this->p->vendor['name']) . ' daily report ' . $date;
        $body = "Daily report for " . dt($date) . ".\nThe Excel workbook has the summary, patients, tests, income, expenses, commission, salary, inventory and branch comparison. The PDF is the management summary." . ($this->mixedCurrencies() ? "\nBranches use different currencies. The combined figures are in " . Currency::forTenant($this->t) . " at the clinic's own exchange rates, and the branch comparison sheet shows each branch in its own currency." : '');
        $ml = $this->mailer();
        $ok = true;
        $err = '';
        foreach ($to as $addr) {
            if (!$ml->send($addr, $subject, $body, [['lab-report-' . $date . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx], ['summary-' . $date . '.pdf', 'application/pdf', $pdf]])) {
                $ok = false;
                $err = $ml->lastError ?: 'Could not send';
            }
        }
        $now = date('Y-m-d H:i:s');
        if ($retryId) {
            $this->db->run('UPDATE sc_lab_report_runs SET status = ?, error = ?, tries = tries + 1, updated_at = ? WHERE id = ? AND tenant_id = ?', [$ok ? 'sent' : 'failed', $ok ? null : mb_substr($err, 0, 255), $now, $retryId, $this->t]);
        } else {
            $this->db->insert('sc_lab_report_runs', ['tenant_id' => $this->t, 'branch_id' => 0, 'report_date' => $date, 'recipient' => mb_substr(implode(', ', $to), 0, 190), 'subject' => mb_substr($subject, 0, 255), 'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : mb_substr($err, 0, 255), 'tries' => 1, 'files' => 'xlsx, pdf', 'user_id' => (int) ($this->p->user['id'] ?? 0) ?: null, 'created_at' => $now, 'updated_at' => $now]);
        }
        return $ok ? null : $err;
    }
}
