<?php

namespace Tests\Unit;

use App\Services\Notification\BookingSnapshot;
use App\Support\LegacyText;
use PHPUnit\Framework\TestCase;

class LegacyTextTest extends TestCase
{
    public function test_it_decodes_thai_stored_as_tis620(): void
    {
        $this->assertSame('ทวีเดชน์', LegacyText::toUtf8(hex2bin('b7c7d5e0b4aab9ec')));
        $this->assertSame('ชลบุรี', LegacyText::toUtf8(hex2bin('aac5bad8c3d5')));
    }

    /**
     * TIS-620 Thai regularly forms byte sequences that are also valid UTF-8, so
     * decoding only the values that fail a UTF-8 check would leave these broken.
     */
    public function test_it_decodes_thai_that_also_passes_a_utf8_check(): void
    {
        $storedName = hex2bin('cab9d4b7');

        $this->assertTrue(mb_check_encoding($storedName, 'UTF-8'));
        $this->assertSame('สนิท', LegacyText::toUtf8($storedName));
        $this->assertSame('เพชรทอง', LegacyText::toUtf8(hex2bin('e0beaac3b7cda7')));
    }

    public function test_it_leaves_ascii_and_missing_values_untouched(): void
    {
        $this->assertSame('DOC-00092956', LegacyText::toUtf8('DOC-00092956'));
        $this->assertSame('', LegacyText::toUtf8(''));
        $this->assertNull(LegacyText::toUtf8(null));
    }

    public function test_booking_snapshot_decodes_every_legacy_text_column(): void
    {
        $snapshot = BookingSnapshot::fromRow((object) [
            'doc_id' => 'DOC-00092956',
            'user_id' => 'L368006',
            'plant' => '1800',
            'mgr_select_id' => 'A148029',
            'used_date' => '2026-09-24',
            'used_time' => '08:00',
            'back_date' => '2026-09-24',
            'back_time' => '17:00',
            'request_goto' => hex2bin('bed1b7c2d2'),
            'request_province' => hex2bin('aac5bad8c3d5'),
            'subject_id' => hex2bin('bbc3d0aad8c1'),
            'car_license' => hex2bin('a1a82d39343635'),
            'tel' => '110',
            'requester_fname' => hex2bin('cab9d4b7'),
            'requester_lname' => hex2bin('e0beaac3b7cda7'),
            'requester_department' => 'HR',
        ]);

        $this->assertSame('สนิท เพชรทอง', $snapshot->requesterName);
        $this->assertSame('พัทยา', $snapshot->destination);
        $this->assertSame('ชลบุรี', $snapshot->province);
        $this->assertSame('ประชุม', $snapshot->purpose);
        $this->assertSame('กจ-9465', $snapshot->carLicense);

        $this->assertSame('DOC-00092956', $snapshot->docId);
        $this->assertSame('L368006', $snapshot->requesterCode);
        $this->assertSame('2026-09-24', $snapshot->usedDate);
    }
}
