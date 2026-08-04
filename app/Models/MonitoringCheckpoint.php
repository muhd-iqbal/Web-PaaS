<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringCheckpoint extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'file_identity', 'byte_offset', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
