<?php
// Selfcare original code. Small helper functions used by core classes and templates.
declare(strict_types=1);

namespace Selfcare;

/** Web path prefix when the app is served from a sub folder (XAMPP), '' on a normal domain. */
function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(dirname($script), '/');
    return $dir === '.' ? '' : $dir;
}

/** Path on the current host, e.g. url('login', ['next' => 'x']). */
function url(string $path = '', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . substr((string) filemtime($file), -6) : '';
    return base_path() . '/' . ltrim($path, '/') . $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Full URL on another host (used for hospital sub domains). */
function host_url(string $host, string $path = '', array $query = []): string
{
    return (is_https() ? 'https://' : 'http://') . $host . url($path, $query);
}

/**
 * Redirect that may leave this host (main site <-> vendor site). A plain 302 after a form POST is blocked by the
 * Content-Security-Policy form-action rule, so another host gets a tiny page that moves on by itself.
 */
function hop(string $to): never
{
    $host = parse_url($to, PHP_URL_HOST);
    if ($host === null || $host === false || strcasecmp((string) $host . (($p = parse_url($to, PHP_URL_PORT)) ? ':' . $p : ''), (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0) {
        redirect($to);
    }
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES) . '"><title>One moment</title></head><body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;text-align:center"><p>One moment, taking you there. <a href="' . htmlspecialchars($to, ENT_QUOTES) . '">Continue</a></p></body></html>';
    exit;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** One-time message shown on the next page. */
function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['m' => $message, 't' => $type];
        return null;
    }
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, string $default = ''): string
{
    $v = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($v) ? (string) $v : $default;
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password2'], $data['_csrf']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $s) ?? '', '-'));
    return substr($s, 0, 40);
}

function money(float $amount, ?string $currency = null): string
{
    return Currency::fmt($amount, $currency, true);
}

function dt(?string $value, string $fmt = 'd M Y'): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $t = strtotime($value);
    return $t ? date($fmt, $t) : '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s !== '' ? $s : '?';
}

/** Soft colour pair derived from a text, for generated avatars and cards. */
function tone(string $seed): array
{
    $h = hexdec(substr(md5($seed), 0, 4)) % 360;
    return [$h, ($h + 40) % 360];
}

function nl2p(string $text): string
{
    $out = '';
    foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $p) {
        if (trim($p) !== '') {
            $out .= '<p>' . nl2br(e(trim($p))) . '</p>';
        }
    }
    return $out;
}

/** Inline SVG icon by name. All icons are hand drawn 24px outline paths. */
function icon(string $name, int $size = 20): string
{
    static $p = [
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'cal' => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
        'pill' => '<path d="m10.5 20.5-7-7a4.95 4.95 0 0 1 7-7l7 7a4.95 4.95 0 0 1-7 7Z"/><path d="m8.5 8.5 7 7"/>',
        'flask' => '<path d="M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2.2h12.4a1.5 1.5 0 0 0 1.3-2.2L14 9V3"/><path d="M7.5 15h9"/>',
        'bed' => '<path d="M3 20V6M3 16h18v4M21 16v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="12" r="2"/>',
        'drop' => '<path d="M12 2.7s6 6.1 6 10.6a6 6 0 0 1-12 0C6 8.8 12 2.7 12 2.7Z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M2 21h20"/><path d="M8 7h4M8 11h4M8 15h4"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5M3 17.5 12 22l9-4.5"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2Z"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
        'news' => '<path d="M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2V4Z"/><path d="M19 8h2v10a2 2 0 0 1-2 2M8 8h7M8 12h7M8 16h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h0a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v0a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.4 8.5 8 9 4.6-.5 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'truck' => '<path d="M1 6h12v10H1zM13 10h5l4 3v3h-9"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'heart' => '<path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 13a9 9 0 1 1-10-10 7 7 0 0 0 10 10Z"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'stethoscope' => '<path d="M6 3v6a4 4 0 0 0 8 0V3M10 13v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/>',
        'siren' => '<path d="M7 18v-6a5 5 0 0 1 10 0v6M5 18h14v3H5zM12 2v2M4 6l1.5 1.5M20 6l-1.5 1.5"/>',
        'home2' => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/><path d="M12 14v4M10 16h4"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
        'box' => '<path d="m21 8-9-5-9 5v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'scan' => '<path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2M4 12h16"/>',
        'brain' => '<path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 6 1V4a3 3 0 0 0-3 0ZM15 4a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-6 1"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'unlock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
        'undo' => '<path d="M3 7v6h6"/><path d="M3 13a9 9 0 1 0 3-7.7L3 8"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'play' => '<path d="M6 4l14 8-14 8Z"/>',
        'leaf' => '<path d="M5 19c0-9 5-14 15-15 0 10-5 15-14 15"/><path d="M5 19c3-4 6-6 9-8"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['layers']) . '</svg>';
}

/** The running application (services and the current request context). */
function app(): App
{
    return App::$i;
}

/** Cover or logo image path to a full web path. */
function upload_url(?string $path): string
{
    return $path ? base_path() . '/' . ltrim($path, '/') : '';
}
