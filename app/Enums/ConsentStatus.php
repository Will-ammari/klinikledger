<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
