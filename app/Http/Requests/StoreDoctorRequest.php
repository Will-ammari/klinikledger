<?php

namespace App\Http\Requests;

use App\Models\Doctor;
use App\Models\User;
use App\Support\ApiEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Doctor::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('clinic_id', $this->user()->clinic_id);
                }),
                Rule::unique('doctors', 'user_id'),
            ],
            'specialization' => ['nullable', 'string', 'max:255'],
            'appointment_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('user_id')) {
                return;
            }

            $targetUser = User::query()
                ->where('clinic_id', $this->user()->clinic_id)
                ->find($this->input('user_id'));

            if ($targetUser && ApiEnum::value($targetUser->role) !== 'doctor') {
                $validator->errors()->add('user_id', 'The selected user must have the doctor role.');
            }
        });
    }
}
