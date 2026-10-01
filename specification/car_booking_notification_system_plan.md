# Car Booking Notification System Plan

## 1. Overview

ระบบจองรถเดิมพัฒนาด้วย **PHP แบบเดิม (Legacy PHP)** และใช้ **Oracle Database** อยู่แล้ว

เป้าหมายของงานเพิ่มเติมคือเพิ่มระบบ **Notification แจ้งเตือนทาง Email** ไปยังผู้จัดการ โดยไม่กระทบโครงสร้างระบบจองรถเดิมมากเกินไป

แนวทางที่แนะนำคือแยก Notification ออกเป็น Service ใหม่

- **Legacy PHP** = ระบบจองรถเดิม
- **Laravel** = Notification API / Queue / Mail Service
- **Vue** = หน้าจัดการ Email ผู้จัดการ / Config / Template / Log
- **Oracle** = Database กลาง
- **SMTP / Microsoft 365 / Exchange** = ระบบส่ง Email

---

# 2. Proposed Architecture

```text
┌──────────────────────────────┐
│   ระบบจองรถเดิม              │
│   Legacy PHP                 │
│                              │
│ - Create Booking             │
│ - Approve / Reject           │
│ - Cancel Booking             │
│ - Update Booking             │
└──────────────┬───────────────┘
               │
               │ HTTP REST API
               ▼
┌─────────────────────────────────────────┐
│        Laravel Notification API         │
│                                         │
│  POST /api/v1/notifications             │
│                                         │
│  Notification Service                   │
│          │                              │
│          ├── Find Recipient             │
│          ├── Load Email Template        │
│          ├── Create Notification Log    │
│          ├── Queue Email                │
│          └── Send Email                 │
└──────────────┬──────────────────────────┘
               │
               │ Oracle
               ▼
┌─────────────────────────────────────────┐
│                 Oracle DB               │
│                                         │
│ CAR_BOOKING                             │
│ NOTI_RECIPIENT                          │
│ NOTI_RECIPIENT_EVENT                    │
│ NOTI_CONFIG                             │
│ NOTI_TEMPLATE                           │
│ NOTI_LOG                                │
└─────────────────────────────────────────┘

                     ▲
                     │ REST API
                     │
┌────────────────────┴────────────────────┐
│           Vue Notification Admin        │
│                                         │
│ - จัดการ Email ผู้จัดการ                │
│ - กำหนด Plant / Department             │
│ - เปิด / ปิด Notification              │
│ - จัดการ CC                            │
│ - จัดการ Email Template                │
│ - Notification History                 │
│ - Test Email                           │
└─────────────────────────────────────────┘

               Laravel Queue
                     │
                     ▼
        ┌────────────────────────┐
        │ SMTP / M365 / Exchange │
        └────────────┬───────────┘
                     │
                     ▼
                📧 Manager
```

---

# 3. Design Principle

ระบบจองรถเดิมไม่ควรส่ง Email เอง

Legacy PHP ควรทำหน้าที่เพียง

1. บันทึกข้อมูล Booking
2. Commit Transaction
3. แจ้ง Laravel ว่ามี Event เกิดขึ้น
4. Laravel รับผิดชอบการส่ง Notification

ตัวอย่าง Event

```text
BOOKING_CREATED
BOOKING_UPDATED
BOOKING_APPROVED
BOOKING_REJECTED
BOOKING_CANCELLED
BOOKING_REMINDER
```

รายการนี้คือ event ทั้งหมดที่ออกแบบไว้ **ไม่ใช่ที่ต่อใช้งานจริง**
ตอนนี้ต่อจริงแค่ `BOOKING_CREATED` ตัวเดียว (ดูข้อ 5.4 / 5.5)

---

# 4. Main Flow

## 4.1 Booking Created

```text
Employee
   │
   ▼
Legacy PHP
   │
   │ INSERT CAR_BOOKING
   ▼
Oracle
   │
   │ COMMIT SUCCESS
   ▼
Legacy PHP
   │
   │ POST /api/v1/notifications
   ▼
Laravel API
   │
   ▼
Get Booking Detail
   │
   ▼
Find Notification Config
   │
   ▼
Find Manager / Recipient
   │
   ▼
Load Email Template
   │
   ▼
Create NOTI_LOG
   │
   ▼
Dispatch Queue
   │
   ▼
Return API Success
   │
   ▼
Queue Worker
   │
   ▼
SMTP
   │
   ▼
Manager Email
```

