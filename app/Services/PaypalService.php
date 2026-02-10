<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalService
{
    private $clientId;

    private $clientSecret;

    private $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get PayPal Access Token
     */
    public function getAccessToken()
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal Auth Failed: '.$response->body());

            return null;
        }

        return $response->json()['access_token'];
    }

    /**
     * Create PayPal Order
     */
    public function createOrder($amount, $currency = 'USD')
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
        ];

        $response = Http::withToken($token)
            ->post($this->baseUrl.'/v2/checkout/orders', $payload);

        if ($response->failed()) {
            Log::error('PayPal Create Order Error: '.$response->body());

            return [
                'success' => false,
                'message' => 'Failed to create PayPal order',
                'data' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Capture PayPal Order
     */
    public function captureOrder($orderId)
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->baseUrl."/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            Log::error('PayPal Capture Order Error: '.$response->body());

            return [
                'success' => false,
                'message' => 'Failed to capture PayPal order',
                'data' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Verify PayPal webhook signature using PayPal's verify-webhook-signature API
     *
     * @param  array  $headers  Headers array extracted from request
     * @param  string  $body  Raw request body (JSON)
     * @return bool
     */
    public function verifyWebhookSignature(array $headers, $body)
    {
        $token = $this->getAccessToken();
        if (! $token) {
            Log::error('PayPal verify: failed to get access token');

            return false;
        }

        $payload = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? $headers['Paypal-Auth-Algo'] ?? null,
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? $headers['Paypal-Cert-Url'] ?? null,
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['Paypal-Transmission-Id'] ?? null,
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['Paypal-Transmission-Sig'] ?? null,
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['Paypal-Transmission-Time'] ?? null,
            'webhook_id' => config('services.paypal.webhook_id'),
            'webhook_event' => json_decode($body, true),
        ];

        if (empty($payload['transmission_id']) || empty($payload['transmission_sig']) || empty($payload['webhook_event'])) {
            Log::warning('PayPal verify: missing required headers or body');

            return false;
        }

        $response = Http::withToken($token)
            ->post($this->baseUrl.'/v1/notifications/verify-webhook-signature', $payload);

        if ($response->failed()) {
            Log::error('PayPal verify API failed: '.$response->body());

            return false;
        }

        $json = $response->json();

        return isset($json['verification_status']) && $json['verification_status'] === 'SUCCESS';
    }
}
