<?php

namespace App\Http\Requests;

use App\Enums\PatientStatus;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Patient::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('patients', 'email')->where(function ($query) {
                    $query->where('clinic_id', $this->user()->clinic_id);
                }),
            ],

            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],

            'status' => [
                'sometimes',
                Rule::in([
                    PatientStatus::Active->value,
                    PatientStatus::Inactive->value,
                ]),
            ],

            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
