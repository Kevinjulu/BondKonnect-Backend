<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Seed test users into portaluserlogoninfo for development and QA.
     *
     * Credentials:
     *   Admin : admin@bondkonnect.test / password123
     *   Tester: test@bondkonnect.test  / password123
     */
    public function run(): void
    {
        $connection = DB::connection('bk_db');

        $users = [
            [
                'FirstName'   => 'Admin',
                'OtherNames'  => 'User',
                'Email'       => 'admin@bondkonnect.test',
                'PhoneNumber' => '0700000001',
                'role'        => 1, // Admin
                'password'    => 'password123',
            ],
            [
                'FirstName'   => 'Test',
                'OtherNames'  => 'User',
                'Email'       => 'test@bondkonnect.test',
                'PhoneNumber' => '0700000002',
                'role'        => 2, // Individual
                'password'    => 'password123',
            ],
        ];

        foreach ($users as $userData) {
            $email = $userData['Email'];

            // Check if the user already exists
            $existing = $connection->table('portaluserlogoninfo')
                ->where('Email', $email)
                ->first();

            if ($existing) {
                $this->command->info("User [{$email}] already exists — skipping creation.");
                $userId = $existing->Id;
            } else {
                // Generate a unique AccountId in the same format used across the codebase
                $accountId = 'BK' . date('Y') . rand(1000000000, 9999999999)
                    . substr(str_shuffle('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'), 0, 8);

                $userId = $connection->table('portaluserlogoninfo')->insertGetId([
                    'AccountId'   => $accountId,
                    'FirstName'   => $userData['FirstName'],
                    'OtherNames'  => $userData['OtherNames'],
                    'CompanyName' => null,
                    'Email'       => $email,
                    'PhoneNumber' => $userData['PhoneNumber'],
                    'CdsNo'       => null,
                    'IsLocal'     => true,
                    'IsForeign'   => false,
                    'IsActive'    => true,
                    'created_on'  => Carbon::now(),
                ], 'Id');

                $this->command->info("User [{$email}] created with ID: {$userId}.");
            }

            // Deactivate any existing passwords then set the current one
            $connection->table('portaluserpasswordshistory')
                ->where('User', $userId)
                ->update(['IsActive' => false]);

            $connection->table('portaluserpasswordshistory')->insert([
                'User'       => $userId,
                'Password'   => Hash::make($userData['password']),
                'IsActive'   => true,
                'created_on' => Carbon::now(),
            ]);

            $this->command->info("Password set for [{$email}].");

            // Assign role if not already assigned
            $hasRole = $connection->table('userroles')
                ->where('User', $userId)
                ->where('Role', $userData['role'])
                ->exists();

            if (!$hasRole) {
                $connection->table('userroles')->insert([
                    'User'       => $userId,
                    'Role'       => $userData['role'],
                    'created_on' => Carbon::now(),
                ]);

                $roleName = $userData['role'] === 1 ? 'Admin' : 'Individual';
                $this->command->info("Role [{$roleName}] assigned to [{$email}].");
            }
        }

        $this->command->info('UserSeeder complete.');
    }
}
