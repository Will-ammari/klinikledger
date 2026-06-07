# KlinikLedger / PraxisFlow

KlinikLedger is a Laravel backend API portfolio project for healthcare practice operations.

The project simulates the backend workflows of a small medical practice: authentication, clinic-scoped users, doctors, patients, appointments, availability rules, treatment notes, invoices, audit logs, consents, and GDPR-inspired patient privacy operations.

> This is a backend engineering portfolio project. It is not a certified medical product, not a real electronic health record system, and not intended for production healthcare use. Privacy features are GDPR-inspired and implemented for demonstration purposes only.

---

## Project Goals

This project was built to demonstrate production-oriented backend skills:

- REST API design with Laravel
- Laravel Sanctum authentication
- Role-based authorization with Policies
- Multi-tenant clinic data isolation
- Appointment scheduling business rules
- Audit logging for sensitive operations
- Billing workflows with invoice items and totals
- Patient consent tracking
- GDPR-inspired patient export and anonymization
- Feature tests for critical business rules
- Clean, maintainable Laravel architecture

---

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL
- PHPUnit / Laravel Feature Tests
- Eloquent ORM
- Laravel Form Requests
- Laravel API Resources
- Laravel Policies

---

## Main Features

### Authentication & Users

- Register a clinic owner
- Login/logout with Sanctum tokens
- Get current authenticated user
- Manage clinic users
- Role-based access control:
  - `owner_clinic`
  - `doctor`
  - `receptionist`

### Clinic Scoping

All sensitive records are scoped by `clinic_id`.

Users from one clinic cannot access another clinic's doctors, patients, appointments, invoices, consents, exports, or audit logs.

### Doctors

- Manage doctor profiles
- Link doctor profile to a user account
- Store specialization and appointment duration
- Support active/inactive doctors

### Patients

- Manage clinic patients
- Restrict doctor access to patients linked to their appointments
- Support privacy operations such as export and anonymization

### Working Hours & Availability

- Define doctor working hours
- Add doctor time off
- Reject appointments outside working hours
- Reject appointments during time off
- Prevent double booking

### Appointments

Supported appointment lifecycle:

- `scheduled`
- `confirmed`
- `completed`
- `cancelled`
- `no_show`

Business rules include:

- Confirm appointments
- Cancel appointments with reason
- Complete past appointments
- Prevent completing future appointments
- Mark past appointments as no-show
- Prevent future no-show marking
- Reschedule appointments
- Prevent rescheduling cancelled appointments
- Audit lifecycle changes

### Treatment Notes

- Doctors can create treatment notes for their own appointments
- Receptionists cannot view treatment notes
- Clinic owners can view selected notes only when visibility allows it
- Treatment note reads and changes are audit logged

### Invoices

- Create invoices with invoice items
- Calculate subtotal, tax, and total
- Issue invoices
- Mark issued invoices as paid
- Cancel invoices
- Prevent updating paid invoices
- Prevent doctors from accessing invoices

### Consents

- Track patient consents
- Withdraw consents
- Automatically withdraw previous active consent of the same type when a new one is granted
- Prevent doctors from managing consents
- Audit consent changes

### Patient Export

- Generate JSON export of patient data
- Includes:
  - Patient profile
  - Appointments
  - Invoices and invoice items
  - Consents
- Restricted to owner/receptionist
- Audit logged

### Patient Anonymization

- Owner-only privacy operation
- Replaces personal data without breaking relational records
- Keeps appointments, invoices, consents, and audit logs linked
- Prevents anonymizing an already anonymized patient

### Audit Logs

Sensitive actions are recorded through audit logs, including:

- Viewing sensitive patient-related data
- Appointment lifecycle changes
- Treatment note operations
- Invoice operations
- Consent operations
- Patient export
- Patient anonymization

Only clinic owners can view audit logs.

---

## Demo Data

The project includes a demo seeder.

Run:

```bash
php artisan migrate:fresh --seed
