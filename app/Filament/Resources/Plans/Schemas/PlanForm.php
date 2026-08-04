<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$'),
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
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('max_upload_mb')
                    ->required()
                    ->numeric()
                    ->minValue(1),
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
