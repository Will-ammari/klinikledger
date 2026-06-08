# KlinikLedger / PraxisFlow API Notes

Base URL for local development:

```text
http://127.0.0.1:8000/api
```

Protected endpoints require:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

---

## Authentication

| Method | Endpoint         | Auth | Purpose                           |
| ------ | ---------------- | ---- | --------------------------------- |
| POST   | `/auth/register` | No   | Register a new clinic owner       |
| POST   | `/auth/login`    | No   | Login and receive a Sanctum token |
| POST   | `/auth/logout`   | Yes  | Revoke current token              |
| GET    | `/me`            | Yes  | Return authenticated user         |

Example login:

```json
{
  "email": "owner@example.com",
  "password": "password"
}
```

---

## Clinic

| Method | Endpoint           | Auth | Purpose                            |
| ------ | ------------------ | ---- | ---------------------------------- |
| GET    | `/clinics/current` | Yes  | Get authenticated user's clinic    |
| PATCH  | `/clinics/current` | Yes  | Update authenticated user's clinic |

Clinic operations use the authenticated user's clinic. The API should not trust a manually submitted `clinic_id` for sensitive operations.

---

## Users and roles

| Method | Endpoint             | Auth | Purpose            |
| ------ | -------------------- | ---- | ------------------ |
| GET    | `/users`             | Yes  | List clinic users  |
| POST   | `/users`             | Yes  | Create clinic user |
| GET    | `/users/{user}`      | Yes  | Show clinic user   |
| PATCH  | `/users/{user}`      | Yes  | Update clinic user |
| DELETE | `/users/{user}`      | Yes  | Delete clinic user |
| PATCH  | `/users/{user}/role` | Yes  | Change user role   |

Main roles:

* `owner_clinic`
* `doctor`
* `receptionist`

Important authorization rules:

* only clinic owners can manage users
* users are scoped to the authenticated user's clinic
* the last clinic owner should remain protected

---

## Doctors

| Method | Endpoint            | Auth | Purpose               |
| ------ | ------------------- | ---- | --------------------- |
| GET    | `/doctors`          | Yes  | List doctors          |
| POST   | `/doctors`          | Yes  | Create doctor profile |
| GET    | `/doctors/{doctor}` | Yes  | Show doctor profile   |
| PATCH  | `/doctors/{doctor}` | Yes  | Update doctor profile |
| DELETE | `/doctors/{doctor}` | Yes  | Delete doctor profile |

Doctor profiles belong to a clinic and may be linked to a user account.

Common doctor fields:

* `user_id`
* `specialization`
* `appointment_duration_minutes`
* `is_active`

---

## Working hours, time off, and availability

| Method | Endpoint                                            | Auth | Purpose                         |
| ------ | --------------------------------------------------- | ---- | ------------------------------- |
| GET    | `/doctors/{doctor}/working-hours`                   | Yes  | List working hours              |
| PUT    | `/doctors/{doctor}/working-hours`                   | Yes  | Replace or update working hours |
| GET    | `/doctors/{doctor}/time-offs`                       | Yes  | List time off records           |
| POST   | `/doctors/{doctor}/time-offs`                       | Yes  | Create time off                 |
| DELETE | `/doctors/{doctor}/time-offs/{timeOff}`             | Yes  | Delete time off                 |
| GET    | `/doctors/{doctor}/available-slots?date=YYYY-MM-DD` | Yes  | List available slots            |

Scheduling rules:

* appointments cannot be booked outside working hours
* appointments cannot overlap doctor time off
* appointments cannot overlap existing appointments
* available slots are calculated from working hours, time off, appointment duration, and existing appointments

Example available slots request:

```http
GET /api/doctors/1/available-slots?date=2026-08-15
```

---

## Patients

| Method | Endpoint                        | Auth | Purpose           |
| ------ | ------------------------------- | ---- | ----------------- |
| GET    | `/patients`                     | Yes  | List patients     |
| POST   | `/patients`                     | Yes  | Create patient    |
| GET    | `/patients/{patient}`           | Yes  | Show patient      |
| PATCH  | `/patients/{patient}`           | Yes  | Update patient    |
| DELETE | `/patients/{patient}`           | Yes  | Delete patient    |
| POST   | `/patients/{patient}/anonymize` | Yes  | Anonymize patient |

