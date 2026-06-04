<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeRole', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::in([
                    UserRole::OwnerClinic->value,
                    UserRole::Doctor->value,
                    UserRole::Receptionist->value,
                    UserRole::Patient->value,
                ]),
            ],
        ];
    }
}
