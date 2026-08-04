<?php

namespace App\Models;

use Database\Factories\ProjectUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUpload extends Model
{
    /** @use HasFactory<ProjectUploadFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'original_name',
        'archive_size_bytes',
        'extracted_size_bytes',
        'file_count',
        'sha256',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
