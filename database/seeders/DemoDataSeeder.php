<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\PatientStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\DoctorWorkingHour;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::updateOrCreate(
            ['slug' => 'berlin-family-praxis'],
            [
                'name' => 'Berlin Family Praxis',
                'email' => 'clinic@example.com',
                'phone' => '+49 30 123456',
                'address' => 'Musterstraße 1',
                'city' => 'Berlin',
                'country' => 'Germany',
                'timezone' => 'Europe/Berlin',
            ]
        );

        $owner = User::updateOrCreate(
            ['email' => 'owner@example.com'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'William Ammari',
                'password' => Hash::make('password'),
                'role' => UserRole::OwnerClinic,
                'status' => UserStatus::Active,
            ]
        );

        $receptionist = User::updateOrCreate(
            ['email' => 'receptionist@example.com'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Receptionist One',
                'password' => Hash::make('password'),
                'role' => UserRole::Receptionist,
                'status' => UserStatus::Active,
            ]
        );

        $doctorUser = User::updateOrCreate(
            ['email' => 'doctor@example.com'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Dr Anna Schmidt',
                'password' => Hash::make('password'),
                'role' => UserRole::Doctor,
                'status' => UserStatus::Active,
            ]
        );

        $doctor = Doctor::updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'user_id' => $doctorUser->id,
            ],
            [
                'specialization' => 'General Medicine',
                'appointment_duration_minutes' => 30,
                'is_active' => true,
            ]
        );

        $patients = collect([
            [
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'phone' => '+49 30 111222',
                'date_of_birth' => '1991-07-20',
                'city' => 'Berlin',
            ],
            [
                'first_name' => 'Max',
                'last_name' => 'Müller',
                'email' => 'max.mueller@example.com',
                'phone' => '+49 30 333444',
                'date_of_birth' => '1988-04-12',
                'city' => 'Berlin',
            ],
            [
                'first_name' => 'Sofia',
                'last_name' => 'Weber',
                'email' => 'sofia.weber@example.com',
                'phone' => '+49 30 555666',
                'date_of_birth' => '1979-11-03',
                'city' => 'Berlin',
            ],
        ])->map(function (array $patientData) use ($clinic) {
            return Patient::updateOrCreate(
                ['email' => $patientData['email']],
                [
                    'clinic_id' => $clinic->id,
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'phone' => $patientData['phone'],
                    'date_of_birth' => $patientData['date_of_birth'],
                    'status' => PatientStatus::Active,
                    'address' => 'Demo Address',
                    'city' => $patientData['city'],
                ]
            );
        });

        DoctorWorkingHour::query()
            ->where('clinic_id', $clinic->id)
            ->where('doctor_id', $doctor->id)
            ->delete();

        foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
            DoctorWorkingHour::create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'starts_at' => '09:00',
                'ends_at' => '12:00',
                'is_active' => true,
            ]);

            DoctorWorkingHour::create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'starts_at' => '13:00',
                'ends_at' => '17:00',
                'is_active' => true,
            ]);
        }

        DoctorTimeOff::updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'starts_at' => '2026-06-08 10:00:00',
            ],
            [
                'ends_at' => '2026-06-08 11:00:00',
                'reason' => 'Private appointment',
            ]
        );

        Appointment::updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patients[0]->id,
                'starts_at' => '2026-06-08 09:00:00',
            ],
            [
                'ends_at' => '2026-06-08 09:30:00',
                'status' => AppointmentStatus::Scheduled,
                'reason' => 'Initial consultation',
            ]
        );

        $this->command?->info('Demo data seeded successfully.');
        $this->command?->line('');
        $this->command?->line('Demo credentials:');
        $this->command?->line('Owner: owner@example.com / password');
        $this->command?->line('Receptionist: receptionist@example.com / password');
        $this->command?->line('Doctor: doctor@example.com / password');
    }
}
