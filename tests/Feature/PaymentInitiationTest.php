<?php

namespace Tests\Feature;

use App\Services\MpesaService;
use App\Services\PaypalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpesa_stk_push_initiation()
    {
        // Mock MpesaService
        $this->mock(MpesaService::class, function ($mock) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => ['CheckoutRequestID' => 'ws_CO_12345'],
                ]);
        });

        $payload = [
            'phone' => '0712345678',
            'amount' => 1000,
            'plan_id' => 1,
            'user_email' => 'test@example.com',
        ];

        $response = $this->postJson('/api/V1/payments/mpesa/stk-push', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'checkout_id' => 'ws_CO_12345',
            ]);

        $this->assertDatabaseHas('payments', [
            'user_email' => 'test@example.com',
            'amount' => 1000,
            'gateway_checkout_id' => 'ws_CO_12345',
            'status' => 'pending',
            'payment_method' => 'mpesa',
        ]);
    }

    public function test_paypal_create_order()
    {
        // Mock PaypalService
        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->once()
                ->with(50.00) // Expect float/numeric
                ->andReturn([
                    'success' => true,
                    'data' => ['id' => 'ORDER-ABC'],
                ]);
        });

        $payload = [
            'amount' => 50.00,
            'plan_id' => 2,
        ];

        $response = $this->postJson('/api/V1/payments/paypal/create-order', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order_id' => 'ORDER-ABC',
            ]);
    }

    public function test_paypal_capture_order()
    {
        // Mock PaypalService
        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('ORDER-ABC')
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'status' => 'COMPLETED',
                        'purchase_units' => [[
                            'payments' => [
                                'captures' => [[
                                    'amount' => ['value' => '50.00']
                                ]]
                            ]
                        ]]
                    ],
                ]);
        });

        $payload = [
            'order_id' => 'ORDER-ABC',
            'user_email' => 'paypal@example.com',
            'plan_id' => 2,
        ];

        $response = $this->postJson('/api/V1/payments/paypal/capture-order', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment captured successfully',
            ]);

        $this->assertDatabaseHas('payments', [
            'user_email' => 'paypal@example.com',
            'reference' => 'ORDER-ABC',
            'status' => 'completed',
            'payment_method' => 'paypal',
        ]);
    }
}
