<?php

namespace App\Enums;

enum TreatmentNoteVisibility: string
{
    case DoctorOnly = 'doctor_only';
    case ClinicOwner = 'clinic_owner';
}
