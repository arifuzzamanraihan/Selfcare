<?php
// Selfcare original code. SSL worker for vendor addresses (run by cron on the server, as root).
//   * 3 minutes: * /3 * * * * flock -n /tmp/sc-ssl.lock php /www/wwwroot/selfcare-newcore/bin/ssl-worker.php >> /var/log/sc-ssl-worker.log 2>&1
//   * Reads table sc_domains. For every address that waits (pending_dns, pending_cert) it
//       1. checks DNS (custom domains), 2. writes a small Apache virtual host so the HTTP-01 challenge reaches this app,
//       3. asks Let's Encrypt for a certificate with acme.sh (webroot), 4. installs it and writes the full HTTPS virtual host
//          (one virtual host per address, so Apache picks the right certificate by SNI),
//       5. runs "apachectl -t" before every graceful reload and puts the old file back if the test fails.
//   * Removed addresses lose their virtual host. Other sites and their files are never touched: only files named 0.sc-<host>.conf.
// Settings (.env or environment): SSL_AUTOMATION=1 turns it on; optional ACME_SH, APACHE_VHOST_DIR, APACHE_CERT_DIR, APACHECTL.
declare(strict_types=1);

use Selfcare\{Database, Domains, Env, Tenant};

$root = dirname(__DIR__);
require $root . '/core/Env.php';
require $root . '/core/Database.php';
require $root . '/core/Tenant.php';
require $root . '/core/Domains.php';

$env = new Env();
$env->load($root . '/.env');
if ($env->get('SSL_AUTOMATION') !== '1') {
    exit(0);
}
$db = new Database($env);
$tenant = new Tenant($env, $db);
$domains = new Domains($db, $env, $tenant);

$acme = $env->get('ACME_SH', '/root/.acme.sh/acme.sh');
$vhostDir = $env->get('APACHE_VHOST_DIR', '/www/server/panel/vhost/apache');
$certDir = $env->get('APACHE_CERT_DIR', '/www/server/panel/vhost/cert');
$apachectl = $env->get('APACHECTL', '/www/server/apache/bin/apachectl');
$docroot = $root . '/public';
$php = $env->get('PHP_HANDLER', 'proxy:unix:/tmp/php-cgi-83.sock|fcgi://localhost');
$log = static function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n";
};
$sh = static function (string $cmd, ?int &$code = null): string {
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return implode("\n", $out);
};

$confPath = static fn (string $host): string => $vhostDir . '/0.sc-' . $host . '.conf';

$block = static function (string $host, bool $withSsl) use ($docroot, $certDir, $php): string {
    $common = "    ServerAdmin webmaster@$host\n    DocumentRoot \"$docroot\"\n    ServerName $host\n    ErrorLog \"/www/wwwlogs/selfcare.easybzi.com-error_log\"\n    CustomLog \"/www/wwwlogs/selfcare.easybzi.com-access_log\" combined\n";
    $tail = "    <FilesMatch \\.php\$>\n        SetHandler \"$php\"\n    </FilesMatch>\n    <Files ~ (\\.user.ini|\\.htaccess|\\.git|\\.env|\\.svn|\\.project|LICENSE|README.md)\$>\n        Order allow,deny\n        Deny from all\n    </Files>\n    <Directory \"$docroot\">\n        Options FollowSymLinks\n        AllowOverride All\n        Require all granted\n        DirectoryIndex index.php index.html\n    </Directory>\n";
    $http = "<VirtualHost *:80>\n$common";
    if ($withSsl) {
        $http .= "    <IfModule mod_rewrite.c>\n        RewriteEngine on\n        RewriteCond %{THE_REQUEST} !acme-challenge\n        RewriteRule (.*) https://%{SERVER_NAME}\$1 [L,R=301]\n    </IfModule>\n";
    }
    $http .= $tail . "</VirtualHost>\n";
    if (!$withSsl) {
        return $http;
    }
    return $http . "<VirtualHost *:443>\n$common    SSLEngine On\n    SSLCertificateFile $certDir/$host/fullchain.pem\n    SSLCertificateKeyFile $certDir/$host/privkey.pem\n    SSLProtocol All -SSLv2 -SSLv3 -TLSv1\n    SSLHonorCipherOrder On\n$tail</VirtualHost>\n";
};

/** Writes a vhost file, tests Apache, reloads. Puts the old file back when the test fails. */
$applyConf = static function (string $host, string $content) use ($confPath, $apachectl, $sh, $log): bool {
    $f = $confPath($host);
    $bak = is_file($f) ? file_get_contents($f) : null;
    file_put_contents($f, $content);
    $t = $sh($apachectl . ' -t', $code);
    if ($code !== 0) {
        $log("apachectl -t failed for $host, rolled back: " . substr($t, -200));
        if ($bak === null) {
            @unlink($f);
        } else {
            file_put_contents($f, $bak);
        }
        return false;
    }
    $sh($apachectl . ' graceful', $code);
    return $code === 0;
};

