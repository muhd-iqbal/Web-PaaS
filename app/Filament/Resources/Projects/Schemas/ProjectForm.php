<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->maxLength(100)
                    ->required(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->alphaDash()
                    ->maxLength(63)
                    ->required(),
                Select::make('runtime')
                    ->options(ProjectRuntime::class)
                    ->required(),
                Select::make('status')
                    ->options(ProjectStatus::class)
                    ->default('draft')
                    ->required(),
            ]);
    }
}
