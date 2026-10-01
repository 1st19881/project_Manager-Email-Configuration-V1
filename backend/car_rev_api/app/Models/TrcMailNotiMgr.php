<?php

namespace App\Models;

use App\Casts\LegacyThaiText;
use Illuminate\Database\Eloquent\Model;

class TrcMailNotiMgr extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $connection = 'intra';

    protected $table = 'TRC_MAIL_NOTI_MGR';

    protected $primaryKey = 'noti_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'noti_id',
        'employee_code',
        'plant',
        'position_name',
        'department_name',
        'name_th',
        'name_en',
        'email',
        'active_flag',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'position_name' => LegacyThaiText::class,
            'department_name' => LegacyThaiText::class,
            'name_th' => LegacyThaiText::class,
            'name_en' => LegacyThaiText::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
