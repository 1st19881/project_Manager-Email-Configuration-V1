<?php

namespace Tests\Unit;

use App\Http\Resources\TrcMailNotiMgrResource;
use App\Models\TrcMailNotiLog;
use App\Models\TrcMailNotiMgr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TrcMailNotiMgrTest extends TestCase
{
    public function test_model_uses_existing_oracle_table_configuration(): void
    {
        $manager = new TrcMailNotiMgr;

        $this->assertSame('intra', $manager->getConnectionName());
        $this->assertSame('TRC_MAIL_NOTI_MGR', $manager->getTable());
        $this->assertSame('noti_id', $manager->getKeyName());
        $this->assertSame('string', $manager->getKeyType());
        $this->assertFalse($manager->getIncrementing());
    }

    /**
     * The intra database is WE8DEC, so UTF-8 written there shows up as "เธ..."
     * in the legacy pages and SQL tools. Thai must reach it as TIS-620.
     */
    public function test_thai_text_is_stored_as_tis620(): void
    {
        $manager = new TrcMailNotiMgr([
            'position_name' => 'ผู้จัดการ',
            'department_name' => 'IT',
            'name_th' => 'สมชาย',
            'name_en' => 'Somchai',
        ]);

        $raw = $manager->getAttributes();

        $this->assertSame('cac1aad2c2', bin2hex($raw['name_th']));
        $this->assertSame('bcd9e9a8d1b4a1d2c3', bin2hex($raw['position_name']));
        $this->assertSame('IT', $raw['department_name']);
        $this->assertSame('Somchai', $raw['name_en']);
        $this->assertSame('สมชาย', $manager->name_th);
        $this->assertSame('ผู้จัดการ', $manager->position_name);
    }

    public function test_notification_log_subject_is_stored_as_tis620(): void
    {
        $log = new TrcMailNotiLog(['email_subject' => '[Car Booking] มีรายการ #DOC-1']);

        $this->assertSame('[Car Booking] '.hex2bin('c1d5c3d2c2a1d2c3').' #DOC-1', $log->getAttributes()['email_subject']);
        $this->assertSame('[Car Booking] มีรายการ #DOC-1', $log->email_subject);
    }

    public function test_resource_returns_public_snake_case_contract(): void
    {
        $manager = new TrcMailNotiMgr;
        $manager->setRawAttributes([
            'noti_id' => 'noti-1',
            'employee_code' => 'EMP001',
            'plant' => '1100',
            'position_name' => 'Manager',
            'department_name' => 'IT',
            'name_th' => hex2bin('cac1aad2c2'),
            'name_en' => 'Somchai',
            'email' => 'somchai@example.com',
            'active_flag' => 'Y',
            'created_by' => 'admin',
            'created_at' => Carbon::parse('2026-09-22 10:00:00'),
            'updated_by' => null,
            'updated_at' => null,
        ]);

        $resource = (new TrcMailNotiMgrResource($manager))->toArray(Request::create('/'));

        $this->assertSame([
            'noti_id' => 'noti-1',
            'employee_code' => 'EMP001',
            'plant' => '1100',
            'position_name' => 'Manager',
            'department_name' => 'IT',
            'name_th' => 'สมชาย',
            'name_en' => 'Somchai',
            'email' => 'somchai@example.com',
            'active_flag' => 'Y',
            'created_by' => 'admin',
            'created_at' => '2026-09-22T10:00:00.000000Z',
            'updated_by' => null,
            'updated_at' => null,
        ], $resource);
    }
}
