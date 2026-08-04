<?php

namespace App\Filament\Resources\AdminAlerts;

use App\Filament\Resources\AdminAlerts\Pages\ListAdminAlerts;
use App\Filament\Resources\AdminAlerts\Pages\ViewAdminAlert;
use App\Filament\Resources\AdminAlerts\Schemas\AdminAlertInfolist;
use App\Filament\Resources\AdminAlerts\Tables\AdminAlertsTable;
use App\Models\AdminAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdminAlertResource extends Resource
{
    protected static ?string $model = AdminAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Monitoring alerts';

    public static function getNavigationBadge(): ?string
    {
        $count = AdminAlert::query()->whereNull('resolved_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdminAlertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminAlertsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAlerts::route('/'),
            'view' => ViewAdminAlert::route('/{record}'),
        ];
    }
}
