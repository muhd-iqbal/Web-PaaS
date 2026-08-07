<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\ToyyibPayBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToyyibPayGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_secret_is_encrypted_and_a_bill_uses_sen_and_fpx_settings(): void
    {
        $settings = $this->settings();
        $this->assertSame('test-secret-key', $settings->secret_key);
        $this->assertNotSame('test-secret-key', DB::table('billing_settings')->value('secret_key'));

        Http::fake([
            'https://dev.toyyibpay.com/index.php/api/createBill' => Http::response([['BillCode' => 'abc123']]),
        ]);
        [$user, $plan, $payment] = $this->payment();

        $url = app(ToyyibPayBillingGateway::class)->checkoutUrl(
            $user,
            $plan,
            $payment,
            'https://panel.example.test/billing/return',
            'https://panel.example.test/billing/callback',
        );

        $this->assertSame('https://dev.toyyibpay.com/abc123', $url);
        $this->assertSame('abc123', $payment->refresh()->provider_bill_code);
        Http::assertSent(function (Request $request): bool {
            return $request->isForm()
                && $request['userSecretKey'] === 'test-secret-key'
                && $request['billAmount'] === 500
                && $request['billPayorInfo'] === 0
                && $request['billPaymentChannel'] === 2
                && $request['billChargeToCustomer'] === 2;
        });
    }

    public function test_callback_signature_and_server_transaction_are_both_verified(): void
    {
        $this->settings();
        [, , $payment] = $this->payment(['provider_bill_code' => 'abc123']);
        $payload = [
            'status' => '1',
            'order_id' => $payment->external_reference,
            'billcode' => 'abc123',
            'refno' => 'TP123',
            'amount' => '5.00',
        ];
        $payload['hash'] = md5('test-secret-key1'.$payload['order_id'].'TP123ok');
        Http::fake([
            'https://dev.toyyibpay.com/index.php/api/getBillTransactions' => Http::response([[
                'billpaymentStatus' => '1',
                'billpaymentInvoiceNo' => 'TP123',
                'billpaymentAmount' => '5.00',
            ]]),
        ]);
        $gateway = app(ToyyibPayBillingGateway::class);

        $this->assertTrue($gateway->callbackIsAuthentic($payload));
        $this->assertTrue($gateway->paymentIsSuccessful($payment, $payload));

        $payload['hash'] = 'tampered';
        $this->assertFalse($gateway->callbackIsAuthentic($payload));
    }

    private function settings(): BillingSetting
    {
        return BillingSetting::query()->create([
            'provider' => 'toyyibpay',
            'enabled' => true,
            'environment' => 'sandbox',
            'secret_key' => 'test-secret-key',
            'category_code' => 'test-category',
            'payment_channel' => 2,
            'charge_to_customer' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array{User, Plan, Payment}
     */
    private function payment(array $overrides = []): array
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['name' => 'Student Plan!', 'monthly_price' => 5]);
        $payment = Payment::query()->create($overrides + [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'external_reference' => (string) Str::uuid(),
            'amount' => 5,
            'currency' => 'myr',
            'status' => 'pending',
        ]);

        return [$user, $plan, $payment];
    }
}
