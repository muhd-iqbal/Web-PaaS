<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('runtime')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('file_count')
                    ->numeric(),
                TextEntry::make('storage_bytes')
                    ->label('Storage')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1_048_576, 2).' MB'),
                TextEntry::make('files_updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
