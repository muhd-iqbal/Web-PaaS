<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectRuntime: string implements HasLabel
{
    case Static = 'static';
    case Php = 'php';

    public function label(): string
    {
        return match ($this) {
            self::Static => 'Static HTML',
            self::Php => 'PHP 8.3',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
