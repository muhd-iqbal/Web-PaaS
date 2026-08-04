<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\BandwidthUsage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $bandwidthBytes = (int) BandwidthUsage::query()
            ->whereIn('project_id', $request->user()->projects()->select('id'))
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->sum('bytes_sent');

        return view('dashboard', [
            'projects' => $request->user()->projects()->latest()->limit(5)->get(),
            'projectCount' => $request->user()->projects()->count(),
            'liveProjects' => $request->user()->projects()->where('status', ProjectStatus::Active)->count(),
            'bandwidthBytes' => $bandwidthBytes,
        ]);
    }
}
