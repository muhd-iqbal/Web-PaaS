<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name'),
            TextEntry::make('user.email'),
            TextEntry::make('plan.name'),
            TextEntry::make('provider'),
            TextEntry::make('provider_subscription_id')->placeholder('-'),
            TextEntry::make('provider_price_id')->placeholder('-'),
            TextEntry::make('status')->badge(),
            TextEntry::make('provider_event_created_at')->label('Last provider event')->dateTime()->placeholder('-'),
            TextEntry::make('current_period_start')->dateTime()->placeholder('-'),
            TextEntry::make('current_period_end')->dateTime()->placeholder('-'),
            TextEntry::make('cancel_at_period_end')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
            TextEntry::make('ended_at')->dateTime()->placeholder('-'),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('updated_at')->dateTime(),
        ]);
    }
}
