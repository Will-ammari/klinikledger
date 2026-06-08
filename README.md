# KlinikLedger / PraxisFlow

KlinikLedger, also referred to as PraxisFlow in the project specification, is a Laravel REST API portfolio project for healthcare practice operations.

It simulates the backend workflows of a small clinic: authentication, clinic-scoped users, doctors, patients, appointment scheduling, availability rules, treatment notes, invoices, audit logs, consents, and GDPR-inspired patient privacy operations.

> **Disclaimer**  
> This is a backend engineering portfolio project. It is not a certified medical product, not a real electronic health record system, and not intended for production healthcare use. Privacy features are GDPR-inspired and implemented for demonstration purposes only.

---

## Why this project exists

The goal of this project is to demonstrate practical backend engineering skills beyond basic CRUD:

- REST API design with Laravel
- Laravel Sanctum token authentication
- Role-based authorization with Policies
- Multi-tenant clinic data isolation
- Business rules for scheduling, billing, and privacy workflows
- Audit logging for sensitive operations
- Feature tests for critical domain behavior
- Seeded demo data for quick review
- CI-ready project structure

This project is designed as a portfolio case study for backend roles, especially Laravel / REST API roles where authorization, data isolation, testing, and maintainability matter.

---

## Tech stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- SQLite by default for local development and tests
- MySQL/PostgreSQL compatible Laravel database layer
- Eloquent ORM
- Laravel Form Requests
- Laravel API Resources
- Laravel Policies
- PHPUnit / Laravel Feature Tests
- Laravel Pint
- GitHub Actions CI

---

## Core features

### Authentication and users

- Register a new clinic owner
- Login and logout with Sanctum tokens
- Retrieve the authenticated user via `/api/me`
- Manage clinic users
- Change user roles
- Protect the last `owner_clinic` user from removal/demotion scenarios

Supported roles:

- `owner_clinic`
- `doctor`
- `receptionist`

### Clinic scoping

All sensitive business records are scoped by `clinic_id`.

Users from one clinic cannot access another clinic's:

- users
- doctors
- patients
- appointments
- treatment notes
- invoices
- consents
- patient exports
- audit logs

The API derives the current clinic from the authenticated user instead of trusting `clinic_id` from incoming requests.

### Doctors

- Manage doctor profiles
- Link doctor profiles to user accounts
- Store specialization
- Configure default appointment duration
- Mark doctors as active or inactive

### Patients

- Manage patient records inside the authenticated user's clinic
- Restrict doctors to patients linked to their own appointments
- Support GDPR-inspired privacy operations:
  - patient data export
  - patient anonymization

### Working hours and availability

- Define weekly doctor working hours
- Add doctor time off
- Query available slots for a given date
- Reject appointment creation outside working hours
- Reject appointments during time off
- Prevent double booking

### Appointments

Supported appointment states:

- `scheduled`
- `confirmed`
- `completed`
- `cancelled`
- `no_show`

Supported lifecycle actions:

- confirm
- cancel with reason
- complete
- mark as no-show
- reschedule

Business rules include:

- cancelled appointments cannot be rescheduled
- future appointments cannot be completed
- future appointments cannot be marked as no-show
- appointment lifecycle changes are audit logged

### Treatment notes

- Doctors can create treatment notes for appointments they are allowed to access
- Receptionists cannot view treatment notes by default
- Reads and changes are audit logged
- Treatment note visibility is enforced through Policies

### Invoices

- Create invoices with invoice items
- Calculate subtotal, tax, and total
- Issue draft invoices
- Mark issued invoices as paid
- Cancel invoices
- Prevent updating paid invoices
- Prevent doctors from accessing invoice workflows

### Consents

- Track patient consents
- Withdraw consent records
- Automatically withdraw a previous active consent of the same type when a new one is granted
- Prevent doctors from managing consents
- Audit consent changes

### Patient export

- Generate a JSON export for a patient
- Include profile, appointments, invoices, invoice items, and consents
- Restrict export operations to authorized staff
- Audit export creation and viewing

### Patient anonymization

- Owner-only privacy operation
- Replaces personal data without deleting relational business history
- Keeps appointments, invoices, consents, and audit logs linked
- Prevents anonymizing an already anonymized patient

### Audit logs

Sensitive actions are recorded through audit logs, including:

- viewing sensitive patient-related data
- appointment lifecycle changes
- treatment note operations
- invoice operations
- consent operations
- patient export
- patient anonymization

Only clinic owners can view audit logs.

---

## Demo data

The project includes a demo seeder.

Run:

```bash
php artisan migrate:fresh --seed

```

Seeded clinic:

| Item | Value |
| --- | --- |
| Clinic | Berlin Family Praxis |
| Owner login | `owner@example.com` / `password` |
| Receptionist login | `receptionist@example.com` / `password` |
| Doctor login | `doctor@example.com` / `password` |
| Demo patient | Lena Schneider |
| Demo appointment | Initial consultation, confirmed |
| Demo invoice | Issued invoice, total `119.00` |
| Demo consent | Email reminders granted |

