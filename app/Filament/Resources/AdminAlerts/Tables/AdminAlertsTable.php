<?php

namespace App\Filament\Resources\AdminAlerts\Tables;

use App\Models\AdminAlert;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AdminAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_detected_at', 'desc')
            ->columns([
                TextColumn::make('severity')->badge()->sortable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('project.name')->placeholder('System-wide')->searchable(),
                TextColumn::make('user.email')->placeholder('-')->searchable(),
                TextColumn::make('occurrences')->numeric()->sortable(),
                TextColumn::make('last_detected_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('Open')->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')->options(['warning' => 'Warning', 'critical' => 'Critical']),
                TernaryFilter::make('open')
                    ->label('Open alerts')
                    ->nullable()
                    ->queries(
                        true: fn ($query) => $query->whereNull('resolved_at'),
                        false: fn ($query) => $query->whereNotNull('resolved_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AdminAlert $record): bool => $record->resolved_at === null)
                    ->action(fn (AdminAlert $record) => $record->update(['resolved_at' => now()])),
            ]);
    }
}
