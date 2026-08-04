<?php

namespace App\Http\Controllers;

use App\Contracts\BillingGateway;
use App\Exceptions\BillingException;
use App\Services\BillingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, BillingGateway $gateway, BillingManager $billing): JsonResponse
    {
        try {
            $event = $gateway->parseWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
            $billing->handleWebhook($event);
        } catch (BillingException) {
            return response()->json(['message' => 'Invalid or unprocessable webhook.'], 400);
        }

        return response()->json(['received' => true]);
    }
}
