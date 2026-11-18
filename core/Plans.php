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

    /** Default plans from the feature spec. All values are editable by the super admin. Added only when there are no plans. */
    public static function seedDefaults(Database $db): void
    {
        if ($db->val('SELECT COUNT(*) FROM sc_plans') > 0) {
            return;
        }
        $cols = ['name', 'description', 'currency', 'price', 'price_year', 'price_label', 'duration_days', 'max_staff', 'max_branches', 'max_patients', 'max_appointments', 'storage_gb', 'sms_per_month', 'email_per_month', 'custom_domains', 'custom_roles', 'audit_days', 'support_level', 'modules', 'is_active', 'is_featured', 'sort_order'];
        $rows = [
            ['Free', 'Free forever, with a Self Care badge. Data export always allowed.', 'USD', 0, 0, NULL, NULL, 3, 1, 100, 100, 0.5, 0, 100, 0, 0, 30, 'Community', 'doctors,patients,staff,website,free_services,messages,billing,documents,reports,access,catalog', 1, 0, 1],
            ['Starter', 'For small providers going online.', 'USD', 29, 290, NULL, NULL, 10, 1, 3000, 1000, 5, 100, 1000, 0, 3, 90, 'Email', 'doctors,patients,staff,website,free_services,messages,billing,documents,reports,access,catalog', 1, 0, 2],
            ['Professional', 'For growing hospitals and multi-service providers.', 'USD', 99, 990, NULL, NULL, 40, 3, 25000, 10000, 50, 1000, 10000, 1, 15, 365, 'Priority email and chat', 'doctors,patients,staff,website,free_services,messages,billing,documents,reports,access,catalog,ipd,procurement', 1, 1, 3],
            ['Business', 'For busy multi-branch providers.', 'USD', 249, 2490, NULL, NULL, 150, 10, 150000, 50000, 250, 5000, 50000, 3, 50, 1095, 'Named manager', 'doctors,patients,staff,website,free_services,messages,billing,documents,reports,access,catalog,ipd,procurement,insurance', 1, 0, 4],
            ['Enterprise', 'Custom limits, contract and support.', 'USD', 0, NULL, 'Custom', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SLA', '', 1, 0, 5],
        ];
        foreach ($rows as $r) {
            $db->insert('sc_plans', array_combine($cols, $r));
        }
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
            'SELECT s.*, p.name AS plan_name, p.price, p.currency, p.duration_days, p.max_staff, p.max_branches, p.max_patients, p.max_appointments, p.price_label, p.modules, p.types
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
        $col = ['staff' => 'max_staff', 'patients' => 'max_patients', 'appointments' => 'max_appointments'][$what] ?? null;
        if ($col === null || ($sub[$col] ?? null) === null) {
            return null;
        }
        $max = (int) $sub[$col];
        $n = match ($what) {
            'staff' => (int) $this->db->val("SELECT COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ? JOIN departments d ON d.id = m.role_id WHERE u.tenant_id = ? AND u.status = 1 AND d.name NOT IN ('Patient', 'Family Member', 'Referring Doctor', 'B2B Customer')", [Auth::ROLE_MODEL, $tenantId]),
            'patients' => (int) $this->db->val('SELECT COUNT(*) FROM patients WHERE tenant_id = ?', [$tenantId]),
            default => (int) $this->db->val("SELECT COUNT(*) FROM sc_appointment_requests WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$tenantId]),
        };
        $names = ['staff' => 'staff accounts', 'patients' => 'patient records', 'appointments' => 'requests this month'];
        return $n >= $max ? 'Your plan allows up to ' . $max . ' ' . $names[$what] . '. Ask Self Care to change your plan to add more.' : null;
    }

    /** Gives a vendor a plan. $days: null = use the plan length, 0 = never expires. */
    public function assign(string $tenantId, int $planId, ?int $days, ?string $note, ?int $by): void
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
                'status' => 'active', 'note' => $note !== null ? mb_substr($note, 0, 250) : null, 'assigned_by' => $by,
            ]);
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
