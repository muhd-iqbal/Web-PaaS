<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Http\Requests\StoreFeedbackRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        return view('feedback.index', [
            'types' => FeedbackType::cases(),
            'submissions' => $request->user()->feedbackSubmissions()->latest()->paginate(10),
        ]);
    }

    public function store(StoreFeedbackRequest $request): RedirectResponse
    {
        $request->user()->feedbackSubmissions()->create(
            $request->safe()->only(['type', 'subject', 'message']) + ['status' => FeedbackStatus::New],
        );

        return to_route('feedback.index')->with('status', 'Thank you. Your feedback has been submitted for review.');
    }
}
