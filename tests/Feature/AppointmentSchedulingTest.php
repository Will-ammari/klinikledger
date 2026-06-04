<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\DoctorWorkingHour;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_appointment_inside_working_hours(): void
    {
        [$clinic, $owner, $doctor, $patient] = $this->createSchedulingScenario();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
            'reason' => 'Initial consultation',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.status', AppointmentStatus::Scheduled->value);

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Scheduled->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $owner->id,
            'action' => AuditAction::AppointmentCreated->value,
        ]);
    }

    public function test_double_booking_is_rejected(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();

        Sanctum::actingAs($owner);

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
            'reason' => 'First appointment',
        ])->assertCreated();

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
            'reason' => 'Overlapping appointment',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_appointment_during_doctor_time_off_is_rejected(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();

        DoctorTimeOff::factory()
            ->forDoctor($doctor)
            ->onMondayMorning()
            ->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 10:00:00',
            'reason' => 'During time off',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_appointment_outside_working_hours_is_rejected(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();

        Sanctum::actingAs($owner);

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 18:00:00',
            'reason' => 'Outside working hours',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at']);
    }

    private function createSchedulingScenario(): array
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

        DoctorWorkingHour::factory()
            ->forDoctor($doctor)
            ->mondayMorning()
            ->create();

        $patient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'date_of_birth' => '1991-07-20',
            ]);

        return [$clinic, $owner, $doctor, $patient, $doctorUser];
    }
}
