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

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** First column of the first row, or null. */
    public function val(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Runs a write statement and returns the number of affected rows. */
    public function run(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));
        return $this->run('UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $params));
    }

    private int $depth = 0;

    /** Transactions can nest: only the outermost begin and commit reach the database. */
    public function begin(): void
    {
        if ($this->depth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->depth++;
    }

    public function commit(): void
    {
        if ($this->depth > 0) {
            $this->depth--;
        }
        if ($this->depth === 0 && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    /** Undoes the whole transaction, however deeply it was nested. */
    public function rollBack(): void
    {
        $this->depth = 0;
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
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
