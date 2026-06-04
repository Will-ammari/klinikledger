<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorTimeOffRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
