<?php

namespace App\Http\Resources;

use App\Models\DoctorTimeOff;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DoctorTimeOff
 */
class DoctorTimeOffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'starts_at' => ApiDate::datetime($this->starts_at),
            'ends_at' => ApiDate::datetime($this->ends_at),
            'reason' => $this->reason,
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
