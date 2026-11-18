<?php
// Selfcare original code. One vendor portal request: who is signed in, which vendor, what they may see.
declare(strict_types=1);

namespace Selfcare;

final class Portal
{
    /** Plan module key => label shown in the plan editor. */
    public const MODULES = [
        'doctors' => 'Doctors / team list',
        'patients' => 'Patients / clients',
        'staff' => 'Staff accounts',
        'catalog' => 'Services, products, tests, fleet',
        'website' => 'Website editor (profile, hours, FAQ, gallery, news)',
        'free_services' => 'Free services',
        'messages' => 'Website messages',
        'access' => 'Role access and time access',
        'billing' => 'Invoices, receipts and payments',
        'documents' => 'Printed forms and records',
        'reports' => 'Reports and PDF export',
        'ipd' => 'Admissions, beds and discharge',
        'procurement' => 'Purchase orders and goods received',
        'insurance' => 'Insurance and TPA claims',
    ];

    /** Roles that belong to the people a vendor serves, not to its staff. */
    public const SIDE_ROLES = ['Patient', 'Family Member', 'Referring Doctor', 'B2B Customer'];

    /** @var array<string,mixed> */
    public array $user;
    /** @var array<string,mixed> */
    public array $vendor;
    /** @var array<string,mixed> */
    public array $type;
    /** @var array<string,mixed> */
    public array $tpl;
    public string $role;
    public string $family;
    public string $tenantId;
    /** @var array<string,mixed> */
    public array $sub;
    /** @var list<string> */
    public array $perms;

    private function __construct(private App $app)
    {
    }

    /** Signs-in check and context. Redirects or stops when the request is not allowed. */
    public static function boot(App $app): self
    {
        $user = $app->auth->user();
        if ($user === null) {
            flash('Please sign in to continue.', 'info');
            redirect(url('login'));
        }
        if ($user['role'] === 'Super Admin') {
            redirect($app->tenant->mainUrl('admin'));
        }
        $p = new self($app);
        $p->user = $user;
        $p->tenantId = (string) $user['tenant_id'];
        $row = $app->db->one('SELECT tenant_username FROM tenants WHERE id = ?', [$p->tenantId]);
        $vendor = $row === null ? null : $app->tenant->bySlug((string) $row['tenant_username']);
        if ($vendor === null) {
            $app->auth->logout();
            redirect(url('login'));
        }
        // sub domain mode: the portal must be opened on the vendor's own address
        if ($app->tenant->suffix() !== '') {
            if ($app->hostSlug === null) {
                $token = $app->auth->makeHandoff((int) $user['id']);
                redirect($app->vendorPortalUrl($vendor, 'auth/handoff') . '?token=' . $token);
            }
            if ($app->hostVendor === null || (string) $app->hostVendor['id'] !== $p->tenantId) {
                $app->auth->logout();
                redirect(url('login'));
            }
        }
        $p->vendor = $vendor;
        if ($vendor['status'] === 'suspended') {
            $app->auth->logout();
            flash('This account is suspended. Please contact Self Care.', 'bad');
            redirect(url('login'));
        }
        $p->role = (string) $user['role'];
        $block = $app->access->timeBlock($p->tenantId, (int) $user['id'], $p->role);
        if ($block !== null) {
            $app->auth->logout();
            flash($block, 'bad');
            redirect(url('login'));
        }
        $p->type = $app->typeRow((string) $vendor['type_slug']);
        $p->tpl = VendorTypes::template($p->type);
        $p->family = (string) $p->tpl['family'];
        $p->sub = $app->plans->current($p->tenantId);
        $p->perms = $app->access->effective($p->tenantId, $p->role, $p->family);
        return $p;
    }

    public function isSide(): bool
    {
        return in_array($this->role, self::SIDE_ROLES, true);
    }

    public function can(string $perm): bool
    {
        return in_array($perm, $this->perms, true);
    }

    public function module(string $module): bool
    {
        return $this->app->plans->allows($this->tenantId, $module);
    }

    public function writable(): bool
    {
        return $this->sub['state'] === 'active';
    }

    /** Stops with 403 unless the role has the permission and the plan has the module. */
    public function need(string $perm, ?string $module = null): void
    {
        if ($perm !== 'self' && ($this->isSide() || !$this->can($perm))) {
            $this->app->error(403, 'Your role does not have access to this page. Ask your administrator.', 'No access');
        }
        if ($module !== null && !$this->module($module)) {
            $this->app->error(403, 'This part is not included in your current plan.', 'Not in your plan');
        }
    }

    /** Blocks changes when the plan has expired. */
    public function needWritable(): void
    {
        if (!$this->writable()) {
            flash('Your plan has ended, so this account is read-only. Ask Self Care to renew it.', 'bad');
            redirect($_SERVER['HTTP_REFERER'] ?? url('portal/plan'));
        }
    }

