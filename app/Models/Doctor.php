<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'specialization',
        'appointment_duration_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'appointment_duration_minutes' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DoctorWorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(DoctorWorkingHour::class);
    }

    /**
     * @return HasMany<DoctorTimeOff, $this>
     */
    public function timeOffs(): HasMany
    {
        return $this->hasMany(DoctorTimeOff::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<TreatmentNote, $this>
     */
    public function treatmentNotes(): HasMany
    {
        return $this->hasMany(TreatmentNote::class);
    }
}
