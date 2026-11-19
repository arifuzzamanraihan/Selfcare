<?php
// Selfcare original code. Subscription plans and vendor subscriptions.
//
// A plan has a price, a length in days (NULL = never expires), optional limits
// (NULL = unlimited) and an optional list of portal modules and vendor types.
// A vendor has at most one "active" subscription row. Changing plans marks the
// old row "replaced", revoking marks it "revoked". Nothing is ever deleted.
declare(strict_types=1);

namespace Selfcare;

final class Plans
{
    public function __construct(private Database $db)
    {
    }

    /** Who each group of public plans is for: audience label => vendor type slugs. */
    public const AUDIENCES = [
        'Clinics and specialty centers' => 'clinic,specialty-clinic,dental-clinic,eye-clinic,maternity-center,mental-health,alternative-medicine,rehabilitation,telemedicine,home-care',
        'Laboratories and diagnostic centers' => 'laboratory,diagnostic-center,imaging-center,blood-bank,dialysis-center',
        'Hospitals and nursing homes' => 'hospital,nursing-home',
        'Pharmacies and suppliers' => 'pharmacy,equipment-supplier',
        'Ambulance services' => 'ambulance',
    ];

    /**
     * The public catalogue: three sizes for each kind of provider, in Pakistani Rupees, with a yearly price of ten months.
     * name, audience, size label, description, monthly, yearly, branches, staff, patients, storage GB, modules, featured, support, extra feature lines
     * @return list<array<string,mixed>>
     */
    public static function catalogue(): array
    {
        $base = 'doctors,patients,staff,website,free_services,messages,billing,documents,catalog';
        $mid = $base . ',reports,access';
        $all = $mid . ',ipd,procurement,insurance';
        $A = array_keys(self::AUDIENCES);
        return [
            ['Starter', $A[0], 'One clinic', 'For a single clinic going digital.', 2500, 25000, 1, 5, 3000, 2, $base, 0, 'Email', "Doctor workspace with token queue\nPrescriptions and printed forms\nPatient My care portal", 500, 0],
            ['Professional', $A[0], 'Up to 3 branches', 'For a busy clinic with its own laboratory.', 6500, 65000, 3, 20, 25000, 10, $mid, 1, 'Priority email and chat', "Laboratory suite: MR numbers, samples, results with approval\nWhatsApp and email report sharing\nDoctor commission\nFinance: income, expenses and net result\nStock and inventory\nDaily report email and backups", 3000, 2000],
            ['Multi Branch', $A[0], 'Up to 10 branches', 'For clinic and laboratory groups.', 14000, 140000, 10, 80, 200000, 50, $all, 0, 'Named manager', "Everything in Professional\nBranch wise access control and consolidated reports\nPartner laboratory commission\nStaff attendance with PIN and salary\nScheduled daily backups", 15000, 1500],
            ['Starter', $A[1], 'One laboratory', 'For a single laboratory or diagnostic centre.', 2500, 25000, 1, 5, 5000, 2, $base, 0, 'Email', "Test catalogue, orders and bills\nPrinted reports and receipts", 500, 0],
            ['Professional', $A[1], 'Up to 3 branches', 'For a growing laboratory with collection points.', 6000, 60000, 3, 20, 40000, 10, $mid, 1, 'Priority email and chat', "Role based access for every desk\nReports with Excel and PDF export\nDoctor and partner referrals\nSample tracking and approval", 3000, 1800],
            ['Multi Branch', $A[1], 'Up to 10 branches', 'For laboratory chains.', 16000, 160000, 10, 80, 250000, 50, $all, 0, 'Named manager', "Everything in Professional\nConsolidated reports across branches\nProcurement and goods received", 15000, 1500],
            ['Standard', $A[2], 'Up to 30 beds', 'OPD and IPD for a small hospital.', 18000, 180000, 1, 30, 20000, 20, $mid . ',ipd', 0, 'Priority email and chat', "Admissions, beds and discharge\nDoctor workspace and token queue\nBilling and printed forms", 5000, 0],
            ['Professional', $A[2], 'Up to 3 branches', 'For a hospital with departments and purchasing.', 38000, 380000, 3, 100, 100000, 60, $mid . ',ipd,procurement', 1, 'Named manager', "Departments and wards\nPurchase orders and goods received\nAdvanced reports", 15000, 8000],
            ['Enterprise', $A[2], 'Up to 10 branches', 'For hospital groups with insurance claims.', 70000, 700000, 10, 400, 500000, 200, $all, 0, 'Named manager and SLA', "Insurance and TPA claims\nConsolidated reports across branches\nCustom roles and long audit history", 50000, 6000],
            ['Starter', $A[3], 'One shop', 'For a single pharmacy or equipment shop.', 2000, 20000, 1, 3, 5000, 2, $base, 0, 'Email', "Items, stock and bills\nPrinted receipts", 300, 0],
            ['Professional', $A[3], 'Up to 3 shops', 'For a pharmacy with several counters.', 4500, 45000, 3, 10, 30000, 10, $mid, 1, 'Priority email and chat', "Stock with batches and expiry\nReports with Excel and PDF export", 1500, 1500],
            ['Chain', $A[3], 'Up to 10 shops', 'For pharmacy chains and suppliers.', 11000, 110000, 10, 40, 150000, 40, $all, 0, 'Named manager', "Everything in Professional\nProcurement and goods received\nConsolidated reports", 8000, 1200],
            ['Starter', $A[4], 'Small fleet', 'For one or two vehicles.', 2000, 20000, 1, 3, 2000, 2, $base, 0, 'Email', "Vehicles, drivers and trips\nBilling and receipts", 300, 0],
            ['Professional', $A[4], 'Growing fleet', 'For a dispatch desk with several vehicles.', 5000, 50000, 2, 10, 10000, 10, $mid, 1, 'Priority email and chat', "Dispatch desk and requests\nReports with Excel and PDF export", 1500, 1500],
            ['Fleet', $A[4], 'Large fleet', 'For ambulance networks.', 10000, 100000, 10, 40, 80000, 40, $all, 0, 'Named manager', "Everything in Professional\nConsolidated reports across branches", 6000, 1200],
        ];
    }