---

# 5. Legacy PHP Integration

> สถานะ: **ต่อใช้งานจริงแล้ว เฉพาะ `BOOKING_CREATED`** (2026-09-23)
> หัวข้อนี้เป็น as-built ไม่ใช่ข้อเสนอ — event อื่นยังไม่ต่อ ดูข้อ 5.5

## 5.1 หลักการ

Legacy PHP ส่งข้อมูลให้น้อยที่สุด

```json
{
    "event": "BOOKING_CREATED",
    "reference_id": "DOC-00092965",
    "mgr_select_id": "A148029"
}
```

`mgr_select_id` เป็น optional — ถ้าไม่ส่ง Laravel จะอ่าน `MGR_SELECT_ID`
จากแถว Booking เอง จากนั้น Laravel Query รายละเอียด Booking จาก Oracle เอง

ข้อดี

- ลด Dependency ระหว่าง PHP กับ Laravel
- เปลี่ยนโครงสร้าง Email ได้โดยไม่แก้ PHP เดิม
- ป้องกันข้อมูลจาก PHP กับ DB ไม่ตรงกัน
- Laravel สามารถนำไปใช้กับระบบอื่นในอนาคตได้

---

## 5.2 ระบบเดิมอยู่ที่ไหน

โฟลเดอร์ `Z:\Intranet\PRG\DB-ecc60\` มีสำเนาของระบบจองรถอยู่ **แปดชุด**
(`car_reservation`, `_dev`, `_test`, `_old`, `_2`, `_mian_dev`, ` - Copy`, ` - Copy (2)`)

ชุดที่รันจริงคือ **`car_reservation`** เฉย ๆ — ชุดเดียวที่เข้าถึงได้จากเมนู
`page/menu_l.php` เสิร์ฟที่ `http://192.1.1.48/intranet/prg/DB-ecc60/car_reservation/`

ภายในแต่ละไฟล์ก็มี variant ตายแล้วอีกหลายตัว (`*_v1`, `*_2`, `*_copy`, `*_old`)
**อย่าใช้ mtime ตัดสินว่าไฟล์ไหนคือตัวจริง** ให้ไล่ `include()` จาก `menu_l.php` เสมอ

เส้นทางที่รันจริง (ตรวจสอบ 2026-09-23)

| การกระทำ | เส้นทาง |
|---|---|
| สร้างใบจอง | `form.php` → `form_add_doc_v2.php` → `datafileinsert/insert_adddoc_v2.php` |
| แก้ไขใบจอง | `form_my_booking.php` → `form_edit.php` → `datafileinsert/insert_form_edit.php` |
| ผู้จัดการอนุมัติ / ไม่อนุมัติ | `mgr.php` → `mgr_app.php`, `mgr_app2.php` → `datafileinsert/insert_mgr_update.php` (AP01), `delete_mgr_update.php` (AP05) |
| GA จัดรถ / ไม่อนุมัติ / ยกเลิกจัดรถ | `ga.php` → `ga_app.php` → `datafileinsert/insert_ga_update.php`, `insert_ga_update1.php` (AP02), `delete_ga_update.php` (AP06), `cancel_ga_update.php` |
| ยกเลิกใบจอง | `datafileinsert/delete.php` (AP07/08/09) เรียกจาก `mgr_app*.php` และ `history_by.php` |

ในตารางนี้มีแค่ `insert_adddoc_v2.php` (สร้างใบจอง) ที่ถูกแก้เพื่อยิง notification
ไฟล์ที่เหลือยังเป็นโค้ดเดิมทั้งหมด

ไฟล์เหล่านี้เก็บภาษาไทยเป็น **TIS-620 / CP874 ไม่ใช่ UTF-8** — เปิดแก้ด้วย
editor ที่เซฟกลับเป็น UTF-8 จะทำให้ภาษาไทยพังทั้งไฟล์แบบกู้ไม่ได้

---

## 5.3 ข้อจำกัดของ Server ที่บังคับรูปแบบการเรียก

ทั้งสองข้อนี้ทำให้โค้ดตัวอย่างทั่วไปบนอินเทอร์เน็ตใช้กับที่นี่ไม่ได้

### 5.3.1 Legacy PHP ต่อ HTTPS ไม่ได้เลย

