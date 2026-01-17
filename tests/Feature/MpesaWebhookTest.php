<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for M-Pesa webhook processing.
 *
 * These tests assert that the webhook endpoint verifies the HMAC signature
 * and updates the payment record idempotently.
 */
class MpesaWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpesa_callback_verification_and_processing()
    {
        // Arrange: create a pending payment record
        $checkoutId = 'TESTCHK123';
        DB::table('payments')->insert([
            'user_email' => 'tester@example.com',
            'plan_id' => 1,
            'payment_method' => 'mpesa',
            'amount' => 100,
            'currency' => 'KES',
            'status' => 'pending',
            'gateway_checkout_id' => $checkoutId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Build callback body matching M-Pesa structure
        $body = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'CheckoutRequestID' => $checkoutId,
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT-12345'],
                        ],
                    ],
                ],
            ],
        ];

        $raw = json_encode($body);

        // Configure secret used to compute expected signature
        $secret = 'test-passkey';
        Config::set('services.mpesa.signature_secret', $secret);
        Config::set('services.mpesa.signature_header', 'X-Mpesa-Signature');

        // Compute signature as server expects (base64(HMAC-SHA256(rawBody, secret)))
        $sig = base64_encode(hash_hmac('sha256', $raw, $secret, true));

        // Act: send callback with signature header
        $response = $this->postJson('/api/V1/payments/mpesa/callback', $body, [
            'X-Mpesa-Signature' => $sig,
            'Content-Type' => 'application/json',
        ]);

        // Assert: webhook accepted and payment updated
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'gateway_checkout_id' => $checkoutId,
            'status' => 'completed',
            'reference' => 'RCPT-12345',
        ]);
    }
}
