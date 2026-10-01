<?php

namespace Tests\Feature;

use App\Models\TrcMailNotiLog;
use App\Services\Notification\NotificationLogStore;
use Illuminate\Support\Carbon;
use Tests\Support\FakeNotificationLogStore;
use Tests\TestCase;

class NotificationLogApiTest extends TestCase
{
    private FakeNotificationLogStore $logs;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notification.report_timezone', 'Asia/Bangkok');

        $this->logs = new FakeNotificationLogStore;
        $this->app->instance(NotificationLogStore::class, $this->logs);
    }

    public function test_the_summary_counts_logs_by_status_event_and_day(): void
    {
        $this->log(TrcMailNotiLog::STATUS_SENT, 'BOOKING_CREATED', '2026-09-30 02:00:00');
        $this->log(TrcMailNotiLog::STATUS_SENT, 'BOOKING_CREATED', '2026-09-30 03:00:00');
        $this->log(TrcMailNotiLog::STATUS_FAILED, 'BOOKING_APPROVED', '2026-10-01 01:00:00');

        $this->getJson('/api/v1/notification-logs/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_status.SENT', 2)
            ->assertJsonPath('data.by_status.FAILED', 1)
            ->assertJsonPath('data.by_status.PENDING', 0)
            ->assertJsonPath('data.by_event.0.event_code', 'BOOKING_CREATED')
            ->assertJsonPath('data.by_event.0.total', 2)
            ->assertJsonPath('data.by_event.2.event_code', 'BOOKING_APPROVED')
            ->assertJsonPath('data.by_event.2.by_status.FAILED', 1)
            ->assertJsonPath('data.daily.0.date', '2026-09-30')
            ->assertJsonPath('data.daily.0.total', 2)
            ->assertJsonPath('data.daily.1.date', '2026-10-01');
    }

    public function test_days_are_counted_on_the_report_clock_not_utc(): void
    {
        // 18:00 UTC on 30 Sep is already 01:00 on 1 Oct in Bangkok.
        $this->log(TrcMailNotiLog::STATUS_SENT, 'BOOKING_CREATED', '2026-09-30 18:00:00');
        $this->log(TrcMailNotiLog::STATUS_SENT, 'BOOKING_CREATED', '2026-09-30 16:00:00');

        $this->getJson('/api/v1/notification-logs/summary?date_from=2026-10-01&date_to=2026-10-01')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.daily.0.date', '2026-10-01');
    }

    public function test_the_date_range_must_be_in_order(): void
    {
        $this->getJson('/api/v1/notification-logs/summary?date_from=2026-10-02&date_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_the_list_is_newest_first_and_filterable_by_status(): void
    {
        $this->log(TrcMailNotiLog::STATUS_FAILED, 'BOOKING_CREATED', '2026-09-30 02:00:00', 'BK-OLD');
        $this->log(TrcMailNotiLog::STATUS_FAILED, 'BOOKING_CREATED', '2026-10-01 02:00:00', 'BK-NEW');
        $this->log(TrcMailNotiLog::STATUS_SENT, 'BOOKING_CREATED', '2026-10-01 03:00:00', 'BK-SENT');

        $this->getJson('/api/v1/notification-logs?status=FAILED')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.reference_id', 'BK-NEW')
            ->assertJsonPath('data.1.reference_id', 'BK-OLD')
            ->assertJsonPath('data.0.created_at', '2026-10-01T02:00:00.000000Z');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->getJson('/api/v1/notification-logs?status=LOST')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    private function log(string $status, string $eventCode, string $createdAtUtc, string $referenceId = 'BK26090001'): void
    {
        $this->logs->create([
            'event_code' => $eventCode,
            'reference_id' => $referenceId,
            'mgr_select_id' => 'A148029',
            'email_to' => 'manager@summitautogroup.com',
            'email_cc' => null,
            'email_subject' => 'subject',
            'status' => $status,
            'created_at' => Carbon::parse($createdAtUtc, 'UTC'),
        ]);
    }
}
