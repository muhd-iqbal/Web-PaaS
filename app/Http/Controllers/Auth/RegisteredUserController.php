<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, BillingManager $billing): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $plan = Plan::query()->whereKey($validated['plan_id'])->where('is_active', true)->firstOrFail();
        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        try {
            if ($plan->isFree()) {
                $billing->activateFreePlan($user, $plan);

                return redirect()->route('dashboard');
            }

            return redirect()->away($billing->checkoutUrl($user, $plan));
        } catch (BillingException $exception) {
            return redirect()->route('billing.index')->withErrors(['billing' => $exception->getMessage()]);
        }
    }
}
