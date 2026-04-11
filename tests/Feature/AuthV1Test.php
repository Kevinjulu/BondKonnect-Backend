<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class AuthV1Test extends TestCase
{
    // use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure all connections are migrated since we use multiple connections in the app
        $this->artisan('migrate:fresh', ['--database' => 'sqlite']);
        $this->artisan('migrate:fresh', ['--database' => 'bk_db']);
        $this->artisan('migrate:fresh', ['--database' => 'bk_api_db']);

        // Manually create legacy tables if they don't exist
        if (!Schema::hasTable('cmalist')) {
            Schema::create('cmalist', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('Website');
            });
        }

        if (!Schema::hasTable('portaluseremailverification')) {
            Schema::create('portaluseremailverification', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('User');
                $table->string('Signature');
                $table->timestamp('ExpiresAt')->nullable();
                $table->boolean('IsVerified')->default(false);
                $table->timestamp('created_on')->nullable();
            });
        }
        
        DB::table('cmalist')->updateOrInsert(['Website' => 'https://www.standardinvestment-bank.com'], ['Website' => 'https://www.standardinvestment-bank.com']);
        DB::table('cmalist')->updateOrInsert(['Website' => 'https://www.dyerandblair.com'], ['Website' => 'https://www.dyerandblair.com']);
    }

    public function test_register_individual_success()
    {
        $this->withoutExceptionHandling();
        Mail::fake();

        // Need an existing dealer for the intermediary check
        DB::table('portaluserlogoninfo')->insert([
            'AccountId' => 'DLR123',
            'FirstName' => 'Dealer',
            'Email' => 'dealer@standardinvestment-bank.com',
            'PhoneNumber' => '0711111111',
            'IsActive' => true,
            'created_on' => now()
        ]);

        $response = $this->postJson('/api/V1/auth/user-register', [
            'is_individual' => true,
            'is_agent' => false,
            'is_corporate' => false,
            'is_broker' => false,
            'is_dealer' => false,
            'is_admin' => false,
            'email' => 'test@standardinvestment-bank.com',
            'phone' => '0712345678',
            'first_name' => 'John',
            'other_names' => 'Doe',
            'cds_number' => '12345678',
            'locality' => 'local',
            'broker_dealer' => ['dealer@standardinvestment-bank.com']
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);
        $this->assertDatabaseHas('portaluserlogoninfo', [
            'Email' => 'test@standardinvestment-bank.com',
            'FirstName' => 'John'
        ]);
    }

    public function test_register_fails_with_invalid_domain()
    {
        $response = $this->postJson('/api/V1/auth/user-register', [
            'is_individual' => true,
            'is_agent' => false,
            'is_corporate' => false,
            'is_broker' => false,
            'is_dealer' => false,
            'is_admin' => false,
            'email' => 'test@gmail.com',
            'phone' => '0712345678',
            'first_name' => 'John',
            'other_names' => 'Doe',
            'cds_number' => '12345678',
            'locality' => 'local'
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Invalid Email.  Only company emails are accepted.']);
    }

    public function test_set_password_success()
    {
        // 1. Create a user
        $userId = DB::table('portaluserlogoninfo')->insertGetId([
            'AccountId' => 'ACC123',
            'FirstName' => 'Jane',
            'Email' => 'jane@dyerandblair.com',
            'PhoneNumber' => '0787654321',
            'IsActive' => false,
            'created_on' => now()
        ]);

        // 2. Generate a valid signature and token
        $email = 'jane@dyerandblair.com';
        $timestamp = time();
        $signature = hash_hmac('sha256', $email . $timestamp, config('app.key'));
        $csrf_token = hash_hmac('sha256', $timestamp, config('app.key'));

        // 3. Create verification record
        DB::table('portaluseremailverification')->insert([
            'User' => $userId,
            'Signature' => $signature,
            'ExpiresAt' => Carbon::now()->addHour(),
            'IsVerified' => false,
            'created_on' => now()
        ]);

        // 4. Call setPassword
        $response = $this->postJson('/api/V1/auth/set-password', [
            'email' => $email,
            'password' => 'newpassword123',
            'is_res' => false,
            'csrf_token' => $csrf_token,
            'csrf_timestamp' => (string)$timestamp,
            's' => $signature,
            't' => (string)$timestamp
        ]);

        $response->assertStatus(200);
        
        // 5. Verify password is saved
        $this->assertDatabaseHas('portaluserpasswordshistory', [
            'User' => $userId,
            'IsActive' => true
        ]);
        
        // 6. Verify user is activated
        $this->assertDatabaseHas('portaluserlogoninfo', [
            'Id' => $userId,
            'IsActive' => true
        ]);
    }

    public function test_login_success_bypass_otp()
    {
        $this->withoutExceptionHandling();
        // 1. Create user with password
        $email = 'kevinjulu@gmail.com'; // This email bypasses OTP
        $password = 'secret123';
        
        $userId = DB::table('portaluserlogoninfo')->insertGetId([
            'AccountId' => 'DEV123',
            'FirstName' => 'Developer',
            'Email' => $email,
            'PhoneNumber' => '0700000000',
            'IsActive' => true,
            'created_on' => now()
        ]);

        DB::table('portaluserpasswordshistory')->insert([
            'User' => $userId,
            'Password' => Hash::make($password),
            'IsActive' => true,
            'created_on' => now()
        ]);

        // Seed role for user
        DB::table('userroles')->insert([
            'User' => $userId,
            'Role' => 1, // Admin
            'created_on' => now()
        ]);

        // 2. Attempt login
        $response = $this->postJson('/api/V1/auth/user-login', [
            'email' => $email,
            'password' => $password
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true, 'otp_bypassed' => true]);
        $response->assertJsonStructure(['token']);
    }

    public function test_otp_verification_flow()
    {
        $this->withoutExceptionHandling();
        
        $email = 'user@example.com';
        $password = 'password123';
        
        $userId = DB::table('portaluserlogoninfo')->insertGetId([
            'AccountId' => 'USR123',
            'FirstName' => 'Regular',
            'Email' => $email,
            'PhoneNumber' => '0711223344',
            'IsActive' => true,
            'created_on' => now()
        ]);

        DB::table('portaluserpasswordshistory')->insert([
            'User' => $userId,
            'Password' => Hash::make($password),
            'IsActive' => true,
            'created_on' => now()
        ]);

        // Seed role for user
        DB::table('userroles')->insert([
            'User' => $userId,
            'Role' => 2, // Individual
            'created_on' => now()
        ]);

        // 1. Login (should trigger OTP)
        Mail::fake();
        $loginResponse = $this->postJson('/api/V1/auth/user-login', [
            'email' => $email,
            'password' => $password
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonFragment(['message' => 'OTP sent successfully']);

        // 2. Get the seeded OTP from database
        $otpRecord = DB::table('portaluserotphistory')->where('User', $userId)->first();
        $this->assertNotNull($otpRecord);
        $otp = $otpRecord->Otp;

        // 3. Verify OTP
        $verifyResponse = $this->postJson('/api/V1/auth/verify-otp', [
            'email' => $email,
            'otp' => (int)$otp,
            'ip_address' => '127.0.0.1'
        ]);

        if ($verifyResponse->status() !== 200) {
            dump($verifyResponse->json());
        }

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonFragment(['message' => 'OTP verified successfully']);
        $verifyResponse->assertJsonStructure(['data']);

        // 4. Verify OTP is now marked as used (IsActive = true)
        $this->assertDatabaseHas('portaluserotphistory', [
            'Id' => $otpRecord->Id,
            'IsActive' => true
        ]);
    }
}
