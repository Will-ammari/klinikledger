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
            'working_hours' => ['required', 'array', 'max:100'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'working_hours.*.starts_at' => ['required', 'date_format:H:i'],
            'working_hours.*.ends_at' => ['required', 'date_format:H:i'],
            'working_hours.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $input = $this->input('working_hours', []);
            if (is_array($input) === false) {
                return;
            }

            $intervals = [];

            foreach ($input as $index => $hour) {
                if (is_array($hour) === false) {
                    continue;
                }

                $start = $hour['starts_at'] ?? null;
                $end = $hour['ends_at'] ?? null;
                $day = $hour['day_of_week'] ?? null;

                if (is_string($start) === false || is_string($end) === false) {
                    continue;
                }

                if ($start >= $end) {
                    $validator->errors()->add(
                        "working_hours.{$index}.ends_at",
                        'The end time must be after the start time.'
                    );

                    continue;
                }

                if (is_numeric($day) === false || (int) $day < 0 || (int) $day > 6) {
                    continue;
                }

                if (filter_var($hour['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN) === false) {
                    continue;
                }

                $intervals[(int) $day][] = [$start, $end, $index];
            }

            foreach ($intervals as $dayIntervals) {
                usort($dayIntervals, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
                $lastEnd = null;

                foreach ($dayIntervals as [$start, $end, $index]) {
                    if ($lastEnd !== null && $start < $lastEnd) {
                        $validator->errors()->add(
                            "working_hours.{$index}.starts_at",
                            'Active working hour intervals cannot overlap on the same day.'
                        );
                    }

                    if ($lastEnd === null || $end > $lastEnd) {
                        $lastEnd = $end;
                    }
                }
            }
        });
    }
}
