<?php

namespace App\Http\Controllers;

use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Services\BillingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        return view('billing.index', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscriptions' => $request->user()->subscriptions()->with('plan')->latest()->get(),
            'currentSubscription' => $request->user()->currentSubscription(),
        ]);
    }

    public function subscribe(Request $request, Plan $plan, BillingManager $billing): RedirectResponse
    {
        if (! $plan->is_active) {
            abort(404);
        }

        try {
            if ($plan->isFree()) {
                $billing->activateFreePlan($request->user(), $plan);

                return to_route('dashboard')->with('status', 'Your free hosting plan is active.');
            }

            return redirect()->away($billing->checkoutUrl($request->user(), $plan));
        } catch (BillingException $exception) {
            throw ValidationException::withMessages(['billing' => $exception->getMessage()]);
        }
    }

    public function portal(Request $request, BillingManager $billing): RedirectResponse
    {
        try {
            return redirect()->away($billing->portalUrl($request->user()));
        } catch (BillingException $exception) {
            throw ValidationException::withMessages(['billing' => $exception->getMessage()]);
        }
    }

    public function success(): RedirectResponse
    {
        return to_route('billing.index')->with('status', 'Checkout completed. Your plan will activate as soon as Stripe confirms the subscription.');
    }
}
