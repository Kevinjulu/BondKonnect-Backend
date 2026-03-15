<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserSyncSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Syncing users from bk_db.portaluserlogoninfo to default.users...');

        $portalUsers = DB::connection('bk_db')->table('portaluserlogoninfo')->get();
        $syncedCount = 0;

        foreach ($portalUsers as $portalUser) {
            // Check if user already exists by email
            $existingUser = DB::table('users')->where('email', $portalUser->Email)->first();

            // Get the latest active password from bk_db
            $portalPassword = DB::connection('bk_db')->table('portaluserpasswordshistory')
                ->where('User', $portalUser->Id)
                ->where('IsActive', true)
                ->first();

            $password = $portalPassword ? $portalPassword->Password : bcrypt('BondKonnect@2026');

            $userData = [
                'portal_id' => $portalUser->Id,
                'name' => trim(($portalUser->FirstName ?? '') . ' ' . ($portalUser->OtherNames ?? '')),
                'email' => $portalUser->Email,
                'password' => $password,
                'email_verified_at' => $portalUser->IsActive ? Carbon::now() : null,
                'created_at' => $portalUser->created_on ?? Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            if ($existingUser) {
                DB::table('users')->where('id', $existingUser->id)->update($userData);
            } else {
                DB::table('users')->insert($userData);
            }
            $syncedCount++;
        }

        $this->command->info("Synced {$syncedCount} users.");
    }
}
