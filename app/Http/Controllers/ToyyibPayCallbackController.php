<?php

namespace App\Http\Controllers;

use App\Exceptions\BillingException;
use App\Services\BillingManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ToyyibPayCallbackController extends Controller
{
    public function __invoke(Request $request, BillingManager $billing): Response
    {
        try {
            $billing->handlePaymentCallback($request->all());
        } catch (BillingException $exception) {
            return response($exception->getMessage(), 400);
        }

        return response('OK');
    }
}
