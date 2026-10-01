<?php

namespace Tests\Feature;

use App\Jobs\SendBookingNotificationEmail;
use App\Mail\BookingNotificationMail;
use App\Models\TrcMailNotiLog;
use App\Models\TrcMailNotiMgr;
use App\Services\Notification\BookingReader;
use App\Services\Notification\NotificationLogStore;
use App\Services\Notification\RecipientResolver;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeBookingReader;
use Tests\Support\FakeNotificationLogStore;
use Tests\Support\FakeRecipientResolver;
use Tests\Support\NotificationFixtures;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    private const API_KEY = 'test-api-key';

    private FakeNotificationLogStore $logs;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notification.api_key', self::API_KEY);
        config()->set('notification.queue.connection', 'sync');

        $this->logs = new FakeNotificationLogStore;
        $this->app->instance(NotificationLogStore::class, $this->logs);
    }

    public function test_request_without_an_api_key_is_rejected(): void
    {
        $this->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
        ])->assertUnauthorized();
    }

    public function test_request_with_a_wrong_api_key_is_rejected(): void
    {
        $this->withHeader('X-API-KEY', 'nope')
            ->postJson('/api/v1/notifications', [
                'event' => 'BOOKING_CREATED',
                'reference_id' => 'BK26090001',
            ])->assertUnauthorized();
    }

    public function test_endpoint_is_unavailable_when_no_api_key_is_configured(): void
    {
        config()->set('notification.api_key', null);

        $this->withHeader('X-API-KEY', self::API_KEY)
            ->postJson('/api/v1/notifications', [
                'event' => 'BOOKING_CREATED',
                'reference_id' => 'BK26090001',
            ])->assertServiceUnavailable();
    }

    public function test_unknown_event_and_missing_reference_are_rejected(): void
    {
        $this->authorised()
            ->postJson('/api/v1/notifications', ['event' => 'SOMETHING_ELSE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event', 'reference_id']);
    }

    public function test_booking_created_event_is_logged_and_queued(): void
    {
        Bus::fake();
        $this->bindDirectory();

        $response = $this->authorised()->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
            'mgr_select_id' => 'a148029',
        ]);

        $response->assertAccepted()->assertJsonPath('status', TrcMailNotiLog::STATUS_PENDING);

        $log = $this->logs->find($response->json('log_id'));

        $this->assertNotNull($log);
        $this->assertSame('BOOKING_CREATED', $log->event_code);
        $this->assertSame('BK26090001', $log->reference_id);
        $this->assertSame('A148029', $log->mgr_select_id);
        $this->assertSame('manager@summitautogroup.com', $log->email_to);
        $this->assertSame('transport@summitautogroup.com', $log->email_cc);
        $this->assertSame('[Car Booking] มีรายการจองรถใหม่ #BK26090001', $log->email_subject);

        Bus::assertDispatched(
            SendBookingNotificationEmail::class,
            fn (SendBookingNotificationEmail $job) => $job->logId === $log->log_id
                && $job->eventCode === 'BOOKING_CREATED'
                && $job->booking->managerName === 'ณัฏฐ์ศวัส แก้วธนะสิน',
        );
    }

    public function test_queued_job_sends_the_mail_and_marks_the_log_sent(): void
    {
        Mail::fake();
        $this->bindDirectory();

        $response = $this->authorised()->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
        ]);

        $response->assertAccepted();
        $log = $this->logs->find($response->json('log_id'));

        $this->assertSame(TrcMailNotiLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame(1, $log->attempt_count);

        Mail::assertSent(
            BookingNotificationMail::class,
            fn (BookingNotificationMail $mail) => $mail->hasTo('manager@summitautogroup.com')
                && $mail->hasCc('transport@summitautogroup.com'),
        );
    }

    public function test_unknown_booking_returns_404_without_logging(): void
    {
        $this->bindDirectory();

        $this->authorised()->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK00000000',
        ])->assertNotFound();

        $this->assertSame([], $this->logs->logs);
    }

    public function test_unconfigured_recipient_is_logged_as_failed(): void
    {
        Bus::fake();
        $this->bindDirectory(managers: []);

        $response = $this->authorised()->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
        ]);

        $response->assertUnprocessable()->assertJsonPath('status', TrcMailNotiLog::STATUS_FAILED);

        $log = $this->logs->find($response->json('log_id'));

        $this->assertSame(TrcMailNotiLog::STATUS_FAILED, $log->status);
        $this->assertNull($log->email_to);
        $this->assertStringContainsString('A148029', (string) $log->error_message);

        Bus::assertNothingDispatched();
    }

    public function test_booking_without_an_approver_is_logged_as_failed(): void
    {
        Bus::fake();
        $this->bindDirectory(booking: NotificationFixtures::booking(managerCode: null));

        $response = $this->authorised()->postJson('/api/v1/notifications', [
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
        ]);

        $response->assertUnprocessable();

        $this->assertSame(
            TrcMailNotiLog::STATUS_FAILED,
            $this->logs->find($response->json('log_id'))->status,
        );

        Bus::assertNothingDispatched();
    }

    private function authorised(): self
    {
        return $this->withHeader('X-API-KEY', self::API_KEY);
    }

    /**
     * @param  array<string, TrcMailNotiMgr>|null  $managers
     */
    private function bindDirectory(mixed $booking = null, ?array $managers = null): void
    {
        $booking ??= NotificationFixtures::booking();
        $managers ??= ['A148029' => NotificationFixtures::manager()];

        $this->app->instance(BookingReader::class, new FakeBookingReader(['BK26090001' => $booking]));
        $this->app->instance(RecipientResolver::class, new FakeRecipientResolver(
            $managers,
            ['transport@summitautogroup.com'],
        ));
    }
}