`Z:\Intranet\web.config` ประกาศ handler `PHP52_via_FastCGI` ไว้เป็นตัวแรก
`*.php` ทุกไฟล์ใต้ `/intranet/prg/...` จึงถูกรันด้วย **PHP 5.2.17**
ไม่ใช่ PHP 7.x / 8.4 ที่ลงทะเบียนไว้ด้านล่าง

```text
php_version  = 5.2.17
curl_version = 7.21.0
ssl_version  = OpenSSL/0.9.8q      <- ปี 2010 ไม่มี TLS 1.2
```

ผลการทดสอบยิงจากตัว web server เอง

```text
http://192.1.1.48/intranet/php84/GA/car_rev_api/public/api/v1/notifications
    -> HTTP 200  0.42s  OK

http://localhost/intranet/php84/GA/car_rev_api/public/api/v1/notifications
    -> HTTP 200  0.36s  OK

https://e.summitautogroup.com/intranet/php84/GA/car_rev_api/public/api/v1/notifications
    -> HTTP 0    Unknown SSL protocol error in connection to e.summitautogroup.com:443
```

`https` ตายตั้งแต่ขั้น handshake **request ไม่เคยถูกส่งออกไป** ใบจองบันทึกสำเร็จ
แต่ notification หายเงียบ ร่องรอยเดียวอยู่ใน `C:\Windows\temp\php-errors.log`
บนเครื่อง server ซึ่งมองไม่เห็นผ่าน share `Z:`

> **ห้ามเปลี่ยน URL ใน `noti_api.php` กลับไปเป็น `https://`**
> จนกว่าจะย้าย legacy app ไปรันบน PHP รุ่นใหม่

นอกจากนี้ PHP ตัวนี้ไม่มี environment variable ตั้งไว้ให้
(`getenv('CAR_NOTI_API_URL')` คืน `false`) config จึงต้องอยู่ในไฟล์ ไม่ใช่ IIS/FastCGI env

### 5.3.2 IIS แปลง HTTP status ทุกตัวเป็น 200

Laravel ตอบ 202 / 401 / 404 / 422 แต่ client ได้รับ **200 เสมอ** พร้อม JSON body ที่ถูกต้อง
(เป็น setting ระดับ server เจอกับทุก service บน PHP84 ไม่ใช่เฉพาะแอปนี้)

ดังนั้น **ห้ามตัดสินสำเร็จ/ล้มเหลวจาก HTTP status** ต้องอ่านจาก body

```text
สำเร็จ   = มี log_id ที่ไม่ว่าง และ status ไม่ใช่ "FAILED"
ล้มเหลว  = {"message":"Booking ... was not found.","log_id":null,"status":null}
           {"message":"Invalid API key."}
           {"message":"No active notification recipient ...","status":"FAILED"}
```

---

## 5.4 โครงสร้างโค้ดที่ใช้จริง

client ตัวเดียวกลางอยู่ที่ **`page/noti_api.php`** (ASCII ล้วน ไม่มีภาษาไทย)

```php
car_noti_send($event, $bookingId, $managerEmployeeCode = null);   // คืน true/false
```

- config เป็น `define()` ที่หัวไฟล์ override ด้วย
  `getenv('CAR_NOTI_API_URL')` / `getenv('CAR_NOTI_API_KEY')` ได้
- endpoint ที่ใช้จริง
  `http://192.1.1.48/intranet/php84/GA/car_rev_api/public/api/v1/notifications`
- ส่ง `X-API-KEY` ตรงกับ `NOTI_API_KEY` ใน `.env` ของ Laravel
- `CONNECTTIMEOUT` 2 วินาที `TIMEOUT` 5 วินาที
- ตรวจผลจาก body ตาม 5.3.2
- ล้มเหลว = เขียน `error_log()` แล้ว return `false` **ไม่ throw ไม่ rollback**
- กัน include ซ้ำด้วย `function_exists()` / `defined()`

จุดเรียกทั้งหมด — เรียก**หลัง** `oci_execute()` สำเร็จเท่านั้น ตามข้อ 6

ขอบเขตรอบนี้ต่อ **`BOOKING_CREATED` อย่างเดียว** event อื่นยังไม่ต่อ (ดูข้อ 5.5)

| ไฟล์ | Event | เงื่อนไข |
|---|---|---|
| `datafileinsert/insert_adddoc_v2.php` | `BOOKING_CREATED` | insert แถว `TRC_REQUEST_RECORD_NEW` สำเร็จ |

ตัวอย่างการต่อจุดใหม่

