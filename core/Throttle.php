<?php
// Selfcare original code. Simple attempt limiter stored in the database.
declare(strict_types=1);

namespace Selfcare;

final class Throttle
{
    public function __construct(private Database $db)
    {
    }

    private function key(string $bucket, string $who): string
    {
        return hash('sha256', $bucket . '|' . strtolower($who));
    }

    public function tooMany(string $bucket, string $who, int $max, int $seconds): bool
    {
        $row = $this->db->one('SELECT attempts, first_at FROM sc_throttle WHERE tkey = ?', [$this->key($bucket, $who)]);
        if ($row === null) {
            return false;
        }
        if (strtotime((string) $row['first_at']) < time() - $seconds) {
            $this->clear($bucket, $who);
            return false;
        }
        return (int) $row['attempts'] >= $max;
    }

    public function hit(string $bucket, string $who): void
    {
        $k = $this->key($bucket, $who);
        $this->db->run(
            'INSERT INTO sc_throttle (tkey, attempts, first_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1',
            [$k]
        );
    }

    public function clear(string $bucket, string $who): void
    {
        $this->db->run('DELETE FROM sc_throttle WHERE tkey = ?', [$this->key($bucket, $who)]);
    }
}
