<?php

namespace App\Http\Requests;

use App\Enums\TreatmentNoteVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTreatmentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('treatment_note'));
    }

    public function rules(): array
    {
        return [
            'subjective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visibility' => [
                'sometimes',
                Rule::in([
                    TreatmentNoteVisibility::DoctorOnly->value,
                    TreatmentNoteVisibility::ClinicOwner->value,
                ]),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (empty($this->validated())) {
                    $validator->errors()->add(
                        'note',
                        'At least one field must be provided for update.'
                    );
                }
            },
        ];
    }
}
