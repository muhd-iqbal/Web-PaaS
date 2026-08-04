<?php

namespace App\Filament\Resources\AdminAlerts\Pages;

use App\Filament\Resources\AdminAlerts\AdminAlertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminAlert extends ViewRecord
{
    protected static string $resource = AdminAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->resolved_at === null)
                ->action(fn () => $this->record->update(['resolved_at' => now()])),
        ];
    }
}
