<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('monthly_price')
                    ->money('MYR')
                    ->sortable(),
                TextColumn::make('access_days')
                    ->label('Access days')
                    ->numeric(),
                TextColumn::make('website_limit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('storage_mb')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bandwidth_mb')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('database_mb')
                    ->label('Database MB')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_upload_mb')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_extracted_mb')
                    ->label('Extracted MB')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('max_file_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
