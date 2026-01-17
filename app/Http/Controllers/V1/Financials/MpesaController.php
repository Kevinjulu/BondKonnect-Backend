<?php

namespace App\Http\Controllers\V1\Financials;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    private $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Initiate STK Push
     */
    public function initiateStkPush(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric',
            'plan_id' => 'required|integer',
            'user_email' => 'required|email',
        ]);

        $reference = 'BK-'.strtoupper(substr(uniqid(), -6));
        $desc = 'Subscription payment for BondKonnect';

        $result = $this->mpesaService->stkPush(
            $request->phone,
            $request->amount,
            $reference,
            $desc
        );

        if ($result['success']) {
            $checkoutRequestId = $result['data']['CheckoutRequestID'] ?? null;

            // Log the request to database
            DB::table('payments')->insert([
                'user_email' => $request->user_email,
                'plan_id' => $request->plan_id,
                'payment_method' => 'mpesa',
                'amount' => $request->amount,
                'currency' => 'KES', // M-Pesa is usually KES
                'status' => 'pending',
                'gateway_checkout_id' => $checkoutRequestId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment request sent to your phone. Enter PIN to complete.',
                'checkout_id' => $checkoutRequestId,
            ]);
        }

        return response()->json($result, 400);
    }

    /**
     * M-Pesa Callback with transaction safety
     */
    public function handleCallback(Request $request)
    {
        Log::info('M-Pesa Callback Received: '.json_encode($request->all()));

        try {
            // Verify signature if configured
            if (! $this->verifyMpesaCallback($request)) {
                Log::warning('M-Pesa callback signature verification failed', ['ip' => $request->ip()]);

                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $callbackData = $request->input('Body.stkCallback');
            if (! $callbackData) {
                return response()->json(['success' => false], 400);
            }

            $resultCode = $callbackData['ResultCode'];
            $checkoutRequestId = $callbackData['CheckoutRequestID'];

            $status = ($resultCode == 0) ? 'completed' : 'failed';
            $reference = null;

            if ($resultCode == 0) {
                $meta = $callbackData['CallbackMetadata']['Item'];
                $reference = collect($meta)->where('Name', 'MpesaReceiptNumber')->first()['Value'] ?? null;
            }

            // Begin transaction for atomic update
            DB::beginTransaction();

            // Lock row to prevent race conditions
            $payment = DB::table('payments')
                ->where('gateway_checkout_id', $checkoutRequestId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                DB::rollBack();
                Log::warning('Payment not found for checkout ID', ['checkout_id' => $checkoutRequestId]);

                return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
            }

            // Prevent double-processing
            if ($payment->status !== 'pending') {
                DB::rollBack();
                Log::info('Payment already processed', ['checkout_id' => $checkoutRequestId, 'status' => $payment->status]);

                return response()->json(['success' => true]); // Idempotent
            }

            // Update payment record
            DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => $status,
                    'reference' => $reference,
                    'raw_response' => json_encode($request->all()),
                    'updated_at' => Carbon::now(),
                ]);

            // If success, trigger downstream logic
            if ($status === 'completed') {
                // Logic to activate user subscription
                Log::info('M-Pesa Payment Successful', [
                    'reference' => $reference,
                    'user_email' => $payment->user_email,
                    'checkout_id' => $checkoutRequestId,
                ]);
                // You could emit event here: event(new PaymentCompleted($payment));
            }

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('M-Pesa Callback Exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Verify M-Pesa callback authenticity.
     * Supports HMAC-SHA256 verification using configured secret or passkey.
     */
    private function verifyMpesaCallback(Request $request)
    {
        $headerName = config('services.mpesa.signature_header', 'X-Mpesa-Signature');
        $providedSig = $request->header($headerName);

        // If no signature header configured or provided, fail-safe: reject
        if (empty($providedSig)) {
            Log::info('No M-Pesa signature header provided');

            return false;
        }

        // Optional IP whitelist check
        $allowedIps = config('services.mpesa.allowed_ips', []);
        if (is_array($allowedIps) && count(array_filter($allowedIps)) > 0) {
            $clientIp = $request->ip();
            if (! in_array($clientIp, $allowedIps)) {
                Log::warning('M-Pesa callback from disallowed IP', ['ip' => $clientIp]);

                return false;
            }
        }

        $rawBody = $request->getContent();
        $secret = config('services.mpesa.signature_secret') ?: config('services.mpesa.passkey');
        if (empty($secret)) {
            Log::warning('No M-Pesa signature secret configured');

            return false;
        }

        // Compute expected signature (base64 of HMAC-SHA256)
        $expectedBinary = hash_hmac('sha256', $rawBody, $secret, true);
        $expected = base64_encode($expectedBinary);

        // Compare in constant-time
        if (! hash_equals($expected, $providedSig)) {
            Log::warning('M-Pesa signature mismatch', ['expected' => substr($expected, 0, 8).'...', 'provided' => substr($providedSig, 0, 8).'...']);

            return false;
        }

        return true;
    }

    /**
     * Check Payment Status (Polling endpoint)
     */
    public function checkStatus(Request $request)
    {
        $request->validate(['checkout_id' => 'required|string']);

        $payment = DB::table('payments')
            ->where('gateway_checkout_id', $request->checkout_id)
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'reference' => $payment->reference,
        ]);
    }
}
