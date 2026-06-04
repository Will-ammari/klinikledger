<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        User $actor,
        AuditAction $action,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'clinic_id' => $actor->clinic_id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $this->sanitizeMetadata($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        unset(
            $metadata['password'],
            $metadata['password_confirmation'],
            $metadata['remember_token'],
            $metadata['token'],
            $metadata['access_token']
        );

        return $metadata;
    }
}
