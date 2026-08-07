<?php

namespace App\Filament\Resources\BillingSettings\Pages;

use App\Filament\Resources\BillingSettings\BillingSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBillingSetting extends CreateRecord
{
    protected static string $resource = BillingSettingResource::class;
}
