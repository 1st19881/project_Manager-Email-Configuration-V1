<?php

namespace App\Services\Notification;

use App\Support\LegacyText;

/**
 * Booking details captured at the moment the notification was queued, so the
 * mail a manager receives always reflects the event that triggered it.
 */
class BookingSnapshot
{
    public function __construct(
        public readonly string $docId,
        public readonly ?string $plant,
        public readonly ?string $requesterCode,
        public readonly ?string $requesterName,
        public readonly ?string $department,
        public readonly ?string $tel,
        public readonly ?string $usedDate,
        public readonly ?string $usedTime,
        public readonly ?string $backDate,
        public readonly ?string $backTime,
        public readonly ?string $destination,
        public readonly ?string $province,
        public readonly ?string $purpose,
        public readonly ?string $carLicense,
        public readonly ?string $managerCode,
        public readonly ?string $managerName,
    ) {}

    /**
     * Build a snapshot from the joined booking / employee row.
     *
     * Every free text column here comes from the legacy intranet tables, which
     * hold Thai as TIS-620, so each one is decoded before it reaches the mail.
     * Codes, dates and times are ASCII and are passed through untouched.
     */
    public static function fromRow(object $row): self
    {
        $requesterName = trim(implode(' ', array_filter([
            LegacyText::toUtf8($row->requester_fname ?? null),
            LegacyText::toUtf8($row->requester_lname ?? null),
        ])));

        return new self(
            docId: (string) $row->doc_id,
            plant: LegacyText::toUtf8($row->plant ?? null),
            requesterCode: $row->user_id ?? null,
            requesterName: $requesterName === '' ? null : $requesterName,
            department: LegacyText::toUtf8($row->requester_department ?? null),
            tel: LegacyText::toUtf8($row->tel ?? null),
            usedDate: $row->used_date ?? null,
            usedTime: $row->used_time ?? null,
            backDate: $row->back_date ?? null,
            backTime: $row->back_time ?? null,
            destination: LegacyText::toUtf8($row->request_goto ?? null),
            province: LegacyText::toUtf8($row->request_province ?? null),
            purpose: LegacyText::toUtf8($row->subject_id ?? null),
            carLicense: LegacyText::toUtf8($row->car_license ?? null),
            managerCode: $row->mgr_select_id ?? null,
            managerName: null,
        );
    }

    /**
     * Sample details used by the "test email" screen when no real booking is
     * given, so the template can be checked without touching a live booking.
     */
    public static function preview(): self
    {
        return new self(
            docId: 'BK-PREVIEW',
            plant: '1100',
            requesterCode: 'A000000',
            requesterName: 'ตัวอย่าง ผู้จองรถ',
            department: 'Information Technology',
            tel: '1234',
            usedDate: now()->addDay()->format('Y-m-d'),
            usedTime: '08:00',
            backDate: now()->addDay()->format('Y-m-d'),
            backTime: '17:00',
            destination: 'สำนักงานใหญ่',
            province: 'สมุทรปราการ',
            purpose: 'ทดสอบระบบแจ้งเตือนอีเมล',
            carLicense: '1กก 1234',
            managerCode: null,
            managerName: null,
        );
    }

    /**
     * Return a copy of the snapshot addressed to the resolved manager.
     */
    public function withManager(string $managerCode, ?string $managerName): self
    {
        return new self(
            docId: $this->docId,
            plant: $this->plant,
            requesterCode: $this->requesterCode,
            requesterName: $this->requesterName,
            department: $this->department,
            tel: $this->tel,
            usedDate: $this->usedDate,
            usedTime: $this->usedTime,
            backDate: $this->backDate,
            backTime: $this->backTime,
            destination: $this->destination,
            province: $this->province,
            purpose: $this->purpose,
            carLicense: $this->carLicense,
            managerCode: $managerCode,
            managerName: $managerName,
        );
    }
}
