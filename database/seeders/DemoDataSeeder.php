<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Enums\InvoiceStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consent;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Clinic $clinic */
        $clinic = Clinic::query()->updateOrCreate(
            ['slug' => 'berlin-family-praxis'],
            [
                'name' => 'Berlin Family Praxis',
                'email' => 'contact@berlin-family-praxis.test',
                'phone' => '+49 30 123456',
                'address' => 'Friedrichstrasse 100',
                'city' => 'Berlin',
                'country' => 'Germany',
                'timezone' => 'Europe/Berlin',
            ]
        );

        /** @var User $owner */
        $owner = User::factory()
            ->owner()
            ->for($clinic)
            ->create([
                'name' => 'William Ammari',
                'email' => 'owner@example.com',
                'password' => Hash::make('password'),
            ]);

        /** @var User $receptionist */
        $receptionist = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Receptionist One',
                'email' => 'receptionist@example.com',
                'password' => Hash::make('password'),
            ]);

        /** @var User $doctorUser */
        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Anna Schmidt',
                'email' => 'doctor@example.com',
                'password' => Hash::make('password'),
            ]);

        /** @var Doctor $doctor */
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

        /** @var Patient $patient */
        $patient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'phone' => '+49 30 987654',
                'date_of_birth' => '1991-07-20',
                'address' => 'Example Street 10',
                'city' => 'Berlin',
            ]);

        /** @var Appointment $appointment */
        $appointment = Appointment::factory()
            ->forClinicDoctorAndPatient($clinic, $doctor, $patient)
            ->scheduledAt('2026-06-08 09:00:00')
            ->create([
                'status' => AppointmentStatus::Confirmed,
                'reason' => 'Initial consultation',
            ]);

        /** @var Invoice $invoice */
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

        $invoice->items()->createMany([
            [
                'description' => 'General consultation',
                'quantity' => '1.00',
                'unit_price' => '80.00',
                'line_total' => '80.00',
            ],
            [
                'description' => 'Medical certificate',
                'quantity' => '1.00',
                'unit_price' => '20.00',
                'line_total' => '20.00',
            ],
        ]);

        Consent::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $receptionist->id,
            'type' => ConsentType::EmailReminders,
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
            'notes' => 'Patient agreed to receive appointment reminders by email.',
        ]);
    }
}
