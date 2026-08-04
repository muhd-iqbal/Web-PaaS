<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Contracts\ContainerRuntime;
use App\Enums\ProjectStatus;
use App\Exceptions\DeploymentException;
use App\Models\Project;
use App\Services\DeploymentManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('url')
                    ->url(fn (Project $record): ?string => $record->url, shouldOpenInNewTab: true)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('runtime')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('file_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('storage_bytes')
                    ->label('Storage')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1_048_576, 2).' MB')
                    ->sortable(),
                TextColumn::make('hostedDatabase.size_bytes')
                    ->label('Database')
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 1_048_576, 2).' MB')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latestResourceSnapshot.health')
                    ->label('Health')
                    ->placeholder('-'),
                TextColumn::make('latestResourceSnapshot.cpu_percent')
                    ->label('CPU')
                    ->suffix('%')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('files_updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Action::make('redeploy')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (Project $record): bool => $record->file_count > 0 && $record->status !== ProjectStatus::Deploying && $record->user->canUseProject($record))
                    ->requiresConfirmation()
                    ->action(fn (Project $record) => self::queueAction($record, 'deploy')),
                Action::make('restart')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Project $record): bool => filled($record->container_name) && $record->status === ProjectStatus::Active && $record->user->canUseProject($record))
                    ->requiresConfirmation()
                    ->action(fn (Project $record) => self::queueAction($record, 'restart')),
                Action::make('suspend')
                    ->color('warning')
                    ->icon('heroicon-o-pause')
                    ->visible(fn (Project $record): bool => filled($record->container_name) && $record->status === ProjectStatus::Active)
                    ->requiresConfirmation()
                    ->action(fn (Project $record) => self::queueAction($record, 'suspend')),
                Action::make('logs')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (Project $record): bool => filled($record->container_name))
                    ->modalHeading(fn (Project $record): string => "Container logs: {$record->name}")
                    ->modalContent(function (Project $record) {
                        try {
                            $logs = app(ContainerRuntime::class)->logs($record, config('hosting.deployment.log_lines'));
                        } catch (\Throwable $exception) {
                            $logs = 'Logs unavailable: '.$exception->getMessage();
                        }

                        return view('filament.container-logs', compact('logs'));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                DeleteAction::make()
                    ->visible(fn (Project $record): bool => $record->status !== ProjectStatus::Deploying),
            ]);
    }

    private static function queueAction(Project $project, string $operation): void
    {
        try {
            $manager = app(DeploymentManager::class);
            match ($operation) {
                'deploy' => $manager->queueDeploy($project, auth()->user()),
                'restart' => $manager->queueRestart($project, auth()->user()),
                'suspend' => $manager->queueSuspend($project, auth()->user()),
            };

            Notification::make()->title(ucfirst($operation).' queued')->success()->send();
        } catch (DeploymentException $exception) {
            Notification::make()->title('Operation could not be queued')->body($exception->getMessage())->danger()->send();
        }
    }
}
