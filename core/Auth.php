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
        $user = $this->db->one('SELECT id, password, status, tenant_id FROM users WHERE email = ? LIMIT 1', [$email]);
        // Always run one hash check so unknown emails and wrong passwords take the same time.
        $hash = $user['password'] ?? '$2y$10$4eDrck0H92ZyIWUUCDvMEO7kjLUk.FOnBfaiAZ4p639/pxSw3N4z6';
        $ok = password_verify($password, (string) $hash);
        if ($user === null || !$ok || (int) $user['status'] !== 1) {
            return null;
        }
        if ($tenantId !== null && (string) $user['tenant_id'] !== $tenantId) {
            return null;
        }
        return (int) $user['id'];
    }

    /** Signs a user in (new session id to stop session fixation). */
    public function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['selfcare_user_id'] = $userId;
        $this->cache = false;
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
            'SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.hospital_name, u.tenant_id, u.status, u.is_super_admin_default, d.name AS role
             FROM users u
             LEFT JOIN model_has_roles m ON m.model_id = u.id AND m.model_type = ?
             LEFT JOIN departments d ON d.id = m.role_id
             WHERE u.id = ? LIMIT 1',
            [self::ROLE_MODEL, (int) $id]
        );
        if ($u === null || (int) $u['status'] !== 1) {
            unset($_SESSION['selfcare_user_id']);
            return $this->cache = null;
        }
        $u['role'] = (int) $u['is_super_admin_default'] === 1 ? 'Super Admin' : (string) ($u['role'] ?? 'Patient');
        return $this->cache = $u;
    }

    public function isSuperAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'Super Admin';
    }

    public function logout(): void
    {
        unset($_SESSION['selfcare_user_id']);
        $this->cache = false;
        session_regenerate_id(true);
    }

    /** One-time token to carry a signed-in user to a vendor sub domain. */
    public function makeHandoff(int $userId): string
    {
        $token = bin2hex(random_bytes(24));
        $this->db->run('DELETE FROM sc_login_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
        $this->db->insert('sc_login_tokens', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 60)]);
        return $token;
    }

    public function useHandoff(string $token, string $tenantId): ?int
    {
        $row = $this->db->one(
            'SELECT t.id, t.user_id, u.tenant_id FROM sc_login_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($row === null || (string) $row['tenant_id'] !== $tenantId) {
            return null;
        }
        $this->db->run('UPDATE sc_login_tokens SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        return (int) $row['user_id'];
    }
}
