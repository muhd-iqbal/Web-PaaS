<?php

namespace App\Filament\Resources\BillingSettings\Pages;

use App\Filament\Resources\BillingSettings\BillingSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditBillingSetting extends EditRecord
{
    protected static string $resource = BillingSettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['secret_key'] = null;

        return $data;
    }
}
