<?php

namespace App\Enums;

enum ConsentType: string
{
    case EmailReminders = 'email_reminders';
    case SmsReminders = 'sms_reminders';
    case DataProcessing = 'data_processing';
    case MarketingCommunication = 'marketing_communication';
}
