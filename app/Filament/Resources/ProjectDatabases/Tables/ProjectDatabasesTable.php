<?php

namespace App\Filament\Resources\ProjectDatabases\Tables;

use App\Models\ProjectDatabase;
use App\Services\ProjectDatabaseManager;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectDatabasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.user.name')->label('Owner')->searchable(),
                TextColumn::make('project.name')->label('Project')->searchable(),
                TextColumn::make('database_name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('size_bytes')
                    ->label('Usage')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1_048_576, 2).' MB')
                    ->sortable(),
                TextColumn::make('usage_checked_at')->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refreshUsage')
                    ->label('Refresh usage')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (ProjectDatabase $record): void {
                        try {
                            app(ProjectDatabaseManager::class)->refreshUsageForUser($record->project->user);
                            Notification::make()->title('Database usage refreshed')->success()->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->title('Usage refresh failed')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