Patient access rules:

* patients belong to the authenticated user's clinic
* users cannot access patients from another clinic
* doctors can only access patients connected to their own appointments
* anonymization is restricted to clinic owners
* anonymization replaces personal data without breaking relational history

---

## Appointments

| Method | Endpoint                                 | Auth | Purpose                     |
| ------ | ---------------------------------------- | ---- | --------------------------- |
| GET    | `/appointments`                          | Yes  | List appointments           |
| POST   | `/appointments`                          | Yes  | Create appointment          |
| GET    | `/appointments/{appointment}`            | Yes  | Show appointment            |
| PATCH  | `/appointments/{appointment}`            | Yes  | Update appointment          |
| POST   | `/appointments/{appointment}/confirm`    | Yes  | Confirm appointment         |
| POST   | `/appointments/{appointment}/cancel`     | Yes  | Cancel appointment          |
| POST   | `/appointments/{appointment}/complete`   | Yes  | Complete appointment        |
| POST   | `/appointments/{appointment}/no-show`    | Yes  | Mark appointment as no-show |
| POST   | `/appointments/{appointment}/reschedule` | Yes  | Reschedule appointment      |

Appointment statuses:

* `scheduled`
* `confirmed`
* `completed`
* `cancelled`
* `no_show`

Appointment lifecycle rules:

* appointments must be inside doctor working hours
* appointments cannot overlap existing appointments
* appointments cannot be scheduled during doctor time off
* cancelled appointments cannot be rescheduled
* future appointments cannot be completed
* future appointments cannot be marked as no-show
* lifecycle changes are audit logged

Example appointment creation payload:

```json
{
  "doctor_id": 1,
  "patient_id": 1,
  "starts_at": "2026-08-15T09:30:00",
  "ends_at": "2026-08-15T10:00:00",
  "reason": "Initial consultation"
}
```

Example reschedule payload:

```json
{
  "starts_at": "2026-08-15T10:30:00",
  "ends_at": "2026-08-15T11:00:00"
}
```

Example cancel payload:

```json
{
  "reason": "Patient requested cancellation"
}
```

---

## Treatment notes

| Method | Endpoint                            | Auth | Purpose                     |
| ------ | ----------------------------------- | ---- | --------------------------- |
| GET    | `/appointments/{appointment}/notes` | Yes  | List notes for appointment  |
| POST   | `/appointments/{appointment}/notes` | Yes  | Create note for appointment |
| GET    | `/treatment-notes/{note}`           | Yes  | Show treatment note         |
| PATCH  | `/treatment-notes/{note}`           | Yes  | Update treatment note       |
| DELETE | `/treatment-notes/{note}`           | Yes  | Delete treatment note       |

Treatment notes are sensitive clinical-style records used only for portfolio demonstration.

Authorization rules:

* doctors can create notes for appointments they are allowed to access
* receptionists cannot view treatment notes by default
* owners can only view notes when visibility allows clinic owner access
* reads and changes are audit logged

Example treatment note payload:

```json
{
  "subjective": "Patient reports recurring headache.",
  "objective": "No acute distress observed.",
  "assessment": "Likely tension headache.",
  "plan": "Hydration, rest, follow-up if symptoms continue.",
  "visibility": "doctor_only"
}
```

---

## Invoices

| Method | Endpoint                        | Auth | Purpose              |
| ------ | ------------------------------- | ---- | -------------------- |
| GET    | `/invoices`                     | Yes  | List invoices        |
| POST   | `/invoices`                     | Yes  | Create invoice       |
| GET    | `/invoices/{invoice}`           | Yes  | Show invoice         |
| PATCH  | `/invoices/{invoice}`           | Yes  | Update invoice       |
| POST   | `/invoices/{invoice}/issue`     | Yes  | Issue invoice        |
| POST   | `/invoices/{invoice}/mark-paid` | Yes  | Mark invoice as paid |
| POST   | `/invoices/{invoice}/cancel`    | Yes  | Cancel invoice       |

Invoice statuses:

* `draft`
* `issued`
* `paid`
* `cancelled`

Invoice business rules:

* invoice totals are calculated from invoice items
* paid invoices cannot be updated
* cancelled invoices cannot be paid
* doctors cannot access invoice workflows
* an invoice appointment must belong to the selected patient and clinic

Example invoice payload:

