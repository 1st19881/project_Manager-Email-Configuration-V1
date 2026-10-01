<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrcRequestRecord extends Model
{
    protected $connection = 'intra';

    protected $table = 'TRC_REQUEST_RECORD_NEW';

    protected $primaryKey = 'doc_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'update_last_time' => 'datetime',
        ];
    }
}