    /** Adds the catalogue plans that are missing (matched by name and audience). Returns how many were added. */
    public static function seedCatalogue(Database $db): int
    {
        $cols = ['name', 'audience', 'size_label', 'description', 'currency', 'price', 'price_year', 'duration_days', 'max_staff', 'max_branches', 'max_patients', 'storage_gb', 'modules', 'types', 'is_featured', 'support_level', 'features', 'is_active', 'is_private', 'sort_order', 'audit_days', 'custom_roles', 'custom_domains', 'email_per_month', 'sms_per_month', 'trial_days', 'extra_branch_price'];
        $n = 0;
        $ord = 10;
        foreach (self::catalogue() as $r) {
            [$name, $aud, $size, $desc, $m, $y, $br, $st, $pa, $gb, $mods, $feat, $sup, $lines, $em, $xb] = $r;
            $ord += 1;
            if ((int) $db->val('SELECT COUNT(*) FROM sc_plans WHERE name = ? AND audience = ?', [$name, $aud]) > 0) {
                continue;
            }
            $big = $br >= 10;
            $db->insert('sc_plans', array_combine($cols, [$name, $aud, $size, $desc, 'PKR', $m, $y, null, $st, $br, $pa, $gb, $mods, self::AUDIENCES[$aud], $feat, $sup, $lines, 1, 0, $ord, $big ? 1095 : 365, $big ? 20 : ($m >= 8000 ? 8 : 3), $big ? 3 : ($m >= 8000 ? 1 : 0), $em, 0, 14, $xb > 0 ? $xb : null]));
            $n++;
        }
        return $n;
    }

