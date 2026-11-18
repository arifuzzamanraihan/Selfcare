<?php
// Selfcare original code. Minimal method+path router with {name} parameters.
declare(strict_types=1);

namespace Selfcare;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler !== null) {
            $handler([]);
            return;
        }
        foreach ($this->routes[$method] ?? [] as $pattern => $h) {
            if (!str_contains($pattern, '{')) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $m)) {
                $h(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
                return;
            }
        }
        app()->error(404, 'We could not find that page.');
    }
}
