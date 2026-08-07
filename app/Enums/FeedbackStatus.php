<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeedbackStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return str($this->value)->title()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Reviewed => 'info',
            self::Closed => 'gray',
        };
    }
}
