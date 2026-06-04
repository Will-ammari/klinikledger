<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => [
                'sometimes',
                Rule::in([
                    AppointmentStatus::Scheduled->value,
                    AppointmentStatus::Confirmed->value,
                ]),
            ],
        ];
    }
}
