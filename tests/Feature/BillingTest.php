<?php

namespace Tests\Feature;

use App\Contracts\BillingGateway;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\RunProjectDeployment;
use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeBillingGateway;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Hosting subscription');
    }

    private FakeBillingGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hosting.deployment.base_domain', 'sites.example.test');
        $this->gateway = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $this->gateway);
    }

    public function test_a_free_trial_grants_access_once_and_expires(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create(['monthly_price' => 0, 'trial_days' => 14]);
        $billing = app(BillingManager::class);
        $subscription = $billing->activateFreePlan($user, $plan);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($user->refresh()->hasHostingAccess());
        $this->assertSame($plan->id, $user->plan_id);

        $subscription->update(['current_period_end' => now()->subMinute()]);
        $this->assertSame(1, $billing->expireInternalTrials());
        $this->assertFalse($user->refresh()->hasHostingAccess());
        $this->assertNull($user->plan_id);

        $this->actingAs($user)->post(route('billing.subscribe', $plan))->assertSessionHasErrors('billing');
    }

    public function test_paid_checkout_does_not_grant_access_before_a_webhook(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create([
            'monthly_price' => 5,
            'stripe_price_id' => 'price_student',
        ]);

        $this->actingAs($user)
            ->post(route('billing.subscribe', $plan))
            ->assertRedirect($this->gateway->checkout);

        $this->assertFalse($user->refresh()->hasHostingAccess());
        $this->assertNull($user->plan_id);
        $this->assertSame([['user_id' => $user->id, 'plan_id' => $plan->id]], $this->gateway->checkouts);
    }

    public function test_paid_registration_creates_the_account_then_redirects_to_checkout(): void
    {
        $plan = Plan::factory()->create(['monthly_price' => 5, 'stripe_price_id' => 'price_student']);

        $this->post(route('register'), [
            'name' => 'Paid Student',
            'email' => 'paid@example.test',
            'plan_id' => $plan->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect($this->gateway->checkout);

        $user = User::query()->where('email', 'paid@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->plan_id);
        $this->assertFalse($user->hasHostingAccess());
    }

    public function test_checkout_webhook_activates_a_paid_plan_idempotently(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $plan = Plan::factory()->create(['monthly_price' => 5, 'stripe_price_id' => 'price_student']);
        $this->gateway->subscriptions['sub_student'] = $this->stripeSubscription($user, $plan, 'active');
        $event = [
            'id' => 'evt_checkout',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => (string) $user->id,
                'customer' => 'cus_student',
                'subscription' => 'sub_student',
            ]],
        ];
        $billing = app(BillingManager::class);

        $billing->handleWebhook($event);
        $billing->handleWebhook($event);

        $this->assertSame($plan->id, $user->refresh()->plan_id);
        $this->assertSame('cus_student', $user->stripe_customer_id);
        $this->assertTrue($user->hasHostingAccess());
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(1, BillingWebhookEvent::query()->whereNotNull('processed_at')->count());
    }

    public function test_terminal_subscription_status_revokes_access_and_suspends_websites(): void
    {
        Queue::fake();
        $plan = Plan::factory()->create(['stripe_price_id' => 'price_student']);
        $user = User::factory()->create(['plan_id' => null, 'stripe_customer_id' => 'cus_student']);
        Subscription::factory()->for($user)->for($plan)->create([
            'provider_subscription_id' => 'sub_student',
            'provider_price_id' => 'price_student',
        ]);
        $project = Project::factory()->for($user)->create([
            'status' => ProjectStatus::Active,
            'container_name' => 'hosting-project-1',
            'hostname' => 'site.sites.example.test',
            'url' => 'https://site.sites.example.test',
            'deployed_at' => now(),
        ]);
        app(BillingManager::class)->handleWebhook([
            'id' => 'evt_canceled',
            'type' => 'customer.subscription.deleted',
            'created' => 200,
            'data' => ['object' => $this->stripeSubscription($user, $plan, 'canceled')],
        ]);

        app(BillingManager::class)->handleWebhook([
            'id' => 'evt_stale_active',
            'type' => 'customer.subscription.updated',
            'created' => 100,
            'data' => ['object' => $this->stripeSubscription($user, $plan, 'active')],
        ]);

        $this->assertNull($user->refresh()->plan_id);
        $this->assertFalse($user->hasHostingAccess());
        $this->assertSame(SubscriptionStatus::Canceled, $user->subscriptions()->where('provider', 'stripe')->firstOrFail()->status);
        $this->assertSame(ProjectStatus::Deploying, $project->refresh()->status);
        Queue::assertPushed(RunProjectDeployment::class);
    }

    public function test_a_plan_downgrade_suspends_only_projects_over_the_new_limit(): void
    {
        Queue::fake();
        $plan = Plan::factory()->create(['website_limit' => 1, 'stripe_price_id' => 'price_small']);
        $user = User::factory()->create(['plan_id' => null, 'stripe_customer_id' => 'cus_user']);
        $first = Project::factory()->for($user)->create(['status' => ProjectStatus::Active, 'container_name' => 'hosting-project-1']);
        $second = Project::factory()->for($user)->create(['status' => ProjectStatus::Active, 'container_name' => 'hosting-project-2']);

        app(BillingManager::class)->handleWebhook([
            'id' => 'evt_downgrade',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $this->stripeSubscription($user, $plan, 'active')],
        ]);

        $this->assertSame(ProjectStatus::Active, $first->refresh()->status);
        $this->assertSame(ProjectStatus::Deploying, $second->refresh()->status);
        $this->assertTrue($user->canUseProject($first));
        $this->assertFalse($user->canUseProject($second));
    }

    public function test_invalid_webhook_signatures_are_rejected_without_csrf_errors(): void
    {
        $this->gateway->invalidSignature = true;

        $this->postJson(route('stripe.webhook'), [])->assertBadRequest();
    }

    public function test_an_account_without_entitlement_cannot_mutate_hosting_but_can_delete_it(): void
    {
        $user = User::factory()->create(['plan_id' => null]);
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'Blocked change',
            'slug' => $project->slug,
            'runtime' => $project->runtime->value,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    /** @return array<string, mixed> */
    private function stripeSubscription(User $user, Plan $plan, string $status): array
    {
        return [
            'id' => 'sub_student',
            'customer' => $user->stripe_customer_id ?: 'cus_student',
            'status' => $status,
            'metadata' => ['user_id' => (string) $user->id],
            'items' => ['data' => [[
                'price' => ['id' => $plan->stripe_price_id],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ]]],
            'cancel_at_period_end' => false,
        ];
    }
}
