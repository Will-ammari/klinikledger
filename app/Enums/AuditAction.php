<?php

namespace App\Enums;

enum AuditAction: string
{
    case PatientCreated = 'patient.created';
    case PatientViewed = 'patient.viewed';
    case PatientUpdated = 'patient.updated';
    case PatientDeleted = 'patient.deleted';

    case UserCreated = 'user.created';
    case UserViewed = 'user.viewed';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserRoleChanged = 'user.role_changed';

    case DoctorCreated = 'doctor.created';
    case DoctorViewed = 'doctor.viewed';
    case DoctorUpdated = 'doctor.updated';
    case DoctorDeleted = 'doctor.deleted';

    case DoctorWorkingHoursUpdated = 'doctor.working_hours.updated';
    case DoctorTimeOffCreated = 'doctor.time_off.created';
    case DoctorTimeOffDeleted = 'doctor.time_off.deleted';

    case AppointmentCreated = 'appointment.created';
    case AppointmentViewed = 'appointment.viewed';
    case AppointmentUpdated = 'appointment.updated';
    case AppointmentConfirmed = 'appointment.confirmed';
    case AppointmentCancelled = 'appointment.cancelled';
    case AppointmentCompleted = 'appointment.completed';
    case AppointmentNoShowMarked = 'appointment.no_show_marked';
    case AppointmentRescheduled = 'appointment.rescheduled';

    case TreatmentNoteCreated = 'treatment_note.created';
    case TreatmentNoteViewed = 'treatment_note.viewed';
    case TreatmentNoteUpdated = 'treatment_note.updated';
    case TreatmentNoteDeleted = 'treatment_note.deleted';

    case InvoiceCreated = 'invoice.created';
    case InvoiceViewed = 'invoice.viewed';
    case InvoiceUpdated = 'invoice.updated';
    case InvoiceIssued = 'invoice.issued';
    case InvoiceMarkedPaid = 'invoice.marked_paid';
    case InvoiceCancelled = 'invoice.cancelled';

    case ConsentCreated = 'consent.created';
    case ConsentViewed = 'consent.viewed';
    case ConsentWithdrawn = 'consent.withdrawn';
    case PatientExportRequested = 'patient_export.requested';
    case PatientExportViewed = 'patient_export.viewed';
    case PatientAnonymized = 'patient.anonymized';
}
