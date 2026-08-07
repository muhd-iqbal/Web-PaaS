<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'status' => FeedbackStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
