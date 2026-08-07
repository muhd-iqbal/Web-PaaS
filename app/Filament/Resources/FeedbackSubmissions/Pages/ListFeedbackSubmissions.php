<?php

namespace App\Filament\Resources\FeedbackSubmissions\Pages;

use App\Filament\Resources\FeedbackSubmissions\FeedbackSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedbackSubmissions extends ListRecords
{
    protected static string $resource = FeedbackSubmissionResource::class;
}