```php
include('../noti_api.php');

$sql = "insert into ... ";
$stmt = oci_parse($dbcon, $sql);
$saved = oci_execute($stmt);          // oci_execute commit ให้อัตโนมัติ

if ($saved) {
    car_noti_send('BOOKING_CREATED', $DOC_ID, $MGR_SELECT_ID);
}
```

---

## 5.5 สิ่งที่ยังไม่ได้ต่อ และเหตุผล

**`BOOKING_UPDATED` / `BOOKING_CANCELLED`** ยังไม่ต่อ ตามที่ตกลงให้ทำ
`BOOKING_CREATED` ก่อนอย่างเดียว hook ที่เคยใส่ไว้ใน
`datafileinsert/insert_form_edit.php` และ `datafileinsert/delete.php`
ถูกถอดออกแล้ว ทั้งสองไฟล์กลับไปเป็นโค้ดเดิม ไม่มี `include('../noti_api.php')`
ฝั่ง Laravel ยังรองรับ event ทั้งสองอยู่ (config + template) ถ้าจะเปิดใช้
ก็แค่ใส่ hook กลับตามตัวอย่างข้างบน

**`BOOKING_APPROVED` / `BOOKING_REJECTED`** ยังไม่ได้ต่อ

`NotificationService` ส่งเมลไปที่ `MGR_SELECT_ID` ของใบจองเสมอทุก event
ไม่มี logic ส่งกลับหาผู้จอง ถ้าต่อตอนนี้เมล "อนุมัติแล้ว" จะเด้งกลับไปหา
คนที่เพิ่งกดอนุมัติเอง ต้องแก้ `RecipientResolver` ให้ resolve ผู้รับ
จาก `USER_ID` → `INTRA.USERS` ก่อน แล้วค่อยต่อ
`insert_mgr_update.php`, `delete_mgr_update.php`,
`insert_ga_update.php`, `insert_ga_update1.php`, `delete_ga_update.php`

**Bug เดิมที่เจอระหว่างทาง ยังไม่ได้แก้** (ไม่เกี่ยวกับ notification)

1. `datafileinsert/delete.php` เขียน `APP_ID` เป็นค่าว่างทุกครั้ง —
   ทั้ง `de()` ใน `mgr_app.php` และ `dl()` ใน `history_by.php`
   ส่งมาแค่ `DOC_ID` ไม่ส่ง `APP_ID` ตัวแปร `$a` จึงไม่ถูกกำหนดค่า
   ที่ถูกควรได้ AP07 / AP08 / AP09
2. `datafileinsert/insert_mgr_update.php` บรรทัด 2 มี `include("no_url.php")`
   แต่ไฟล์อยู่ที่ `page/no_url.php` ไม่ใช่ `page/datafileinsert/`
   → warning ทุกครั้งที่กดอนุมัติ

---

## 5.6 วิธี Debug เมื่อ "กดบันทึกแล้วไม่มีเมล"

`error_log()` ของ legacy app ไปที่ `C:\Windows\temp\php-errors.log` บนเครื่อง server
ซึ่งเข้าไม่ถึงจาก share `Z:` (map เป็น `d$`) วิธีที่ใช้ได้ผลคือวางไฟล์ diagnostic
ชั่วคราวไว้ใน `page/` แล้วเรียกผ่าน HTTP — จะได้รันด้วย PHP ตัวจริงของ server
ไม่ใช่ PHP บนเครื่อง dev (คนละเวอร์ชันคนละ SSL library) อย่าลืมลบทิ้งเมื่อเสร็จ

ลำดับการไล่

```text
1. car_noti_send() ถูกเรียกไหม          -> เพิ่ม error_log() ชั่วคราว
2. curl ต่อถึง endpoint ไหม             -> curl_error() ต้องว่าง ถ้าเจอ SSL error = ข้อ 5.3.1
3. API ตอบอะไร                          -> อ่าน body ไม่ใช่ status code (ข้อ 5.3.2)
4. มีแถวใน TRC_MAIL_NOTI_LOG ไหม        -> ไม่มี = request ไปไม่ถึง หรือโดนปัดตกก่อนสร้าง log
5. ผู้อนุมัติมีใน TRC_MAIL_NOTI_MGR ไหม -> ต้องมี EMPLOYEE_CODE ตรง และ ACTIVE_FLAG = 'Y'
6. SMTP ใช้ได้ไหม                       -> POST /api/v1/mail/test ส่งหาตัวเองก่อน
```

