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

    public function test_available_slots_exclude_bookings_and_reopen_after_cancellation(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();
        Sanctum::actingAs($owner);

        $url = "/api/doctors/{$doctor->id}/available-slots?date=2026-06-08";
        $initial = collect($this->getJson($url)->assertOk()->json('data.slots'))
            ->pluck('starts_at')->all();
        $this->assertCount(6, $initial);
        $this->assertContains('2026-06-08T09:00:00+02:00', $initial);

        $appointmentId = $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
        ])->assertCreated()->json('data.id');

        $afterBooking = collect($this->getJson($url)->assertOk()->json('data.slots'))
            ->pluck('starts_at')->all();
        $this->assertCount(5, $afterBooking);
        $this->assertNotContains('2026-06-08T09:00:00+02:00', $afterBooking);

        $this->postJson("/api/appointments/{$appointmentId}/cancel")->assertOk();

        $afterCancel = collect($this->getJson($url)->assertOk()->json('data.slots'))
            ->pluck('starts_at')->all();
        $this->assertCount(6, $afterCancel);
    }

    public function test_time_off_cannot_silently_conflict_with_existing_booking(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
        ])->assertCreated()->json('data.id');

        $timeOffUrl = "/api/doctors/{$doctor->id}/time-offs";
        $data = [
            'starts_at' => '2026-06-08 09:00:00',
            'ends_at' => '2026-06-08 10:00:00',
        ];

        $this->postJson($timeOffUrl, $data)->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
        $this->assertDatabaseCount('doctor_time_offs', 0);

        $this->postJson("/api/appointments/{$id}/cancel")->assertOk();
        $this->postJson($timeOffUrl, $data)->assertCreated();
        $this->assertDatabaseCount('doctor_time_offs', 1);
    }

    public function test_overlapping_reschedule_is_rejected_without_losing_original_times(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();
        Sanctum::actingAs($owner);

        $first = $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:00:00',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08 09:30:00',
        ])->assertCreated();

        $this->postJson("/api/appointments/{$first}/reschedule", [
            'starts_at' => '2026-06-08 09:30:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);

        $this->assertDatabaseHas('appointments', [
            'id' => $first,
            'starts_at' => '2026-06-08 09:00:00',
        ]);
    }

    public function test_inactive_doctor_does_not_advertise_bookable_slots(): void
    {
        [, $owner, $doctor] = $this->createSchedulingScenario();
        $doctor->update(['is_active' => false]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/doctors/{$doctor->id}/available-slots?date=2026-06-08")
            ->assertOk()->assertJsonPath('data.slots', []);
    }

    public function test_overlapping_working_hours_are_rejected_without_changing_schedule(): void
    {
        [, $owner, $doctor] = $this->createSchedulingScenario();
        Sanctum::actingAs($owner);

        $this->putJson("/api/doctors/{$doctor->id}/working-hours", [
            'working_hours' => [
                ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                ['day_of_week' => 1, 'starts_at' => '11:00', 'ends_at' => '13:00'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['working_hours.1.starts_at']);

        $this->assertDatabaseCount('doctor_working_hours', 1);
    }

    public function test_offset_timestamp_is_normalized_to_clinic_local_booking_time(): void
    {
        [, $owner, $doctor, $patient] = $this->createSchedulingScenario();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'starts_at' => '2026-06-08T07:00:00+00:00',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $id,
            'starts_at' => '2026-06-08 09:00:00',
        ]);
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
