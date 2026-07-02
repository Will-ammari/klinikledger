<?php

namespace App\Models;

use App\Enums\TreatmentNoteVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'doctor_id',
        'patient_id',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => TreatmentNoteVisibility::class,
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
