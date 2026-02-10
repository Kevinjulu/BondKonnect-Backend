<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private $consumerKey;

    private $consumerSecret;

    private $baseUrl;

    private $shortcode;

    private $passkey;

    private $callbackUrl;

    public function __construct()
    {
        $this->consumerKey = config('services.mpesa.key');
        $this->consumerSecret = config('services.mpesa.secret');
        $this->shortcode = config('services.mpesa.shortcode');
        $this->passkey = config('services.mpesa.passkey');
        $this->callbackUrl = config('services.mpesa.callback_url');

        $this->baseUrl = config('services.mpesa.env') === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Generate M-Pesa Access Token
     */
    public function getAccessToken()
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get($this->baseUrl.'/oauth/v1/generate?grant_type=client_credentials');

        if ($response->failed()) {
            Log::error('M-Pesa Auth Failed: '.$response->body());

            return null;
        }

        return $response->json()['access_token'];
    }

    /**
     * Initiate STK Push (Lipa na M-Pesa Online)
     */
    public function stkPush($phoneNumber, $amount, $accountReference, $transactionDesc)
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);

        // Ensure phone number format (254XXXXXXXXX)
        $phoneNumber = preg_replace('/^(0|\+)/', '254', $phoneNumber);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ];

        $response = Http::withToken($token)
            ->post($this->baseUrl.'/mpesa/stkpush/v1/processrequest', $payload);

        if ($response->failed()) {
            Log::error('M-Pesa STK Push Error: '.$response->body());

            return [
                'success' => false,
                'message' => 'Failed to initiate payment',
                'data' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'message' => 'STK Push initiated successfully',
            'data' => $response->json(),
        ];
    }
}
