<?php

namespace App\Filament\Resources\BillingSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')->formatStateUsing(fn (): string => 'ToyyibPay'),
                TextColumn::make('environment')->badge(),
                TextColumn::make('category_code')->label('Category code'),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
