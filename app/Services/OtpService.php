<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OtpService
{
    protected $db;

    public function __construct()
    {
        $isTesting = defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__') || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');
        $this->db = $isTesting ? DB::connection() : DB::connection('bk_db');
    }

    /**
     * Generate a new OTP for a user and save it to the database.
     *
     * @param int $userId
     * @return string|null
     */
    public function generate(int $userId): ?string
    {
        try {
            $otp = (string) rand(100000, 999999);

            // Invalidate any previous active OTPs for this user
            $this->db->table('portaluserotphistory')
                ->where('User', $userId)
                ->where('IsActive', false)
                ->update(['IsActive' => true]);

            // Save new OTP
            $this->db->table('portaluserotphistory')->insert([
                'Otp' => $otp,
                'User' => $userId,
                'OtpExpiry' => Carbon::now()->addMinutes(5),
                'IsActive' => false, // false means it's active/unused
                'created_on' => Carbon::now(),
            ]);

            Log::info('OTP Generated Successfully', [
                'user_id' => $userId,
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(5)
            ]);

            return $otp;
        } catch (\Throwable $th) {
            Log::error('Error generating OTP in OtpService', [
                'user_id' => $userId,
                'error' => $th->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verify an OTP for a user.
     *
     * @param int $userId
     * @param string $otp
     * @return bool
     */
    public function verify(int $userId, string $otp): bool
    {
        try {
            $otpRecord = $this->db->table('portaluserotphistory')
                ->where('User', $userId)
                ->where('Otp', $otp)
                ->where('IsActive', false)
                ->where('OtpExpiry', '>', Carbon::now())
                ->first();

            if (!$otpRecord) {
                Log::warning('Invalid or expired OTP attempt', [
                    'user_id' => $userId,
                    'otp' => $otp
                ]);
                return false;
            }

            // Mark OTP as used
            $this->db->table('portaluserotphistory')
                ->where('Id', $otpRecord->Id)
                ->update(['IsActive' => true]);

            Log::info('OTP Verified Successfully', [
                'user_id' => $userId
            ]);

            return true;
        } catch (\Throwable $th) {
            Log::error('Error verifying OTP in OtpService', [
                'user_id' => $userId,
                'error' => $th->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the last active OTP for a user (useful for testing or resending).
     *
     * @param int $userId
     * @return object|null
     */
    public function getActiveOtp(int $userId)
    {
        return $this->db->table('portaluserotphistory')
            ->where('User', $userId)
            ->where('IsActive', false)
            ->where('OtpExpiry', '>', Carbon::now())
            ->orderBy('created_on', 'desc')
            ->first();
    }
}
