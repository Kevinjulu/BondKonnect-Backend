<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\PortalUserLogonInfo;
use App\Models\Payment;
use App\Models\UserRating;
use App\Models\UserCredibilityScore;
use App\Models\RatingDispute;
use App\Models\CredibilityScoreHistory;
use Database\Seeders\KenyaMockDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseSeedingTest extends TestCase
{
    /**
     * Test that legacy models have the correct primary key configuration.
     * This is critical for PostgreSQL 'RETURNING "Id"' compatibility.
     */
    public function test_models_have_correct_primary_key_overrides()
    {
        $models = [
            PortalUserLogonInfo::class,
            Payment::class,
            UserRating::class,
            UserCredibilityScore::class,
            RatingDispute::class,
            CredibilityScoreHistory::class,
        ];

        foreach ($models as $modelClass) {
            $model = new $modelClass;
            $this->assertEquals('Id', $model->getKeyName(), "Model {$modelClass} must use 'Id' as primary key.");
            $this->assertTrue($model->getIncrementing(), "Model {$modelClass} must have incrementing Id.");
            $this->assertEquals('int', $model->getKeyType(), "Model {$modelClass} must have integer key type.");
        }
    }

    /**
     * Test that the KenyaMockDataSeeder runs without SQL errors.
     * This catches the SQLSTATE[42703] "id" column not found error.
     */
    public function test_kenya_mock_data_seeder_execution()
    {
        // We catch exceptions to provide a clear error message if it fails
        try {
            $this->seed(KenyaMockDataSeeder::class);
            
            // Assert users were created in legacy table
            $portalUsersCount = DB::table('portaluserlogoninfo')->count();
            $this->assertGreaterThan(0, $portalUsersCount, "portaluserlogoninfo table should not be empty after seeding.");

            // Assert users were created in standard table
            $usersCount = DB::table('users')->count();
            $this->assertGreaterThan(0, $usersCount, "users table should not be empty after seeding.");

            // Assert roles were assigned
            $userRolesCount = DB::table('userroles')->count();
            $this->assertGreaterThan(0, $userRolesCount, "userroles table should not be empty after seeding.");

            // Assert bonds were seeded
            $bondsCount = DB::table('statstable')->count();
            $this->assertGreaterThan(0, $bondsCount, "statstable table should not be empty after seeding.");

            // Assert portfolios were seeded
            $portfoliosCount = DB::table('portfolio')->count();
            $this->assertGreaterThan(0, $portfoliosCount, "portfolio table should not be empty after seeding.");

        } catch (\Exception $e) {
            $this->fail("Seeding failed: " . $e->getMessage());
        }
    }

    /**
     * Test Query Builder insertGetId compatibility.
     */
    public function test_query_builder_insert_get_id_legacy_compatibility()
    {
        // Test manual insert on a table with 'Id' primary key
        // Using users table as it's safe to test on, but verifying the logic
        try {
            $email = 'test_insert_' . uniqid() . '@example.com';
            $id = DB::table('portaluserlogoninfo')->insertGetId([
                'AccountId' => 'TEST-' . uniqid(),
                'FirstName' => 'Test',
                'Email' => $email,
                'IsActive' => true,
                'created_on' => now(),
            ], 'Id');

            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
            
            $record = DB::table('portaluserlogoninfo')->where('Id', $id)->first();
            $this->assertNotNull($record);
            $this->assertEquals($email, $record->Email);

        } catch (\Exception $e) {
            $this->fail("Manual insertGetId failed: " . $e->getMessage());
        }
    }
}
