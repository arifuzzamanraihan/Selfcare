<?php
// Selfcare original code. Session login against the users table.
declare(strict_types=1);

namespace Selfcare;

final class Auth
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->one('SELECT id, password FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return false;
        }
        $_SESSION['selfcare_user_id'] = (int) $user['id'];
        return true;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        $id = $_SESSION['selfcare_user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->db->one(
            'SELECT id, first_name, last_name, email, hospital_name FROM users WHERE id = ? LIMIT 1',
            [(int) $id]
        );
    }

    public function logout(): void
    {
        unset($_SESSION['selfcare_user_id']);
    }
}
