<?php

namespace Tests\Feature;

use App\Contracts\BillingGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeBillingGateway;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private FakeBillingGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hosting.deployment.base_domain', 'sites.example.test');
        $this->gateway = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $this->gateway);
    }

    public function test_billing_page_describes_one_off_toyyibpay_access(): void
    {
        $user = User::factory()->create();
        Plan::factory()->create(['monthly_price' => 5, 'currency' => 'myr', 'access_days' => 30]);

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Prepaid hosting')
            ->assertSee('one-off payment')
            ->assertSee('RM 5.00');
    }

    public function test_a_free_trial_grants_access_once_and_expires(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create(['monthly_price' => 0, 'trial_days' => 14]);
        $billing = app(BillingManager::class);
        $subscription = $billing->activateFreePlan($user, $plan);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($user->refresh()->hasHostingAccess());

        $subscription->update(['current_period_end' => now()->subMinute()]);
        $this->assertSame(1, $billing->expireInternalTrials());
        $this->assertFalse($user->refresh()->hasHostingAccess());
        $this->assertNull($user->plan_id);

        $this->actingAs($user)->post(route('billing.subscribe', $plan))->assertSessionHasErrors('billing');
    }

    public function test_paid_checkout_creates_a_pending_payment_without_granting_access(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create(['monthly_price' => 5, 'access_days' => 30]);

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan))
            ->assertRedirect($this->gateway->checkout);

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('5.00', $payment->amount);
        $this->assertFalse($user->refresh()->hasHostingAccess());
        $this->assertSame($payment->id, $this->gateway->checkouts[0]['payment_id']);
    }

    public function test_paid_registration_redirects_to_one_off_checkout(): void
    {
        $plan = Plan::factory()->create(['monthly_price' => 5, 'access_days' => 30]);

        $this->post(route('register'), [
            'name' => 'Paid Student',
            'email' => 'paid@example.test',
            'plan_id' => $plan->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect($this->gateway->checkout);

        $user = User::query()->where('email', 'paid@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasHostingAccess());
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_verified_callback_activates_paid_access_idempotently(): void
    {
        [$user, $plan, $payment] = $this->pendingPayment();
        $payload = $this->callbackPayload($payment);

        $this->post(route('toyyibpay.callback'), $payload)->assertOk();
        $this->post(route('toyyibpay.callback'), $payload)->assertOk();

        $payment->refresh();
        $subscription = Subscription::query()->sole();
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame('TXN-100', $payment->provider_transaction_id);
        $this->assertSame($plan->id, $user->refresh()->plan_id);
        $this->assertTrue($user->hasHostingAccess());
        $this->assertSame('toyyibpay', $subscription->provider);
        $this->assertTrue($subscription->current_period_end->isBetween(now()->addDays(29), now()->addDays(31)));
    }

    public function test_verified_callback_accepts_a_total_that_includes_customer_payment_fees(): void
    {
        [$user, , $payment] = $this->pendingPayment();
        $payload = $this->callbackPayload($payment);
        $payload['amount'] = '6.00';

        $this->post(route('toyyibpay.callback'), $payload)->assertOk();

        $this->assertSame(PaymentStatus::Successful, $payment->refresh()->status);
        $this->assertTrue($user->refresh()->hasHostingAccess());
    }

    public function test_invalid_or_unverified_callbacks_never_grant_access(): void
    {
        [$user, , $payment] = $this->pendingPayment();
        $this->gateway->authentic = false;

        $this->post(route('toyyibpay.callback'), $this->callbackPayload($payment))->assertBadRequest();
        $this->assertFalse($user->refresh()->hasHostingAccess());

        $this->gateway->authentic = true;
        $this->gateway->successful = false;
        $this->post(route('toyyibpay.callback'), $this->callbackPayload($payment))->assertBadRequest();
        $this->assertFalse($user->refresh()->hasHostingAccess());
    }

    public function test_another_payment_extends_existing_access(): void
    {
        [$user, $plan, $first] = $this->pendingPayment();
        app(BillingManager::class)->handlePaymentCallback($this->callbackPayload($first));
        $firstEnd = $user->currentSubscription()->current_period_end;

        app(BillingManager::class)->checkoutUrl($user, $plan);
        $second = Payment::query()->latest('id')->firstOrFail();
        app(BillingManager::class)->handlePaymentCallback($this->callbackPayload($second, 'TXN-200'));

        $this->assertTrue($user->refresh()->currentSubscription()->current_period_end->equalTo($firstEnd->addDays(30)));
        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertSame(1, Subscription::query()->where('status', SubscriptionStatus::Active)->count());
    }

    public function test_successful_browser_return_reconciles_with_toyyibpay_without_trusting_the_query_string(): void
    {
        [$user, $plan, $payment] = $this->pendingPayment();

        $this->actingAs($user)->get(route('billing.return', [
            'status_id' => 1,
            'billcode' => $payment->provider_bill_code,
            'order_id' => $payment->external_reference,
        ]))->assertRedirect(route('billing.index'));

        $this->assertSame(PaymentStatus::Successful, $payment->refresh()->status);
        $this->assertSame('TXN-RECONCILED', $payment->provider_transaction_id);
        $this->assertSame($plan->id, $user->refresh()->plan_id);

        [$otherUser, , $otherPayment] = $this->pendingPayment();
        $this->gateway->successful = false;
        $this->actingAs($otherUser)->get(route('billing.return', [
            'status_id' => 1,
            'order_id' => $otherPayment->external_reference,
        ]))->assertRedirect(route('billing.index'));
        $this->assertSame(PaymentStatus::Pending, $otherPayment->refresh()->status);
        $this->assertFalse($otherUser->refresh()->hasHostingAccess());
    }

    public function test_scheduler_reconciles_a_missed_callback(): void
    {
        [$user, , $payment] = $this->pendingPayment();

        $this->artisan('billing:reconcile-toyyibpay')->assertSuccessful();

        $this->assertSame(PaymentStatus::Successful, $payment->refresh()->status);
        $this->assertTrue($user->refresh()->hasHostingAccess());
    }

    public function test_expired_prepaid_access_is_revoked(): void
    {
        [$user, , $payment] = $this->pendingPayment();
        app(BillingManager::class)->handlePaymentCallback($this->callbackPayload($payment));
        $user->currentSubscription()->update(['current_period_end' => now()->subMinute()]);

        $this->assertSame(1, app(BillingManager::class)->expireInternalTrials());
        $this->assertFalse($user->refresh()->hasHostingAccess());
        $this->assertNull($user->plan_id);
    }

    public function test_pending_and_failed_callbacks_do_not_activate_access(): void
    {
        [$user, , $payment] = $this->pendingPayment();
        $payload = $this->callbackPayload($payment);
        $payload['status'] = '2';

        $this->post(route('toyyibpay.callback'), $payload)->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertFalse($user->refresh()->hasHostingAccess());

        $payload['status'] = '3';
        $payload['reason'] = 'Declined';
        $this->post(route('toyyibpay.callback'), $payload)->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertFalse($user->refresh()->hasHostingAccess());
    }

    /** @return array{User, Plan, Payment} */
    private function pendingPayment(): array
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create(['monthly_price' => 5, 'access_days' => 30]);
        app(BillingManager::class)->checkoutUrl($user, $plan);

        return [$user, $plan, Payment::query()->latest('id')->firstOrFail()];
    }

    /** @return array<string, string> */
    private function callbackPayload(Payment $payment, string $reference = 'TXN-100'): array
    {
        return [
            'status' => '1',
            'order_id' => $payment->external_reference,
            'billcode' => $payment->provider_bill_code,
            'refno' => $reference,
            'amount' => $payment->amount,
            'hash' => 'fake-valid-hash',
        ];
    }
}
