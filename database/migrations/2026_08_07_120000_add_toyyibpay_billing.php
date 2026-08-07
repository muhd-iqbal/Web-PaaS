<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('access_days')->default(30)->after('trial_days');
        });

        Schema::create('billing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('sandbox');
            $table->text('secret_key');
            $table->string('category_code', 100);
            $table->unsignedTinyInteger('payment_channel')->default(2);
            $table->boolean('charge_to_customer')->default(false);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->default('toyyibpay');
            $table->uuid('external_reference')->unique();
            $table->string('provider_bill_code')->nullable()->unique();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('myr');
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique(['stripe_price_id']);
            $table->dropColumn('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('billing_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->string('stripe_customer_id')->nullable()->unique()->after('plan_id');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('access_days');
            $table->string('stripe_price_id')->nullable()->unique()->after('monthly_price');
        });
    }
};
