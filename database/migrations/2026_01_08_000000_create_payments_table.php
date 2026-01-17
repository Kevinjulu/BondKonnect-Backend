<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $column) {
            $column->id();
            $column->string('user_email');
            $column->integer('plan_id');
            $column->string('payment_method'); // mpesa, paypal, card
            $column->decimal('amount', 10, 2);
            $column->string('currency')->default('USD');
            $column->string('status')->default('pending'); // pending, completed, failed, cancelled
            $column->string('reference')->nullable(); // MpesaReceiptNumber or PayPal Order ID
            $column->string('gateway_checkout_id')->unique()->nullable(); // M-Pesa CheckoutRequestID
            $column->json('raw_response')->nullable();
            $column->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
