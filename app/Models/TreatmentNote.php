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

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
