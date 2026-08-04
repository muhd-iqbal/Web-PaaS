<?php

namespace App\Http\Controllers;

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
            'draftCount' => $request->user()->projects()->where('status', 'draft')->count(),
        ]);
    }
}
