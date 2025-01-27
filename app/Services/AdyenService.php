<?php

namespace App\Services;

use Adyen\Client;
use Adyen\Service\Checkout;
use Adyen\Service\Modification;
use Illuminate\Support\Facades\Log;

class AdyenService
{
    protected $client;
    protected $checkout;
    protected $modification;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setXApiKey(env('ADYEN_API_KEY'));
        $this->client->setMerchantAccount(env('ADYEN_MERCHANT_ACCOUNT'));
        $this->client->setEnvironment(env('ADYEN_ENVIRONMENT')); // test or live

        $this->checkout = new Checkout($this->client);
        $this->modification = new Modification($this->client); // For capturing payments
    }

    public function initiatePayment(array $paymentDetails)
    {
        return $this->checkout->payments($paymentDetails);
    }

    public function capturePayment(array $captureDetails)
    {
        try {
            $response = $this->modification->capture([
                'merchantAccount' => $captureDetails['merchantAccount'],
                'originalReference' => $captureDetails['pspReference'], // PSP reference of the authorized payment
                'modificationAmount' => $captureDetails['amount'], // Amount to capture
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Adyen Capture Payment Error: ' . $e->getMessage());
            throw new \Exception('Failed to capture payment. Error: ' . $e->getMessage());
        }
    }

    public function handleWebhook($payload)
    {
        // Validate and process Adyen webhook payload
        // For example, update payment status
    }
}
