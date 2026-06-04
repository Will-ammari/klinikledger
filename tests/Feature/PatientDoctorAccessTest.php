<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientDoctorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_only_sees_patients_linked_to_their_appointments(): void
    {
        [$clinic, $doctorUser, $doctor] = $this->createDoctorScenario();

        $linkedPatient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Linked',
                'last_name' => 'Patient',
                'email' => 'linked.patient@example.com',
            ]);

        $unlinkedPatient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Unlinked',
                'last_name' => 'Patient',
                'email' => 'unlinked.patient@example.com',
            ]);

        Appointment::factory()
            ->forClinicDoctorAndPatient($clinic, $doctor, $linkedPatient)
            ->scheduledAt('2026-06-08 09:00:00')
            ->create([
                'status' => AppointmentStatus::Scheduled,
                'reason' => 'Initial consultation',
            ]);

        Sanctum::actingAs($doctorUser);

        $response = $this->getJson('/api/patients');

        $response->assertOk();

        $patientIds = collect($response->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($linkedPatient->id, $patientIds);
        $this->assertNotContains($unlinkedPatient->id, $patientIds);
    }

    public function test_doctor_cannot_view_unlinked_patient_directly(): void
    {
        [$clinic, $doctorUser] = $this->createDoctorScenario();

        $unlinkedPatient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Private',
                'last_name' => 'Patient',
                'email' => 'private.patient@example.com',
            ]);

        Sanctum::actingAs($doctorUser);

        $this->getJson('/api/patients/' . $unlinkedPatient->id)
            ->assertForbidden();
    }

    private function createDoctorScenario(): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Berlin Family Praxis',
            'slug' => 'berlin-family-praxis-access',
            'timezone' => 'Europe/Berlin',
        ]);

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Anna Schmidt',
                'email' => 'doctor.patient.access@example.com',
            ]);

        $doctor = Doctor::factory()
            ->linkedToUser($doctorUser)
            ->create([
                'specialization' => 'General Medicine',
                'appointment_duration_minutes' => 30,
                'is_active' => true,
            ]);

        return [$clinic, $doctorUser, $doctor];
    }
}
