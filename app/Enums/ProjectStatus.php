<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Ready for files',
            self::Suspended => 'Suspended',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
