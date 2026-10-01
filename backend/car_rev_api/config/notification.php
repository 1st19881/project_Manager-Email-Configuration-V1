<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Internal API Key
    |--------------------------------------------------------------------------
    |
    | Legacy systems authenticate against the notification endpoint with the
    | "X-API-KEY" header. Requests are rejected outright when no key has been
    | configured so that the endpoint can never be left publicly writable.
    |
    */

    'api_key' => env('NOTI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The connection and queue name used to deliver notification mail. The API
    | never waits for SMTP, so a slow mail server cannot stall a booking save.
    |
    */

    'queue' => [
        'connection' => env('NOTI_QUEUE_CONNECTION'),
        'name' => env('NOTI_QUEUE', 'notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Supported event codes mapped to the subject line used for their mail.
    | The ":reference" placeholder is replaced with the booking document id.
    |
    */

    'events' => [
        'BOOKING_CREATED' => '[Car Booking] มีรายการจองรถใหม่ #:reference',
        'BOOKING_UPDATED' => '[Car Booking] รายการจองรถถูกแก้ไข #:reference',
        'BOOKING_APPROVED' => '[Car Booking] รายการจองรถได้รับการอนุมัติ #:reference',
        'BOOKING_REJECTED' => '[Car Booking] รายการจองรถไม่ได้รับการอนุมัติ #:reference',
        'BOOKING_CANCELLED' => '[Car Booking] รายการจองรถถูกยกเลิก #:reference',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Body Wording
    |--------------------------------------------------------------------------
    |
    | Every piece of text rendered inside the notification mail. The blade view
    | and the "mail-templates" endpoint both read from here, so the preview the
    | administrators see on screen is the wording that actually gets sent.
    |
    */

    'body' => [

        'greeting' => 'เรียน :name',
        'greeting_fallback' => 'ผู้อนุมัติ',

        'headlines' => [
            'BOOKING_CREATED' => 'มีรายการจองรถใหม่ รอการตรวจสอบ',
            'BOOKING_UPDATED' => 'รายการจองรถถูกแก้ไข',
            'BOOKING_APPROVED' => 'รายการจองรถได้รับการอนุมัติ',
            'BOOKING_REJECTED' => 'รายการจองรถไม่ได้รับการอนุมัติ',
            'BOOKING_CANCELLED' => 'รายการจองรถถูกยกเลิก',
        ],

        'fields' => [
            'doc_id' => 'เลขที่รายการ',
            'requester' => 'ผู้จอง',
            'department' => 'หน่วยงาน',
            'plant' => 'โรงงาน',
            'tel' => 'เบอร์ติดต่อ',
            'used_at' => 'วันที่ใช้รถ',
            'back_at' => 'วันที่กลับ',
            'destination' => 'สถานที่ไป',
            'purpose' => 'วัตถุประสงค์',
            'car_license' => 'ทะเบียนรถ',
        ],

        'empty_value' => '-',
        'closing' => 'กรุณาตรวจสอบรายการ',
        'cta' => 'เปิดรายการจองรถ',
        'footer' => 'อีเมลฉบับนี้ถูกส่งโดยอัตโนมัติจากระบบจองรถ กรุณาอย่าตอบกลับ',

    ],

    /*
    |--------------------------------------------------------------------------
    | Standing CC Recipients
    |--------------------------------------------------------------------------
    |
    | Comma separated addresses copied on every notification, typically the
    | transport administrators.
    |
    */

    'cc' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('NOTI_MAIL_CC', ''))),
    )),

    /*
    |--------------------------------------------------------------------------
    | Approval Link
    |--------------------------------------------------------------------------
    |
    | Target of the button at the bottom of every mail. ":reference" is replaced
    | with the booking document id when the link is rendered in the mail body.
    | Falls back to the intranet portal when NOTI_APPROVAL_URL is left empty.
    |
    */

    'approval_url' => env('NOTI_APPROVAL_URL') ?: 'https://e.summitautogroup.com/',

    /*
    |--------------------------------------------------------------------------
    | Report Timezone
    |--------------------------------------------------------------------------
    |
    | Log timestamps are stored in UTC. The log dashboard groups and filters
    | them by calendar day on this clock so the counts match the office day.
    |
    */

    'report_timezone' => env('NOTI_REPORT_TIMEZONE', 'Asia/Bangkok'),

];
