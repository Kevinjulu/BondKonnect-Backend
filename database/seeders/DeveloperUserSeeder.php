<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DeveloperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'kevinjulu@gmail.com';
        $password = 'password';

        // Use the 'bk_db' connection
        $connection = DB::connection('bk_db');

        // Check if user exists
        $user = $connection->table('portaluserlogoninfo')
            ->where('Email', $email)
            ->first();

        if ($user) {
            $this->command->info("User {$email} already exists.");
            $userId = $user->Id;
        } else {
            // Generate AccountId
            $accountId = 'BL' . date('Y') . rand(1000000000, 9999999999) .
                         substr(str_shuffle('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'), 0, 10);

            // Insert User
            $userId = $connection->table('portaluserlogoninfo')->insertGetId([
                'created_on' => Carbon::now(),
                'AccountId' => $accountId,
                'FirstName' => 'Kevin',
                'OtherNames' => 'Julu',
                'CompanyName' => 'Developer Corp',
                'Email' => $email,
                'PhoneNumber' => '0700000000',
                'CdsNo' => 'DEV123',
                'IsLocal' => true,
                'IsForeign' => false,
                'IsActive' => true,
            ]);

            $this->command->info("User {$email} created with ID: {$userId}");
        }

        // Insert Password History (Active Password)
        // Deactivate old passwords
        $connection->table('portaluserpasswordshistory')
            ->where('User', $userId)
            ->update(['IsActive' => false]);

        $connection->table('portaluserpasswordshistory')->insert([
            'Password' => Hash::make($password),
            'IsActive' => true,
            'User' => $userId,
            'created_on' => Carbon::now(),
        ]);
        $this->command->info("Password set for {$email}");

        // Assign Role 2 (Individual) if not already assigned
        $hasRole = $connection->table('userroles')
            ->where('User', $userId)
            ->where('Role', 2)
            ->exists();

        if (!$hasRole) {
            $connection->table('userroles')->insert([
                'User' => $userId,
                'Role' => 2, // Individual
                'created_on' => Carbon::now(),
            ]);
            $this->command->info("Role 'Individual' assigned to {$email}");
        }
        
         // Assign Role 1 (Admin) as well for developer access
        $hasAdminRole = $connection->table('userroles')
            ->where('User', $userId)
            ->where('Role', 1)
            ->exists();

        if (!$hasAdminRole) {
            $connection->table('userroles')->insert([
                'User' => $userId,
                'Role' => 1, // Admin
                'created_on' => Carbon::now(),
            ]);
            $this->command->info("Role 'Admin' assigned to {$email}");
        }
    }
}
