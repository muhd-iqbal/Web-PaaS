<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name'),
            TextEntry::make('user.email'),
            TextEntry::make('plan.name'),
            TextEntry::make('amount')->money('MYR'),
            TextEntry::make('status')->badge(),
            TextEntry::make('external_reference'),
            TextEntry::make('provider_bill_code')->placeholder('-'),
            TextEntry::make('provider_transaction_id')->placeholder('-'),
            TextEntry::make('failure_reason')->placeholder('-')->columnSpanFull(),
            TextEntry::make('paid_at')->dateTime()->placeholder('-'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
