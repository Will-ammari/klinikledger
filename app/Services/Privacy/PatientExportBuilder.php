<?php

namespace App\Services\Privacy;

use App\Models\Patient;
use App\Support\ApiDate;
use App\Support\ApiEnum;

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
            'generated_at' => ApiDate::datetime(now()),

            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => ApiDate::date($patient->date_of_birth),
                'status' => ApiEnum::value($patient->status),
                'created_at' => ApiDate::datetime($patient->created_at),
                'updated_at' => ApiDate::datetime($patient->updated_at),
            ],

            'appointments' => $patient->appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'doctor_name' => $appointment->doctor?->user?->name,
                    'starts_at' => ApiDate::datetime($appointment->starts_at),
                    'ends_at' => ApiDate::datetime($appointment->ends_at),
                    'status' => ApiEnum::value($appointment->status),
                    'reason' => $appointment->reason,
                    'created_at' => ApiDate::datetime($appointment->created_at),
                ];
            })->values()->all(),

            'invoices' => $patient->invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'appointment_id' => $invoice->appointment_id,
                    'status' => ApiEnum::value($invoice->status),
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'due_date' => ApiDate::date($invoice->due_date),
                    'issued_at' => ApiDate::datetime($invoice->issued_at),
                    'paid_at' => ApiDate::datetime($invoice->paid_at),
                    'cancelled_at' => ApiDate::datetime($invoice->cancelled_at),
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
                    'type' => ApiEnum::value($consent->type),
                    'status' => ApiEnum::value($consent->status),
                    'granted_at' => ApiDate::datetime($consent->granted_at),
                    'withdrawn_at' => ApiDate::datetime($consent->withdrawn_at),
                    'notes' => $consent->notes,
                    'granted_by_user_id' => $consent->granted_by_user_id,
                    'withdrawn_by_user_id' => $consent->withdrawn_by_user_id,
                ];
            })->values()->all(),
        ];
    }
}
