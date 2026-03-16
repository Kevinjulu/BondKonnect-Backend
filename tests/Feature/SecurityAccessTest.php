<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PortalUserLogonInfo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityAccessTest extends TestCase
{
    /**
     * Test that routes requiring a subscription are blocked for users without one.
     */
    public function test_subscription_middleware_blocks_unauthorized_access()
    {
        // 1. Create a user without a subscription
        $email = 'no-sub@example.com';
        $userId = DB::table('portaluserlogoninfo')->insertGetId([
            'AccountId' => 'BK-TEST-NOSUB',
            'FirstName' => 'No',
            'Email' => $email,
            'IsActive' => true,
            'created_on' => now(),
        ], 'Id');

        // 2. Attempt to access a protected route (e.g., stats-table)
        // Note: We use the 'user_email' fallback in the middleware for testing simplicity
        $response = $this->postJson('/api/V1/services/stats-table', [
            'user_email' => $email
        ]);

        // 3. Assert access is denied (403 Forbidden)
        $response->assertStatus(403);
        $response->assertJsonFragment(['error_code' => 'SUBSCRIPTION_REQUIRED']);
    }

    /**
     * Test that routes requiring a broker are blocked for users without a sponsor.
     */
    public function test_broker_middleware_blocks_unauthorized_access()
    {
        // 1. Create a user without a broker
        $email = 'no-broker@example.com';
        DB::table('portaluserlogoninfo')->insert([
            'AccountId' => 'BK-TEST-NOBROKER',
            'FirstName' => 'NoBroker',
            'Email' => $email,
            'IsActive' => true,
            'created_on' => now(),
        ]);

        // 2. Attempt to create a quote (requires broker middleware)
        $response = $this->postJson('/api/V1/services/create-quote', [
            'user_email' => $email,
            'bond_id' => 1
        ]);

        // 3. Assert access is denied
        $response->assertStatus(403);
        $response->assertJsonFragment(['error_code' => 'BROKER_REQUIRED']);
    }

    /**
     * Test that Admin-only cache management is restricted.
     */
    public function test_admin_cache_endpoints_require_admin_role()
    {
        // 1. Create a non-admin user
        $email = 'regular@example.com';
        $userId = DB::table('portaluserlogoninfo')->insertGetId([
            'AccountId' => 'BK-TEST-REGULAR',
            'FirstName' => 'Regular',
            'Email' => $email,
            'IsActive' => true,
            'created_on' => now(),
        ], 'Id');

        // Assign 'Individual' role (ID: 4) instead of 'Admin' (ID: 1)
        DB::table('userroles')->insert([
            'User' => $userId,
            'Role' => 4,
            'created_on' => now()
        ]);

        // 2. Attempt to clear all cache (Admin function)
        $response = $this->postJson('/api/V1/admin/cache/clear-all', [
            'email' => $email
        ]);

        // 3. Assert access is unauthorized (403)
        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Unauthorized. Admin access required.']);
    }
}
