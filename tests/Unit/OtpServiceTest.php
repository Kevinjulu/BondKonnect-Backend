<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OtpServiceTest extends TestCase
{
    protected $otpService;
    protected $testUserId = 1; // Assuming user ID 1 exists from seeders or mock

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure all connections are migrated
        $this->artisan('migrate:fresh', ['--database' => 'sqlite']);
        $this->artisan('migrate:fresh', ['--database' => 'bk_db']);
        $this->artisan('migrate:fresh', ['--database' => 'bk_api_db']);

        $this->otpService = new OtpService();
        
        // Ensure test user exists in portaluserlogoninfo if using real DB
        // For unit testing, we can use a mock or a controlled environment.
        // Since we already have seeders, we can assume some data or insert it.
        $exists = DB::table('portaluserlogoninfo')->where('Id', $this->testUserId)->exists();
        if (!$exists) {
             DB::table('portaluserlogoninfo')->insert([
                'Id' => $this->testUserId,
                'AccountId' => 'TEST-123',
                'FirstName' => 'Test',
                'Email' => 'test@example.com',
                'created_on' => Carbon::now()
            ]);
        }
        
        // Clean up OTP history for test user
        DB::table('portaluserotphistory')->where('User', $this->testUserId)->delete();
    }

    public function test_it_can_generate_an_otp()
    {
        $otp = $this->otpService->generate($this->testUserId);

        $this->assertNotNull($otp);
        $this->assertEquals(6, strlen($otp));
        $this->assertDatabaseHas('portaluserotphistory', [
            'User' => $this->testUserId,
            'Otp' => $otp,
            'IsActive' => false
        ]);
    }

    public function test_it_can_verify_a_valid_otp()
    {
        $otp = $this->otpService->generate($this->testUserId);
        $isValid = $this->otpService->verify($this->testUserId, $otp);

        $this->assertTrue($isValid);
        $this->assertDatabaseHas('portaluserotphistory', [
            'User' => $this->testUserId,
            'Otp' => $otp,
            'IsActive' => true // Should be marked as used
        ]);
    }

    public function test_it_fails_with_invalid_otp()
    {
        $this->otpService->generate($this->testUserId);
        $isValid = $this->otpService->verify($this->testUserId, '000000');

        $this->assertFalse($isValid);
    }

    public function test_it_fails_with_expired_otp()
    {
        // Manually insert an expired OTP
        DB::table('portaluserotphistory')->insert([
            'Otp' => '999999',
            'User' => $this->testUserId,
            'OtpExpiry' => Carbon::now()->subMinutes(1),
            'IsActive' => false,
            'created_on' => Carbon::now()->subMinutes(6),
        ]);

        $isValid = $this->otpService->verify($this->testUserId, '999999');
        $this->assertFalse($isValid);
    }

    public function test_it_invalidates_old_otps_when_generating_new_one()
    {
        $otp1 = $this->otpService->generate($this->testUserId);
        $otp2 = $this->otpService->generate($this->testUserId);

        // Verify otp1 is now inactive (used/invalidated)
        $this->assertDatabaseHas('portaluserotphistory', [
            'User' => $this->testUserId,
            'Otp' => $otp1,
            'IsActive' => true
        ]);

        // Verify otp2 is active
        $this->assertDatabaseHas('portaluserotphistory', [
            'User' => $this->testUserId,
            'Otp' => $otp2,
            'IsActive' => false
        ]);
    }
}
