<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'granted_by_user_id',
        'withdrawn_by_user_id',
        'type',
        'status',
        'granted_at',
        'withdrawn_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'status' => ConsentStatus::class,
            'granted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Clinic, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user_id');
    }

    public function isGranted(): bool
    {
        return $this->status === ConsentStatus::Granted;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === ConsentStatus::Withdrawn;
    }
}
