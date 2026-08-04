<?php

namespace App\Filament\Resources\ProjectDatabases\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProjectDatabaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('project.user.name')->label('Owner'),
            TextEntry::make('project.name')->label('Project'),
            TextEntry::make('status')->badge(),
            TextEntry::make('database_name'),
            TextEntry::make('username'),
            TextEntry::make('host'),
            TextEntry::make('port')->numeric(),
            TextEntry::make('size_bytes')
                ->label('Usage')
                ->formatStateUsing(fn (int $state): string => number_format($state / 1_048_576, 2).' MB'),
            TextEntry::make('usage_checked_at')->dateTime()->placeholder('-'),
            TextEntry::make('provisioned_at')->dateTime()->placeholder('-'),
            TextEntry::make('last_error')->placeholder('-')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('updated_at')->dateTime(),
        ]);
    }
}
