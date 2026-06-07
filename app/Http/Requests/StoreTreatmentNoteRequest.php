<?php

namespace App\Http\Requests;

use App\Enums\TreatmentNoteVisibility;
use App\Models\TreatmentNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTreatmentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            'createForAppointment',
            [TreatmentNote::class, $this->route('appointment')]
        );
    }

    public function rules(): array
    {
        return [
            'subjective' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
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
                if (! $this->anyFilled(['subjective', 'objective', 'assessment', 'plan'])) {
                    $validator->errors()->add(
                        'note',
                        'At least one treatment note field must be provided.'
                    );
                }
            },
        ];
    }
}
