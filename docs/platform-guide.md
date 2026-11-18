# Self Care platform guide (public site, tenancy, portals, plans, documents)

## Run it

```
cp .env.example .env            # then fill DB_*, TENANT_SUFFIX, MAIN_HOST, MAIL_*
php database/phase1/install.php            # tables (add --fresh on a throw-away database only)
php database/phase1/seed-demo.php --creds=/path/to/CREDENTIALS.txt   # optional demo vendors and a super admin
```

`.env` keys added: `TENANT_SUFFIX` (for example `.example.com`, so a vendor with slug `citygeneral` lives at `citygeneral.example.com`), `MAIN_HOST`, `RESERVED_SLUGS`, `MAIL_DRIVER` (`smtp` or `log`), `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USER`, `SMTP_PASSWORD`. With `MAIL_DRIVER=log` messages are written to `storage/mail.log`. Without a wildcard DNS name every vendor is also reachable at `/h/{slug}` on the main host.

Local test without DNS: use hosts like `sc.test` and `citygeneral.sc.test` that you map to 127.0.0.1 (for Chrome: `--host-resolver-rules="MAP *.sc.test 127.0.0.1"`), with `TENANT_SUFFIX=.sc.test`, `MAIN_HOST=sc.test`.

## Hosts and sign-in

- The main host serves the public site, directory, registration and the super admin (`/admin`).
- A vendor host (`{slug}{suffix}`) serves that vendor's public website at `/` and its portal at `/portal`. The tenant is resolved from the Host header (`core/Tenant.php`); user data is always filtered by `tenant_id`.
- Signing in on the main host sends a hospital user to their own host with a one-time handoff token (`sc_login_tokens`, sha256, 60 seconds, works once).
- Reset password: `/forgot` mails a link (`sc_password_resets`, hashed, 60 minutes, works once). Login, forgot and register are rate limited (`sc_throttle`). Every POST needs the CSRF field.
- Time based access: `staff_access_windows` (valid from / until) and `staff_shifts` (weekday and hours) are checked at sign-in and again on every request (`Access::timeBlock`).

## Roles and portals

The 44 roles of the spec are rows of the legacy `departments` table. `core/config/roles.php` says which roles each of the 20 vendor types switches on. `role_has_permissions` holds the default grants, `tenant_role_has_permissions` the per vendor changes, `tenant_roles` the on/off per vendor. The vendor admin manages them in **Role access** (`/portal/access`). Guardrails: you cannot give more than you hold, cannot change your own role, cannot remove the last admin, and every change is written to `permission_change_log`.

The portal menu (`Portal::nav`) is built from the effective permissions, the family template and the plan modules, so people only see what they can use.

## Plans

`sc_plans` (spec columns, `modules`, `types`) and `sc_subscriptions` (history). Super admin: **Plans** (CRUD) and **Vendors > one vendor** (give, change, extend, never expire, revoke, suspend). When a plan ends the vendor is read-only (`Portal::needWritable`), PDFs stay available. Limits (staff, patients, requests per month) are checked in `Plans::limitError`. The free $0 plan is an ordinary plan row.

## Free services

Table `free_services` (tenant_id, title, description, schedule, eligibility, icon, image, is_active, sort_order). Vendor portal: `/portal/c/free-services`. Public: each vendor website, the main page `/free-services`, super admin overview `/admin/free-services`. Query helper: `Vendors::freeServices()`. A printable record of each delivery is the form `free_service_delivery` (in `sc_documents`).

## Documents and reports

See `docs/documents-and-reports.md` (generated table) and `docs/ui-system.md`.

- PDF writer: `core/Pdf.php` (PDF 1.4, Helvetica, Code128 barcode, JPEG logo, repeated table headers, page X of Y, landscape for wide tables). `core/Documents.php` turns one document model into either the PDF or the print sheet.
- Numbering: `sc_number_sequences` per vendor and document type, safe for two people saving at once. Prefix per type is editable in **Bills and legal details** (`/portal/settings`, stored in `settings` as `invoice_prefix_{type}`). Platform documents use the sequence tenant `_platform` and prefixes in `front_settings`.
- Invoice catalog and forms per type: `core/config/documents.php` (`by_family`, optional `types` list on an item).

## Spec alignment and what is deliberately coarse

- Roles (44), vendor types (20), 5 plan tiers and plan fields follow `spec/selfcare-feature-spec.md`. Prices and limits are editable seed values.
- PostgreSQL row level security and the Postgres data model from the spec are not used. The app keeps MySQL/MariaDB and the existing `tenant_id` scoping.
- The permission matrices of the spec are mapped onto about 17 permission names of the existing `permissions` table. Finer permissions are future work.
- Telemedicine video, dispatch map, multi-branch, public API, SMS and storage quotas exist as plan fields or flags only. They are not enforced yet.
- Reports for admissions, census, bed occupancy, turnaround and free services delivered are derived from the JSON of saved forms in `sc_documents`; there is no separate IPD or LIS module yet.