    /** Brings the catalogue plans that already exist up to the current numbers (matched by name and audience). Never touches Free or private plans. Returns how many were updated. */
    public static function refreshCatalogue(Database $db): int
    {
        $n = 0;
        foreach (self::catalogue() as $r) {
            [$name, $aud, $size, $desc, $m, $y, $br, $st, $pa, $gb, $mods, $feat, $sup, $lines, $em, $xb] = $r;
            $n += $db->run('UPDATE sc_plans SET price = ?, price_year = ?, max_branches = ?, max_staff = ?, max_patients = ?, storage_gb = ?, email_per_month = ?, sms_per_month = 0, trial_days = 14, extra_branch_price = ?, features = ?, support_level = ?, size_label = ?, description = ? WHERE name = ? AND audience = ? AND is_private = 0', [$m, $y, $br, $st, $pa, $gb, $em, $xb > 0 ? $xb : null, $lines, $sup, $size, $desc, $name, $aud]);
        }
        $db->run("UPDATE sc_plans SET email_per_month = 100, sms_per_month = 0 WHERE price = 0 AND is_private = 0 AND email_per_month IS NULL");
        return $n;
    }

    /** The private Ultimate plan: no limit on anything, every module, every kind of vendor, never expires. Never shown on the website or at registration. */
    public static function seedUltimate(Database $db): int
    {
        if ((int) $db->val("SELECT COUNT(*) FROM sc_plans WHERE name = 'Ultimate' AND is_private = 1") > 0) {
            return 0;
        }
        $db->insert('sc_plans', ['name' => 'Ultimate', 'description' => 'Private plan with no limits. Only Self Care can give it.', 'currency' => 'PKR', 'price' => 0, 'price_year' => null, 'price_label' => 'Custom', 'duration_days' => null, 'modules' => '', 'types' => '', 'support_level' => 'Named manager and SLA', 'is_active' => 1, 'is_private' => 1, 'is_featured' => 0, 'sort_order' => 999, 'audience' => null, 'features' => "No limit on branches, staff, patients, storage or messages\nEvery module and every feature\nPrivate: given by Self Care only"]);
        return 1;
    }

    /** Default plans. Added only when there are no plans. */
    public static function seedDefaults(Database $db): void
    {
        if ($db->val('SELECT COUNT(*) FROM sc_plans') > 0) {
            return;
        }
        $db->insert('sc_plans', ['name' => 'Free', 'description' => 'Try Self Care with a very small team. Data export always allowed.', 'currency' => 'PKR', 'price' => 0, 'price_year' => 0, 'duration_days' => null, 'max_staff' => 3, 'max_branches' => 1, 'max_patients' => 100, 'max_appointments' => 100, 'storage_gb' => 0.5, 'audit_days' => 30, 'support_level' => 'Community', 'modules' => 'doctors,patients,staff,website,free_services,messages,billing,documents,reports,access,catalog', 'types' => '', 'features' => "Your own website and portal\nPatients, doctors and billing\nSelf Care badge", 'is_active' => 1, 'sort_order' => 1]);
        self::seedCatalogue($db);
        self::seedUltimate($db);
    }

    /** Public plans: offered on the website and at registration. Private plans are never in this list. @return list<array<string,mixed>> */
    public function publicPlans(): array
    {
        return $this->db->all('SELECT * FROM sc_plans WHERE is_active = 1 AND is_private = 0 ORDER BY sort_order, price, id');
    }

