<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Support\ApiDate;
use App\Support\ApiEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => ApiDate::date($this->date_of_birth),
            'status' => ApiEnum::value($this->status),
            'address' => $this->address,
            'city' => $this->city,
            'is_anonymized' => $this->isAnonymized(),
            'anonymized_at' => ApiDate::datetime($this->anonymized_at),
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
