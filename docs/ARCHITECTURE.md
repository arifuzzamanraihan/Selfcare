# Architecture

Self Care is a small, framework-free PHP application. Everything lives in the `Selfcare` namespace under `core/`.

## Request lifecycle

```mermaid
flowchart TD
    A[HTTP request] --> B[public/index.php]
    B --> C[core/bootstrap.php<br/>loads classes, opens DB, seeds types and plans]
    C --> D[App: read Host header]
    D --> E{Tenant::slugFromHost}
    E -->|main host| F[main site routes]
    E -->|vendor subdomain or custom domain| G[vendor site + portal routes]
    E -->|unknown or deleted vendor| H[404 'no provider at this address']
    F & G --> I[CSRF check on POST]
    I --> J[Router: first registered match wins]
    J --> K[Portal::boot / adminUser: sign-in, role, plan, permissions]
    K --> L[Route handler → Crud / Listing / People / Billing]
    L --> M[View: templates/*.php]
```

## Modules

| File | Job |
| --- | --- |
| `Tenant`, `Domains` | Host → vendor, subdomains, custom domains, DNS check, certificate queue |
| `Auth`, `Throttle` | Sign-in, sessions, password-set links, hand-off tokens between hosts, impersonation |
| `Access` | Roles (`departments`), permissions, per-vendor overrides, time windows, audit log |
| `Plans`, `Billing`, `Currency` | Subscriptions, invoices and payments, money formatting and amount in words |
| `Vendors`, `VendorTypes` | Create, soft delete, restore, purge vendors; type and family templates |
| `Portal`, `People` | One portal request (who, which vendor, nav); staff and people management |
| `Listing`, `Crud`, `Charts` | Reusable table, form and chart components (`docs/ui-system.md`) |
| `Documents`, `Pdf`, `Reports` | Printed forms, invoice PDFs, reports |
| `routes/*.php` | `public`, `auth`, `site`, `portal`, `admin`, `documents` |

## Data model (main tables)

```mermaid
erDiagram
    tenants ||--|| sc_vendors : "is"
    sc_vendor_types ||--o{ sc_vendors : "kind"
    tenants ||--o{ users : "has"
    users ||--o{ model_has_roles : "role"
    departments ||--o{ model_has_roles : "name"
    tenants ||--o{ sc_subscriptions : "plan history"
    sc_plans ||--o{ sc_subscriptions : ""
    tenants ||--o{ sc_invoices : "billing"
    sc_invoices ||--o{ sc_invoice_items : ""
    sc_invoices ||--o{ sc_invoice_payments : ""
    tenants ||--o{ sc_appointment_requests : "from website"
    tenants ||--o{ sc_domains : "addresses + SSL state"
    tenants ||--o{ sc_stock_items : "stock"
    sc_registration_requests }o--o| tenants : "approved into"
    sc_currencies ||--o{ sc_invoices : "code"
```

`tenant_id` is on every vendor-owned row. Platform-level audit entries use the pseudo tenant `_platform`.

## Security notes

- **CSRF**: every POST is checked; every form carries a token.
- **Passwords**: bcrypt; reset and set-password links are random 256-bit tokens, stored as SHA-256, valid 7 days, single use.
- **Cross-host sign-in**: a 60-second, single-use token (`sc_login_tokens`) carries a session from the main host to a vendor host. The browser is sent through a small HTML hop page because the Content-Security-Policy blocks a POST redirecting to another host.
- **Impersonation** ("Open portal"): the token records the super admin; the vendor portal shows a banner and `Return to super admin`; entry and exit are written to the audit log; it does not touch the vendor user's last-login time.
- **Soft delete**: vendors and users get `deleted_at`; deleted vendors answer 404 and their users cannot sign in. Purge needs two typed confirmations.
- **Least privilege**: a user can only grant roles whose permissions they hold; only an admin can edit another admin; the last active admin cannot be removed.
- **Content-Security-Policy** and other security headers are sent on every response.
