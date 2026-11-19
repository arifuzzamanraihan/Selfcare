<?php
// Selfcare original code. Session login against the users table, with roles.
//
// Roles live in the existing tables: model_has_roles links a user to a row of
// the "departments" table (Admin, Doctor, Patient, Nurse, Receptionist, Pharmacist,
// Accountant, Case Manager, Lab Technician, Super Admin).
declare(strict_types=1);

namespace Selfcare;

final class Auth
{
    public const ROLE_MODEL = 'App\\Models\\User';

    /** @var array<string,mixed>|null|false */
    private $cache = false;

    public function __construct(private Database $db)
    {
    }

    /** Checks the password and returns the user id, or null. Does not sign in. */
    public function verify(string $email, string $password, ?string $tenantId): ?int
    {
        $user = $this->db->one('SELECT id, password, status, tenant_id, deleted_at FROM users WHERE email = ? LIMIT 1', [$email]);
        // Always run one hash check so unknown emails and wrong passwords take the same time.
        $hash = $user['password'] ?? '$2y$10$4eDrck0H92ZyIWUUCDvMEO7kjLUk.FOnBfaiAZ4p639/pxSw3N4z6';
        $ok = password_verify($password, (string) $hash);
        if ($user === null || !$ok || (int) $user['status'] !== 1 || $user['deleted_at'] !== null) {
            return null;
        }
        if ($tenantId !== null && (string) $user['tenant_id'] !== $tenantId) {
            return null;
        }
        if ($user['tenant_id'] !== null && $this->vendorDeleted((string) $user['tenant_id'])) {
            return null;
        }
        return (int) $user['id'];
    }

    /** True when the vendor was soft deleted (sc_vendors.status = deleted). */
    public function vendorDeleted(string $tenantId): bool
    {
        return (string) $this->db->val('SELECT status FROM sc_vendors WHERE tenant_id = ?', [$tenantId]) === 'deleted';
    }

    /** Signs a user in (new session id to stop session fixation). */
    public function login(int $userId, bool $record = true): void
    {
        session_regenerate_id(true);
        unset($_SESSION['sc_impersonator']); // a normal sign in always ends "viewing as"
        $_SESSION['selfcare_user_id'] = $userId;
        $this->cache = false;
        if ($record) {
            $this->db->run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
        }
    }

    /** Back-compatible helper from phase 1. */
    public function attempt(string $email, string $password, ?string $tenantId = null): bool
    {
        $id = $this->verify($email, $password, $tenantId);
        if ($id === null) {
            return false;
        }
        $this->login($id);
        return true;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->cache !== false) {
            return $this->cache;
        }
        $id = $_SESSION['selfcare_user_id'] ?? null;
        if ($id === null) {
            return $this->cache = null;
        }
        $u = $this->db->one(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.hospital_name, u.tenant_id, u.status, u.is_super_admin_default, u.email_verified_at, u.pending_email, d.name AS role
             FROM users u
             LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ?
             LEFT JOIN departments d ON d.id = m.role_id
             WHERE u.id = ? LIMIT 1',
            [self::ROLE_MODEL, (int) $id]
        );
        if ($u === null || (int) $u['status'] !== 1 || ($u['tenant_id'] !== null && $this->vendorDeleted((string) $u['tenant_id']))) {
            unset($_SESSION['selfcare_user_id'], $_SESSION['sc_impersonator']);
            return $this->cache = null;
        }
        $u['role'] = (int) $u['is_super_admin_default'] === 1 ? 'Super Admin' : (string) ($u['role'] ?? 'Patient');
        return $this->cache = $u;
    }

    public function isSuperAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'Super Admin';
    }

    /** Super admin id while "viewing as" a vendor (Open portal), else null. */
    public function impersonator(): ?int
    {
        $id = $_SESSION['sc_impersonator'] ?? null;
        return $id === null ? null : (int) $id;
    }

    public function logout(): void
    {
        unset($_SESSION['selfcare_user_id'], $_SESSION['sc_impersonator']);
        $this->cache = false;
        session_regenerate_id(true);
    }

    /** One-time token to carry a signed-in user to a vendor sub domain. */
    public function makeHandoff(int $userId, ?int $impersonatorId = null): string
    {
        $token = bin2hex(random_bytes(24));
        $this->db->run('DELETE FROM sc_login_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
        $this->db->insert('sc_login_tokens', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 60), 'impersonator_id' => $impersonatorId]);
        return $token;
    }

    /** Super admin id carried by the last token accepted by useHandoff(), or null. */
    public ?int $handoffImpersonator = null;

    public function useHandoff(string $token, string $tenantId): ?int
    {
        $this->handoffImpersonator = null;
        $row = $this->db->one(
            'SELECT t.id, t.user_id, t.impersonator_id, u.tenant_id,
                    (SELECT COUNT(*) FROM users s WHERE s.id = t.impersonator_id AND s.is_super_admin_default = 1 AND s.status = 1) AS imp_ok
             FROM sc_login_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($row === null || (string) $row['tenant_id'] !== $tenantId) {
            return null;
        }
        if ($row['impersonator_id'] !== null) {
            if ((int) $row['imp_ok'] !== 1) {
                return null; // only a live super admin can carry "viewing as"
            }
            $this->handoffImpersonator = (int) $row['impersonator_id'];
        }
        $this->db->run('UPDATE sc_login_tokens SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        return (int) $row['user_id'];
    }
}
