<?php

namespace App\Services\Notification;

use App\Jobs\SendBookingNotificationEmail;
use App\Mail\BookingNotificationMail;
use App\Models\TrcMailNotiLog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function __construct(
        private readonly BookingReader $bookings,
        private readonly RecipientResolver $recipients,
        private readonly NotificationLogStore $logs,
    ) {}

    /**
     * Log a booking event and hand delivery to the queue.
     *
     * The log row is written before the job is dispatched so that every event
     * the booking system reports stays auditable even when mail never goes out.
     *
     * @throws NotificationException when the booking or its approver is unknown
     */
    public function notifyBookingEvent(
        string $eventCode,
        string $referenceId,
        ?string $mgrSelectId = null,
    ): TrcMailNotiLog {
        $booking = $this->bookings->find($referenceId);

        if ($booking === null) {
            throw new NotificationException(
                "Booking {$referenceId} was not found.",
                Response::HTTP_NOT_FOUND,
            );
        }

        $managerCode = $this->normalise($mgrSelectId) ?? $this->normalise($booking->managerCode);
        $subject = $this->subjectFor($eventCode, $referenceId);
        $carbonCopies = $this->recipients->standingCarbonCopies();

        if ($managerCode === null) {
            throw new NotificationException(
                "Booking {$referenceId} has no approver to notify.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $this->logs->create($this->logAttributes(
                    $eventCode, $referenceId, null, null, $carbonCopies, $subject,
                    TrcMailNotiLog::STATUS_FAILED, 'No approver is recorded on the booking.',
                )),
            );
        }

        $manager = $this->recipients->findByEmployeeCode($managerCode);

        if ($manager === null) {
            throw new NotificationException(
                "No active notification recipient is configured for {$managerCode}.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $this->logs->create($this->logAttributes(
                    $eventCode, $referenceId, $managerCode, null, $carbonCopies, $subject,
                    TrcMailNotiLog::STATUS_FAILED, "No active recipient is configured for employee {$managerCode}.",
                )),
            );
        }

        $log = $this->logs->create($this->logAttributes(
            $eventCode, $referenceId, $managerCode, $manager->email, $carbonCopies, $subject,
            TrcMailNotiLog::STATUS_PENDING,
        ));

        SendBookingNotificationEmail::dispatch(
            $log->log_id,
            $eventCode,
            $booking->withManager($managerCode, $manager->name_th ?: $manager->name_en),
        )
            ->onConnection(config('notification.queue.connection') ?: config('queue.default'))
            ->onQueue(config('notification.queue.name'));

        return $log;
    }

    /**
     * Send a single notification mail on demand so an administrator can verify
     * the SMTP settings and the template wording from the admin screen.
     *
     * Unlike a real event this is delivered inline: the caller is waiting for
     * the result and needs the SMTP error itself when delivery fails.
     *
     * @throws NotificationException when the booking is unknown or SMTP fails
     */
    public function sendTestMail(string $email, string $eventCode, ?string $referenceId = null): TrcMailNotiLog
    {
        $booking = BookingSnapshot::preview();

        if ($referenceId !== null) {
            $booking = $this->bookings->find($referenceId);

            if ($booking === null) {
                throw new NotificationException(
                    "Booking {$referenceId} was not found.",
                    Response::HTTP_NOT_FOUND,
                );
            }
        }

        $subject = '[ทดสอบ] '.$this->subjectFor($eventCode, $booking->docId);

        $log = $this->logs->create($this->logAttributes(
            $eventCode, $booking->docId, null, $email, [], $subject, TrcMailNotiLog::STATUS_PROCESSING,
        ));

        try {
            Mail::to($email)->send(new BookingNotificationMail($eventCode, $subject, $booking));
        } catch (Throwable $exception) {
            $this->logs->update($log, [
                'status' => TrcMailNotiLog::STATUS_FAILED,
                'attempt_count' => 1,
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw new NotificationException(
                'ส่งอีเมลไม่สำเร็จ: '.$exception->getMessage(),
                Response::HTTP_BAD_GATEWAY,
                $log,
            );
        }

        $this->logs->update($log, [
            'status' => TrcMailNotiLog::STATUS_SENT,
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        return $log;
    }

    private function subjectFor(string $eventCode, string $referenceId): string
    {
        /** @var array<string, string> $events */
        $events = config('notification.events', []);

        $subject = $events[$eventCode] ?? "[Car Booking] {$eventCode} #:reference";

        return mb_substr(str_replace(':reference', $referenceId, $subject), 0, 500);
    }

    /**
     * @param  list<string>  $carbonCopies
     * @return array<string, mixed>
     */
    private function logAttributes(
        string $eventCode,
        string $referenceId,
        ?string $managerCode,
        ?string $emailTo,
        array $carbonCopies,
        string $subject,
        string $status,
        ?string $errorMessage = null,
    ): array {
        return [
            'event_code' => $eventCode,
            'reference_id' => $referenceId,
            'mgr_select_id' => $managerCode,
            'email_to' => $emailTo,
            'email_cc' => $carbonCopies === [] ? null : implode(',', $carbonCopies),
            'email_subject' => $subject,
            'status' => $status,
            'error_message' => $errorMessage === null ? null : mb_substr($errorMessage, 0, 4000),
        ];
    }

    private function normalise(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : strtoupper($value);
    }
}
