<?php

namespace App\Models;

use Database\Factories\ProjectFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    /** @use HasFactory<ProjectFileFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'path',
        'size_bytes',
        'mime_type',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
