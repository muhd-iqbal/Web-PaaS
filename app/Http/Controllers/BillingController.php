<?php

namespace App\Http\Controllers;

use App\Exceptions\BillingException;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\BillingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        return view('billing.index', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscriptions' => $request->user()->subscriptions()->with('plan')->latest()->get(),
            'currentSubscription' => $request->user()->currentSubscription(),
            'payments' => $request->user()->payments()->with('plan')->latest()->limit(20)->get(),
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

    public function return(Request $request, BillingManager $billing): RedirectResponse
    {
        $payment = Payment::query()
            ->whereBelongsTo($request->user())
            ->where('external_reference', (string) $request->query('order_id'))
            ->first();

        if (! $payment) {
            return to_route('billing.index')->withErrors(['billing' => 'The returned payment could not be found.']);
        }

        if ((string) $request->query('status_id') === '1' && $payment->status->value !== 'successful') {
            try {
                $billing->reconcilePayment($payment);
                $payment->refresh();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return to_route('billing.index')->with(
            'status',
            $payment->status->value === 'successful'
                ? 'Payment confirmed. Your hosting access is active.'
                : 'Payment received. ToyyibPay is still confirming it; this page will update after the callback arrives.',
        );
    }
}
