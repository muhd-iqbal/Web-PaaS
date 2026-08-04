<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlertSeverity: string implements HasColor, HasLabel
{
    case Warning = 'warning';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return $this === self::Critical ? 'danger' : 'warning';
    }
}
