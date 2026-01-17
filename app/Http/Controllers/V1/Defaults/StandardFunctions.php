<?php

namespace App\Http\Controllers\V1\Defaults;

// use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StandardFunctions extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_TIME = 15;

    public const CSRF_EXPIRY = 3600; // 1 hour in seconds

    public function validateCsrfToken($token, $timestamp)
    {
        if (! $token || ! $timestamp) {
            return false;
        }

        // Check if token has expired
        if ((time() - $timestamp) > self::CSRF_EXPIRY) {
            return false;
        }

        // Verify token authenticity using the same method used to generate it
        $expectedToken = hash_hmac('sha256', $timestamp, env('APP_KEY'));

        return hash_equals($expectedToken, $token);
    }

    // generate the csrf token
    public function generateCsrfToken()
    {
        $timestamp = time();
        $token = hash_hmac('sha256', $timestamp, env('APP_KEY'));

        return response()->json([
            'success' => true,
            'message' => 'CSRF token generated successfully',
            'data' => [
                'token' => $token,
                'timestamp' => $timestamp,
            ],
        ]);
    }

    public function checkTooManyFailedAttempts($email)
    {
        $key = 'login_attempts_'.md5($email);
        $attempts = Cache::get($key, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return true;
        }

        return false;
    }

    public function incrementFailedAttempts($email)
    {
        $key = 'login_attempts_'.md5($email);
        $attempts = Cache::get($key, 0);
        Cache::put($key, $attempts + 1, Carbon::now()->addMinutes(self::LOCKOUT_TIME));
        // $expirationTime = Carbon::now()->addMinutes(self::LOCKOUT_TIME);
        // Cache::put($key, $attempts + 1, $expirationTime);
    }

    public function clearFailedAttempts($email)
    {
        $key = 'login_attempts_'.md5($email);
        Cache::forget($key);
    }

    public function get_user_id($email)
    {
        // Use cached user details
        return CacheService::getUserDetails($email);
    }

    public function get_user_role($user_id)
    {
        // Use cached user role
        return CacheService::getUserRole($user_id);
    }

    public function generateUniqueAccountId()
    {
        try {
            $length = 34; // Standard length for crypto-style account IDs

            do {
                // Generate random string with specific pattern
                $accountId = 'BL'.date('Y').rand(1000000000, 9999999999).
                             substr(str_shuffle('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'), 0, 10);

                // Check if this ID already exists in the database
                $exists = $this->bk_db->table('portaluserlogoninfo')
                    ->where('AccountId', $accountId)
                    ->exists();

            } while ($exists); // Keep generating until we get a unique one

            return $accountId;

        } catch (\Exception $e) {
            // Log the error and return null or throw custom exception
            Log::error('Error generating unique account ID: '.$e->getMessage());

            return null;
        }
    }

    public function generatePlacementNumber()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $count = $this->bk_db->table('quotebook')->whereDate('created_on', now())->count() + 1;
        $serial = str_pad($count, 3, '0', STR_PAD_LEFT);

        return 'BL'.'-'.substr(str_shuffle(str_repeat($characters, ceil(3 / strlen($characters)))), 0, 4).$count.'/'.$serial.'/'.date('Y');
    }

    public function logEmail($recipientId, $subject, $body, $allRecipientsEmails, $cc, $bcc, $scheduleDate, $roleGroupSendingTo, $isDraft, $isFromSystem, $isBulkEmail)
    {
        try {

            if (! $roleGroupSendingTo) {
                $roleGroupSendingTo = null;
            }
            if (! $allRecipientsEmails) {
                $allRecipientsEmails = null;
            }
            if (! $cc) {
                $cc = null;
            }
            if (! $bcc) {
                $bcc = null;
            }
            if (! $scheduleDate) {
                $scheduleDate = null;
            }
            if (! $isDraft) {
                $isDraft = 0;
            }
            if (! $isFromSystem) {
                $isFromSystem = 0;
            }
            if (! $isBulkEmail) {
                $isBulkEmail = 0;
            }
            // for these below check if is present insert or else it is null
            if ($recipientId) {
                $this->bk_db->table('emaillogs')->insert([
                    'Recipient' => $recipientId,
                    'AllRecipientsEmails' => $allRecipientsEmails,
                    'Subject' => $subject,
                    'Body' => $body,
                    'CC' => $cc,
                    'BCC' => $bcc,
                    'ScheduleDate' => $scheduleDate,
                    'RoleGroupSendingTo' => $roleGroupSendingTo,
                    'IsDraft' => $isDraft,
                    'IsSent' => true,
                    'IsFromSystem' => $isFromSystem,
                    'IsBulkEmail' => $isBulkEmail,
                    'created_on' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email logged successfully',
                'data' => null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error logging email: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error logging email: '.$e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
    }
}
