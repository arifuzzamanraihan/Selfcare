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
date_default_timezone_set($env->get('APP_TIMEZONE', 'Asia/Karachi')); // the same clock as the web app: next_try_at is written by both
$db = new Database($env);
$db->run('SET time_zone = ?', [date('P')]);
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

$block = static function (string $host, bool $withSsl, ?string $certHost = null) use ($docroot, $certDir, $php): string {
    $certHost = $certHost ?? $host;
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
    return $http . "<VirtualHost *:443>\n$common    SSLEngine On\n    SSLCertificateFile $certDir/$certHost/fullchain.pem\n    SSLCertificateKeyFile $certDir/$certHost/privkey.pem\n    SSLProtocol All -SSLv2 -SSLv3 -TLSv1\n    SSLHonorCipherOrder On\n$tail</VirtualHost>\n";
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

// A vendor that was deleted in Self Care (soft delete) no longer has a website: its addresses are switched off here, which lets the
// clean-up below remove the virtual host and the certificate. Restoring the vendor queues them again (Domains::revive).
$gone = $db->run("UPDATE sc_domains d JOIN sc_vendors v ON v.tenant_id = d.tenant_id SET d.status = 'removed', d.last_error = 'Vendor deleted', d.updated_at = NOW() WHERE v.status = 'deleted' AND d.status <> 'removed'");
if ($gone > 0) {
    $log("switched off $gone address(es) of deleted vendors");
}

// Orphans: a virtual host written by this worker whose address is gone (removed domain, purged vendor) is deleted.
// Only files named 0.sc-<host>.conf are touched. The certificate is dropped too when nothing else uses it.
foreach (glob($vhostDir . '/0.sc-*.conf') ?: [] as $f) {
    if (!preg_match('#/0\.sc-([a-z0-9.-]+)\.conf$#', $f, $m)) {
        continue;
    }
    $host = $m[1];
    if ($db->val("SELECT COUNT(*) FROM sc_domains WHERE host = ? AND status <> 'removed'", [$host]) > 0) {
        continue;
    }
    $bak = file_get_contents($f);
    @unlink($f);
    $sh($apachectl . ' -t', $code);
    if ($code !== 0) {
        file_put_contents($f, $bak);
        continue;
    }
    $sh($apachectl . ' graceful');
    $log("removed virtual host of $host");
}

// Certificates of vendor sub domains that no address uses any more (never the main host's, never other sites').
$suffix = (string) $env->get('TENANT_SUFFIX');
$mainHost = (string) $env->get('MAIN_HOST');
foreach ($suffix !== '' ? (glob($certDir . '/*' . $suffix) ?: []) : [] as $dir) {
    $host = basename($dir);
    if ($host === $mainHost || !preg_match('/^[a-z0-9.-]+$/', $host) || !is_file($dir . '/.sc-worker') || !is_dir('/root/.acme.sh/' . $host . '_ecc')) { // only certificates this worker installed (marker file)
        continue;
    }
    if ($db->val("SELECT COUNT(*) FROM sc_domains WHERE (cert_name = ? OR host = ?) AND status <> 'removed'", [$host, $host]) == 0) {
        $sh(escapeshellarg($acme) . ' --remove --ecc -d ' . escapeshellarg($host));
        $sh('rm -rf ' . escapeshellarg('/root/.acme.sh/' . $host . '_ecc') . ' ' . escapeshellarg($dir));
        $log("dropped the unused certificate of $host");
    }
}

/** Does the installed certificate for $certHost cover every host and last 30 more days? */
$certCovers = static function (string $certHost, array $hosts) use ($certDir, $sh): bool {
    $f = $certDir . '/' . $certHost . '/fullchain.pem';
    if (!is_file($f)) {
        return false;
    }
    $sh('openssl x509 -noout -checkend 2592000 -in ' . escapeshellarg($f), $code);
    if ($code !== 0) {
        return false;
    }
    $san = $sh('openssl x509 -noout -ext subjectAltName -in ' . escapeshellarg($f));
    foreach ($hosts as $h) {
        if (stripos($san, 'DNS:' . $h) === false) {
            return false;
        }
    }
    return true;
};

/** Asks Let's Encrypt for one certificate covering $hosts (first one names it) and installs it. @return array{0:bool,1:string} */
$issue = static function (array $hosts) use ($acme, $docroot, $certDir, $apachectl, $sh): array {
    $main = $hosts[0];
    $cmd = escapeshellarg($acme) . ' --issue --ecc --server letsencrypt -w ' . escapeshellarg($docroot) . ' --keylength ec-256';
    foreach ($hosts as $h) {
        $cmd .= ' -d ' . escapeshellarg($h);
    }
    $out = $sh($cmd, $code);
    $have = is_file('/root/.acme.sh/' . $main . '_ecc/fullchain.cer');
    if ($code !== 0 && !($code === 2 && $have)) {
        return [false, 'Certificate was not issued yet: ' . trim((string) preg_replace('/\s+/', ' ', substr($out, -240)))];
    }
    @mkdir($certDir . '/' . $main, 0700, true);
    $reload = escapeshellarg($apachectl . ' -t && ' . $apachectl . ' graceful');
    $sh(escapeshellarg($acme) . ' --install-cert --ecc -d ' . escapeshellarg($main) . ' --key-file ' . escapeshellarg($certDir . '/' . $main . '/privkey.pem') . ' --fullchain-file ' . escapeshellarg($certDir . '/' . $main . '/fullchain.pem') . ' --reloadcmd ' . $reload, $code);
    if (!is_file($certDir . '/' . $main . '/fullchain.pem')) {
        return [false, 'Certificate could not be installed.'];
    }
    @file_put_contents($certDir . '/' . $main . '/.sc-worker', date('c') . "\n"); // marks it as ours for the clean-up below
    return [true, ''];
};

$markActive = static function (array $d, string $certName) use ($db): void {
    $db->run("UPDATE sc_domains SET status = 'active', cert_name = ?, cert_at = COALESCE(cert_at, NOW()), last_error = NULL, attempts = 0, updated_at = NOW() WHERE id = ?", [$certName, (int) $d['id']]);
};

// Let's Encrypt allows 50 new certificates per registered domain per week. Stay well below it.
$weekly = (int) $db->val('SELECT COUNT(DISTINCT cert_name) FROM sc_domains WHERE cert_at >= ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
$budget = 40 - $weekly;

$todo = $db->all("SELECT * FROM sc_domains WHERE status IN ('pending_dns', 'pending_cert') AND (next_try_at IS NULL OR next_try_at <= ?) ORDER BY id LIMIT 20", [$now]);
$subs = [];
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
    // a certificate that is already installed and valid is never ordered again
    $existing = (string) ($d['cert_name'] ?? '') !== '' ? (string) $d['cert_name'] : $host;
    if ($certCovers($existing, [$host])) {
        if ($applyConf($host, $block($host, true, $existing))) {
            $markActive($d, $existing);
            $log("$host: already covered by the certificate of $existing");
        } else {
            $fail($d, 'Could not switch on HTTPS in the web server.');
        }
        continue;
    }
    if ($budget <= 0) {
        $db->run('UPDATE sc_domains SET last_error = ?, next_try_at = ?, updated_at = NOW() WHERE id = ?', ['Waiting: weekly certificate limit reached, will retry automatically.', date('Y-m-d H:i:s', time() + 6 * 3600), (int) $d['id']]);
        $log("$host: deferred (weekly limit)");
        continue;
    }
    if ($d['kind'] === 'custom') {
        // custom domains need their own virtual host so the HTTP-01 challenge lands in this app
        if (!is_file($confPath($host)) && !$applyConf($host, $block($host, false))) {
            $fail($d, 'Could not add the web server entry.');
            continue;
        }
        [$ok, $err] = $issue([$host]);
        $budget--;
        if (!$ok) {
            $fail($d, $err);
            continue;
        }
        if (!$applyConf($host, $block($host, true))) {
            $fail($d, 'Could not switch on HTTPS in the web server.');
            continue;
        }
        $markActive($d, $host);
        $log("$host: HTTPS is live");
        continue;
    }
    $subs[] = $d; // vendor sub domains are batched below
}

// Vendor sub domains: one certificate for up to 10 waiting addresses (one order instead of ten).
$fresh = array_values(array_filter($subs, static fn ($d) => (int) $d['attempts'] === 0));
$retry = array_values(array_filter($subs, static fn ($d) => (int) $d['attempts'] > 0));
$chunks = array_merge(array_chunk($fresh, 10), array_map(static fn ($d) => [$d], $retry)); // a retry goes alone
foreach ($chunks as $chunk) {
    if ($budget <= 0) {
        break;
    }
    $hosts = array_map(static fn ($d) => (string) $d['host'], $chunk);
    [$ok, $err] = $issue($hosts);
    $budget--;
    if (!$ok && count($hosts) > 1) {
        // one bad address must not block the others: order them one by one on the next run
        foreach ($chunk as $d) {
            $fail($d, $err);
            $db->run('UPDATE sc_domains SET next_try_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() + 120), (int) $d['id']]);
        }
        $log('batch of ' . count($hosts) . ' failed, will retry: ' . $err);
        continue;
    }
    foreach ($chunk as $d) {
        $host = (string) $d['host'];
        if (!$ok) {
            $fail($d, $err);
            continue;
        }
        if (!$applyConf($host, $block($host, true, $hosts[0]))) {
            $fail($d, 'Could not switch on HTTPS in the web server.');
            continue;
        }
        $markActive($d, $hosts[0]);
        $log("$host: HTTPS is live (certificate $hosts[0], " . count($hosts) . ' address' . (count($hosts) > 1 ? 'es' : '') . ')');
    }
}
