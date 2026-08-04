<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('monthly_price')
                    ->money(),
                TextEntry::make('website_limit')
                    ->numeric(),
                TextEntry::make('storage_mb')
                    ->numeric(),
                TextEntry::make('bandwidth_mb')
                    ->numeric(),
                TextEntry::make('database_mb')
                    ->label('Account database quota (MB)')
                    ->numeric(),
                TextEntry::make('max_upload_mb')
                    ->numeric(),
                TextEntry::make('max_extracted_mb')
                    ->label('Maximum extracted size (MB)')
                    ->numeric(),
                TextEntry::make('max_file_count')
                    ->label('Maximum file count')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('sort_order')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
