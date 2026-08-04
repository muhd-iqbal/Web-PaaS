<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectResourceSnapshot extends Model
{
    protected $fillable = [
        'project_id', 'sampled_at', 'is_running', 'health', 'cpu_percent',
        'memory_percent', 'memory_usage_bytes', 'memory_limit_bytes',
        'process_count', 'restart_count', 'oom_killed', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'is_running' => 'boolean',
            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'oom_killed' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
