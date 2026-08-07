<?php

namespace App\Http\Controllers;

use App\Exceptions\BillingException;
use App\Services\BillingManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ToyyibPayCallbackController extends Controller
{
    public function __invoke(Request $request, BillingManager $billing): Response
    {
        try {
            $billing->handlePaymentCallback($request->all());
        } catch (BillingException $exception) {
            Log::warning('ToyyibPay callback rejected.', [
                'message' => $exception->getMessage(),
                'billcode' => (string) $request->input('billcode'),
                'order_id' => (string) $request->input('order_id'),
                'refno' => (string) $request->input('refno'),
                'status' => (string) $request->input('status'),
            ]);

            return response($exception->getMessage(), 400);
        }

        return response('OK');
    }
}
