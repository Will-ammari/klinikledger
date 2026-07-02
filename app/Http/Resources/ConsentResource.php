<?php

namespace App\Http\Resources;

use App\Models\Consent;
use App\Support\ApiDate;
use App\Support\ApiEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => ApiEnum::value($this->type),
            'status' => ApiEnum::value($this->status),
            'granted_at' => ApiDate::datetime($this->granted_at),
            'withdrawn_at' => ApiDate::datetime($this->withdrawn_at),
            'notes' => $this->notes,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'granted_by' => new UserResource($this->whenLoaded('grantedBy')),
            'withdrawn_by' => new UserResource($this->whenLoaded('withdrawnBy')),
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
