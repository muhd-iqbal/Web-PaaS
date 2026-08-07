<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->alphaDash()
                    ->maxLength(255)
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('monthly_price')
                    ->label('One-off price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('RM'),
                TextInput::make('currency')
                    ->required()
                    ->length(3)
                    ->default('myr'),
                TextInput::make('access_days')
                    ->label('Paid access days')
                    ->helperText('Each successful payment grants this many days. Renewals extend the current end date.')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(30),
                TextInput::make('trial_days')
                    ->label('Free trial days')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('website_limit')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('storage_mb')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('bandwidth_mb')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('database_mb')
                    ->label('Account database quota (MB)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('max_upload_mb')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('max_extracted_mb')
                    ->label('Maximum extracted size (MB)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(100),
                TextInput::make('max_file_count')
                    ->label('Maximum file count')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1000),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