ตาราง `TRC_MAIL_NOTI_LOG` และ `TRC_MAIL_NOTI_MGR` อยู่บน connection `intra`
(`192.1.1.241 / SAGDB / WEB`) ซึ่งเป็น credential เดียวกับที่ legacy app ใช้
จึง query ตรวจสอบจากฝั่ง legacy ได้เลย

---

# 6. Important Transaction Rule

ห้ามเรียก Notification API ก่อน Commit Booking

ไม่ควรทำ

```text
INSERT BOOKING
   ↓
Call Laravel
   ↓
Send Mail
   ↓
Database Error
   ↓
ROLLBACK
```

เพราะ Manager อาจได้รับ Email ทั้งที่ Booking ถูก Rollback

ควรทำ

```text
INSERT BOOKING
   ↓
COMMIT
   ↓
Commit Success
   ↓
Call Notification API
```

---

# 7. Database Design

## 7.1 NOTI_RECIPIENT

เก็บผู้รับ Notification

```sql
CREATE TABLE NOTI_RECIPIENT
(
    ID              NUMBER PRIMARY KEY,

    RECIPIENT_NAME  VARCHAR2(200),
    EMAIL           VARCHAR2(255) NOT NULL,

    COMPANY_CODE    VARCHAR2(20),
    PLANT_CODE      VARCHAR2(20),
    DEPT_CODE       VARCHAR2(20),

    RECIPIENT_TYPE  VARCHAR2(20),

    ACTIVE_FLAG     CHAR(1) DEFAULT 'Y',

    CREATED_BY      VARCHAR2(50),
    CREATED_AT      DATE DEFAULT SYSDATE,
    UPDATED_BY      VARCHAR2(50),
    UPDATED_AT      DATE
);
```

ตัวอย่าง `RECIPIENT_TYPE`

```text
MANAGER
CC
ADMIN
```

ตัวอย่างข้อมูล

| NAME | EMAIL | PLANT | DEPT | TYPE |
|---|---|---|---|---|
| Somchai | somchai@company.com | SMM | IT | MANAGER |
| Somsak | somsak@company.com | SMM | HR | MANAGER |
| Transport Admin | transport@company.com | SMM | ALL | CC |

---

# 8. NOTI_RECIPIENT_EVENT

ใช้สำหรับกำหนดว่า Recipient แต่ละคนรับ Event อะไรบ้าง

```sql
CREATE TABLE NOTI_RECIPIENT_EVENT
(
    ID              NUMBER PRIMARY KEY,

    RECIPIENT_ID    NUMBER NOT NULL,
    EVENT_CODE      VARCHAR2(50) NOT NULL,

    ACTIVE_FLAG     CHAR(1) DEFAULT 'Y',

    CREATED_AT      DATE DEFAULT SYSDATE
);
```

ตัวอย่าง

```text
Somchai
   ├── BOOKING_CREATED
   ├── BOOKING_UPDATED
   └── BOOKING_CANCELLED
```

ข้อดีคือไม่ต้องเพิ่ม Column ใหม่ทุกครั้งที่มี Event ใหม่

---

# 9. NOTI_CONFIG

กำหนด Configuration ของ Notification

```sql
CREATE TABLE NOTI_CONFIG
(
    ID              NUMBER PRIMARY KEY,

    EVENT_CODE      VARCHAR2(50) NOT NULL,
    EVENT_NAME      VARCHAR2(200),

    ENABLE_EMAIL    CHAR(1) DEFAULT 'Y',

    TEMPLATE_CODE   VARCHAR2(50),

    ACTIVE_FLAG     CHAR(1) DEFAULT 'Y',

    CREATED_AT      DATE DEFAULT SYSDATE,
    UPDATED_AT      DATE
);
```

ตัวอย่าง

```text
BOOKING_CREATED
BOOKING_UPDATED
BOOKING_APPROVED
BOOKING_REJECTED
BOOKING_CANCELLED
BOOKING_REMINDER
```

---

# 10. NOTI_TEMPLATE

ใช้จัดการ Email Template

```sql
CREATE TABLE NOTI_TEMPLATE
(
    ID              NUMBER PRIMARY KEY,

    TEMPLATE_CODE   VARCHAR2(50) UNIQUE,
    TEMPLATE_NAME   VARCHAR2(200),

    EMAIL_SUBJECT   VARCHAR2(500),
    EMAIL_BODY      CLOB,

    ACTIVE_FLAG     CHAR(1) DEFAULT 'Y',

    CREATED_AT      DATE DEFAULT SYSDATE,
    UPDATED_AT      DATE
);
```

