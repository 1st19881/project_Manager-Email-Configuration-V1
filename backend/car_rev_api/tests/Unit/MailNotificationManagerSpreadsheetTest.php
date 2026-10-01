<?php

namespace Tests\Unit;

use App\Support\MailNotificationManagerSpreadsheet;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class MailNotificationManagerSpreadsheetTest extends TestCase
{
    public function test_generated_template_can_be_read(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-import-test-');
        $spreadsheet = new MailNotificationManagerSpreadsheet;

        $spreadsheet->createTemplate($path);

        $file = new UploadedFile(
            $path,
            'template.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->assertSame([], $spreadsheet->read($file));
        unlink($path);
    }

    public function test_csv_rows_are_mapped_from_the_template_headers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-import-test-');
        file_put_contents(
            $path,
            "employee_code,plant,position_name,department_name,name_th,name_en,email,active_flag\nEMP001,100,Manager,IT,สมชาย,Somchai,somchai@example.com,Y\n",
        );

        $file = new UploadedFile($path, 'recipients.csv', 'text/csv', null, true);
        $rows = (new MailNotificationManagerSpreadsheet)->read($file);

        $this->assertSame('EMP001', $rows[0]['employee_code']);
        $this->assertSame('100', $rows[0]['plant']);
        $this->assertSame('สมชาย', $rows[0]['name_th']);
        $this->assertSame('somchai@example.com', $rows[0]['email']);
        unlink($path);
    }
}
