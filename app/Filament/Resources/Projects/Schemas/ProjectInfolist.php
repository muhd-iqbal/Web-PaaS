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
                TextEntry::make('url')
                    ->url(fn ($record): ?string => $record->url)
                    ->openUrlInNewTab()
                    ->placeholder('-'),
                TextEntry::make('container_name')
                    ->placeholder('-'),
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
                TextEntry::make('deployed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('last_deployment_error')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('hostedDatabase.status')
                    ->label('Database status')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('hostedDatabase.database_name')
                    ->label('Database name')
                    ->placeholder('-'),
                TextEntry::make('hostedDatabase.size_bytes')
                    ->label('Database size')
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 1_048_576, 2).' MB')
                    ->placeholder('-'),
                TextEntry::make('latestResourceSnapshot.health')
                    ->label('Container health')
                    ->placeholder('-'),
                TextEntry::make('latestResourceSnapshot.cpu_percent')
                    ->label('Latest CPU')
                    ->suffix('%')
                    ->placeholder('-'),
                TextEntry::make('latestResourceSnapshot.memory_usage_bytes')
                    ->label('Latest memory')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : number_format($state / 1_048_576, 2).' MB')
                    ->placeholder('-'),
                TextEntry::make('latestResourceSnapshot.sampled_at')
                    ->label('Last monitored')
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
