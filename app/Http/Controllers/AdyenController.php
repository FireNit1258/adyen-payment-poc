<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdyenController extends Controller
{
    private $adyenApiKey;
    private $adyenMerchantAccount;

    public function __construct()
    {
        $this->adyenApiKey = env('ADYEN_API_KEY'); // Set your Adyen API key in .env
        $this->adyenMerchantAccount = env('ADYEN_MERCHANT_ACCOUNT'); // Set your Adyen Merchant Account in .env
    }

    // Fetch available payment methods
    public function getPaymentMethods(Request $request)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->adyenApiKey,
        ])->post('https://checkout-test.adyen.com/v70/paymentMethods', [
            'merchantAccount' => $this->adyenMerchantAccount,
            'countryCode' => 'GB', // Adjust for your region
            'amount' => [
                'value' => 1500, // Amount in minor units (e.g., 1500 = £15.00)
                'currency' => 'GBP', // Adjust currency
            ],
            'channel' => 'Web',
        ]);

        return $response->json();
    }

    // Process payment
    public function processPayment(Request $request)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->adyenApiKey,
        ])->post('https://checkout-test.adyen.com/v70/payments', [
            'merchantAccount' => $this->adyenMerchantAccount,
            'amount' => $request->amount ?? [
                'value' => 1500,
                'currency' => 'GBP',
            ],
            'reference' => 'Test_Payment_' . time(),
            'paymentMethod' => $request->paymentMethod,
            'returnUrl' => url('/payment-result'),
            'browserInfo' => $request->browserInfo,
        ]);

        return $response->json();
    }

    // Handle additional details (e.g., 3D Secure)
    public function submitAdditionalDetails(Request $request)
    {
        try {
            $response = $this->adyenService->submitPaymentDetails($request->input('details'));

            if (!empty($response['resultCode']) && $response['resultCode'] === 'Authorised') {
                return response()->json([
                    'resultCode' => 'Authorised',
                    'message' => 'Payment successful',
                    'redirectUrl' => route('payment.thankYou'),
                ]);
            }

            return response()->json([
                'resultCode' => $response['resultCode'] ?? 'Failed',
                'message' => 'Payment failed',
                'redirectUrl' => route('payment.failed'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'resultCode' => 'Error',
                'message' => $e->getMessage(),
                'redirectUrl' => route('payment.failed'),
            ], 500);
        }
    }

}
