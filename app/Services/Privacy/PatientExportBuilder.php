<?php

namespace App\Services\Privacy;

use App\Models\Patient;

class PatientExportBuilder
{
    public function build(Patient $patient): array
    {
        $patient->load([
            'appointments.doctor.user',
            'invoices.items',
            'consents.grantedBy',
            'consents.withdrawnBy',
        ]);

        return [
            'generated_at' => now()->toISOString(),

            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => optional($patient->date_of_birth)->toDateString(),
                'status' => $patient->status?->value ?? $patient->status,
                'created_at' => optional($patient->created_at)->toISOString(),
                'updated_at' => optional($patient->updated_at)->toISOString(),
            ],

            'appointments' => $patient->appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'doctor_name' => $appointment->doctor?->user?->name,
                    'starts_at' => optional($appointment->starts_at)->toISOString(),
                    'ends_at' => optional($appointment->ends_at)->toISOString(),
                    'status' => $appointment->status?->value ?? $appointment->status,
                    'reason' => $appointment->reason,
                    'created_at' => optional($appointment->created_at)->toISOString(),
                ];
            })->values()->all(),

            'invoices' => $patient->invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'appointment_id' => $invoice->appointment_id,
                    'status' => $invoice->status?->value ?? $invoice->status,
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'issued_at' => optional($invoice->issued_at)->toISOString(),
                    'paid_at' => optional($invoice->paid_at)->toISOString(),
                    'cancelled_at' => optional($invoice->cancelled_at)->toISOString(),
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'line_total' => $item->line_total,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),

            'consents' => $patient->consents->map(function ($consent) {
                return [
                    'id' => $consent->id,
                    'type' => $consent->type?->value ?? $consent->type,
                    'status' => $consent->status?->value ?? $consent->status,
                    'granted_at' => optional($consent->granted_at)->toISOString(),
                    'withdrawn_at' => optional($consent->withdrawn_at)->toISOString(),
                    'notes' => $consent->notes,
                    'granted_by_user_id' => $consent->granted_by_user_id,
                    'withdrawn_by_user_id' => $consent->withdrawn_by_user_id,
                ];
            })->values()->all(),
        ];
    }
}
