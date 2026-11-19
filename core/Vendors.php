<?php
// Selfcare original code. Creating vendors and reading them for the public directory.
declare(strict_types=1);

namespace Selfcare;

final class Vendors
{
    /** Medical departments a hospital starts with (the vendor edits the list in the portal). */
    public const HOSPITAL_DEPARTMENTS = ['General Medicine', 'General Surgery', 'Emergency Medicine', 'Paediatrics', 'Neonatology (NICU)', 'Obstetrics and Gynaecology', 'Orthopaedics', 'Cardiology', 'Neurology', 'Neurosurgery', 'Nephrology and Dialysis', 'Urology', 'Gastroenterology', 'Pulmonology and Chest', 'Oncology', 'ENT', 'Ophthalmology', 'Dental and Maxillofacial', 'Dermatology', 'Psychiatry', 'Endocrinology and Diabetes', 'Anaesthesia and Critical Care (ICU)', 'Radiology and Imaging', 'Pathology and Laboratory', 'Physiotherapy and Rehabilitation', 'Nutrition and Dietetics', 'Plastic and Burns', 'Vascular Surgery'];
    private const DEPARTMENTS = ['General Medicine', 'Paediatrics', 'Gynaecology', 'Orthopaedics', 'Cardiology', 'Dermatology'];

