<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Appointment $appointment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your appointment has been confirmed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointments.confirmation',
            with: [
                'appointment' => $this->appointment,
                'patient' => $this->appointment->patient,
                'doctor' => $this->appointment->doctor,
                'clinic' => $this->appointment->clinic,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
