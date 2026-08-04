<?php

namespace App\Models;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'runtime',
        'status',
        'storage_bytes',
        'file_count',
        'files_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'runtime' => ProjectRuntime::class,
            'status' => ProjectStatus::class,
            'files_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(ProjectUpload::class);
    }

    public function storageDirectory(): string
    {
        return "users/{$this->user_id}/projects/{$this->id}";
    }
}
