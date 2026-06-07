<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_create_invoice_with_items_and_totals(): void
    {
        [$clinic, , , $receptionist, , $patient, $appointment] = $this->createInvoiceScenario();

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'tax_rate' => 19,
            'items' => [
                [
                    'description' => 'General consultation',
                    'quantity' => 1,
                    'unit_price' => 80,
                ],
                [
                    'description' => 'Medical certificate',
                    'quantity' => 1,
                    'unit_price' => 20,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.appointment_id', $appointment->id)
            ->assertJsonPath('data.status', InvoiceStatus::Draft->value)
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.tax', '19.00')
            ->assertJsonPath('data.total', '119.00');

        $this->assertDatabaseHas('invoices', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => '100.00',
            'tax' => '19.00',
            'total' => '119.00',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'description' => 'General consultation',
            'quantity' => '1.00',
            'unit_price' => '80.00',
            'line_total' => '80.00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::InvoiceCreated->value,
        ]);
    }

    public function test_invoice_can_be_issued_and_marked_paid(): void
    {
        [, , , $receptionist, , $patient, $appointment] = $this->createInvoiceScenario();

        $invoice = $this->createDraftInvoice($patient, $appointment);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/invoices/{$invoice->id}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Issued->value);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Issued->value,
        ]);

        $this->postJson("/api/invoices/{$invoice->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::InvoiceIssued->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::InvoiceMarkedPaid->value,
        ]);
    }

    public function test_paid_invoice_cannot_be_updated(): void
    {
        [, , , $receptionist, , $patient, $appointment] = $this->createInvoiceScenario();

        $invoice = $this->createDraftInvoice($patient, $appointment);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'due_date' => now()->addDays(30)->toDateString(),
        ])->assertForbidden();
    }

    public function test_cancelled_invoice_cannot_be_marked_paid(): void
    {
        [, , , $receptionist, , $patient, $appointment] = $this->createInvoiceScenario();

        $invoice = $this->createDraftInvoice($patient, $appointment);

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/invoices/{$invoice->id}/mark-paid")
            ->assertForbidden();
    }

    public function test_doctor_cannot_view_invoices(): void
    {
        [, , $doctorUser, , , $patient, $appointment] = $this->createInvoiceScenario();

        $invoice = $this->createDraftInvoice($patient, $appointment);

        Sanctum::actingAs($doctorUser);

        $this->getJson('/api/invoices')
            ->assertForbidden();

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_invoice_appointment_must_belong_to_selected_patient_in_same_clinic(): void
    {
        [, , , $receptionist, , $patient, $appointment] = $this->createInvoiceScenario();

        $otherPatient = Patient::factory()
            ->forClinic($patient->clinic)
            ->create([
                'first_name' => 'Other',
                'last_name' => 'Patient',
                'email' => 'other.patient@example.com',
            ]);

        Sanctum::actingAs($receptionist);

        $this->postJson('/api/invoices', [
            'patient_id' => $otherPatient->id,
            'appointment_id' => $appointment->id,
            'tax_rate' => 19,
            'items' => [
                [
                    'description' => 'General consultation',
                    'quantity' => 1,
                    'unit_price' => 80,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment_id']);

        $this->assertDatabaseCount('invoices', 0);
    }

    private function createInvoiceScenario(): array
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

        $receptionist = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Receptionist One',
                'email' => 'receptionist@example.com',
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

        return [$clinic, $owner, $doctorUser, $receptionist, $doctor, $patient, $appointment];
    }

    private function createDraftInvoice(Patient $patient, Appointment $appointment): Invoice
    {
        $invoice = Invoice::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => InvoiceStatus::Draft,
            'subtotal' => '100.00',
            'tax' => '19.00',
            'total' => '119.00',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $invoice->items()->create([
            'description' => 'General consultation',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'line_total' => '100.00',
        ]);

        return $invoice;
    }
}
