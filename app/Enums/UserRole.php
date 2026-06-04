<?php

namespace App\Enums;

enum UserRole: string
{
    case OwnerClinic = 'owner_clinic';
    case Doctor = 'doctor';
    case Receptionist = 'receptionist';
    case Patient = 'patient';
}
