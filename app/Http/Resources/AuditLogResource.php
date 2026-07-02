<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Support\ApiDate;
use App\Support\ApiEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => ApiEnum::value($this->action),
            'actor' => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
                'email' => $this->actor?->email,
                'role' => ApiEnum::value($this->actor?->role),
            ],
            'auditable' => [
                'type' => $this->auditable_type,
                'id' => $this->auditable_id,
            ],
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => ApiDate::datetime($this->created_at),
        ];
    }
}
