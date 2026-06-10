<x-mail::message>
# Appointment Reminder

Hello {{ $patient->full_name }},

This is a reminder for your upcoming appointment.

<x-mail::panel>
**Clinic:** {{ $clinic->name }}
**Doctor:** {{ $doctor->user?->name ?? 'Assigned doctor' }}
**Date:** {{ $appointment->starts_at?->format('Y-m-d') }}
**Time:** {{ $appointment->starts_at?->format('H:i') }} - {{ $appointment->ends_at?->format('H:i') }}
**Reason:** {{ $appointment->reason ?? 'Not provided' }}
</x-mail::panel>

If you cannot attend, please contact the clinic as soon as possible.

Thanks,
{{ config('app.name') }}
</x-mail::message>