---

## Local setup

### Requirements

- PHP 8.2 or newer
- Composer
- SQLite extension enabled for PHP

### Installation

```bash
git clone <repository-url>
cd KlinikLedger
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The API will be available at:

```text
http://127.0.0.1:8000/api
```

---

## Running tests

```bash
php artisan test
```

Current documented stable result:

```text
45 tests passed
160 assertions
```

The tests cover the most important business rules:

- appointment scheduling and availability
- appointment lifecycle transitions
- clinic-scoped authorization
- treatment note visibility
- invoice workflow rules
- consent workflow rules
- patient export
- patient anonymization
- audit log authorization

---

## Code quality

Run Laravel Pint formatting checks:

```bash
./vendor/bin/pint --test
```

Apply formatting:

```bash
./vendor/bin/pint
```

---

## API overview

Base URL for local development:

```text
http://127.0.0.1:8000/api
```

Most endpoints require a Sanctum bearer token.

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Main endpoint groups:

| Area | Endpoints |
| --- | --- |
| Auth | `/auth/register`, `/auth/login`, `/auth/logout`, `/me` |
| Clinic | `/clinics/current` |
| Users | `/users`, `/users/{user}`, `/users/{user}/role` |
| Doctors | `/doctors`, `/doctors/{doctor}` |
| Working hours | `/doctors/{doctor}/working-hours` |
| Time off | `/doctors/{doctor}/time-offs` |
| Availability | `/doctors/{doctor}/available-slots` |
| Patients | `/patients`, `/patients/{patient}`, `/patients/{patient}/anonymize` |
| Appointments | `/appointments`, `/appointments/{appointment}` |
| Appointment lifecycle | `/appointments/{appointment}/confirm`, `/cancel`, `/complete`, `/no-show`, `/reschedule` |
| Treatment notes | `/appointments/{appointment}/notes`, `/treatment-notes/{note}` |
| Invoices | `/invoices`, `/invoices/{invoice}`, `/issue`, `/mark-paid`, `/cancel` |
| Consents | `/patients/{patient}/consents`, `/consents/{consent}/withdraw` |
| Patient exports | `/patients/{patient}/export-request`, `/patient-exports/{patientExport}` |
| Audit logs | `/audit-logs`, `/audit-logs/{auditLog}` |

More endpoint details are documented in [`docs/api.md`](docs/api.md).

---

## Example API flow

### Login

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@example.com","password":"password"}'
```

Use the returned token as a bearer token.

### Get current user

```bash
curl http://127.0.0.1:8000/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### List patients

```bash
curl http://127.0.0.1:8000/api/patients \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

---

## Architecture notes

The project keeps business logic out of controllers where practical:

- Controllers handle HTTP input/output
- Form Requests validate request data
- Policies enforce authorization
- API Resources shape JSON responses
- Services encapsulate domain logic
- Enums centralize state/action values

Important services:

- `App\Services\Scheduling\AvailabilityService`
- `App\Services\Billing\InvoiceCalculator`
- `App\Services\Audit\AuditLogger`
- `App\Services\Privacy\PatientExportBuilder`
- `App\Services\Privacy\PatientAnonymizer`

Important enums:

- `AppointmentStatus`
- `InvoiceStatus`
- `UserRole`
- `ConsentType`
- `ConsentStatus`
- `AuditAction`
- `TreatmentNoteVisibility`

---

## Security and privacy approach

Implemented security/privacy principles:

- Sanctum authentication for protected endpoints
- Policy-based authorization for sensitive models
- clinic-level tenant scoping
- Form Request validation
- audit logs for sensitive operations
- patient soft deletion/anonymization strategy
- no real patient data in seeders
- GDPR-inspired export/anonymization features

Important wording: this project uses **GDPR-inspired** privacy features. It does not claim legal GDPR compliance.

---

## Continuous integration

GitHub Actions workflow is included at:

```text
.github/workflows/tests.yml
```

It runs on pushes and pull requests:

- install PHP dependencies
- prepare `.env`
- generate app key
- run migrations
- run tests
- run Laravel Pint formatting check

---

## Roadmap

High priority:

- Add OpenAPI or Postman collection
- Improve filters and pagination for list endpoints
- Add more endpoint examples to `docs/api.md`

Medium priority:

- Add PHPStan/Larastan static analysis
- Add Docker Compose with app, nginx, database, redis, and mailpit
- Add queue-based email reminders

Later:

- Add deployment demo
- Add screenshots or a short demo video
- Add generated API reference documentation

---

## Portfolio positioning

Suggested CV description:

> Built a Laravel backend SaaS case study for clinic operations, including multi-tenant data isolation, role-based access control, appointment scheduling, patient records, invoice workflows, audit logging, consent tracking, and GDPR-inspired data export/anonymization features. Designed REST APIs, database schema, service-layer business logic, policies, feature tests, demo seed data, and GitHub Actions CI.

---

## License

This project is provided as a portfolio/study case project. See the repository license if one is added.
