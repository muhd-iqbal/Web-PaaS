<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectDatabaseStatus: string implements HasColor, HasLabel
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case QuotaExceeded = 'quota_exceeded';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::QuotaExceeded => 'Quota exceeded',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Provisioning => 'info',
            self::Active => 'success',
            self::QuotaExceeded => 'warning',
            self::Failed => 'danger',
        };
    }
}
