<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['sometimes', 'nullable', 'integer', 'exists:appointments,id'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (empty($this->validated())) {
                    $validator->errors()->add('invoice', 'At least one field must be provided for update.');
                    return;
                }

                $clinicId = $this->user()->clinic_id;
                $patientId = $this->integer('patient_id') ?: $this->route('invoice')->patient_id;

                $patient = Patient::query()
                    ->where('clinic_id', $clinicId)
                    ->find($patientId);

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
