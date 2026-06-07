<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Enums\InvoiceStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consent;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_request_patient_export(): void
    {
        [$clinic, , $receptionist, , $patient, $appointment, $invoice, $consent] = $this->createPatientExportScenario();

        Sanctum::actingAs($receptionist);

        $response = $this->postJson("/api/patients/{$patient->id}/export-request");

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.requested_by_user_id', $receptionist->id)
            ->assertJsonPath('data.payload.patient.email', 'lena.schneider@example.com')
            ->assertJsonPath('data.payload.appointments.0.id', $appointment->id)
            ->assertJsonPath('data.payload.invoices.0.id', $invoice->id)
            ->assertJsonPath('data.payload.consents.0.id', $consent->id);

        $this->assertDatabaseHas('patient_exports', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'requested_by_user_id' => $receptionist->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::PatientExportRequested->value,
        ]);
    }

    public function test_owner_can_view_existing_patient_export(): void
    {
        [, $owner, $receptionist, , $patient] = $this->createPatientExportScenario();

        $patientExport = PatientExport::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'requested_by_user_id' => $receptionist->id,
            'payload' => [
                'patient' => [
                    'id' => $patient->id,
                    'email' => $patient->email,
                ],
            ],
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/patient-exports/{$patientExport->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patientExport->id)
            ->assertJsonPath('data.patient_id', $patient->id);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'action' => AuditAction::PatientExportViewed->value,
        ]);
    }

    public function test_doctor_cannot_request_patient_export(): void
    {
        [, , , $doctorUser, $patient] = $this->createPatientExportScenario();

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/patients/{$patient->id}/export-request")
            ->assertForbidden();

        $this->assertDatabaseCount('patient_exports', 0);
    }

    public function test_user_cannot_view_patient_export_from_another_clinic(): void
    {
        [, , $receptionist] = $this->createPatientExportScenario();

        $otherClinic = Clinic::factory()->create([
            'name' => 'Munich Praxis',
            'slug' => 'munich-praxis',
            'timezone' => 'Europe/Berlin',
        ]);

        $otherPatient = Patient::factory()
            ->forClinic($otherClinic)
            ->create([
                'first_name' => 'Other',
                'last_name' => 'Patient',
                'email' => 'other.patient@example.com',
            ]);

        $otherExport = PatientExport::create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'requested_by_user_id' => null,
            'payload' => [
                'patient' => [
                    'id' => $otherPatient->id,
                    'email' => $otherPatient->email,
                ],
            ],
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->getJson("/api/patient-exports/{$otherExport->id}")
            ->assertForbidden();
    }

    private function createPatientExportScenario(): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Berlin Family Praxis',
            'slug' => 'berlin-family-praxis',
            'timezone' => 'Europe/Berlin',
        ]);

        $owner = User::factory()
            ->owner()
            ->for($clinic)
            ->create([
                'name' => 'Owner User',
                'email' => 'owner@example.com',
            ]);

        $receptionist = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Receptionist One',
                'email' => 'receptionist@example.com',
            ]);

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Anna Schmidt',
                'email' => 'doctor@example.com',
            ]);

        $doctor = Doctor::factory()
            ->linkedToUser($doctorUser)
            ->create([
                'specialization' => 'General Medicine',
                'appointment_duration_minutes' => 30,
                'is_active' => true,
            ]);

        $patient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'date_of_birth' => '1991-07-20',
            ]);

        $appointment = Appointment::factory()
            ->forClinicDoctorAndPatient($clinic, $doctor, $patient)
            ->scheduledAt(now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'))
            ->create([
                'reason' => 'Initial consultation',
            ]);

        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => InvoiceStatus::Issued,
            'subtotal' => '100.00',
            'tax' => '19.00',
            'total' => '119.00',
            'due_date' => now()->addDays(14)->toDateString(),
            'issued_at' => now(),
        ]);

        $invoice->items()->create([
            'description' => 'General consultation',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'line_total' => '100.00',
        ]);

        $consent = Consent::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $receptionist->id,
            'type' => ConsentType::EmailReminders,
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
            'notes' => 'Patient agreed to email reminders.',
        ]);

        return [
            $clinic,
            $owner,
            $receptionist,
            $doctorUser,
            $patient,
            $appointment,
            $invoice,
            $consent,
        ];
    }
}
