<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeedbackType: string implements HasLabel
{
    case Suggestion = 'suggestion';
    case Problem = 'problem';
    case General = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suggestion => 'Suggestion',
            self::Problem => 'Problem report',
            self::General => 'General feedback',
        };
    }
}