    /**
     * Creates tenant, admin user, role link, vendor facts, default grants, default plan and starter content.
     * @param array<string,mixed> $in type, name, slug, city, phone, first_name, last_name, email, password, plan_id?
     * @return string tenant id
     */
    public static function create(App $app, array $in): string
    {
        $db = $app->db;
        $slug = (string) $in['slug'];
        $type = $app->typeRow((string) $in['type']);
        $family = (string) VendorTypes::template($type)['family'];
        $adminRole = $app->access->roleId('Admin') ?? 1;
        $db->begin();
        try {
            $db->insert('tenants', ['id' => $slug, 'tenant_username' => $slug, 'hospital_name' => $in['name'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $uid = $db->insert('users', [
                'department_id' => $adminRole, 'first_name' => $in['first_name'], 'last_name' => $in['last_name'] ?? '', 'email' => $in['email'],
                'city' => $in['city'] ?? null, 'password' => $in['password_hash'] ?? password_hash((string) $in['password'], PASSWORD_BCRYPT), 'phone' => $in['phone'] ?? null,
                'is_super_admin_default' => 0, 'gender' => 0, 'is_admin_default' => 1, 'status' => 1, 'language' => 'en',
                'hospital_name' => $in['name'], 'tenant_id' => $slug, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('model_has_roles', ['role_id' => $adminRole, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
            $db->insert('sc_vendors', ['tenant_id' => $slug, 'type_slug' => $type['slug'], 'status' => in_array($in['status'] ?? 'active', ['active', 'suspended'], true) ? $in['status'] ?? 'active' : 'active', 'city' => $in['city'] ?? null, 'phone' => $in['phone'] ?? null, 'is_listed' => 1]);
            $bs = new Billing($db);
            $bs->setSetting($slug, 'currency', isset($in['currency']) && isset(Currency::all(true)[(string) $in['currency']]) ? (string) $in['currency'] : Currency::platform());
            $bs->setSetting($slug, 'country', (string) ($in['country'] ?? $app->site->get(null, 'default_country', 'Pakistan')));
            $bs->setSetting($slug, 'timezone', (string) ($in['timezone'] ?? $app->site->get(null, 'default_timezone', 'Asia/Karachi')));
            $app->access->seedTenant($slug, $family, (string) $type["slug"]);
            $planId = (int) ($in['plan_id'] ?? 0);
            if (!$planId) {
                $planId = (int) $app->site->get(null, 'default_plan_id', '0');
            }
            $plan = $planId ? $app->plans->find($planId) : null;
            if ($plan === null || !$app->plans->fitsType($plan, (string) $type['slug'])) {
                $plan = $db->one('SELECT * FROM sc_plans WHERE price = 0 AND is_active = 1 ORDER BY id LIMIT 1') ?? $db->one('SELECT * FROM sc_plans ORDER BY id LIMIT 1');
            }
            if ($plan !== null) {
                $app->plans->assign($slug, (int) $plan['id'], null, 'Given at registration', null);
            }
            foreach (['sat', 'sun', 'mon', 'tue', 'wed', 'thu'] as $d) {
                $app->site->set($slug, 'hours_' . $d, '09:00 - 17:00');
            }
            $app->site->set($slug, 'hours_fri', 'Closed');
            if ($family === 'clinical') {
                foreach (($type['slug'] === 'hospital' ? self::HOSPITAL_DEPARTMENTS : self::DEPARTMENTS) as $title) {
                    $db->insert('doctor_departments', ['title' => $title, 'tenant_id' => $slug, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        try {
            $app->domains->queueSubdomain($slug); // SSL for the new address (worker picks it up within minutes)
        } catch (\Throwable) {
        }
        return $slug;
    }

    /** Why a web address cannot be used, or null when it is free. Checks the rules, reserved words, vendors and waiting requests. */
    public static function slugError(App $app, string $slug, ?int $exceptRequest = null): ?string
    {
        if (!$app->tenant->validSlug($slug)) {
            return 'The web address must be 3 to 40 letters, numbers or dashes, and cannot be a reserved word.';
        }
        if ($app->db->val('SELECT COUNT(*) FROM tenants WHERE tenant_username = ? OR id = ?', [$slug, $slug]) > 0) {
            return 'That web address is already taken.';
        }
        if ($app->db->val("SELECT COUNT(*) FROM sc_registration_requests WHERE slug = ? AND status IN ('pending','unverified') AND id <> ?", [$slug, (int) $exceptRequest]) > 0) {
            return 'That web address is already asked for by a waiting registration.';
        }
        return null;
    }

    /** Deletes a vendor and everything that belongs to it for good. Only for vendors that are already soft deleted. */
    public static function purge(Database $db, string $slug): void
    {
        $db->begin();
        try {
            $db->run('SET FOREIGN_KEY_CHECKS = 0');
            $uids = array_column($db->all('SELECT id FROM users WHERE tenant_id = ?', [$slug]), 'id');
            $db->run('DELETE i FROM sc_invoice_items i JOIN sc_invoices v ON v.id = i.invoice_id WHERE v.tenant_id = ?', [$slug]);
            $db->run('DELETE p FROM sc_invoice_payments p JOIN sc_invoices v ON v.id = p.invoice_id WHERE v.tenant_id = ?', [$slug]);
            foreach ($uids as $uid) {
                $db->run('DELETE FROM model_has_roles WHERE model_id = ? AND model_type = ?', [(int) $uid, Auth::ROLE_MODEL]);
                $db->run('DELETE FROM sc_password_resets WHERE user_id = ?', [(int) $uid]);
                $db->run('DELETE FROM sc_login_tokens WHERE user_id = ?', [(int) $uid]);
                $db->run('DELETE FROM sc_email_tokens WHERE user_id = ?', [(int) $uid]);
            }
            foreach ($db->all("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id' AND TABLE_NAME NOT IN ('tenants', 'permission_change_log')") as $r) {
                $db->run('DELETE FROM `' . str_replace('`', '', (string) $r['t']) . '` WHERE tenant_id = ?', [$slug]);
            }
            $db->run('DELETE FROM tenants WHERE id = ?', [$slug]);
            $db->run('SET FOREIGN_KEY_CHECKS = 1');
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $db->run('SET FOREIGN_KEY_CHECKS = 1');
            throw $e;
        }
    }

    /**
     * Listed vendors for the public pages.
     * @return list<array<string,mixed>>
     */
    public static function listed(Database $db, ?string $type = null, string $q = '', int $limit = 60, int $offset = 0): array
    {
        $where = ["v.status = 'active'", 'v.is_listed = 1'];
        $p = [];
        if ($type !== null && $type !== '') {
            $where[] = 'v.type_slug = ?';
            $p[] = $type;
        }
        if ($q !== '') {
            $where[] = '(t.hospital_name LIKE ? OR v.city LIKE ?)';
            $p[] = '%' . addcslashes($q, '%_\\') . '%';
            $p[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        return $db->all(
            "SELECT t.id, t.tenant_username AS slug, t.hospital_name AS name, v.type_slug, v.city, v.phone, ty.name AS type_name, ty.icon AS type_icon,
                    (SELECT fs.`value` FROM front_settings fs WHERE fs.tenant_id = t.id AND fs.`key` = 'cover_image' LIMIT 1) AS cover,
                    (SELECT fs.`value` FROM front_settings fs WHERE fs.tenant_id = t.id AND fs.`key` = 'tagline' LIMIT 1) AS tagline
             FROM tenants t JOIN sc_vendors v ON v.tenant_id = t.id LEFT JOIN sc_vendor_types ty ON ty.slug = v.type_slug
             WHERE " . implode(' AND ', $where) . " ORDER BY ty.sort_order, t.hospital_name LIMIT $limit OFFSET $offset",
            $p
        );
    }

    /** Active free services across listed vendors. @return list<array<string,mixed>> */
    public static function freeServices(Database $db, ?string $type = null, string $q = '', int $limit = 60): array
    {
        $where = ["v.status = 'active'", 'v.is_listed = 1', 'f.is_active = 1'];
        $p = [];
        if ($type) {
            $where[] = 'v.type_slug = ?';
            $p[] = $type;
        }
        if ($q !== '') {
            $where[] = '(f.title LIKE ? OR f.description LIKE ? OR t.hospital_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        return $db->all(
            'SELECT f.*, t.tenant_username AS slug, t.hospital_name AS vendor_name, v.type_slug, v.city, ty.name AS type_name
             FROM free_services f JOIN tenants t ON t.id = f.tenant_id JOIN sc_vendors v ON v.tenant_id = t.id LEFT JOIN sc_vendor_types ty ON ty.slug = v.type_slug
             WHERE ' . implode(' AND ', $where) . " ORDER BY f.sort_order, f.id DESC LIMIT $limit",
            $p
        );
    }
}
