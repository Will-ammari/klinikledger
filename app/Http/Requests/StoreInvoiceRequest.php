<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $clinicId = $this->user()->clinic_id;

                $patient = Patient::query()
                    ->where('clinic_id', $clinicId)
                    ->find($this->integer('patient_id'));

                if (! $patient) {
                    $validator->errors()->add('patient_id', 'The selected patient does not belong to your clinic.');
                    return;
                }

                if ($this->filled('appointment_id')) {
                    $appointment = Appointment::query()
                        ->where('clinic_id', $clinicId)
                        ->where('patient_id', $patient->id)
                        ->find($this->integer('appointment_id'));

                    if (! $appointment) {
                        $validator->errors()->add(
                            'appointment_id',
                            'The selected appointment does not belong to this patient in your clinic.'
                        );
                    }
                }
            },
        ];
    }
}
