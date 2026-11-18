<?php
// Selfcare original code. Reads simple KEY=value pairs from a file.
declare(strict_types=1);

namespace Selfcare;

final class Env
{
    /** @var array<string,string> */
    private array $values = [];

    public function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }
            $this->values[trim($key)] = $value;
        }
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }
}
