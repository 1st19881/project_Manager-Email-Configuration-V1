<?php

namespace Tests\Support;

use App\Models\TrcMailNotiMgr;
use App\Services\Notification\BookingSnapshot;

class NotificationFixtures
{
    public static function booking(string $docId = 'BK26090001', ?string $managerCode = 'A148029'): BookingSnapshot
    {
        return new BookingSnapshot(
            docId: $docId,
            plant: '1100',
            requesterCode: 'A100001',
            requesterName: 'สมชาย ใจดี',
            department: 'IT',
            tel: '1234',
            usedDate: '2026-09-25',
            usedTime: '08:00',
            backDate: '2026-09-25',
            backTime: '17:00',
            destination: 'สำนักงานใหญ่',
            province: 'สมุทรปราการ',
            purpose: 'ประชุมประจำเดือน',
            carLicense: '1กก 1234',
            managerCode: $managerCode,
            managerName: null,
        );
    }

    public static function manager(string $employeeCode = 'A148029'): TrcMailNotiMgr
    {
        return new TrcMailNotiMgr([
            'noti_id' => 'noti-1',
            'employee_code' => $employeeCode,
            'plant' => '1100',
            'name_th' => 'ณัฏฐ์ศวัส แก้วธนะสิน',
            'name_en' => 'Nutsawat K.',
            'email' => 'manager@summitautogroup.com',
            'active_flag' => 'Y',
        ]);
    }
}
