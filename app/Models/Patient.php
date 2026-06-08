<?php

namespace App\Models;

use App\Enums\PatientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'status',
        'address',
        'city',
        'anonymized_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'status' => PatientStatus::class,
            'anonymized_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function treatmentNotes(): HasMany
    {
        return $this->hasMany(TreatmentNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(PatientExport::class);
    }
}
