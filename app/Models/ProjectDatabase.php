<?php

namespace App\Models;

use App\Enums\ProjectDatabaseStatus;
use Database\Factories\ProjectDatabaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDatabase extends Model
{
    /** @use HasFactory<ProjectDatabaseFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'status',
        'database_name',
        'username',
        'password',
        'host',
        'port',
        'size_bytes',
        'usage_checked_at',
        'provisioned_at',
        'last_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'status' => ProjectDatabaseStatus::class,
            'password' => 'encrypted',
            'usage_checked_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
