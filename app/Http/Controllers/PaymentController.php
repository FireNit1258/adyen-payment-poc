<?php

namespace App\Http\Controllers;

use App\Services\AdyenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $adyenService;

    public function __construct(AdyenService $adyenService)
    {
        $this->adyenService = $adyenService;
    }

    // Display the Checkout Page
    public function checkoutPage()
    {
        return view('payment.checkout');
    }

    // Process the Payment
    public function processPayment(Request $request)
    {
        try {
            // Base payment details
            $paymentDetails = [
                'amount' => [
                    'currency' => 'GBP',
                    'value' => 1500,
                ],
                'reference' => 'ORDER-' . time(),
                'merchantAccount' => env('ADYEN_MERCHANT_ACCOUNT'),
                'returnUrl' => route('payment.handleResponse'),
                'shopperReference' => 'shopper-' . (auth()->id() ?? 'guest'),
                'shopperEmail' => auth()->user()->email ?? 'guest@domain.com',
                'countryCode' => 'GB',
                'billingAddress' => [
                    'street' => 'Test Street',
                    'houseNumberOrName' => '123',
                    'postalCode' => '12345',
                    'city' => 'Test City',
                    'country' => 'GB',
                ],
                'lineItems' => [
                    [
                        'id' => 'item1',
                        'description' => 'Test Product 1',
                        'quantity' => 1,
                        'amountIncludingTax' => 1500,
                        'taxPercentage' => 2000,
                    ],
                ],
            ];

            // Handle specific payment method data
            $paymentMethod = $request->input('paymentMethod');
            $paymentDetails['paymentMethod'] = $paymentMethod;

            switch ($paymentMethod['type']) {
                case 'scheme':
                    $paymentDetails['paymentMethod']['encryptedCardNumber'] = $paymentMethod['encryptedCardNumber'];
                    $paymentDetails['paymentMethod']['encryptedExpiryMonth'] = $paymentMethod['encryptedExpiryMonth'];
                    $paymentDetails['paymentMethod']['encryptedExpiryYear'] = $paymentMethod['encryptedExpiryYear'];
                    $paymentDetails['paymentMethod']['encryptedSecurityCode'] = $paymentMethod['encryptedSecurityCode'];
                    break;

                case 'paypal':
                    // For PayPal, no card details needed, just the payment method type
                    $paymentDetails['paymentMethod'] = [
                        'type' => 'paypal',
                    ];
                    break;

                case 'klarna':
                    $paymentDetails['countryCode'] = 'GB';
                    break;

                case 'paysafecard':
                    $paymentDetails['paymentMethod'] = [
                        'type' => 'paysafecard'
                    ];
                    break;

                case 'directdebit_GB':
                    $paymentDetails['paymentMethod'] = [
                        'type' => $paymentMethod['type'] ,
                        'holderName' => $paymentMethod['holderName'],
                        'bankAccountNumber' => $paymentMethod['bankAccountNumber'],
                        'bankLocationId' => $paymentMethod['bankLocationId'],
                        'checkoutAttemptId' => $paymentMethod['checkoutAttemptId'],

                    ];
                    break;

                case 'klarna_account':
                    $paymentDetails['paymentMethod'] = [
                        'type' => 'klarna_account'
                    ];
                    break;

                case 'paybybank':
                    $paymentDetails['paymentMethod'] = [
                        'type' => 'paybybank'
                    ];
                    break;

                default:
                    // Handle other payment methods or throw an error for unsupported methods
                    throw new \Exception('Unsupported payment method: ' . $paymentMethod['type']);
            }

            // Call the Adyen service to initiate the payment
            $response = $this->adyenService->initiatePayment($paymentDetails);

            // Handle Adyen's response
            if (!empty($response['action'])) {
                return response()->json(['action' => $response['action']]);
            }

            if (!empty($response['resultCode']) && $response['resultCode'] === 'Authorised') {
                // Capture payment after authorization
                $paymentPspReference = $response['pspReference'];

                $captureResponse = $this->adyenService->capturePayment([
                    'merchantAccount' => env('ADYEN_MERCHANT_ACCOUNT'),
                    'amount' => $paymentDetails['amount'],
                    'pspReference' => $paymentPspReference,
                ]);

                if ($captureResponse['response'] === '[capture-received]') {
                    return response()->json([
                        'resultCode' => 'Captured',
                        'redirectUrl' => route('payment.thankYou'),
                    ]);
                } else {
                    return response()->json([
                        'resultCode' => 'CaptureFailed',
                        'redirectUrl' => route('payment.failed'),
                    ]);
                }
            }

            return response()->json([
                'resultCode' => $response['resultCode'] ?? 'Failed',
                'redirectUrl' => route('payment.failed'),
            ]);
        } catch (\Exception $e) {
            dd($e);
            Log::error('Adyen Payment Error: ' . $e->getMessage());
            return response()->json([
                'resultCode' => 'Error',
                'message' => 'Payment failed. Please try again.',
                'redirectUrl' => route('payment.failed'),
            ], 500);
        }
    }

    // Handle Adyen Redirect Response
    public function handleResponse(Request $request)
    {
        $details = $request->all();
        try {
            // Send request to Adyen for payment details processing
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-API-Key' => env('ADYEN_API_KEY'), // Adyen API Key from .env
            ])->post('https://checkout-test.adyen.com/v70/payments/details', [
                'details' => $details, // Payment-specific details
            ])->json();

            // Log the full response for debugging
            Log::debug('Adyen Payment Response:', $response);

            // Validate the response structure
            if (empty($response['resultCode'])) {
                throw new \Exception('Invalid response received from Adyen.');
            }

            // Handle successful payments (Authorised)
            if ($response['resultCode'] === 'Authorised') {
                // Retrieve payment reference
                $paymentPspReference = $response['pspReference'] ?? null;

                if (!$paymentPspReference) {
                    Log::warning('Missing payment reference:', $response);
                    throw new \Exception('Missing payment reference in the response.');
                }

                // Check for the amount and currency in the response
                $amount = null;
                if (isset($response['amount']['currency']) && isset($response['amount']['value'])) {
                    // If the response has the amount and currency, use them
                    $amount = [
                        'currency' => $response['amount']['currency'],
                        'value' => $response['amount']['value']
                    ];
                } else {
                    // Log warning if amount and currency are missing
                    Log::warning('Missing amount or currency in response:', $response);

                    // Fallback: use the amount sent in the initial payment request if missing
                    $amount = [
                        'currency' => 'GBP',
                        'value' => 1500
                    ];
                }

                // Log the amount and currency for debugging
                Log::debug('Using Amount:', $amount);

                // Attempt to capture the payment
                $captureResponse = $this->adyenService->capturePayment([
                    'merchantAccount' => env('ADYEN_MERCHANT_ACCOUNT'),
                    'amount' => $amount,
                    'pspReference' => $paymentPspReference,
                ]);

                // Check capture response
                if (!empty($captureResponse['response']) && $captureResponse['response'] === '[capture-received]') {
                    return response()->json([
                        'resultCode' => 'Captured',
                        'redirectUrl' => route('payment.thankYou'),
                    ]);
                }

                return response()->json([
                    'resultCode' => 'CaptureFailed',
                    'redirectUrl' => route('payment.failed'),
                ]);
            }

            // Handle Pending Payments
            if ($response['resultCode'] === 'Pending') {
                return redirect()->route('payment.pending');
            }

            // Handle failed payments
            return redirect()->route('payment.failed')
                ->withErrors(['error' => 'Payment failed: ' . $response['resultCode']]);
        } catch (\Exception $e) {
            dd($e);
            Log::error('Error processing payment response: ' . $e->getMessage());
            return redirect()->route('payment.failed')
                ->withErrors(['error' => 'Error processing payment: ' . $e->getMessage()]);
        }
    }

    // Display the Thank You Page
    public function thankYouPage()
    {
        return view('payment.thankYou');
    }
}
