<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User; // Assuming you have a User model connected to portaluserlogoninfo

class KenyaMockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Starting Kenya Mock Data Seeder...');
        
        $this->seedUsers();
        $this->seedBonds();
        $this->seedChartData();
        $this->seedPortfolios();

        $this->command->info('Kenya Mock Data Seeder finished.');
    }

    private function seedUsers()
    {
        $this->command->info('Seeding users...');

        // Truncate tables for a clean seed
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('portaluserlogoninfo')->truncate();
        DB::table('users')->truncate();
        DB::table('userroles')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = DB::table('roles')->pluck('Id', 'Name')->all();

        $users = [
            ['first' => 'John', 'last' => 'Mwangi', 'company' => 'Nairobi Capital', 'role' => 'Broker'],
            ['first' => 'Mary', 'last' => 'Wanjiru', 'company' => 'Kenya Power', 'role' => 'Issuer'],
            ['first' => 'James', 'last' => 'Omondi', 'company' => null, 'role' => 'Individual'],
            ['first' => 'David', 'last' => 'Kimani', 'company' => 'Safaricom PLC', 'role' => 'Issuer'],
            ['first' => 'Grace', 'last' => 'Akinyi', 'company' => 'Equity Bank', 'role' => 'Broker'],
            ['first' => 'Peter', 'last' => 'Njoroge', 'company' => null, 'role' => 'Individual'],
        ];

        foreach ($users as $userData) {
            $email = strtolower($userData['first'] . '.' . $userData['last'] . '@example.com');
            
            // 1. Create the main user record in the legacy table
            $userId = DB::table('portaluserlogoninfo')->insertGetId([
                'FirstName' => $userData['first'],
                'OtherNames' => $userData['last'],
                'CompanyName' => $userData['company'],
                'Email' => $email,
                'AccountId' => 'BK' . Carbon::now()->year . '-' . strtoupper(uniqid()),
                'PhoneNumber' => '07' . rand(10000000, 99999999),
                'CdsNo' => 'CDS' . rand(100000, 999999),
                'IsLocal' => true,
                'IsForeign' => false,
                'IsActive' => true,
                'created_on' => Carbon::now(),
            ]);

            // 2. Create the corresponding Laravel auth user
            DB::table('users')->insert([
                'name' => $userData['first'] . ' ' . $userData['last'],
                'email' => $email,
                'password' => Hash::make('password'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // 3. Assign the role
            if (isset($roles[$userData['role']])) {
                DB::table('userroles')->insert([
                    'User' => $userId,
                    'Role' => $roles[$userData['role']],
                    'created_on' => Carbon::now(),
                ]);
            }
        }
    }

    private function seedBonds()
    {
        $this->command->info('Seeding bonds into statstable...');
        DB::table('statstable')->truncate();

        $bonds = [
            [
                'name' => 'FXD1/2023/10', 'issue_date' => '2023-11-25', 'maturity_date' => '2033-11-24',
                'coupon' => 16.97, 'dtm_yrs' => 7.9,
            ],
            [
                'name' => 'IFB1/2024/07', 'issue_date' => '2024-02-14', 'maturity_date' => '2031-02-13',
                'coupon' => 18.46, 'dtm_yrs' => 5.8,
            ],
            [
                'name' => 'FXD1/2024/05', 'issue_date' => '2024-04-10', 'maturity_date' => '2029-04-09',
                'coupon' => 16.50, 'dtm_yrs' => 3.1,
            ],
            [
                'name' => 'KPLC-CORP-2027', 'issue_date' => '2020-06-01', 'maturity_date' => '2027-05-31',
                'coupon' => 13.50, 'dtm_yrs' => 1.2,
            ],
            [
                'name' => 'FXD2/2019/15', 'issue_date' => '2019-10-21', 'maturity_date' => '2034-10-20',
                'coupon' => 12.80, 'dtm_yrs' => 8.5,
            ]
        ];

        foreach ($bonds as $bond) {
            $spotYield = $bond['coupon'] + (rand(-50, 50) / 100);
            $dirtyPrice = 98 + (rand(-300, 300) / 100);
            
            DB::table('statstable')->insert([
                'BondIssueNo' => $bond['name'],
                'IssueDate' => Carbon::parse($bond['issue_date']),
                'MaturityDate' => Carbon::parse($bond['maturity_date']),
                'ValueDate' => Carbon::now(),
                'Coupon' => $bond['coupon'],
                'SpotYield' => $spotYield,
                'DirtyPrice' => $dirtyPrice,
                'QuotedYield' => (string)($spotYield + (rand(-10, 10) / 100)),
                'DtmYrs' => $bond['dtm_yrs'],
                'Duration' => $bond['dtm_yrs'] * 0.8,
                'MDuration' => $bond['dtm_yrs'] * 0.75,
                'Convexity' => rand(50, 150) / 10,
                'Dv01' => rand(100, 500) / 1000,
                'created_on' => Carbon::now(),
                'dola' => Carbon::now(),
            ]);
        }
    }

    private function seedChartData()
    {
        $this->command->info('Seeding chart data into graphtable...');
        DB::table('graphtable')->truncate();

        $today = Carbon::today();
        $ninetyDaysAgo = $today->copy()->subDays(90);

        // Let's start with a base rate for our mock data
        $currentSpotRate = 16.85;

        for ($date = $ninetyDaysAgo; $date->lte($today); $date->addDay()) {
            // Introduce some random daily fluctuation
            $currentSpotRate += (rand(-15, 15) / 100); // Fluctuate by up to 0.15%
            
            // Ensure the rate stays within a realistic band
            if ($currentSpotRate < 14) $currentSpotRate = 14.1;
            if ($currentSpotRate > 19) $currentSpotRate = 18.9;

            $nseRate = $currentSpotRate - (rand(5, 20) / 100); // NSE rate slightly lower

            DB::table('graphtable')->insert([
                'Date' => $date,
                'Year' => (string)$date->year,
                'SpotRate' => (string)round($currentSpotRate, 4),
                'NseRate' => (string)round($nseRate, 4),
                'UpperBand' => (string)round($currentSpotRate * 1.02, 4),
                'LowerBand' => (string)round($currentSpotRate * 0.98, 4),
                'created_on' => Carbon::now(),
                'dola' => Carbon::now(),
            ]);
        }
    }

    private function seedPortfolios()
    {
        $this->command->info('Seeding user portfolios...');
        DB::table('portfolio')->truncate();
        DB::table('portfoliodata')->truncate();

        $users = DB::table('portaluserlogoninfo')->get();
        $bonds = DB::table('statstable')->get();

        if ($users->isEmpty() || $bonds->isEmpty()) {
            $this->command->error('Cannot seed portfolios. No users or bonds found.');
            return;
        }

        // Give the first 3 users a portfolio
        foreach ($users->take(3) as $user) {
            $portfolioId = DB::table('portfolio')->insertGetId([
                'Name' => $user->FirstName . "'s Main Portfolio",
                'Description' => 'A mix of government and corporate bonds.',
                'ValueDate' => Carbon::now(),
                'UserId' => $user->Id,
                'created_on' => Carbon::now(),
            ]);

            // Add 2-3 random bonds to this portfolio
            foreach ($bonds->random(rand(2, 3)) as $bond) {
                $faceValue = rand(1, 5) * 100000;
                $buyingPrice = $bond->DirtyPrice - (rand(1, 50) / 100);

                DB::table('portfoliodata')->insert([
                    'PortfolioId' => $portfolioId,
                    'BondId' => $bond->Id,
                    'User' => $user->Id,
                    'Type' => 'Buy',
                    'BuyingDate' => Carbon::now()->subDays(rand(10, 365)),
                    'BuyingPrice' => $buyingPrice,
                    'FaceValueBuys' => $faceValue,
                    'ClosingPrice' => $bond->DirtyPrice,
                    'PortfolioValue' => $faceValue * ($bond->DirtyPrice / 100),
                    'CouponNET' => ($faceValue * $bond->Coupon / 100) * 0.85,
                    'UnrealizedPNL' => ($faceValue * ($bond->DirtyPrice / 100)) - ($faceValue * ($buyingPrice / 100)),
                    'created_on' => Carbon::now(),
                ]);
            }
        }
    }
}
