<?php

namespace App\Http\Requests;

use App\Enums\ConsentType;
use App\Models\Consent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            'createForPatient',
            [Consent::class, $this->route('patient')]
        );
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in([
                    ConsentType::EmailReminders->value,
                    ConsentType::SmsReminders->value,
                    ConsentType::DataProcessing->value,
                    ConsentType::MarketingCommunication->value,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
