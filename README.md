# KlinikLedger / PraxisFlow

![Tests](https://github.com/Will-ammari/klinikledger/actions/workflows/tests.yml/badge.svg)

KlinikLedger, also referred to as PraxisFlow in the project specification, is a Laravel REST API portfolio project for healthcare practice operations.

It simulates the backend workflows of a small clinic: authentication, clinic-scoped users, doctors, patients, appointment scheduling, availability rules, treatment notes, invoices, audit logs, consents, and GDPR-inspired patient privacy operations.

> **Disclaimer**  
> This is a backend engineering portfolio project. It is not a certified medical product, not a real electronic health record system, and not intended for production healthcare use. Privacy features are GDPR-inspired and implemented for demonstration purposes only.

---

## Hiring Signal

KlinikLedger demonstrates that I can build privacy-conscious, healthcare-style Laravel backends rather than only CRUD APIs. The project combines clinic-scoped authorization, patient privacy workflows, audit logging, appointment availability, invoice workflows, automated tests, static analysis, request tracing, standardized API errors, and CI quality gates.

Target positioning:

```text
Backend Developer | PHP/Laravel | REST APIs | Healthcare-style Workflows | Docker | PHPUnit | PHPStan | CI
```

---

## Production Readiness

The codebase includes production-oriented backend patterns for a portfolio review context:

- Laravel Pint formatting checks
- Larastan / PHPStan static analysis
- PHPUnit feature coverage for critical domain workflows
- GitHub Actions CI quality gate
- Standardized JSON API error responses
- `X-Request-Id` request tracing for API responses and log correlation
- Health endpoint at `GET /api/v1/health`
- Docker Compose development environment

---

## Privacy & GDPR-inspired Design

KlinikLedger implements GDPR-inspired privacy workflows for portfolio purposes. It is not a legal compliance claim and is not certified for real healthcare production use.

Privacy-oriented workflows include:

- patient export
- patient anonymization
- consent records
- audit logs for sensitive patient operations
- clinic-scoped access control
- role-based authorization for owners, doctors, and receptionists

---

## Quality & Test Coverage

The quality gate is intentionally simple and review-friendly:

```bash
composer quality
```

This runs:

```bash
composer lint
composer analyse
composer test
```

Core tested areas include patient export, patient anonymization, clinic isolation, audit log authorization, consent workflows, invoice workflows, appointment scheduling, treatment note visibility, standardized API errors, and the health endpoint.

---

## Architecture

KlinikLedger follows a conventional Laravel MVC/API structure with domain-focused services:

- Form Requests for validation
- Policies for role and clinic-scoped authorization
- API Resources for response shape consistency
- Services for audit logging, scheduling availability, invoice calculation, patient export, patient anonymization, and health checks
- Feature tests for business-critical flows

---

## Health Monitoring

The public health endpoint is available at:

```http
GET /api/v1/health
```

It checks:

- database connectivity
- cache read/write behavior
- queue configuration
- Redis connectivity when Redis is used by the active cache or queue configuration
- per-check latency in milliseconds

---

## Why this project exists

The goal of this project is to demonstrate practical backend engineering skills beyond basic CRUD:

- REST API design with Laravel
- Laravel Sanctum token authentication
- Role-based authorization with Policies
- Multi-tenant clinic data isolation
- Business rules for scheduling, billing, and privacy workflows
- Audit logging for sensitive operations
- Filters and pagination for list endpoints
- Feature tests for critical domain behavior
- Seeded demo data for quick review
- Docker Compose local development setup
- CI-ready project structure

This project is designed as a portfolio case study for backend roles, especially Laravel / REST API roles where authorization, data isolation, testing, and maintainability matter.

---

## Tech stack

- PHP 8.2+ locally
- PHP 8.3 in Docker
- Laravel 12
- Laravel Sanctum
- MySQL via Docker Compose
- SQLite-compatible tests/local development
- Redis via Docker Compose
- Mailpit via Docker Compose
- Eloquent ORM
- Laravel Form Requests
- Laravel API Resources
- Laravel Policies
- PHPUnit / Laravel Feature Tests
- Laravel Pint
- GitHub Actions CI
- Docker Compose

---

## Core features

### Authentication and users

- Register a new clinic owner
- Login and logout with Sanctum tokens
- Retrieve the authenticated user via `/api/me`
- Manage clinic users
- Filter users by role, status, and search term
- Paginate users with capped `per_page` support
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
- Filter doctors by specialization and active status
- Search doctors by specialization or linked user details
- Paginate doctors with capped `per_page` support

### Patients

- Manage patient records inside the authenticated user's clinic
- Restrict doctors to patients linked to their own appointments
- Support filters and pagination for patient lists
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
- appointment lists support filtering and pagination

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
- Invoice lists support filtering and pagination

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

Audit log lists support filtering and pagination.

---

## Demo data

The project includes a demo seeder.

Run locally:

```bash
php artisan migrate:fresh --seed
```

Run inside Docker:

```bash
docker compose exec app php artisan migrate:fresh --seed
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

## Running with Docker

This project includes a Docker Compose setup for local development and review.

### Docker services

- PHP-FPM application container
- Nginx web server
- MySQL database
- Redis
- Mailpit for local email testing

### Docker setup

From the project root:

```bash
cp .env.docker.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The application will be available at:

```text
http://localhost:8080
```

The API base URL will be:

```text
http://localhost:8080/api
```

Mailpit will be available at:

```text
http://localhost:8025
```

### Running tests inside Docker

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

### Docker database connection from host machine

Use these credentials if you want to connect from a local database client:

| Item | Value |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3307` |
| Database | `klinikledger` |
| Username | `klinikledger` |
| Password | `secret` |

### Useful Docker commands

```bash
docker compose ps
docker compose logs -f
docker compose logs -f app
docker compose logs -f nginx
docker compose down
docker compose down -v
```

Use `docker compose down -v` only when you want to remove the database volume and start fresh.

---

## Local setup without Docker

### Requirements

- PHP 8.2 or newer
- Composer
- SQLite extension enabled for PHP, or another configured database

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

Run the full test suite:

```bash
php artisan test
```

Run the test suite inside Docker:

```bash
docker compose exec app php artisan test
```

Current documented stable result:

```text
52 tests passed
175 assertions
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
- filters and capped pagination for list endpoints

---

## Code quality

Run the complete local quality gate:

```bash
composer quality
```

This runs:

- the Laravel feature test suite
- Laravel Pint formatting checks
- Larastan / PHPStan static analysis

Run Laravel Pint formatting checks locally:

```bash
composer lint
```

Run Laravel Pint formatting checks inside Docker:

```bash
docker compose exec app ./vendor/bin/pint --test
```

Apply formatting locally:

```bash
composer format
```

Apply formatting inside Docker:

```bash
docker compose exec app ./vendor/bin/pint
```

Run static analysis locally:

```bash
composer analyse
```

Run static analysis inside Docker:

```bash
docker compose exec app ./vendor/bin/phpstan analyse
```

Current documented stable results:

```text
52 tests passed
175 assertions
Laravel Pint PASS
Larastan / PHPStan PASS
```

---

## API overview

Base URL for local development without Docker:

```text
http://127.0.0.1:8000/api
```

Base URL for Docker development:

```text
http://localhost:8080/api
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

A Postman collection is available at [`docs/postman/KlinikLedger.postman_collection.json`](docs/postman/KlinikLedger.postman_collection.json).

---

## List filters and pagination

List endpoints use pagination and selected filters.

### Users

```http
GET /api/users?role=doctor
GET /api/users?status=active
GET /api/users?search=lina
GET /api/users?per_page=15
```

Supported filters:

- `role`
- `status`
- `search`
- `per_page`

### Doctors

```http
GET /api/doctors?specialization=cardio
GET /api/doctors?is_active=1
GET /api/doctors?search=lina
GET /api/doctors?per_page=15
```

Supported filters:

- `specialization`
- `is_active`
- `search`
- `per_page`

### Pagination behavior

`per_page` is capped to prevent oversized API responses.

```http
GET /api/users?per_page=500
```

The API caps the effective page size at `100`.

---

## Example API flow

### Login without Docker

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@example.com","password":"password"}'
```

### Login with Docker

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@example.com","password":"password"}'
```

Use the returned token as a bearer token.

### Get current user

```bash
curl http://localhost:8080/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### List patients

```bash
curl http://localhost:8080/api/patients \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

### List doctors with filters

```bash
curl "http://localhost:8080/api/doctors?search=lina&is_active=1" \
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
- capped pagination for list responses
- no secrets committed to the repository

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
- run Larastan / PHPStan static analysis

---

## Postman

A Postman collection is included for API review and manual testing:

```text
docs/postman/KlinikLedger.postman_collection.json
```

Recommended manual flow:

1. Login as owner
2. Call `/api/me`
3. List patients
4. List doctors
5. Create or inspect an appointment
6. Inspect invoices
7. Inspect audit logs as owner

---

## Roadmap

### High priority

- Add queue-based appointment confirmation emails
- Add queue-based appointment reminder emails
- Add Mailpit-backed email workflow examples for local review
- Add tests that verify appointment email jobs are dispatched at the correct lifecycle points

### Medium priority

- Add an OpenAPI specification for the main public API surface
- Add more request/response examples to `docs/api.md`
- Add generated API reference documentation

### Later

- Add a deployment demo
- Add screenshots or a short demo video
- Add release notes for the final portfolio version

### Completed portfolio milestones

- README documentation
- API documentation in `docs/api.md`
- Postman collection
- GitHub Actions CI
- Feature tests for core business rules
- Filters and capped pagination
- Docker Compose local development setup
- Larastan / PHPStan static analysis
- Portfolio positioning and project disclaimer

---

## License

This project is provided as a portfolio/study case project. See the repository license if one is added.
