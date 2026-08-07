<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('plan.name'),
                TextColumn::make('amount')->money('MYR')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('provider_transaction_id')->label('Transaction')->placeholder('-')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }
}
