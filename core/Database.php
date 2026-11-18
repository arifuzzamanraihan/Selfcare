<?php
// Selfcare original code. Thin wrapper around PHP's built-in PDO.
declare(strict_types=1);

namespace Selfcare;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(Env $env)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env->get('DB_HOST', '127.0.0.1'),
            $env->get('DB_PORT', '3306'),
            $env->get('DB_DATABASE', 'selfcare')
        );
        $this->pdo = new PDO($dsn, $env->get('DB_USERNAME', 'root'), $env->get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function count(string $table): int
    {
        // Table names here are fixed strings written in our own routes.
        $row = $this->one("SELECT COUNT(*) AS c FROM `$table`");
        return (int) ($row['c'] ?? 0);
    }

    public function healthy(): bool
    {
        try {
            return (int) $this->one('SELECT 1 AS ok')['ok'] === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
