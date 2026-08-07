<?php

namespace App\Filament\Resources\FeedbackSubmissions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FeedbackSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')->badge(),
            TextEntry::make('type')->badge(),
            TextEntry::make('subject')->columnSpanFull(),
            TextEntry::make('user.name'),
            TextEntry::make('user.email'),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('reviewed_at')->dateTime()->placeholder('Not reviewed'),
        ]);
    }
}
