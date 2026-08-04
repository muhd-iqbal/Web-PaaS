<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel
{
    case Draft = 'draft';
    case ChangesPending = 'changes_pending';
    case Deploying = 'deploying';
    case Active = 'active';
    case Failed = 'failed';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Ready to deploy',
            self::ChangesPending => 'Changes need deployment',
            self::Deploying => 'Deploying',
            self::Active => 'Live',
            self::Failed => 'Deployment failed',
            self::Suspended => 'Suspended',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
