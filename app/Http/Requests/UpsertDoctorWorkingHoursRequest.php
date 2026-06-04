<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;

class UpsertDoctorWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        if (! $doctor instanceof Doctor) {
            return false;
        }

        if ($this->user()->clinic_id !== $doctor->clinic_id) {
            return false;
        }

        if ($this->user()->role === UserRole::OwnerClinic) {
            return true;
        }

        return $this->user()->role === UserRole::Doctor
            && $doctor->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'working_hours' => ['required', 'array'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'working_hours.*.starts_at' => ['required', 'date_format:H:i'],
            'working_hours.*.ends_at' => ['required', 'date_format:H:i'],
            'working_hours.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('working_hours', []) as $index => $workingHour) {
                $startsAt = $workingHour['starts_at'] ?? null;
                $endsAt = $workingHour['ends_at'] ?? null;

                if (! $startsAt || ! $endsAt) {
                    continue;
                }

                if ($startsAt >= $endsAt) {
                    $validator->errors()->add(
                        "working_hours.{$index}.ends_at",
                        'The end time must be after the start time.'
                    );
                }
            }
        });
    }
}