    public function label(string $key, string $default = ''): string
    {
        return (string) ($this->tpl[$key] ?? $default);
    }

    /** Sidebar entries visible to this user. @return list<array<string,mixed>> grouped: ['group'=>..., 'items'=>[...]] */
    public function nav(): array
    {
        $t = $this->tpl;
        $all = [];
        $add = function (string $group, string $key, string $label, string $icon, string $path, ?string $perm, ?string $module = null) use (&$all): void {
            if ($this->isSide()) {
                return;
            }
            if ($perm !== null && !$this->can($perm)) {
                return;
            }
            if ($module !== null && !$this->module($module)) {
                return;
            }
            $all[$group][] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => url($path)];
        };
        if ($this->isSide()) {
            $all['Menu'][] = ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home', 'url' => url('portal')];
            $all['Menu'][] = ['key' => 'mine', 'label' => 'My requests', 'icon' => 'cal', 'url' => url('portal/mine')];
            $all['Menu'][] = ['key' => 'account', 'label' => 'My account', 'icon' => 'user', 'url' => url('portal/account')];
            return $this->shape($all);
        }
        $all['Main'][] = ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home', 'url' => url('portal')];
        $add('Main', 'requests', (string) $t['requests'], 'cal', 'portal/requests', 'manage_appointments');
        if ($this->hasKind('team')) {
            $add('People', 'doctors', (string) $t['team'], 'stethoscope', 'portal/doctors', 'manage_doctors', 'doctors');
        }
        if (!empty($t['people'])) {
            $add('People', 'patients', (string) $t['people'], 'users', 'portal/patients', 'manage_patients', 'patients');
        }
        $add('People', 'staff', 'Staff accounts', 'user', 'portal/staff', 'manage_users', 'staff');
        $add('Website', 'website', 'Profile and hours', 'globe', 'portal/website', 'manage_settings', 'website');
        foreach ($t['sections'] as $s) {
            $perm = 'manage_settings';
            $mod = 'website';
            if ($s['kind'] === 'cards') {
                $perm = 'manage_catalog';
                $mod = 'catalog';
            } elseif ($s['kind'] === 'news') {
                $perm = 'manage_notice_board';
            } elseif ($s['kind'] === 'departments') {
                $perm = 'manage_doctor_departments';
            } elseif (!in_array($s['kind'], ['faq', 'testimonials', 'gallery'], true)) {
                continue;
            }
            $add('Website', 'c-' . $s['key'], (string) $s['label'], match ($s['kind']) { 'cards' => (string) ($s['icon'] ?? 'layers'), 'faq' => 'help', 'testimonials' => 'star', 'gallery' => 'image', 'news' => 'news', default => 'layers' }, 'portal/c/' . $s['key'], $perm, $mod);
        }
        $add('Website', 'c-free-services', 'Free services', 'heart', 'portal/c/free-services', 'manage_free_services', 'free_services');
        $add('Website', 'messages', 'Messages', 'mail', 'portal/messages', 'view_messages', 'messages');
        $add('Money', 'billing', 'Invoices and bills', 'card', 'portal/billing', 'manage_invoices', 'billing');
        $add('Money', 'reports', 'Reports', 'chart', 'portal/reports', 'manage_reports', 'reports');
        if (in_array($this->family, ['pharmacy', 'bloodbank', 'equipment'], true)) {
            $add('Money', 'stock', 'Stock', 'box', 'portal/stock', 'manage_medicines', 'documents');
        }
        foreach (['manage_prescriptions', 'manage_medicines', 'manage_blood_bank'] as $dp) {
            if ($this->can($dp) && $this->module('documents')) {
                $all['Records'][] = ['key' => 'documents', 'label' => 'Forms and records', 'icon' => 'file', 'url' => url('portal/documents')];
                break;
            }
        }
        $add('Account', 'access', 'Role access', 'shield', 'portal/access', 'manage_access', 'access');
        $add('Account', 'settings', 'Bills and legal details', 'settings', 'portal/settings', 'manage_settings');
        $add('Account', 'plan', 'Plan and expiry', 'card', 'portal/plan', 'view_plan');
        $all['Account'][] = ['key' => 'account', 'label' => 'My account', 'icon' => 'user', 'url' => url('portal/account')];
        return $this->shape($all);
    }

    /** @param array<string,list<array<string,mixed>>> $all */
    private function shape(array $all): array
    {
        $out = [];
        foreach ($all as $g => $items) {
            $out[] = ['group' => $g, 'items' => $items];
        }
        return $out;
    }

    public function hasKind(string $kind): bool
    {
        foreach ($this->tpl['sections'] as $s) {
            if ($s['kind'] === $kind) {
                return true;
            }
        }
        return false;
    }

    /** Section config by key, or null. @return array<string,mixed>|null */
    public function section(string $key): ?array
    {
        foreach ($this->tpl['sections'] as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        return null;
    }
}
