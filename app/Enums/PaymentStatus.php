<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return str($this->value)->title()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Successful => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }
}
