<?php
// Selfcare original front bootstrap. No framework, only PHP itself.
declare(strict_types=1);

require __DIR__ . '/Env.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/View.php';

use Selfcare\Auth;
use Selfcare\Database;
use Selfcare\Env;
use Selfcare\Router;
use Selfcare\View;

$root = dirname(__DIR__);
$env = new Env();
$env->load($root . '/.env');

session_name('SELFCARESESSID');
session_start();

$db = new Database($env);
$auth = new Auth($db);
$view = new View($root . '/templates');
$router = new Router();

$goHome = static function (): void {
    $base = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $pos = strpos($base, '/public');
    header('Location: ' . ($pos === false ? '/' : substr($base, 0, $pos + 7) . '/'));
    exit;
};

$router->get('/', static function () use ($view, $db, $auth): void {
    $view->page('home', [
        'title' => 'Selfcare',
        'user' => $auth->user(),
        'counts' => [
            'Hospitals' => $db->count('tenants'),
            'Users' => $db->count('users'),
            'Doctors' => $db->count('doctors'),
            'Patients' => $db->count('patients'),
            'Departments' => $db->count('departments'),
        ],
    ]);
});

$router->get('/login', static function () use ($view, $auth): void {
    $view->page('login', ['title' => 'Sign in — Selfcare', 'user' => $auth->user(), 'error' => '']);
});

$router->post('/login', static function () use ($view, $auth, $goHome): void {
    $ok = $auth->attempt(
        trim((string) ($_POST['email'] ?? '')),
        (string) ($_POST['password'] ?? '')
    );
    if (!$ok) {
        $view->page('login', ['title' => 'Sign in — Selfcare', 'user' => null, 'error' => 'Email or password is not correct.']);
        return;
    }
    $goHome();
});

$router->post('/logout', static function () use ($auth, $goHome): void {
    $auth->logout();
    $goHome();
});

$router->get('/health', static function () use ($db): void {
    header('Content-Type: application/json');
    echo json_encode(['app' => 'Selfcare', 'database' => $db->healthy()]);
});

$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$pos = strpos($uri, '/public');
$path = $pos === false ? $uri : substr($uri, $pos + 7);
$router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path === '' ? '/' : $path);