ตัวอย่าง Subject

```text
[Car Booking] มีรายการจองรถใหม่ #{BOOKING_NO}
```

ตัวอย่าง Body

```html
เรียน {MANAGER_NAME}

มีรายการจองรถใหม่ รอการตรวจสอบ

เลขที่รายการ : {BOOKING_NO}
ผู้จอง : {EMPLOYEE_NAME}
หน่วยงาน : {DEPARTMENT}
วันที่ใช้รถ : {BOOKING_DATE}
เวลา : {START_TIME} - {END_TIME}
สถานที่ : {DESTINATION}
วัตถุประสงค์ : {PURPOSE}

กรุณาตรวจสอบรายการ

{APPROVAL_LINK}
```

Laravel Replace Template Variable ก่อนส่ง Email

ตัวอย่าง

```php
$variables = [
    '{BOOKING_NO}'    => $booking->booking_no,
    '{EMPLOYEE_NAME}' => $booking->employee_name,
    '{DEPARTMENT}'    => $booking->department_name,
];
```

---

# 11. NOTI_LOG

เก็บประวัติการส่ง Notification

```sql
CREATE TABLE NOTI_LOG
(
    ID                NUMBER PRIMARY KEY,

    EVENT_CODE        VARCHAR2(50),

    REF_TYPE          VARCHAR2(50),
    REF_ID            VARCHAR2(100),

    EMAIL_TO          VARCHAR2(1000),
    EMAIL_CC          VARCHAR2(1000),

    EMAIL_SUBJECT     VARCHAR2(500),

    STATUS            VARCHAR2(20),

    ERROR_MESSAGE     VARCHAR2(4000),

    SENT_AT           DATE,

    CREATED_AT        DATE DEFAULT SYSDATE
);
```

ค่า STATUS

```text
PENDING
PROCESSING
SENT
FAILED
```

ตัวอย่างหน้า History

```text
Booking       Event              To                   Status
----------------------------------------------------------------
BK26090001    BOOKING_CREATED    manager@company.com  SENT
BK26090002    BOOKING_CREATED    manager2@company.com FAILED
BK26090003    BOOKING_APPROVED   user@company.com     SENT
```

---

# 12. Vue Admin

แนะนำเมนู

```text
Notification Management

├── Dashboard
│
├── Email Recipients
│   ├── Manager
│   ├── CC
│   └── Admin
│
├── Notification Rules
│
├── Email Templates
│
├── Notification History
│
└── Test Email
```

---

# 13. Manager Email Screen

ตัวอย่าง Layout

```text
┌────────────────────────────────────────────────────────┐
│ Manager Email Configuration                            │
├────────────────────────────────────────────────────────┤
│ Search [____________________]        [+ Add Manager]    │
│                                                        │
│ Plant   Department   Manager        Email       Status  │
│ ------------------------------------------------------ │
│ SMM     IT           Somchai        xxx@...     Active  │
│ SMM     HR           Somsak         xxx@...     Active  │
│ SMM     ACC          Somsri         xxx@...     Active  │
│                                                        │
│                         Edit / Disable                  │
└────────────────────────────────────────────────────────┘
```

Add / Edit

```text
Company
[ Summit Auto ]

Plant
[ SMM ▼ ]

Department
[ IT ▼ ]

Manager Name
[ Somchai ]

Email
[ somchai@company.com ]

CC
[ transport@company.com ]

Notification Event

☑ Booking Created
☑ Booking Updated
☑ Booking Cancelled
☑ Booking Approval Required

                     Cancel    Save
```

---

# 14. Laravel Project Structure

```text
app/
├── Http/
│   └── Controllers/
│       ├── NotificationController.php
│       ├── RecipientController.php
│       ├── TemplateController.php
│       ├── NotificationConfigController.php
│       └── NotificationLogController.php
│
├── Services/
│   └── Notification/
│       ├── NotificationService.php
│       ├── RecipientService.php
│       └── TemplateService.php
│
├── Jobs/
│   └── SendNotificationEmail.php
│
├── Mail/
│   └── BookingNotificationMail.php
│
└── Models/
    ├── NotificationRecipient.php
    ├── NotificationRecipientEvent.php
    ├── NotificationConfig.php
    ├── NotificationTemplate.php
    └── NotificationLog.php
```

