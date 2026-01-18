<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('k-o-t');
        
        if (!$token) {
            // Fallback: Check if user_email is provided in request (for some endpoints)
            if ($request->has('user_email')) {
                $email = $request->input('user_email');
                $user = DB::table('portaluserlogoninfo')->where('Email', $email)->first();
                if ($user) {
                    return $this->checkSubscription($user->Id) ? $next($request) : $this->forbiddenResponse();
                }
            }
            
            return response()->json(['success' => false, 'message' => 'Unauthorized: No session token found'], 401);
        }

        $validation = $this->validateSecureToken($token, $request->ip());
        
        if (!$validation['valid']) {
             return response()->json(['success' => false, 'message' => 'Unauthorized: ' . $validation['reason']], 401);
        }

        $userId = $validation['user_id'];

        if (!$this->checkSubscription($userId)) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    private function checkSubscription($userId)
    {
        // Check for active subscription: Status = 1 (Active) AND DueDate > Now
        return DB::table('subscriptions')
            ->where('User', $userId)
            ->where('SubscriptionStatus', 1)
            ->where('DueDate', '>', Carbon::now())
            ->exists();
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Access Denied: Active Subscription Required. Please purchase a subscription to access this feature.',
            'error_code' => 'SUBSCRIPTION_REQUIRED'
        ], 403);
    }

    // Reuse token validation logic (simplified version of AuthController's logic)
    private function validateSecureToken($token, $currentIpAddress)
    {
        try {
            $decodedToken = base64_decode($token);
            if (!$decodedToken) return ['valid' => false, 'reason' => 'Invalid token format'];

            $parts = explode('|', $decodedToken);
            if (count($parts) !== 2) return ['valid' => false, 'reason' => 'Invalid token structure'];

            [$encryptedPayload, $signature] = $parts;

            // Verify HMAC
            $expectedSignature = hash_hmac('sha256', $encryptedPayload, Config::get('app.key'));
            if (!hash_equals($expectedSignature, $signature)) return ['valid' => false, 'reason' => 'Signature verification failed'];

            // Decrypt
            $jsonPayload = decrypt($encryptedPayload);
            $payload = json_decode($jsonPayload, true);

            if (!$payload || !isset($payload['user_id'])) return ['valid' => false, 'reason' => 'Invalid payload'];

            // Skip IP check in dev/local for ease of testing, but keep it for prod if needed
            // For this middleware, we'll verify the token exists in DB to be sure
            $tokenRecord = DB::table('portaluserlogintoken')
                ->where('Token', $token)
                ->where('User', $payload['user_id'])
                ->where('ExpiresAt', '>', Carbon::now())
                ->where('IsActive', true)
                ->first();

            if (!$tokenRecord) return ['valid' => false, 'reason' => 'Session expired or invalid'];

            return ['valid' => true, 'user_id' => $payload['user_id']];

        } catch (\Throwable $e) {
            Log::error('Middleware Token Validation Error: ' . $e->getMessage());
            return ['valid' => false, 'reason' => 'Validation error'];
        }
    }
}
