<?php

namespace App\Filament\Resources\FeedbackSubmissions\Pages;

use App\Enums\FeedbackStatus;
use App\Filament\Resources\FeedbackSubmissions\FeedbackSubmissionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewFeedbackSubmission extends ViewRecord
{
    protected static string $resource = FeedbackSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')
                ->label('Mark reviewed')
                ->color('info')
                ->visible(fn (): bool => $this->record->status === FeedbackStatus::New)
                ->action(fn () => $this->record->update([
                    'status' => FeedbackStatus::Reviewed,
                    'reviewed_at' => now(),
                ])),
            Action::make('close')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== FeedbackStatus::Closed)
                ->action(fn () => $this->record->update([
                    'status' => FeedbackStatus::Closed,
                    'reviewed_at' => $this->record->reviewed_at ?? now(),
                ])),
        ];
    }
}
