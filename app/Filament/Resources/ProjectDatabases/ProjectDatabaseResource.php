<?php

namespace App\Filament\Resources\ProjectDatabases;

use App\Filament\Resources\ProjectDatabases\Pages\ListProjectDatabases;
use App\Filament\Resources\ProjectDatabases\Pages\ViewProjectDatabase;
use App\Filament\Resources\ProjectDatabases\Schemas\ProjectDatabaseInfolist;
use App\Filament\Resources\ProjectDatabases\Tables\ProjectDatabasesTable;
use App\Models\ProjectDatabase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectDatabaseResource extends Resource
{
    protected static ?string $model = ProjectDatabase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function infolist(Schema $schema): Schema
    {
        return ProjectDatabaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectDatabasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectDatabases::route('/'),
            'view' => ViewProjectDatabase::route('/{record}'),
        ];
    }
}
