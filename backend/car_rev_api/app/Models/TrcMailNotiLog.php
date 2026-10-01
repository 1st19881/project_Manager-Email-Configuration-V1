<?php

namespace App\Models;

use App\Casts\LegacyThaiText;
use Illuminate\Database\Eloquent\Model;

class TrcMailNotiLog extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_SENT = 'SENT';

    public const STATUS_FAILED = 'FAILED';

    protected $connection = 'intra';

    protected $table = 'TRC_MAIL_NOTI_LOG';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'log_id',
        'event_code',
        'reference_id',
        'mgr_select_id',
        'email_to',
        'email_cc',
        'email_subject',
        'status',
        'attempt_count',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'email_subject' => LegacyThaiText::class,
            'error_message' => LegacyThaiText::class,
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
