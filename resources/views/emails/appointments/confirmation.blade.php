<x-mail::message>
# Appointment Confirmed

Hello {{ $patient->full_name }},

Your appointment has been confirmed.

<x-mail::panel>
**Clinic:** {{ $clinic->name }}
**Doctor:** {{ $doctor->user?->name ?? 'Assigned doctor' }}
**Date:** {{ $appointment->starts_at?->format('Y-m-d') }}
**Time:** {{ $appointment->starts_at?->format('H:i') }} - {{ $appointment->ends_at?->format('H:i') }}
**Reason:** {{ $appointment->reason ?? 'Not provided' }}
</x-mail::panel>

Please arrive a few minutes before your scheduled appointment time.

Thanks,
{{ config('app.name') }}
</x-mail::message>
