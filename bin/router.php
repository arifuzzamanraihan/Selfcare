<?php
// Router script for PHP's built-in server:  php -S 127.0.0.1:8080 -t public bin/router.php
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // serve static files directly
}
require dirname(__DIR__) . '/public/index.php';
