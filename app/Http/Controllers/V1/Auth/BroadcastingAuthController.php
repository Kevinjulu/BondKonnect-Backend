<?php

namespace App\Http\Controllers\V1\Auth;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Auth\AuthController;

class BroadcastingAuthController extends Controller
{
    // Simple test method to verify controller is working
    public function test()
    {
        Log::info('=== BroadcastingAuthController::test method called ===');
        
        try {
            // Test database connection
            $test = $this->bk_db->select('SELECT 1 as test');
            Log::info('Database test successful', ['result' => $test]);
            
            return response()->json([
                'status' => 'BroadcastingAuthController is working',
                'database' => 'Connected',
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            Log::error('BroadcastingAuthController test failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'BroadcastingAuthController test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function authenticate(Request $request)
    {
        $startTime = microtime(true);
        Log::info('=== BroadcastingAuthController::authenticate method called ===');
        
        try {
            // Get the k-o-t token from cookies or header
            $rawToken = $request->cookie('k-o-t') ?? $request->header('X-Auth-Token');
            // Sometimes cookies may be URL-encoded, decode for database lookup
            $token = $rawToken ? urldecode($rawToken) : null;
            
            Log::info('Token extraction', [
                'from_cookie' => $request->cookie('k-o-t') ? 'Present' : 'Missing',
                'from_header' => $request->header('X-Auth-Token') ? 'Present' : 'Missing',
                'final_token' => $token ? 'Present' : 'Missing',
                'token_preview' => $token ? substr($token, 0, 32) . '...' : 'None',
                'token_length' => $token ? strlen($token) : 0
            ]);
            
            if (!$token) {
                Log::warning('No k-o-t token found in broadcasting auth request');
                return response()->json(['message' => 'No authentication token provided'], 403);
            }

            Log::info('Token found', [
                'token_preview' => substr($token, 0, 16) . '...',
                'token_length' => strlen($token),
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            // Simple database lookup - should be fast
            $user_details = $this->bk_db->table('portaluserlogintoken AS t')
                ->join('portaluserlogoninfo AS u', 't.User', '=', 'u.Id')
                ->where('t.Token', $token)
                ->where('t.ExpiresAt', '>', Carbon::now())
                ->where('t.IsActive', true)
                ->select(
                    'u.FirstName', 'u.Email', 'u.Id', 'u.CompanyName',
                    't.ExpiresAt', 't.IpAddress as TokenIpAddress'
                )
                ->first();

            Log::info('Database query completed', [
                'user_found' => $user_details ? true : false,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            if (!$user_details) {
                // Let's check what tokens exist for debugging
                $allTokens = $this->bk_db->table('portaluserlogintoken')
                    ->where('IsActive', true)
                    ->select('Token', 'ExpiresAt', 'User', 'IpAddress')
                    ->get();

                Log::warning('Token validation failed - no matching record found', [
                    'token_preview' => substr($token, 0, 16) . '...',
                    'total_active_tokens' => $allTokens->count(),
                    'current_time' => Carbon::now()->toDateTimeString(),
                    'tokens_preview' => $allTokens->take(3)->map(function($t) {
                        return [
                            'token_preview' => substr($t->Token, 0, 16) . '...',
                            'expires_at' => $t->ExpiresAt,
                            'user_id' => $t->User,
                            'ip' => $t->IpAddress
                        ];
                    })
                ]);
                return response()->json(['message' => 'Invalid or expired authentication token'], 403);
            }

            $userId = $user_details->Id;
            $channelName = $request->channel_name;

            Log::info('User authenticated for broadcasting', [
                'user_id' => $userId,
                'user_email' => $user_details->Email,
                'channel' => $channelName,
                'token_expires_at' => $user_details->ExpiresAt,
                'token_ip' => $user_details->TokenIpAddress,
                'current_ip' => $request->ip()
            ]);

            // Check if the user can access the requested channel
            if (str_starts_with($channelName, 'private-notifications.')) {
                $requestedUserId = (int) str_replace('private-notifications.', '', $channelName);
                $canAccess = (int) $userId === $requestedUserId;
                
                Log::info('Checking notifications channel access', [
                    'user_id' => $userId,
                    'requested_user_id' => $requestedUserId,
                    'can_access' => $canAccess
                ]);

                if (!$canAccess) {
                    return response()->json(['message' => 'Forbidden - Channel access denied'], 403);
                }
            } elseif (str_starts_with($channelName, 'private-messages.')) {
                $requestedUserId = (int) str_replace('private-messages.', '', $channelName);
                if ((int) $userId !== $requestedUserId) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
            }

            // Generate Pusher signature
            $socketId = $request->socket_id;
            $stringToSign = $socketId . ':' . $channelName;
            $pusherSecret = config('broadcasting.connections.pusher.secret');
            $pusherKey = config('broadcasting.connections.pusher.key');
            
            Log::info('Generating Pusher signature', [
                'pusher_key' => $pusherKey ? 'Present' : 'Missing',
                'pusher_secret' => $pusherSecret ? 'Present' : 'Missing',
                'string_to_sign' => $stringToSign
            ]);

            if (!$pusherSecret || !$pusherKey) {
                Log::error('Pusher configuration missing', [
                    'pusher_key' => $pusherKey ? 'Present' : 'Missing',
                    'pusher_secret' => $pusherSecret ? 'Present' : 'Missing'
                ]);
                return response()->json(['message' => 'Broadcasting configuration error'], 500);
            }

            $signature = hash_hmac('sha256', $stringToSign, $pusherSecret);
            $auth = $pusherKey . ':' . $signature;

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('Broadcasting authentication successful', [
                'user_id' => $userId,
                'channel' => $channelName,
                'total_time_ms' => $totalTime
            ]);

            return response()->json([
                'auth' => $auth,
                'channel_data' => json_encode([
                    'user_id' => $userId,
                    'user_info' => [
                        'id' => $userId,
                        'email' => $user_details->Email,
                        'first_name' => $user_details->FirstName ?? '',
                        'company_name' => $user_details->CompanyName ?? ''
                    ]
                ])
            ]);

        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('Broadcasting authentication error', [
                'error' => $e->getMessage(),
                'total_time_ms' => $totalTime,
                'line' => $e->getLine()
            ]);

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
} 