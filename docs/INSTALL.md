# Install and run locally

Self Care needs **PHP 8.1+** and **MySQL 5.7+ / MariaDB 10.x**. There is no Composer step and no npm step.

## 1. Requirements

- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `curl`, `fileinfo`, `intl`, `json`
- An empty database and a user that can create tables in it

## 2. Get the code

```bash
git clone https://github.com/arifuzzamanraihan/Selfcare.git
cd Selfcare
cp .env.example .env
```

Edit `.env` and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

## 3. Create the tables

```bash
php database/phase1/install.php
```

The command is safe to run again. It creates missing tables, adds missing columns (`migrate-r4.sql`), and adds the 20 vendor types, the default plans and the nine currencies if they are missing. `--fresh` drops every table first: use it only on a throw-away database.

## 4. Optional demo data

```bash
php database/phase1/seed-demo.php --creds=/tmp/selfcare-credentials.txt
```

Creates a super admin and 23 demo vendors (3 hospitals plus every other type) with websites, doctors, staff, patients, requests, invoices, forms and stock. Random passwords are written to the file you name. Nothing is printed.

Options: `--admin-email=you@example.com`, `--main=HOST`, `--host-suffix=.example.test`, `--scheme=http`.

## 5. Start the server

```bash
php -S 127.0.0.1:8080 -t public bin/router.php
```

Open <http://127.0.0.1:8080>. Sign in at `/login` with the super admin from your credentials file.

## 6. Vendor subdomains on localhost

Vendor sites live at `slug.<suffix>`. To try that locally:

1. In `.env`: `TENANT_SUFFIX=.sc.test` and `MAIN_HOST=sc.test`
2. Make `sc.test` and `*.sc.test` resolve to `127.0.0.1` (dnsmasq `address=/sc.test/127.0.0.1`, or one `/etc/hosts` line per vendor)
3. Seed with `--main=sc.test --host-suffix=.sc.test --scheme=http`, and browse to `http://sc.test:8080` and `http://citygeneral.sc.test:8080`

With both left empty the app runs on a single host and vendor sites are at `/h/{slug}`.

## 7. Email

`MAIL_DRIVER=log` (default) writes every message to `storage/mail.log`. Set `MAIL_DRIVER=smtp` and the `SMTP_*` values to send for real. When email is not available, password-set links are shown on screen once.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| "Self Care is starting up" | The database is unreachable. Check `.env` and that the server is running. |
| Blank page or HTTP 500 | Look at PHP's error log; `php -l` on the file it names. |
| Styles missing | Start the server with `-t public` and the `bin/router.php` router script. |
| Vendor subdomain says "no provider at this address" | `TENANT_SUFFIX` / `MAIN_HOST` do not match the host you typed, or the vendor is deleted. |
| Uploads fail | `public/uploads/` must be writable by the PHP user. |
