<?php

namespace App\Filament\Resources\BillingSettings\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BillingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('provider')->default('toyyibpay'),
            Toggle::make('enabled')
                ->helperText('Checkout is unavailable until this is enabled.')
                ->default(false),
            Select::make('environment')
                ->options([
                    'sandbox' => 'Sandbox (dev.toyyibpay.com)',
                    'production' => 'Production (toyyibpay.com)',
                ])
                ->required()
                ->default('sandbox'),
            TextInput::make('secret_key')
                ->label('User secret key')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Encrypted in the database. Leave blank while editing to keep the current key.'),
            TextInput::make('category_code')
                ->label('Category code')
                ->required()
                ->maxLength(100),
            Select::make('payment_channel')
                ->label('Accepted payment methods')
                ->options([
                    0 => 'FPX only',
                    1 => 'Card only',
                    2 => 'FPX and card',
                ])
                ->required()
                ->default(2),
            Toggle::make('charge_to_customer')
                ->label('Charge ToyyibPay fee to customer')
                ->default(false),
        ]);
    }
}