    /** Lines for a plan card, built from the plan record itself: size, limits, portal parts, extras, support. @return list<string> */
    public static function featureLines(array $p): array
    {
        $lim = static fn ($v, string $one, string $many): ?string => $v === null ? 'Unlimited ' . $many : number_format((float) $v) . ' ' . ((float) $v == 1 ? $one : $many);
        $o = [];
        foreach ([[$p['max_branches'] ?? null, 'branch', 'branches'], [$p['max_staff'] ?? null, 'staff user', 'staff users'], [$p['max_patients'] ?? null, 'patient record', 'patient records']] as [$v, $a, $b]) {
            $o[] = $lim($v, $a, $b);
        }
        if (($p['storage_gb'] ?? null) !== null && (float) $p['storage_gb'] > 0) {
            $o[] = rtrim(rtrim(number_format((float) $p['storage_gb'], 1), '0'), '.') . ' GB storage';
        }
        if (($p['email_per_month'] ?? null) !== null && (int) $p['email_per_month'] > 0) {
            $o[] = number_format((int) $p['email_per_month']) . ' emails a month';
        }
        foreach (preg_split('/\r?\n/', (string) ($p['features'] ?? '')) ?: [] as $l) {
            if (trim($l) !== '') {
                $o[] = trim($l);
            }
        }
        if ((float) ($p['extra_branch_price'] ?? 0) > 0) {
            $o[] = 'Extra branch: ' . Currency::fmt((float) $p['extra_branch_price'], (string) ($p['currency'] ?? 'PKR'), true) . ' a month each';
        }
        if ((int) ($p['trial_days'] ?? 0) > 0 && (float) $p['price'] > 0) {
            $o[] = (int) $p['trial_days'] . ' day free trial';
        }
        $mods = array_filter(array_map('trim', explode(',', (string) ($p['modules'] ?? ''))));
        $names = ['reports' => 'Reports and PDF export', 'access' => 'Role access and time access', 'ipd' => 'Admissions, beds and discharge', 'procurement' => 'Purchase orders and goods received', 'insurance' => 'Insurance and TPA claims'];
        foreach ($names as $k => $label) {
            if (!$mods || in_array($k, $mods, true)) {
                if (!in_array($label, $o, true) && !preg_grep('/' . preg_quote(strtok($label, ' '), '/') . '/i', $o)) {
                    $o[] = $label;
                }
            }
        }
        if (trim((string) ($p['support_level'] ?? '')) !== '') {
            $o[] = 'Support: ' . $p['support_level'];
        }
        return array_values(array_filter($o));
    }

    /** Yearly saving in percent against twelve months, or 0. */
    public static function yearSaving(array $p): int
    {
        $m = (float) $p['price'];
        $y = (float) ($p['price_year'] ?? 0);
        return ($m > 0 && $y > 0 && $y < $m * 12) ? (int) round((1 - $y / ($m * 12)) * 100) : 0;
    }

    /** @return list<array<string,mixed>> */
    public function all(bool $onlyActive = false): array
    {
        return $this->db->all('SELECT * FROM sc_plans' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, price, id');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM sc_plans WHERE id = ?', [$id]);
    }

