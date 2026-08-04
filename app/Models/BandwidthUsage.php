<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthUsage extends Model
{
    protected $fillable = [
        'project_id', 'period_start', 'bytes_sent', 'bytes_received',
        'request_count', 'last_request_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'last_request_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
