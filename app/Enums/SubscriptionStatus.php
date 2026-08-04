<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Paused = 'paused';

    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    public function getLabel(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active, self::Trialing => 'success',
            self::PastDue => 'warning',
            self::Incomplete, self::Paused => 'info',
            default => 'danger',
        };
    }
}
