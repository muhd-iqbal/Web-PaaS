<?php

namespace App\Filament\Resources\AdminAlerts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AdminAlertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('severity')->badge(),
            TextEntry::make('type'),
            TextEntry::make('title'),
            TextEntry::make('project.name')->placeholder('System-wide'),
            TextEntry::make('user.email')->placeholder('-'),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('occurrences')->numeric(),
            TextEntry::make('first_detected_at')->dateTime(),
            TextEntry::make('last_detected_at')->dateTime(),
            TextEntry::make('resolved_at')->dateTime()->placeholder('Open'),
            TextEntry::make('context')->formatStateUsing(fn (?array $state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—')->columnSpanFull(),
        ]);
    }
}
