<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Exceptions\BillingException;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ToyyibPayBillingGateway implements BillingGateway
{
    public function checkoutUrl(User $user, Plan $plan, Payment $payment, string $returnUrl, string $callbackUrl): string
    {
        $settings = $this->settings();

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($settings->baseUrl().'/index.php/api/createBill', [
                    'userSecretKey' => $settings->secret_key,
                    'categoryCode' => $settings->category_code,
                    'billName' => $this->apiText($plan->name, 30),
                    'billDescription' => $this->apiText($plan->description ?: $plan->name.' hosting access', 100),
                    'billPriceSetting' => 1,
                    'billPayorInfo' => 0,
                    'billAmount' => (int) round((float) $payment->amount * 100),
                    'billReturnUrl' => $returnUrl,
                    'billCallbackUrl' => $callbackUrl,
                    'billExternalReferenceNo' => $payment->external_reference,
                    'billTo' => $this->apiText($user->name, 100),
                    'billEmail' => $user->email,
                    'billPhone' => '',
                    'billPaymentChannel' => $settings->payment_channel,
                    'billChargeToCustomer' => $settings->charge_to_customer ? 1 : 2,
                    'billExpiryDays' => 1,
                    'billExpiryDate' => now()->addDay()->format('d-m-Y'),
                ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new BillingException('ToyyibPay could not create the payment. Please try again.', previous: $exception);
        }

        $billCode = data_get($response->json(), '0.BillCode');

        if (! is_string($billCode) || $billCode === '') {
            throw new BillingException('ToyyibPay did not return a valid bill code. Check the billing configuration.');
        }

        $payment->update(['provider_bill_code' => $billCode]);

        return $settings->baseUrl().'/'.$billCode;
    }

    public function callbackIsAuthentic(array $payload): bool
    {
        $settings = $this->settings();
        $provided = strtolower((string) ($payload['hash'] ?? ''));
        $expected = md5(
            $settings->secret_key
            .($payload['status'] ?? '')
            .($payload['order_id'] ?? '')
            .($payload['refno'] ?? '')
            .'ok'
        );

        return $provided !== '' && hash_equals($expected, $provided);
    }

    public function paymentIsSuccessful(Payment $payment, array $payload): bool
    {
        if ((string) ($payload['status'] ?? '') !== '1'
            || ! $this->amountMatches($payment, $payload['amount'] ?? null)) {
            return false;
        }

        $settings = $this->settings();

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($settings->baseUrl().'/index.php/api/getBillTransactions', [
                    'billCode' => $payment->provider_bill_code,
                    'billpaymentStatus' => 1,
                ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new BillingException('ToyyibPay payment verification is temporarily unavailable.', previous: $exception);
        }

        $transactions = $response->json();

        if (! is_array($transactions)) {
            return false;
        }

        foreach ($transactions as $transaction) {
            if (! is_array($transaction) || (string) ($transaction['billpaymentStatus'] ?? '') !== '1') {
                continue;
            }

            $transactionReference = (string) ($transaction['billpaymentInvoiceNo'] ?? '');
            $callbackReference = (string) ($payload['refno'] ?? '');

            if ($transactionReference !== '' && $callbackReference !== '' && ! hash_equals($transactionReference, $callbackReference)) {
                continue;
            }

            if (isset($transaction['billpaymentAmount'])
                && ! $this->amountMatches($payment, $transaction['billpaymentAmount'])) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function settings(): BillingSetting
    {
        $settings = BillingSetting::toyyibPay();

        if (! $settings?->enabled || blank($settings->secret_key) || blank($settings->category_code)) {
            throw new BillingException('ToyyibPay checkout is not configured or enabled.');
        }

        return $settings;
    }

    private function apiText(string $value, int $limit): string
    {
        $value = preg_replace('/[^A-Za-z0-9 _]/', ' ', $value) ?: 'Hosting';

        return Str::limit(preg_replace('/\s+/', ' ', trim($value)) ?: 'Hosting', $limit, '');
    }

    private function amountMatches(Payment $payment, mixed $amount): bool
    {
        return is_numeric($amount)
            && abs((float) $payment->amount - (float) $amount) < 0.005;
    }
}
