<?php

namespace App\Jobs;

use App\Mail\BookingNotificationMail;
use App\Models\TrcMailNotiLog;
use App\Services\Notification\BookingSnapshot;
use App\Services\Notification\NotificationLogStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBookingNotificationEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Deliveries are retried so a briefly unavailable SMTP server does not
     * silently lose a notification.
     */
    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $logId,
        public readonly string $eventCode,
        public readonly BookingSnapshot $booking,
    ) {}

    public function handle(NotificationLogStore $logs): void
    {
        $log = $logs->find($this->logId);

        if ($log === null || $log->status === TrcMailNotiLog::STATUS_SENT) {
            return;
        }

        $logs->update($log, [
            'status' => TrcMailNotiLog::STATUS_PROCESSING,
            'attempt_count' => $this->currentAttempt(),
        ]);

        $pending = Mail::to((string) $log->email_to);

        if (filled($log->email_cc)) {
            $pending->cc($this->carbonCopies((string) $log->email_cc));
        }

        $pending->send(new BookingNotificationMail(
            $this->eventCode,
            (string) $log->email_subject,
            $this->booking,
        ));

        $logs->update($log, [
            'status' => TrcMailNotiLog::STATUS_SENT,
            'error_message' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * Record the terminal failure once every retry has been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $logs = app(NotificationLogStore::class);
        $log = $logs->find($this->logId);

        if ($log === null) {
            return;
        }

        $logs->update($log, [
            'status' => TrcMailNotiLog::STATUS_FAILED,
            'attempt_count' => $this->currentAttempt(),
            'error_message' => mb_substr($exception?->getMessage() ?? 'Unknown mail failure.', 0, 4000),
        ]);
    }

    /**
     * @return list<string>
     */
    private function carbonCopies(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * The queue only tracks attempts once a job has actually been enqueued.
     */
    private function currentAttempt(): int
    {
        return $this->job !== null ? $this->attempts() : 1;
    }
}