$now = date('Y-m-d H:i:s');
$fail = static function (array $d, string $msg) use ($db, $log): void {
    $n = (int) $d['attempts'] + 1;
    $wait = min(21600, 600 * (2 ** min($n, 6)));
    $status = $n >= 8 ? 'failed' : $d['status'];
    $db->run('UPDATE sc_domains SET attempts = ?, status = ?, last_error = ?, next_try_at = ?, updated_at = NOW() WHERE id = ?', [$n, $status, mb_substr($msg, 0, 250), date('Y-m-d H:i:s', time() + $wait), (int) $d['id']]);
    $log("{$d['host']}: $msg");
};

// removed addresses: drop their virtual host
foreach ($db->all("SELECT * FROM sc_domains WHERE status = 'removed' AND kind = 'custom'") as $d) {
    $f = $confPath((string) $d['host']);
    if (is_file($f)) {
        $bak = file_get_contents($f);
        @unlink($f);
        $sh($apachectl . ' -t', $code);
        if ($code === 0) {
            $sh($apachectl . ' graceful');
            $log("removed virtual host of {$d['host']}");
        } else {
            file_put_contents($f, $bak);
        }
    }
}

$todo = $db->all("SELECT * FROM sc_domains WHERE status IN ('pending_dns', 'pending_cert') AND (next_try_at IS NULL OR next_try_at <= ?) ORDER BY id LIMIT 4", [$now]);
foreach ($todo as $d) {
    $host = (string) $d['host'];
    if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
        continue;
    }
    if ($d['status'] === 'pending_dns') {
        [$ok, $msg] = $domains->checkDns($host, (string) $d['tenant_id']);
        if (!$ok) {
            $db->run('UPDATE sc_domains SET last_error = ?, next_try_at = ?, updated_at = NOW() WHERE id = ?', [mb_substr($msg, 0, 250), date('Y-m-d H:i:s', time() + 600), (int) $d['id']]);
            continue;
        }
        $db->run("UPDATE sc_domains SET status = 'pending_cert', dns_ok_at = NOW(), last_error = NULL, attempts = 0, updated_at = NOW() WHERE id = ?", [(int) $d['id']]);
        $d['status'] = 'pending_cert';
        $log("$host: DNS is right");
    }
    // 1. custom domains need their own virtual host so the HTTP-01 challenge lands in this app
    if ($d['kind'] === 'custom' && !is_file($confPath($host))) {
        if (!$applyConf($host, $block($host, false))) {
            $fail($d, 'Could not add the web server entry.');
            continue;
        }
    }
    // 2. certificate
    $out = $sh(escapeshellarg($acme) . ' --issue --ecc --server letsencrypt -d ' . escapeshellarg($host) . ' -w ' . escapeshellarg($docroot) . ' --keylength ec-256', $code);
    $haveCert = is_file(getenv('HOME') . '/.acme.sh/' . $host . '_ecc/fullchain.cer') || is_file('/root/.acme.sh/' . $host . '_ecc/fullchain.cer');
    if ($code !== 0 && !($code === 2 && $haveCert)) {
        $line = trim((string) preg_replace('/\s+/', ' ', substr($out, -240)));
        $fail($d, 'Certificate was not issued yet: ' . $line);
        continue;
    }
    @mkdir($certDir . '/' . $host, 0700, true);
    $reload = escapeshellarg($apachectl . ' -t && ' . $apachectl . ' graceful');
    $inst = $sh(escapeshellarg($acme) . ' --install-cert --ecc -d ' . escapeshellarg($host) . ' --key-file ' . escapeshellarg($certDir . '/' . $host . '/privkey.pem') . ' --fullchain-file ' . escapeshellarg($certDir . '/' . $host . '/fullchain.pem') . ' --reloadcmd ' . $reload, $code);
    if (!is_file($certDir . '/' . $host . '/fullchain.pem')) {
        $fail($d, 'Certificate could not be installed.');
        continue;
    }
    // 3. full HTTPS virtual host for SNI
    if (!$applyConf($host, $block($host, true))) {
        $fail($d, 'Could not switch on HTTPS in the web server.');
        continue;
    }
    $db->run("UPDATE sc_domains SET status = 'active', cert_at = NOW(), last_error = NULL, attempts = 0, updated_at = NOW() WHERE id = ?", [(int) $d['id']]);
    $log("$host: HTTPS is live");
}
