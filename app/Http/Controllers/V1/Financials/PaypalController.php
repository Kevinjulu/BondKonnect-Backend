<?php

namespace App\Http\Controllers\V1\Financials;

use App\Http\Controllers\Controller;
use App\Services\PaypalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaypalController extends Controller
{
    private $paypalService;

    public function __construct(PaypalService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    /**
     * Create PayPal Order
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'plan_id' => 'required|integer',
        ]);

        $result = $this->paypalService->createOrder($request->amount);

        if ($result['success']) {
            $orderId = $result['data']['id'];

            return response()->json([
                'success' => true,
                'order_id' => $orderId,
            ]);
        }

        return response()->json($result, 400);
    }

    /**
     * Capture PayPal Order
     */
    public function captureOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'user_email' => 'required|email',
            'plan_id' => 'required|integer',
        ]);

        $result = $this->paypalService->captureOrder($request->order_id);

        if ($result['success'] && $result['data']['status'] === 'COMPLETED') {
            $amount = $result['data']['purchase_units'][0]['payments']['captures'][0]['amount']['value'];

            // Log successful payment
            DB::table('payments')->insert([
                'user_email' => $request->user_email,
                'plan_id' => $request->plan_id,
                'payment_method' => 'paypal',
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => $request->order_id,
                'raw_response' => json_encode($result['data']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Activate Subscription
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($request->user_email);

            if ($user) {
                $planBilling = DB::table('billingdetails')
                    ->where('SubscriptionPlanId', $request->plan_id)
                    ->first();

                if ($planBilling) {
                    $dueDate = Carbon::now()->addDays($planBilling->Days);

                    DB::table('subscriptions')
                        ->where('User', $user->Id)
                        ->where('SubscriptionStatus', 1)
                        ->update(['SubscriptionStatus' => 3]);

                    DB::table('subscriptions')->insert([
                        'User' => $user->Id,
                        'PlanId' => $request->plan_id,
                        'DueDate' => $dueDate,
                        'AmountPaid' => $amount,
                        'Discount' => 0,
                        'SubscriptionStatus' => 1,
                        'created_by' => $user->Id,
                        'created_on' => Carbon::now()
                    ]);
                    
                    Log::info('PayPal Subscription activated for user: ' . $request->user_email);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment captured and subscription activated successfully',
                'data' => $result['data'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment capture failed',
            'data' => $result['data'] ?? null,
        ], 400);
    }

    /**
     * PayPal webhook endpoint for async notifications with transaction safety
     */
    public function handleWebhook(Request $request)
    {
        $raw = $request->getContent();

        try {
            // Collect relevant headers for verification
            $headers = [
                'PAYPAL-TRANSMISSION-ID' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'PAYPAL-TRANSMISSION-TIME' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'PAYPAL-CERT-URL' => $request->header('PAYPAL-CERT-URL'),
                'PAYPAL-AUTH-ALGO' => $request->header('PAYPAL-AUTH-ALGO'),
                'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            ];

            $verified = $this->paypalService->verifyWebhookSignature($headers, $raw);
            if (! $verified) {
                Log::warning('PayPal webhook verification failed');

                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $event = json_decode($raw, true);
            $eventType = $event['event_type'] ?? null;

            // Handle payment completed events
            if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                $resource = $event['resource'] ?? [];

                // Try to find a reference/order id in possible fields
                $candidates = [];
                if (isset($resource['supplementary_data']['related_ids']['order_id'])) {
                    $candidates[] = $resource['supplementary_data']['related_ids']['order_id'];
                }
                if (isset($resource['invoice_id'])) {
                    $candidates[] = $resource['invoice_id'];
                }
                if (isset($resource['id'])) {
                    $candidates[] = $resource['id'];
                }

                // Begin transaction
                DB::beginTransaction();

                $payment = null;
                foreach ($candidates as $c) {
                    if (empty($c)) {
                        continue;
                    }
                    $payment = DB::table('payments')
                        ->where(function ($q) use ($c) {
                            $q->where('reference', $c)
                                ->orWhere('gateway_checkout_id', $c)
                                ->orWhere('raw_response', 'like', '%"'.$c.'"%');
                        })
                        ->lockForUpdate()  // Lock row to prevent race conditions
                        ->first();
                    if ($payment) {
                        break;
                    }
                }

                if ($payment) {
                    // Prevent double-processing
                    if ($payment->status === 'completed') {
                        DB::rollBack();
                        Log::info('PayPal webhook: payment already completed', ['payment_id' => $payment->id]);

                        return response()->json(['success' => true]); // Idempotent
                    }

                    DB::table('payments')->where('id', $payment->id)->update([
                        'status' => 'completed',
                        'reference' => $resource['id'] ?? $payment->reference,
                        'raw_response' => json_encode($event),
                        'updated_at' => Carbon::now(),
                    ]);

                    Log::info('PayPal webhook: marked payment completed', [
                        'payment_id' => $payment->id,
                        'user_email' => $payment->user_email,
                        'resource_id' => $resource['id'] ?? 'unknown',
                    ]);
                } else {
                    DB::rollBack();
                    Log::warning('PayPal webhook: payment not found for event', ['resource_id' => $resource['id'] ?? 'unknown']);

                    // Still return success to prevent webhook retries
                    return response()->json(['success' => true]);
                }

                DB::commit();
            } else {
                Log::info('PayPal webhook: unhandled event type', ['event_type' => $eventType]);
            }

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PayPal Webhook Exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Still return success to prevent webhook retries
            return response()->json(['success' => true]);
        }
    }
}