---

# 15. REST API Design

## Notification

```text
POST /api/v1/notifications
```

Request

```json
{
    "event": "BOOKING_CREATED",
    "reference_id": "BK26090001"
}
```

---

## Recipient

```text
GET    /api/v1/recipients
POST   /api/v1/recipients
GET    /api/v1/recipients/{id}
PUT    /api/v1/recipients/{id}
DELETE /api/v1/recipients/{id}
```

---

## Notification Events

```text
GET /api/v1/notification-events
```

---

## Templates

```text
GET /api/v1/templates
GET /api/v1/templates/{id}
PUT /api/v1/templates/{id}
```

---

## Logs

```text
GET /api/v1/notification-logs
GET /api/v1/notification-logs/{id}
```

---

## Test Mail

```text
POST /api/v1/mail/test
```

---

# 16. Laravel Notification Service Flow

```text
NotificationController
        │
        ▼
NotificationService
        │
        ├── Validate Event
        │
        ├── Get Booking
        │
        ├── Get NOTI_CONFIG
        │
        ├── Get Recipient
        │
        ├── Load Template
        │
        ├── Render Template
        │
        ├── Create NOTI_LOG
        │
        └── Dispatch Queue
                     │
                     ▼
          SendNotificationEmail
                     │
                     ▼
                  SMTP
                     │
                     ▼
                Send Email
                     │
                     ▼
              Update NOTI_LOG
```

---

# 17. Queue Design

ไม่แนะนำให้ API รอ SMTP ส่ง Email เสร็จ

ไม่ควร

```text
PHP
 ↓
Laravel API
 ↓
SMTP
 ↓
Send Email
 ↓
Return Response
```

ควร

```text
PHP
 ↓
Laravel API
 ↓
Queue Job
 ↓
Return Success

Queue Worker
 ↓
SMTP
 ↓
Send Email
 ↓
Update Log
```

ข้อดี

- ระบบจองรถไม่ค้าง
- SMTP ช้าไม่กระทบ User
- สามารถ Retry ได้
- รองรับ Notification จำนวนมากในอนาคต

---

# 18. Retry

แนะนำให้ Queue Retry เมื่อส่ง Email ไม่สำเร็จ

ตัวอย่าง

```text
Attempt 1
   ↓ Failed

Wait

Attempt 2
   ↓ Failed

Wait

Attempt 3
   ↓ Failed

STATUS = FAILED
```

จากนั้น Admin สามารถตรวจสอบผ่าน Notification History ได้

---

# 19. Security

สำหรับ Internal API แนะนำอย่างน้อย

```text
HTTPS
+
API Key
+
IP Whitelist
```

ตัวอย่าง Header

```http
X-API-KEY: xxxxxxxxxxxxxxxxxx
```

ในอนาคตสามารถเพิ่ม

```text
JWT
OAuth2
Laravel Sanctum
```

ได้ตามความเหมาะสม

---

# 20. Recommended Final Architecture

```text
                    ┌─────────────────┐
                    │     Employee    │
                    └────────┬────────┘
                             │
                             ▼
                ┌──────────────────────┐
                │ Legacy Car Booking   │
                │ PHP                  │
                └──────────┬───────────┘
                           │
                           │ Oracle
                           ▼
                ┌──────────────────────┐
                │     CAR_BOOKING      │
                └──────────────────────┘

                           │
                     After COMMIT
                           │
                           ▼
                 POST Notification
                           │
                           ▼
┌─────────────────────────────────────────────────┐
│              Laravel Notification               │
│                                                 │
│ API → Service → Queue → Mail                    │
│       │                                         │
│       ├── Recipient Management                  │
│       ├── Notification Rules                    │
│       ├── Template Engine                       │
│       └── Notification Log                      │
└───────────────┬───────────────────┬─────────────┘
                │                   │
                │                   │
                ▼                   ▼
        ┌──────────────┐    ┌──────────────────┐
        │   Oracle DB  │    │ SMTP / Exchange  │
        └──────────────┘    └────────┬─────────┘
                                     │
                                     ▼
                                 Manager

                ▲
                │
             REST API
                │
       ┌────────┴────────┐
       │    Vue Admin    │
       │                 │
       │ Manager Email   │
       │ Rules           │
       │ Templates       │
       │ Logs            │
       └─────────────────┘
```

