<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeploymentType: string implements HasLabel
{
    case Deploy = 'deploy';
    case Redeploy = 'redeploy';
    case Restart = 'restart';
    case Suspend = 'suspend';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deploy => 'Initial deployment',
            self::Redeploy => 'Redeployment',
            self::Restart => 'Container restart',
            self::Suspend => 'Suspension',
        };
    }
}
