# Roles by feature

Generated from the live role templates (tests/roleinfo.php). One section per vendor type. **V** = can open the module; then the actions it may do inside: **A**dd, **E**dit, **D**elete/void, **P**rint, e**X**port. Admin and Owner always have everything. Vendors can change all of this under *Role access* and can make their own roles; Self Care sets what new vendors start with under *Role templates*.


## alternative-medicine

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Pharmacy Assistant | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |  |

## ambulance

| Role | Requests (appointments, orders, bookings) | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Accountant | VAEDPX |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| HR Officer |  | VAE |  |  |  |  |  |  |  |  |  |  |  |
| Dispatcher | VAEP |  |  |  | VAE |  | VE |  |  | VAEP |  |  |  |
| Driver | VEP |  |  |  |  |  |  |  |  |  |  |  |  |
| Paramedic | VAEP |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Store Manager |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |

## blood-bank

| Role | Requests (appointments, orders, bookings) | Patients and clients | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Stock, store and registers | Blood bank records | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Receptionist | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  | V | VAEDPX |  |  | VAEDPX | VPX |
| Lab Technician | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX |  | VAED |  |  |  |  |  |  |  |  |  |  | VPX |
| Branch Manager | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  |  | VAEDPX | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| Phlebotomist | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Blood Bank Technician | VAEP | VAEP |  | VAE |  | VAE |  |  |  |  |  |  | VAEP |  |  |
| Store Manager |  |  |  |  |  | VAED |  |  |  |  | VAEDPX | VAEDPX |  |  |  |

## clinic

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Lab Technician | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Pharmacy Assistant | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |  |

## dental-clinic

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |

## diagnostic-center

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Lab Technician | VAEP | VAEP |  |  |  |  | VAE | VAE |  |  |  | VAEP | VAEP |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| Pathologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  | VPX |
| Phlebotomist | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Radiologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |
| Imaging Technologist | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Store Manager |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |

## dialysis-center

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Ward Attendant | VEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |
| Insurance Officer | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |

## equipment-supplier

| Role | Requests (appointments, orders, bookings) | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Stock, store and registers | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Accountant | VAEDPX |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Dispatcher | VAEP |  |  |  |  |  | VE |  |  |  |  |  |  |
| Driver | VEP |  |  |  |  |  |  |  |  |  |  |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Store Manager |  |  |  |  | VAED |  |  |  |  | VAEDPX | VAEDPX |  |  |
| Sales Representative | VAEP |  |  |  | VAE |  |  |  |  | VAEP |  |  |  |
| Field Engineer | VAEP |  |  |  | VAE |  |  |  |  |  | VAEP |  |  |

## eye-clinic

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Optometrist | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  | VAEP |  |  |
| Store Manager |  |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |  |  |

## home-care

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  | VAEPX |  |  |
| Therapist | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Phlebotomist | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Caregiver | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |

## hospital

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Pharmacist | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX | VAEPX |  |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Lab Technician | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| IT Administrator |  |  |  | VAED |  |  |  |  |  |  | VAED |  |  |  |  |  |  |  |
| HR Officer |  |  |  | VAE |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Resident | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  | VAEP |  |  |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Midwife | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Therapist | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Pathologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |
| Phlebotomist | VAEP |  |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |
| Radiologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |
| Imaging Technologist | VAEP |  |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |
| Pharmacy Assistant | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |  |
| Ward Attendant | VEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |
| Insurance Officer | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |
| Medical Records Officer |  | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Store Manager |  |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |  |  |

## imaging-center

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| Radiologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |
| Imaging Technologist | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |

## laboratory

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Lab Technician | VAEP | VAEP |  |  |  |  | VAE | VAE |  |  |  | VAEP | VAEP |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| Pathologist |  | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  | VPX |
| Phlebotomist | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Store Manager |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |

## maternity-center

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Pharmacist | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX | VAEPX |  |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| HR Officer |  |  |  | VAE |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Resident | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  | VAEP |  |  |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Midwife | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Pharmacy Assistant | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |  |  |  |
| Ward Attendant | VEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |
| Insurance Officer | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |  |  |
| Medical Records Officer |  | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Store Manager |  |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |  |  |

## mental-health

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |
| Counsellor | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Ward Attendant | VEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Medical Records Officer |  | VAEPX |  |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  | VP |  |

## nursing-home

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Pharmacist | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX | VAEPX |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Compliance Officer |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VPX |
| HR Officer |  |  |  | VAE |  |  |  |  |  |  |  |  |  |  |  |
| Consultant | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  |  |
| Head Nurse | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  |  | VAEPX |  |  |
| Therapist | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Ward Attendant | VEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Caregiver | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Medical Records Officer |  | VAEPX |  |  |  |  |  |  |  |  |  |  | VAEPX |  |  |
| Store Manager |  |  |  |  |  |  | VAED |  |  |  |  | VAEDPX |  |  |  |

## pharmacy

| Role | Requests (appointments, orders, bookings) | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Stock, store and registers | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Pharmacist | VAEPX |  |  |  | VAE | VAE |  |  |  | VAEPX | VAEPX |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Pharmacy Assistant | VAEP |  |  |  | VAE |  |  |  |  | VAEP |  |  |  |
| Billing Clerk | VAEPX |  |  |  |  |  |  |  |  | VAEPX |  |  |  |
| Store Manager |  |  |  |  | VAED |  |  |  |  | VAEDPX | VAEDPX |  |  |

## rehabilitation

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX | VPX |
| Case Manager | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  |  |
| Therapist | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |
| Counsellor | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  |

## specialty-clinic

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | Departments | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  | VAE |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VAE | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Clinical Assistant | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  |  |  |  |  | VP |  |

## telemedicine

| Role | Requests (appointments, orders, bookings) | Patients and clients | Doctors and clinical team | Staff accounts | News and notices | Website and profile | Services, products, tests and fleet | Free services | Website messages | Role access and time access | Plan and expiry | Invoices and payments | Clinical documents and forms | Accounts: cash book, expenses and dues | Prescriptions: write, issue and print | Prescriptions: view and print | Reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Doctor | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Nurse | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
| Receptionist | VAEP | VAEP | VAEP |  |  |  |  |  | VE |  |  | VAEP |  |  |  | VP |  |
| Accountant | VAEDPX |  |  |  |  |  |  |  |  |  | V | VAEDPX |  | VAEDPX |  |  | VPX |
| Owner | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all | all |
| Medical Director |  | VAEDPX | VAEDPX |  | VAED |  |  |  |  |  |  |  | VAEDPX |  | VAEDPX |  | VPX |
| Branch Manager | VAEDPX | VAEDPX | VAEDPX |  |  |  |  |  | VED |  |  |  |  | VAEDPX |  | VP | VPX |
| Consultant | VAEPX | VAEPX | VAEPX |  | VAE |  |  |  |  |  |  |  | VAEPX |  | VAEPX |  |  |
| Counsellor | VAEP | VAEP |  |  |  |  |  |  |  |  |  |  | VAEP |  |  | VP |  |
