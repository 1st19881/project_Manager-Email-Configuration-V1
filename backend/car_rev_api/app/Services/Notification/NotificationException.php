<?php

namespace App\Services\Notification;

use App\Models\TrcMailNotiLog;
use RuntimeException;

/**
 * Raised when a notification cannot be queued. The HTTP status tells the legacy
 * caller whether the payload was wrong or the notification setup is incomplete.
 */
class NotificationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?TrcMailNotiLog $log = null,
    ) {
        parent::__construct($message);
    }
}
