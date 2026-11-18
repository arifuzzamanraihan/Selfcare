# Vendor types

Self Care serves 20 kinds of healthcare organisation. Each kind belongs to a **family**, and a family is one website template plus one portal template. Types are rows of the table `sc_vendor_types` (editable in Super admin > Vendor types: switch on or off, rename, change family). The seed values live in `core/config/vendor_types.php`.

## Types

| Type | Short name | Family | On by default |
| --- | --- | --- | --- |
| Hospital | `hospital` | clinical | yes |
| Clinic | `clinic` | clinical | yes |
| Specialty clinic | `specialty-clinic` | clinical | yes |
| Dental clinic | `dental-clinic` | clinical | yes |
| Eye clinic | `eye-clinic` | clinical | yes |
| Maternity and child care | `maternity-center` | clinical | yes |
| Mental health center | `mental-health` | clinical | yes |
| Traditional and alternative medicine | `alternative-medicine` | clinical | yes |
| Dialysis center | `dialysis-center` | clinical | yes |
| Diagnostic center | `diagnostic-center` | diagnostics | yes |
| Pathology laboratory | `laboratory` | diagnostics | yes |
| Imaging and radiology center | `imaging-center` | diagnostics | yes |
| Pharmacy | `pharmacy` | pharmacy | yes |
| Nursing home | `nursing-home` | residential | yes |
| Rehabilitation and physiotherapy | `rehabilitation` | rehab | yes |
| Blood bank | `blood-bank` | bloodbank | yes |
| Ambulance service | `ambulance` | ambulance | yes |
| Telemedicine provider | `telemedicine` | telemedicine | yes |
| Home care and nursing | `home-care` | homecare | yes |
| Medical equipment supplier | `equipment-supplier` | equipment | yes |

## Families (website and portal templates)

| Family | Website sections | Words used in the portal |
| --- | --- | --- |
| `clinical` (Hospital and clinic) | Our services, Departments, Our doctors, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Doctors; people: Patients; requests: Appointments |
| `diagnostics` (Laboratory and diagnostics) | Tests we offer, Health check packages, Sample collection, Reports, Our specialists, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Specialists; people: Clients; requests: Test bookings |
| `pharmacy` (Pharmacy) | Popular products, Shop by category, Home delivery, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Pharmacists; people: none; requests: Orders |
| `residential` (Residential and elder care) | Care we provide, Rooms and fees, Facilities, Visiting, Our care team, Office hours, What people say, Questions and answers, Gallery, News and notices | team: Care team; people: Residents; requests: Enquiries |
| `rehab` (Rehabilitation) | Programs, Facilities and equipment, Our therapists, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Therapists; people: Clients; requests: Session bookings |
| `bloodbank` (Blood bank) | Blood stock, How to donate, Donation camps, Opening hours, Questions and answers, Gallery, News and notices | team: Staff; people: Donors; requests: Blood requests |
| `ambulance` (Ambulance service) | Our fleet, Services, Coverage area, Availability, What people say, Questions and answers, Gallery, News and notices | team: Crew; people: none; requests: Dispatch requests |
| `telemedicine` (Telemedicine) | Consultations, How it works, Our specialists, Available hours, What people say, Questions and answers, Gallery, News and notices | team: Specialists; people: Patients; requests: Consultations |
| `homecare` (Home care) | Care at home, Areas we cover, Our caregivers, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Caregivers; people: Clients; requests: Visit requests |
| `equipment` (Medical equipment) | Equipment, Categories, Rental and maintenance, Delivery and warranty, Opening hours, What people say, Questions and answers, Gallery, News and notices | team: Staff; people: none; requests: Quote requests |

## Registration

`/register` shows the enabled types as a choice. The type is stored in `sc_vendors.type_slug`. It decides the website template, the portal menu, the roles that are switched on (`core/config/roles.php`, from the feature spec), the invoice and form catalog (`core/config/documents.php`) and which plans are offered (`sc_plans.types`).

## Website template per type

The vendor admin edits the template content in the portal under **Website** (profile, hours, logo, cover, text boxes) and under each section of the template (cards such as services or tests, questions, reviews, gallery, news). What the vendor can edit is exactly the `sections` list of its family. Doctor, team and department sections read the same tables as the portal, so nothing is entered twice.

## Add or change a type

1. Open **Super admin > Vendor types** and add a row: name, short name (cannot change later), family, icon, one-line description and who it is for.
2. To change what a family shows, edit `core/config/vendor_types.php`: add a section to `sections` (kinds: `cards`, `faq`, `testimonials`, `gallery`, `news`, `team`, `departments`, `text`, `hours`). A type can override one label with the last array element of its row.
3. To change roles for a type, edit the `enabled` map in `core/config/roles.php`. To change the documents it prints, edit `by_family` in `core/config/documents.php`.

No other code change is needed.
