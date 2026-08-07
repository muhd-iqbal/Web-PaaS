<?php

namespace App\Filament\Resources\BillingSettings;

use App\Filament\Resources\BillingSettings\Pages\CreateBillingSetting;
use App\Filament\Resources\BillingSettings\Pages\EditBillingSetting;
use App\Filament\Resources\BillingSettings\Pages\ListBillingSettings;
use App\Filament\Resources\BillingSettings\Schemas\BillingSettingForm;
use App\Filament\Resources\BillingSettings\Tables\BillingSettingsTable;
use App\Models\BillingSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BillingSettingResource extends Resource
{
    protected static ?string $model = BillingSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Payment settings';

    protected static ?string $modelLabel = 'ToyyibPay configuration';

    public static function form(Schema $schema): Schema
    {
        return BillingSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingSettingsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return ! BillingSetting::query()->where('provider', 'toyyibpay')->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingSettings::route('/'),
            'create' => CreateBillingSetting::route('/create'),
            'edit' => EditBillingSetting::route('/{record}/edit'),
        ];
    }
}
