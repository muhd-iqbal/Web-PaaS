<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->searchable(),
            TextColumn::make('user.email')->searchable(),
            TextColumn::make('plan.name'),
            TextColumn::make('provider')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('current_period_end')->dateTime()->placeholder('-')->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([ViewAction::make()]);
    }
}
