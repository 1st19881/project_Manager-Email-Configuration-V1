<?php

namespace Tests\Support;

use App\Services\Notification\BookingReader;
use App\Services\Notification\BookingSnapshot;

class FakeBookingReader extends BookingReader
{
    /**
     * @param  array<string, BookingSnapshot>  $bookings
     */
    public function __construct(private array $bookings = []) {}

    public function find(string $documentId): ?BookingSnapshot
    {
        return $this->bookings[$documentId] ?? null;
    }
}
