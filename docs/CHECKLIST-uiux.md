# UI/UX checklist — remaining screens (next week)

Tonight’s pass covered **Clinic**, **Hospital** and **Pharmacy** portal screens, plus the **public website** (home) and the **register** page. Standing rules were added in the shared layout, listing, form and button components, so other portals already inherit the header action placement, overflow menus, sticky form footers and 44px touch targets. Those screens still need a visual review, screenshots and the layout test next week — do not treat inheritance as “done”.

Do this work when the user says to build next week. Do not start it before then.

## Standing rules to apply

- One primary action per view, top right of the page header (`.d-top-actions` or `.sc-nav-cta`).
- Row actions: first action stays visible; extra actions go in the overflow (kebab) menu.
- Long forms: sticky footer action bar, Cancel on the left, primary on the right.
- Destructive actions: danger style plus a confirm dialog.
- Options (selects, filters, toggles, tabs, kebab) have a clear label and a sensible default. Long lists (`> 12` options) are searchable. Filters sit in one bar (`.d-tools`).
- Spacing stays on the grid. Touch targets are 44px or larger. Sticky actions work on a phone. Every screen fits 360–1440px in light and dark mode, with no overlapping or off-screen controls and no second primary in the header.

## Done tonight (do not redo unless a regression appears)

- Public home (header: Sign in + Register).
- Public register (Cancel left, Create my account right).
- Vendor public website header (Book as the primary CTA, Sign in as the secondary). Shared header, so every vendor site already has the new CTAs; only Clinic was screenshot-reviewed tonight.
- Clinic, Hospital and Pharmacy: overview, patients list, invoices/bills list, and the add-patient form.

## Public website — remaining pages

- [ ] Directory
- [ ] Vendor type / category pages
- [ ] Free services
- [ ] Pricing section already lives on the home page (review only if the layout changes)
- [ ] Login
- [ ] Forgot password and reset password
- [ ] Terms
- [ ] Privacy
- [ ] Any other marketing or legal page added after tonight

## Super admin

- [ ] Overview / dashboard
- [ ] Vendors list, new vendor, vendor profile, approve and reject
- [ ] Registration requests
- [ ] Plans and subscription invoices
- [ ] Currencies
- [ ] Vendor types
- [ ] Website content and free services
- [ ] Platform reports
- [ ] Settings
- [ ] Staff / platform users (if present)
- [ ] Every other admin list, form and report

## Clinic, Hospital and Pharmacy — remaining portal screens

These three types were the focus tonight, but only the overview, patients, invoices and add-patient form were reviewed. Walk the rest next week:

- [ ] Appointment / request lists and forms (book, add, cancel)
- [ ] Queue / serial workspace (including drag-and-drop, when it ships)
- [ ] Doctors / team
- [ ] Staff accounts and Role access
- [ ] Prescriptions list and the one-screen workspace
- [ ] Forms and records, printed documents and PDFs
- [ ] Accounts, cash book, day-end and reports
- [ ] Stock, stock alerts and stock movements (Pharmacy)
- [ ] Departments (Hospital)
- [ ] Website profile, hours, domains, gallery, FAQ, news, messages, free services
- [ ] Bills and legal details, plan and expiry, My account
- [ ] Any Ops modules unique to these types (visits, rooms, and so on)

## Other vendor types (review each portal end to end)

Shared components already style these screens. Next week, open every list, form, dashboard and operational module for each type below, apply the standing rules, and take light/dark screenshots at 360 and 1440.

- [ ] Specialty clinic (`specialty-clinic`)
- [ ] Dental clinic (`dental-clinic`)
- [ ] Eye clinic (`eye-clinic`)
- [ ] Maternity and child care (`maternity-center`)
- [ ] Mental health center (`mental-health`)
- [ ] Traditional and alternative medicine (`alternative-medicine`)
- [ ] Dialysis center (`dialysis-center`)
- [ ] Diagnostic center (`diagnostic-center`)
- [ ] Pathology laboratory (`laboratory`)
- [ ] Imaging and radiology center (`imaging-center`)
- [ ] Nursing home (`nursing-home`)
- [ ] Rehabilitation and physiotherapy (`rehabilitation`)
- [ ] Blood bank (`blood-bank`)
- [ ] Ambulance service (`ambulance`)
- [ ] Telemedicine provider (`telemedicine`)
- [ ] Home care and nursing (`home-care`)
- [ ] Medical equipment supplier (`equipment-supplier`)

For each type, include its Ops screens (queues, trips, donations, samples, rooms, visits — whatever that type actually has), plus the shared modules: overview, people, requests, billing, accounts, reports, website, roles, settings.

## Other portals and sites

- [ ] Patient portal (login, prescriptions, reports, invoices, family), when it is live
- [ ] Doctor / practitioner workspace, when it is live
- [ ] Per-role portals for every role in every vendor type (receptionist, nurse, accountant, pharmacist, technician, driver, and so on)
- [ ] Vendor public websites for types other than Clinic (header CTAs already shipped; review content, booking form and mobile wrap)
- [ ] Printed document HTML views and PDF layouts

## How to sign a screen off

1. Apply the standing rules (or confirm the shared component already did).
2. Run `tests/uiux_layout.py` against that screen at 360 and 1440, light and dark.
3. Save before/after screenshots under `shots/uiux/`.
4. Tick the item here.