---

# 21. Responsibility

## Legacy PHP

รับผิดชอบ

- Create Booking
- Update Booking
- Cancel Booking
- Booking Business Logic
- Commit Transaction
- Trigger Notification API

ไม่ควรรับผิดชอบ

- SMTP
- Email Template
- Manager Email Mapping
- Notification Retry

---

## Laravel

รับผิดชอบ

- Notification API
- Recipient Resolution
- Template Engine
- Queue
- SMTP
- Retry
- Notification Log
- Notification Configuration

---

## Vue

รับผิดชอบ

- Manage Manager Email
- Manage CC
- Manage Notification Event
- Manage Template
- View Notification History
- Test Email

---

# 22. Future Expansion

Laravel Notification Service สามารถนำไปใช้กับระบบอื่นได้ เช่น

```text
Car Booking
Leave
OT
Recruitment
Expense
PR / PO
Purchase Request
Travel Request
Document Approval
HR Workflow
```

ทุกระบบสามารถใช้ API กลาง

```text
POST /api/v1/notifications
```

ตัวอย่าง

```json
{
    "event": "LEAVE_APPROVAL_REQUIRED",
    "reference_id": "LV26001234"
}
```

หรือ

```json
{
    "event": "EXPENSE_APPROVAL_REQUIRED",
    "reference_id": "EX26000555"
}
```

ดังนั้น Laravel ตัวนี้สามารถพัฒนาเป็น

```text
Central Notification Service
```

ของบริษัทได้ในอนาคต

---

# 23. Implementation Plan

## Phase 1 — Database

สร้าง

```text
NOTI_RECIPIENT
NOTI_RECIPIENT_EVENT
NOTI_CONFIG
NOTI_TEMPLATE
NOTI_LOG
```

---

## Phase 2 — Laravel Backend

พัฒนา

```text
Notification API
Recipient API
Template API
Config API
Notification Log API
Mail Service
Queue Worker
```

---

## Phase 3 — Vue Admin

พัฒนา

```text
Manager Email Management
Notification Rules
Email Template
Notification History
Test Email
```

---

## Phase 4 — Legacy PHP Integration

เพิ่มการ Call

```text
POST /api/v1/notifications
```

หลังจาก Transaction `COMMIT` สำเร็จ

สถานะ (2026-09-23) — รายละเอียด as-built ดูข้อ 5

| Event | สถานะ |
|---|---|
| `BOOKING_CREATED` | ✅ ใช้งานจริงแล้ว |
| `BOOKING_UPDATED` | ⛔ ถอด hook ออกแล้ว รอเปิดใช้รอบถัดไป (ข้อ 5.5) |
| `BOOKING_CANCELLED` | ⛔ ถอด hook ออกแล้ว รอเปิดใช้รอบถัดไป (ข้อ 5.5) |
| `BOOKING_APPROVED` | ⛔ รอแก้ `RecipientResolver` ให้ส่งหาผู้จองก่อน (ข้อ 5.5) |
| `BOOKING_REJECTED` | ⛔ รอแก้ `RecipientResolver` ให้ส่งหาผู้จองก่อน (ข้อ 5.5) |

---

## Phase 5 — Testing

ทดสอบ — รอบนี้ทดสอบได้จริงแค่ `Booking Created` ที่เหลือรอต่อ hook ก่อน

```text
Booking Created            <- ทดสอบรอบนี้
Booking Updated            <- รอต่อ hook
Booking Approved           <- รอต่อ hook
Booking Rejected           <- รอต่อ hook
Booking Cancelled          <- รอต่อ hook
SMTP Error
Queue Retry
Invalid Recipient
Invalid Template
Duplicate Notification
```

---

## Phase 6 — Production

Deploy

```text
Laravel API
Queue Worker
Vue Admin
Oracle Tables
SMTP Configuration
API Key
IP Whitelist
HTTPS
```

---

# 24. Final Recommendation

แนะนำให้แยกระบบเป็น 3 ส่วนหลัก

```text
Legacy PHP
    =
Car Booking Business Logic

Laravel
    =
Notification API + Queue + Mail Service

Vue
    =
Notification Administration
```

แนวทางนี้ช่วยให้ระบบเดิมกระทบน้อย ดูแลรักษาง่าย และสามารถนำ Notification Service ไปใช้กับระบบอื่นภายในบริษัทได้ในอนาคต
