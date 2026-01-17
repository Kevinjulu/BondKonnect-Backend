<?php

namespace Tests\Feature;

use App\Services\PaypalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for PayPal webhook processing.
 *
 * This test uses a mocked PaypalService to simulate successful signature
 * verification and asserts the webhook handler updates the payment record.
 */
class PaypalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_webhook_verification_and_processing()
    {
        // Arrange: insert a pending payment record
        $orderId = 'ORDER-98765';
        DB::table('payments')->insert([
            'user_email' => 'payer@example.com',
            'plan_id' => 2,
            'payment_method' => 'paypal',
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
            'reference' => $orderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock PaypalService to return true for signature verification
        $mock = \Mockery::mock(PaypalService::class);
        $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->app->instance(PaypalService::class, $mock);

        // Build a webhook payload resembling PayPal PAYMENT.CAPTURE.COMPLETED
        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-123',
                'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
            ],
        ];

        // Act: post to webhook endpoint
        $response = $this->postJson('/api/V1/payments/paypal/webhook', $event, [
            'Content-Type' => 'application/json',
            'PAYPAL-TRANSMISSION-ID' => 'tx-1',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-TRANSMISSION-SIG' => 'sig',
            'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
        ]);

        // Assert: webhook accepted and payment record updated to completed
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'reference' => $orderId,
            'status' => 'completed',
        ]);
    }
}
