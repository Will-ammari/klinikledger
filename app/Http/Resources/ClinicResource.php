<?php

namespace App\Http\Resources;

use App\Models\Clinic;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Clinic
 */
class ClinicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
