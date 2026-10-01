<?php

namespace App\Mail;

use App\Services\Notification\BookingSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventCode,
        public readonly string $subjectLine,
        public readonly BookingSnapshot $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
            with: [
                'booking' => $this->booking,
                'eventCode' => $this->eventCode,
                'approvalUrl' => $this->approvalUrl(),
            ],
        );
    }

    private function approvalUrl(): ?string
    {
        $base = (string) config('notification.approval_url');

        if ($base === '') {
            return null;
        }

        return str_replace(':reference', rawurlencode($this->booking->docId), $base);
    }
}
