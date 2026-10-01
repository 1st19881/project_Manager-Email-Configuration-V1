<?php

namespace Tests\Feature;

use App\Mail\BookingNotificationMail;
use App\Models\TrcMailNotiLog;
use App\Services\Notification\BookingReader;
use App\Services\Notification\NotificationLogStore;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\FakeBookingReader;
use Tests\Support\FakeNotificationLogStore;
use Tests\Support\NotificationFixtures;
use Tests\TestCase;

class MailTestApiTest extends TestCase
{
    private FakeNotificationLogStore $logs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logs = new FakeNotificationLogStore;
        $this->app->instance(NotificationLogStore::class, $this->logs);
        $this->app->instance(BookingReader::class, new FakeBookingReader([
            'BK26090001' => NotificationFixtures::booking(),
        ]));
    }

    public function test_an_address_and_a_known_event_are_required(): void
    {
        $this->postJson('/api/v1/mail/test', ['email' => 'not-an-email', 'event' => 'NOPE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'event']);
    }

    public function test_a_sample_booking_is_used_when_no_reference_is_given(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/mail/test', [
            'email' => 'tester@summitautogroup.com',
            'event' => 'BOOKING_CREATED',
        ]);

        $response->assertOk()->assertJsonPath('status', TrcMailNotiLog::STATUS_SENT);

        Mail::assertSent(
            BookingNotificationMail::class,
            fn (BookingNotificationMail $mail) => $mail->hasTo('tester@summitautogroup.com')
                && $mail->booking->docId === 'BK-PREVIEW'
                && str_starts_with($mail->subjectLine, '[ทดสอบ]'),
        );
    }

    public function test_a_real_booking_can_be_previewed(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/mail/test', [
            'email' => 'tester@summitautogroup.com',
            'event' => 'BOOKING_APPROVED',
            'reference_id' => 'BK26090001',
        ])->assertOk();

        Mail::assertSent(
            BookingNotificationMail::class,
            fn (BookingNotificationMail $mail) => $mail->booking->docId === 'BK26090001'
                && $mail->eventCode === 'BOOKING_APPROVED',
        );
    }

    public function test_an_unknown_booking_returns_404(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/mail/test', [
            'email' => 'tester@summitautogroup.com',
            'event' => 'BOOKING_CREATED',
            'reference_id' => 'BK00000000',
        ])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_an_smtp_failure_is_reported_and_logged(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection could not be established.'));

        $response = $this->postJson('/api/v1/mail/test', [
            'email' => 'tester@summitautogroup.com',
            'event' => 'BOOKING_CREATED',
        ]);

        $response
            ->assertStatus(502)
            ->assertJsonPath('status', TrcMailNotiLog::STATUS_FAILED);

        $log = $this->logs->find($response->json('log_id'));

        $this->assertSame(TrcMailNotiLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Connection could not be established.', (string) $log->error_message);
    }
}
