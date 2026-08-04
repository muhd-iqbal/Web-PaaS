<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->unique()->after('monthly_price');
            $table->char('currency', 3)->default('usd')->after('stripe_price_id');
            $table->unsignedSmallInteger('trial_days')->default(0)->after('currency');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique()->after('plan_id');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('stripe');
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('provider_price_id')->nullable();
            $table->string('status', 30);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['provider', 'provider_price_id']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('users')->whereNotNull('plan_id')->orderBy('id')->each(function ($user) use ($now): void {
            DB::table('subscriptions')->insert([
                'user_id' => $user->id,
                'plan_id' => $user->plan_id,
                'provider' => 'legacy',
                'status' => 'active',
                'current_period_start' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['stripe_price_id']);
            $table->dropColumn(['stripe_price_id', 'currency', 'trial_days']);
        });
    }
};
