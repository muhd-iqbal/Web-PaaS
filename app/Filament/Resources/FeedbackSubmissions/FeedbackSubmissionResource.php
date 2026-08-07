<?php

namespace App\Filament\Resources\FeedbackSubmissions;

use App\Filament\Resources\FeedbackSubmissions\Pages\ListFeedbackSubmissions;
use App\Filament\Resources\FeedbackSubmissions\Pages\ViewFeedbackSubmission;
use App\Filament\Resources\FeedbackSubmissions\Schemas\FeedbackSubmissionInfolist;
use App\Filament\Resources\FeedbackSubmissions\Tables\FeedbackSubmissionsTable;
use App\Models\FeedbackSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeedbackSubmissionResource extends Resource
{
    protected static ?string $model = FeedbackSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'User feedback';

    public static function getNavigationBadge(): ?string
    {
        $count = FeedbackSubmission::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return FeedbackSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedbackSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedbackSubmissions::route('/'),
            'view' => ViewFeedbackSubmission::route('/{record}'),
        ];
    }
}
