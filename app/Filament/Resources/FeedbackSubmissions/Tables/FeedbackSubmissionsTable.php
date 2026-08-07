<?php

namespace App\Filament\Resources\FeedbackSubmissions\Tables;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\FeedbackSubmission;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeedbackSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('subject')->searchable()->wrap(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(FeedbackStatus::cases())->mapWithKeys(fn (FeedbackStatus $status): array => [$status->value => $status->getLabel()])->all()),
                SelectFilter::make('type')->options(collect(FeedbackType::cases())->mapWithKeys(fn (FeedbackType $type): array => [$type->value => $type->getLabel()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('review')
                    ->label('Mark reviewed')
                    ->color('info')
                    ->visible(fn (FeedbackSubmission $record): bool => $record->status === FeedbackStatus::New)
                    ->action(fn (FeedbackSubmission $record) => $record->update([
                        'status' => FeedbackStatus::Reviewed,
                        'reviewed_at' => now(),
                    ])),
                Action::make('close')
                    ->requiresConfirmation()
                    ->visible(fn (FeedbackSubmission $record): bool => $record->status !== FeedbackStatus::Closed)
                    ->action(fn (FeedbackSubmission $record) => $record->update([
                        'status' => FeedbackStatus::Closed,
                        'reviewed_at' => $record->reviewed_at ?? now(),
                    ])),
            ]);
    }
}
