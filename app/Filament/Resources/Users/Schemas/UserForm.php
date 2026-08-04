<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(''))
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('is_admin')
                    ->default(false)
                    ->required(),
            ]);
    }
}
