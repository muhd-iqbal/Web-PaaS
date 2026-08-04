<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'projects' => $request->user()->projects()->latest()->limit(5)->get(),
            'projectCount' => $request->user()->projects()->count(),
            'liveProjects' => $request->user()->projects()->where('status', ProjectStatus::Active)->count(),
        ]);
    }
}
