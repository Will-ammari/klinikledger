<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'starts_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $doctor = Doctor::query()->find($this->integer('doctor_id'));
                $patient = Patient::query()->find($this->integer('patient_id'));

                if (! $doctor || ! $patient) {
                    return;
                }

                if ($doctor->clinic_id !== $this->user()->clinic_id) {
                    $validator->errors()->add('doctor_id', 'The selected doctor does not belong to your clinic.');
                }

                if ($patient->clinic_id !== $this->user()->clinic_id) {
                    $validator->errors()->add('patient_id', 'The selected patient does not belong to your clinic.');
                }
            },
        ];
    }
}