```json
{
  "patient_id": 1,
  "appointment_id": 1,
  "tax_rate": 19,
  "items": [
    {
      "description": "Initial consultation",
      "quantity": 1,
      "unit_price": 100
    }
  ]
}
```

---

## Consents

| Method | Endpoint                       | Auth | Purpose               |
| ------ | ------------------------------ | ---- | --------------------- |
| GET    | `/patients/{patient}/consents` | Yes  | List patient consents |
| POST   | `/patients/{patient}/consents` | Yes  | Create consent        |
| POST   | `/consents/{consent}/withdraw` | Yes  | Withdraw consent      |

Consent rules:

* consent records belong to a patient and clinic
* doctors cannot create or view patient consents
* consent changes are audit logged
* creating a new granted consent of the same type withdraws the previous active one
* users cannot withdraw consent records from another clinic

Example consent payload:

```json
{
  "type": "email_reminders",
  "status": "granted",
  "source": "reception_desk"
}
```

---

## Patient exports

| Method | Endpoint                             | Auth | Purpose               |
| ------ | ------------------------------------ | ---- | --------------------- |
| POST   | `/patients/{patient}/export-request` | Yes  | Create patient export |
| GET    | `/patient-exports/{patientExport}`   | Yes  | Show generated export |

The export includes:

* patient profile
* appointments
* invoices
* invoice items
* consents

Patient export rules:

* doctors cannot request patient exports
* users cannot view exports from another clinic
* export creation and viewing are audit logged

Example export response structure:

```json
{
  "data": {
    "patient": {},
    "appointments": [],
    "invoices": [],
    "consents": []
  }
}
```

---

## Patient anonymization

| Method | Endpoint                        | Auth | Purpose                         |
| ------ | ------------------------------- | ---- | ------------------------------- |
| POST   | `/patients/{patient}/anonymize` | Yes  | Anonymize patient personal data |

Anonymization rules:

* only clinic owners can anonymize patients
* receptionists cannot anonymize patients
* doctors cannot anonymize patients
* users cannot anonymize patients from another clinic
* an already anonymized patient cannot be anonymized again
* anonymization keeps relational business history intact

Example anonymized values:

```text
first_name: Anonymous
last_name: Patient
email: anonymized_patient_<id>@example.invalid
phone: null
date_of_birth: null
```

---

## Audit logs

| Method | Endpoint                 | Auth | Purpose         |
| ------ | ------------------------ | ---- | --------------- |
| GET    | `/audit-logs`            | Yes  | List audit logs |
| GET    | `/audit-logs/{auditLog}` | Yes  | Show audit log  |

Audit log rules:

* only clinic owners can view audit logs
* receptionists cannot view audit logs
* audit logs are scoped by clinic
* sensitive operations write audit log entries

Examples of audit logged operations:

* viewing treatment notes
* appointment lifecycle changes
* creating and withdrawing consents
* creating and viewing patient exports
* anonymizing patients
* invoice workflow changes

---

## Error response style

Typical validation or business rule error:

```json
{
  "message": "The selected doctor is not available at this time.",
  "errors": {
    "starts_at": [
      "This time slot overlaps with another appointment."
    ]
  }
}
```

Typical forbidden response:

```json
{
  "message": "This action is unauthorized."
}
```

---

## Demo credentials

After running:

```bash
php artisan migrate:fresh --seed
```

Use these credentials:

| Role         | Email                      | Password   |
| ------------ | -------------------------- | ---------- |
| Owner        | `owner@example.com`        | `password` |
| Receptionist | `receptionist@example.com` | `password` |
| Doctor       | `doctor@example.com`       | `password` |

---

## Quick manual testing flow

1. Login as owner or receptionist.
2. Copy the returned bearer token.
3. Call `/api/me`.
4. List patients through `/api/patients`.
5. Check available doctor slots through `/api/doctors/{doctor}/available-slots?date=YYYY-MM-DD`.
6. Create an appointment.
7. Confirm or reschedule the appointment.
8. Create an invoice.
9. Issue and mark the invoice as paid.
10. Request a patient export.
11. Login as owner and review audit logs.

---

## Recommended next API documentation step

Convert this file into either:

* a Postman collection, or
* an OpenAPI 3 specification.

That will make the project easier for reviewers, recruiters, and technical leads to test quickly.
