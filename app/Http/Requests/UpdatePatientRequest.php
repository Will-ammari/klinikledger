<?php

namespace App\Http\Requests;

use App\Enums\PatientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('patient'));
    }

    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('patients', 'email')
                    ->where(function ($query) {
                        $query->where('clinic_id', $this->user()->clinic_id);
                    })
                    ->ignore($patient->id),
            ],

            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],

            'status' => [
                'sometimes',
                Rule::in([
                    PatientStatus::Active->value,
                    PatientStatus::Inactive->value,
                ]),
            ],

            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
