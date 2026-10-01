<?php

namespace Tests\Feature;

use App\Jobs\SendBookingNotificationEmail;
use App\Mail\BookingNotificationMail;
use App\Models\TrcMailNotiLog;
use App\Services\Notification\NotificationLogStore;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\FakeNotificationLogStore;
use Tests\Support\NotificationFixtures;
use Tests\TestCase;

class SendBookingNotificationEmailTest extends TestCase
{
    private FakeNotificationLogStore $logs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logs = new FakeNotificationLogStore;
        $this->app->instance(NotificationLogStore::class, $this->logs);
    }

    public function test_an_already_sent_log_is_not_mailed_again(): void
    {
        Mail::fake();
        $log = $this->pendingLog(TrcMailNotiLog::STATUS_SENT);

        (new SendBookingNotificationEmail($log->log_id, 'BOOKING_CREATED', NotificationFixtures::booking()))
            ->handle($this->logs);

        Mail::assertNothingSent();
    }

    public function test_a_missing_log_is_ignored(): void
    {
        Mail::fake();

        (new SendBookingNotificationEmail('does-not-exist', 'BOOKING_CREATED', NotificationFixtures::booking()))
            ->handle($this->logs);

        Mail::assertNothingSent();
    }

    public function test_exhausted_retries_record_the_error_on_the_log(): void
    {
        $log = $this->pendingLog();

        (new SendBookingNotificationEmail($log->log_id, 'BOOKING_CREATED', NotificationFixtures::booking()))
            ->failed(new RuntimeException('Connection to SMTP server refused.'));

        $log = $this->logs->find($log->log_id);

        $this->assertSame(TrcMailNotiLog::STATUS_FAILED, $log->status);
        $this->assertSame('Connection to SMTP server refused.', $log->error_message);
        $this->assertNull($log->sent_at);
    }

    public function test_the_mail_body_shows_the_booking_details(): void
    {
        $mail = new BookingNotificationMail(
            'BOOKING_CREATED',
            '[Car Booking] มีรายการจองรถใหม่ #BK26090001',
            NotificationFixtures::booking()->withManager('A148029', 'ณัฏฐ์ศวัส แก้วธนะสิน'),
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('ณัฏฐ์ศวัส แก้วธนะสิน', $rendered);
        $this->assertStringContainsString('BK26090001', $rendered);
        $this->assertStringContainsString('สมชาย ใจดี', $rendered);
        $this->assertStringContainsString('ประชุมประจำเดือน', $rendered);
        $this->assertStringContainsString('มีรายการจองรถใหม่ รอการตรวจสอบ', $rendered);
    }

    public function test_the_approval_link_is_rendered_when_configured(): void
    {
        config()->set('notification.approval_url', 'https://intranet/car/view.php?doc=:reference');

        $rendered = (new BookingNotificationMail(
            'BOOKING_CREATED',
            'subject',
            NotificationFixtures::booking(),
        ))->render();

        $this->assertStringContainsString('https://intranet/car/view.php?doc=BK26090001', $rendered);
    }

    public function test_the_button_links_to_the_intranet_portal_by_default(): void
    {
        $rendered = (new BookingNotificationMail(
            'BOOKING_CREATED',
            'subject',
            NotificationFixtures::booking(),
        ))->render();

        $this->assertStringContainsString('href="https://e.summitautogroup.com/"', $rendered);
        $this->assertStringContainsString((string) config('notification.body.cta'), $rendered);
    }

    private function pendingLog(string $status = TrcMailNotiLog::STATUS_PENDING): TrcMailNotiLog
    {
        return $this->logs->create([
            'event_code' => 'BOOKING_CREATED',
            'reference_id' => 'BK26090001',
            'mgr_select_id' => 'A148029',
            'email_to' => 'manager@summitautogroup.com',
            'email_cc' => null,
            'email_subject' => 'subject',
            'status' => $status,
        ]);
    }
}
