<?php
// Selfcare original code. Creating vendors and reading them for the public directory.
declare(strict_types=1);

namespace Selfcare;

final class Vendors
{
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
                'city' => $in['city'] ?? null, 'password' => password_hash((string) $in['password'], PASSWORD_BCRYPT), 'phone' => $in['phone'] ?? null,
                'is_super_admin_default' => 0, 'gender' => 0, 'is_admin_default' => 1, 'status' => 1, 'language' => 'en',
                'hospital_name' => $in['name'], 'tenant_id' => $slug, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('model_has_roles', ['role_id' => $adminRole, 'model_type' => Auth::ROLE_MODEL, 'model_id' => $uid]);
            $db->insert('sc_vendors', ['tenant_id' => $slug, 'type_slug' => $type['slug'], 'status' => 'active', 'city' => $in['city'] ?? null, 'phone' => $in['phone'] ?? null, 'is_listed' => 1]);
            $app->access->seedTenant($slug, $family);
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
                foreach (self::DEPARTMENTS as $title) {
                    $db->insert('doctor_departments', ['title' => $title, 'tenant_id' => $slug, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $slug;
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
