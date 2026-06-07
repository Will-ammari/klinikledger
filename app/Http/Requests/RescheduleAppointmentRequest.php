<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reschedule', $this->route('appointment'));
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
        ];
    }
}