    /** Extra prices typed as lines "PKR 8000". @return array<string,float> */
    public static function otherPrices(array $plan): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', (string) ($plan['other_prices'] ?? '')) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z]{3})\s*[:=]?\s*([0-9][0-9.]*)\s*$/', $line, $m)) {
                $out[strtoupper($m[1])] = (float) $m[2];
            }
        }
        return $out;
    }

    /** Monthly price to show: [amount, currency]. Uses the price in $want (default: platform currency) when the plan has one. @return array{0:float,1:string} */
    public static function price(array $plan, ?string $want = null): array
    {
        $want = $want ?? Currency::platform();
        if ((float) $plan['price'] == 0.0 && !self::otherPrices($plan)) {
            return [0.0, $want];
        }
        if ((string) $plan['currency'] === $want) {
            return [(float) $plan['price'], $want];
        }
        $o = self::otherPrices($plan);
        if (isset($o[$want])) {
            return [$o[$want], $want];
        }
        if (isset(Currency::all()[$want]) && isset(Currency::all()[(string) $plan['currency']])) {
            // no price set for this currency: convert with the rates, to a whole number of the main unit
            return [round(Currency::convert((float) $plan['price'], (string) $plan['currency'], $want)), $want];
        }
        return [(float) $plan['price'], (string) $plan['currency']];
    }

    public static function priceText(array $plan, ?string $want = null): string
    {
        [$a, $c] = self::price($plan, $want);
        return Currency::fmt($a, $c, true);
    }

    /** Yearly price to show: [amount, currency]. Same rules as price(). @return array{0:float,1:string} */
    public static function yearPrice(array $plan, ?string $want = null): array
    {
        $want = $want ?? Currency::platform();
        $year = (float) ($plan['price_year'] ?? 0);
        if ($year <= 0) {
            return [0.0, $want];
        }
        $tmp = $plan;
        $tmp['price'] = $year;
        return self::price($tmp, $want);
    }

    /** Is this plan offered to a vendor type? */
    public function fitsType(array $plan, string $typeSlug): bool
    {
        $types = array_filter(array_map('trim', explode(',', (string) $plan['types'])));
        return !$types || in_array($typeSlug, $types, true);
    }

    /** Current subscription with state: active | expired | none. @return array<string,mixed> */
    public function current(string $tenantId): array
    {
        $row = $this->db->one(
            'SELECT s.*, p.name AS plan_name, p.price, p.currency, p.duration_days, p.max_staff, p.max_branches, p.max_patients, p.max_appointments, p.price_label, p.modules, p.types, p.email_per_month, p.extra_branch_price, p.trial_days
             FROM sc_subscriptions s JOIN sc_plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ? AND s.status = ? ORDER BY s.id DESC LIMIT 1',
            [$tenantId, 'active']
        );
        if ($row === null) {
            return ['state' => 'none', 'plan_name' => 'No plan', 'ends_at' => null, 'days_left' => null, 'modules' => '', 'max_staff' => 0, 'max_patients' => 0, 'max_appointments' => 0];
        }
        $ends = $row['ends_at'];
        $left = $ends === null ? null : (int) floor((strtotime((string) $ends) - time()) / 86400);
        $row['days_left'] = $left;
        $row['state'] = ($ends === null || strtotime((string) $ends) > time()) ? 'active' : 'expired';
        return $row;
    }

    /** True when the vendor may add or change data. */
    public function writable(string $tenantId): bool
    {
        return $this->current($tenantId)['state'] === 'active';
    }

    /** Is a portal module allowed by the plan? Empty module list means all. */
    public function allows(string $tenantId, string $module): bool
    {
        $sub = $this->current($tenantId);
        $mods = array_filter(array_map('trim', explode(',', (string) $sub['modules'])));
        return !$mods || in_array($module, $mods, true) || in_array($module, ['overview', 'plan', 'requests'], true);
    }

    /** Can the vendor add one more staff user / patient record / request this month? Returns an error text or null. */
    public function limitError(string $tenantId, string $what): ?string
    {
        $sub = $this->current($tenantId);
        $col = ['staff' => 'max_staff', 'patients' => 'max_patients', 'appointments' => 'max_appointments', 'branches' => 'max_branches'][$what] ?? null;
        if ($col === null || ($sub[$col] ?? null) === null) {
            return null;
        }
        $max = (int) $sub[$col] + ($what === 'branches' ? (int) ($sub['extra_branches'] ?? 0) : 0);
        $n = match ($what) {
            'staff' => (int) $this->db->val("SELECT COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND u.status = 1 AND d.name NOT IN ('Patient', 'Family Member', 'Referring Doctor', 'B2B Customer')", [Auth::ROLE_MODEL, $tenantId]),
            'branches' => (int) $this->db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ? AND active = 1', [$tenantId]),
            'patients' => (int) $this->db->val('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$tenantId]),
            default => (int) $this->db->val("SELECT COUNT(*) FROM sc_appointment_requests WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$tenantId]),
        };
        $names = ['staff' => 'staff accounts', 'patients' => 'patient records', 'appointments' => 'requests this month', 'branches' => 'branches'];
        return $n >= $max ? 'Your plan allows up to ' . $max . ' ' . $names[$what] . '. Ask Self Care to change your plan or add branches.' : null;
    }

    /** Emails the vendor's own work sent this month (reports, shared documents, notices; sign in and security mail never counts). */
    public function emailsUsed(string $tenantId): int
    {
        $m = date('Y-m-01');
        $q = (int) $this->db->val("SELECT COUNT(*) FROM sc_mail_queue WHERE tenant_id = ? AND status = 'sent' AND sent_at >= ? AND kind NOT IN ('verify','email_change','invite','password_changed','email_changed','reset','registration')", [$tenantId, $m]);
        try {
            $q += (int) $this->db->val("SELECT COUNT(*) FROM sc_lab_delivery_log WHERE tenant_id = ? AND channel = 'email' AND status = 'sent' AND updated_at >= ?", [$tenantId, $m]);
            $q += (int) $this->db->val("SELECT COALESCE(SUM(1 + LENGTH(recipient) - LENGTH(REPLACE(recipient, ',', ''))), 0) FROM sc_lab_report_runs WHERE tenant_id = ? AND status = 'sent' AND updated_at >= ?", [$tenantId, $m]);
        } catch (\Throwable) {
        }
        return $q;
    }

    /** The plan caps emails a month so mail cost stays predictable. Returns a message when the allowance is used up. */
    public function emailLimitError(string $tenantId): ?string
    {
        $sub = $this->current($tenantId);
        $max = $sub['email_per_month'] ?? null;
        if ($max === null || $sub['state'] === 'none') {
            return null;
        }
        return $this->emailsUsed($tenantId) >= (int) $max ? 'The ' . $sub['plan_name'] . ' plan includes ' . number_format((int) $max) . ' emails a month and this month\'s allowance is used. Sign in and security mail still works. Ask Self Care for a bigger plan.' : null;
    }

    /** Gives a vendor a plan. $days: null = use the plan length, 0 = never expires. */
    public function assign(string $tenantId, int $planId, ?int $days, ?string $note, ?int $by, int $extraBranches = 0): void
    {
        $plan = $this->find($planId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Plan not found');
        }
        $len = $days ?? ($plan['duration_days'] === null ? 0 : (int) $plan['duration_days']);
        $this->db->begin();
        try {
            $this->db->run("UPDATE sc_subscriptions SET status = 'replaced' WHERE tenant_id = ? AND status = 'active'", [$tenantId]);
            $this->db->insert('sc_subscriptions', [
                'tenant_id' => $tenantId, 'plan_id' => $planId, 'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => $len > 0 ? date('Y-m-d H:i:s', time() + $len * 86400) : null,
                'status' => 'active', 'note' => $note !== null ? mb_substr($note, 0, 250) : null, 'assigned_by' => $by, 'extra_branches' => max(0, $extraBranches),
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Gives a plan for an exact date range (ends null = never ends). */
    public function assignRange(string $tenantId, int $planId, string $starts, ?string $ends, ?string $note, ?int $by): void
    {
        if ($this->find($planId) === null) {
            throw new \InvalidArgumentException('Plan not found');
        }
        $this->db->begin();
        try {
            $this->db->run("UPDATE sc_subscriptions SET status = 'replaced' WHERE tenant_id = ? AND status = 'active'", [$tenantId]);
            $this->db->insert('sc_subscriptions', ['tenant_id' => $tenantId, 'plan_id' => $planId, 'starts_at' => $starts, 'ends_at' => $ends, 'status' => 'active', 'note' => $note !== null ? mb_substr($note, 0, 250) : null, 'assigned_by' => $by]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Adds days to the current subscription (starts from today if it already ended). */
    public function extend(string $tenantId, int $days, ?int $by): bool
    {
        $cur = $this->current($tenantId);
        if ($cur['state'] === 'none' || $days <= 0) {
            return false;
        }
        if ($cur['ends_at'] === null) {
            return false; // never expires, nothing to extend
        }
        $base = max(time(), strtotime((string) $cur['ends_at']));
        $this->db->run('UPDATE sc_subscriptions SET ends_at = ?, note = ? WHERE id = ?', [date('Y-m-d H:i:s', $base + $days * 86400), 'Extended by ' . $days . ' days', (int) $cur['id']]);
        return true;
    }

    public function setNoExpiry(string $tenantId): bool
    {
        $cur = $this->current($tenantId);
        return $cur['state'] !== 'none' && $this->db->run('UPDATE sc_subscriptions SET ends_at = NULL, note = ? WHERE id = ?', ['Set to never expire', (int) $cur['id']]) > 0;
    }

    public function revoke(string $tenantId): void
    {
        $this->db->run("UPDATE sc_subscriptions SET status = 'revoked' WHERE tenant_id = ? AND status = 'active'", [$tenantId]);
    }

    /** Plain text summary: "Free, never expires" / "Professional, 12 days left". */
    public function label(array $sub): string
    {
        return match ($sub['state']) {
            'none' => 'No plan',
            'expired' => $sub['plan_name'] . ', expired ' . dt((string) $sub['ends_at']),
            default => $sub['plan_name'] . ($sub['ends_at'] === null ? ', never expires' : ', ends ' . dt((string) $sub['ends_at'])),
        };
    }
}
