<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Http\Controllers\V1\Defaults\StandardFunctions;

class Phase1UserSeeder extends Seeder
{
    public function run(): void
    {
        $stdfn = new StandardFunctions();
        $now = Carbon::now();

        $users = [
            // Admin
            [
                'FirstName' => 'BondKonnect',
                'OtherNames' => 'Administrator',
                'Email' => 'admin@bondkonnect.com',
                'PhoneNumber' => '+254700000001',
                'CompanyName' => 'BondKonnect Ltd',
                'Role' => 1,
                'IsActive' => 1,
            ],
            // Issuers
            [
                'FirstName' => 'National',
                'OtherNames' => 'Treasury Kenya',
                'Email' => 'treasury@finance.go.ke',
                'PhoneNumber' => '+254202252299',
                'CompanyName' => 'The National Treasury',
                'Role' => 4,
                'IsActive' => 1,
            ],
            [
                'FirstName' => 'Safaricom',
                'OtherNames' => 'PLC',
                'Email' => 'investor-relations@safaricom.co.ke',
                'PhoneNumber' => '+254722000000',
                'CompanyName' => 'Safaricom PLC',
                'Role' => 4,
                'IsActive' => 1,
            ],
            // Investors
            [
                'FirstName' => 'James',
                'OtherNames' => 'Kamau',
                'Email' => 'james.kamau@gmail.com',
                'PhoneNumber' => '+254712345678',
                'CompanyName' => 'Individual Investor',
                'Role' => 2,
                'IsActive' => 1,
            ],
            [
                'FirstName' => 'Sarah',
                'OtherNames' => 'Ochieng',
                'Email' => 'sarah.ochieng@outlook.com',
                'PhoneNumber' => '+254723456789',
                'CompanyName' => 'Retail Investor',
                'Role' => 2,
                'IsActive' => 1,
            ],
            [
                'FirstName' => 'Standard',
                'OtherNames' => 'Investment Bank',
                'Email' => 'trading@sib.co.ke',
                'PhoneNumber' => '+254203633000',
                'CompanyName' => 'SIB Brokerage',
                'Role' => 5,
                'IsActive' => 1,
            ],
        ];

        foreach ($users as $userData) {
            $roleId = $userData['Role'];
            unset($userData['Role']);
            
            $userData['AccountId'] = $stdfn->generateUniqueAccountId();
            $userData['created_on'] = $now;
            $userData['IsLocal'] = 1;
            $userData['IsActive'] = 1;

            // Insert User
            $userId = DB::connection('bk_db')->table('portaluserlogoninfo')->insertGetId($userData);

            // Assign Role
            DB::connection('bk_db')->table('userroles')->insert([
                'User' => $userId,
                'Role' => $roleId,
                'created_on' => $now,
            ]);

            // Set Password (Default: BondKonnect@2026)
            DB::connection('bk_db')->table('portaluserpasswordshistory')->insert([
                'User' => $userId,
                'Password' => Hash::make('BondKonnect@2026'),
                'IsActive' => 1,
                'created_on' => $now,
            ]);
        }
    }
}