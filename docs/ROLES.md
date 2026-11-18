# Roles and access

## Who is who

| Who | Where | Can |
| --- | --- | --- |
| **Super Admin** | main host `/admin` | Everything on the platform: vendors, plans, invoices, currencies, registrations, site content. Can open any vendor portal (audited). |
| **Vendor Admin / Owner** | `slug.domain/portal` | Everything inside their own vendor. Always has every permission. |
| **Staff** (42 other roles) | same portal | Only what their role allows, only when their time window and shift allow. |
| **People side** (Patient, Family Member, Referring Doctor, B2B Customer) | `/portal` (limited) | Their own requests and account. |

## Roles

44 roles live in `core/config/roles.php`, grouped as Governance, Clinical, Diagnostics, Pharmacy and supply, Operations and others. Each vendor type ships with a default set switched on (a pharmacy offers *Pharmacist*, *Pharmacy Assistant*, *Billing Clerk*…; a hospital offers *Doctor*, *Nurse*, *Receptionist*…). The vendor admin can switch more roles on or off under **Role access**.

## Permissions

Permissions are named `manage_*` / `view_*` (see `core/config/access.php`), for example `manage_appointments`, `manage_users`, `manage_invoices`, `manage_medicines`, `manage_blood_bank`, `manage_reports`, `manage_access`. Each role has default grants for its vendor type. A vendor admin edits them per role; edits are stored per tenant in `tenant_role_has_permissions`.

Rules enforced in code:

1. Owner and Admin can never be restricted.
2. You can only give a role whose permissions you already hold.
3. Only an admin can edit, suspend, reset or delete another admin.
4. You cannot change your own role, and the last active admin cannot be demoted, suspended or deleted.
5. The vendor's plan decides which modules exist at all.

## Time-based access

Per staff member: a **valid from / until** window (seasonal staff, locums) and **weekly shifts** (day, start, end). Outside them sign-in is refused.

## Audit

Every permission change, role change, suspend/activate, soft delete/restore, password link, impersonation and vendor lifecycle action is written to `permission_change_log` with actor, target, before/after and reason.

## Impersonation

Super admin → vendor profile → **Open portal** (or a specific admin's **Open portal**). A dark banner shows *You are viewing X as super admin* with **Return to super admin**.
