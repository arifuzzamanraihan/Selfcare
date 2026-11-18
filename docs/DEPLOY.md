# Deploy to production

Reference setup: Linux, Apache 2.4 with PHP-FPM 8.x, MariaDB, one wildcard DNS record. The live demo at <https://selfcare.easybzi.com> runs this way.

## 1. DNS

- `A  selfcare.example.com  → server IP`
- `A  *.example.com         → server IP` (wildcard, so every vendor name resolves)
- CAA (if you use one) must allow `letsencrypt.org`

Custom vendor domains: the vendor points an `A` record at the server IP, or a `CNAME` at their `slug.example.com` address.

## 2. Files and database

```bash
# on the server
mkdir -p /var/www/selfcare && cd /var/www/selfcare
# upload ONLY these paths: core public templates database bin .htaccess
cp .env.example .env     # set DB_*, TENANT_SUFFIX=.example.com, MAIN_HOST=selfcare.example.com, MAIL_* , SERVER_IP
php database/phase1/install.php
chown -R www-data:www-data core public templates database bin storage
```

Do **not** upload `legacy/`, `docs/` or any `.env` from another machine.

## 3. Apache

One virtual host answers for the main host and every vendor:

```apache
<VirtualHost *:443>
    ServerName selfcare.example.com
    ServerAlias *.example.com
    DocumentRoot /var/www/selfcare
    SSLEngine on
    SSLCertificateFile    /etc/ssl/selfcare/fullchain.pem
    SSLCertificateKeyFile /etc/ssl/selfcare/privkey.pem
    <Directory /var/www/selfcare>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

The `.htaccess` in the repo root forwards everything to `public/` and then to the front controller. Keep `.env`, `core/`, `database/` and `templates/` away from direct download (the shipped `.htaccess` already rewrites them into the app).

## 4. HTTPS for vendor addresses

`bin/ssl-worker.php` gets one Let's Encrypt certificate per vendor address using HTTP-01 and writes a small Apache virtual host for it. No DNS API is needed.

1. Install [acme.sh](https://github.com/acmesh-official/acme.sh) as root and register with Let's Encrypt.
2. In `.env`: `SSL_AUTOMATION=1` and `SERVER_IP=<your IP>`.
3. Cron (every 3 minutes):
   `*/3 * * * * flock -n /tmp/sc-ssl.lock php /var/www/selfcare/bin/ssl-worker.php >> /var/log/sc-ssl-worker.log 2>&1`
4. acme.sh's own cron renews certificates automatically.

New vendors and verified custom domains are queued in `sc_domains`. A failure retries with back-off and shows its reason on the vendor profile. Let's Encrypt allows 50 new certificates per week per registered domain, so the worker is deliberately slow and never re-issues a certificate that already exists.

## 5. Updating

Every update, in this order:

1. **Back up**: tar the docroot, `mysqldump` the database, copy the vhost and crontab.
2. Upload the code paths from step 2 (never `.env`).
3. Run `php database/phase1/install.php` (adds new tables and columns only).
4. Smoke test: home, `/login`, `/directory`, a vendor site, a vendor portal, `/admin`.
5. PHP-FPM may cache files for up to `opcache.revalidate_freq` seconds.

**Rollback**: restore the docroot tarball. The migrations only add things, so the database can usually stay.

## 6. Mail

Outgoing SMTP on port 25 is blocked on many hosts. Use an SMTP relay on 587/465 and set `MAIL_DRIVER=smtp`. Until then the app shows one-time links instead of emailing them.
