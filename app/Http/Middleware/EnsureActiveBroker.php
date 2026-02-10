<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBroker
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
             // Fallback: Check if user_email is provided in request
             if ($request->has('user_email')) {
                $email = $request->input('user_email');
                $user = DB::connection('bk_db')->table('portaluserlogoninfo')->where('Email', $email)->first();
                if ($user) {
                    return $this->checkBroker($user->Id) ? $next($request) : $this->forbiddenResponse();
                }
            }
            return response()->json(['success' => false, 'message' => 'Unauthorized: No session token found'], 401);
        }

        $validation = $this->validateSecureToken($token, $request->ip());
        
        if (!$validation['valid']) {
             return response()->json(['success' => false, 'message' => 'Unauthorized: ' . $validation['reason']], 401);
        }

        $userId = $validation['user_id'];

        if (!$this->checkBroker($userId)) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    private function checkBroker($userId)
    {
        // Check for active broker sponsorship in portalintermediary
        return DB::connection('bk_db')->table('portalintermediary')
            ->where('User', $userId)
            ->where('IsActive', true)
            ->exists();
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Access Denied: Active Broker Sponsorship Required. Please request broker sponsorship to access this feature.',
            'error_code' => 'BROKER_REQUIRED'
        ], 403);
    }

    // Reuse token validation logic
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

            $tokenRecord = DB::connection('bk_db')->table('portaluserlogintoken')
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
