<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Phase4FinancialSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Get Investors
        $investors = DB::connection('bk_db')->table('portaluserlogoninfo')
            ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
            ->where('userroles.Role', 2)
            ->select('portaluserlogoninfo.Id', 'portaluserlogoninfo.Email')
            ->get();

        $bonds = DB::connection('bk_db')->table('statstable')->get();

        if ($investors->isEmpty() || $bonds->isEmpty()) {
            return;
        }

        // Get Brokers
        $brokers = DB::connection('bk_db')->table('portaluserlogoninfo')
            ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
            ->where('userroles.Role', 5)
            ->select('portaluserlogoninfo.Id', 'portaluserlogoninfo.Email')
            ->get();

        foreach ($brokers as $broker) {
            // Create Active Subscription for middleware
            DB::connection('bk_db')->table('subscriptions')->updateOrInsert(
                ['User' => $broker->Id],
                [
                    'PlanId' => 3, // Enterprise/Broker
                    'DueDate' => $now->addMonth(),
                    'AmountPaid' => 5000.00,
                    'SubscriptionStatus' => 1, // ACTIVE
                    'created_on' => $now,
                ]
            );

            // Self-sponsorship for brokers
            DB::connection('bk_db')->table('portalintermediary')->updateOrInsert(
                ['User' => $broker->Id, 'IntermediaryId' => $broker->Id],
                ['IsActive' => 1, 'created_on' => $now]
            );
        }

        foreach ($investors as $investor) {
            // 1. Create Portfolio for each investor
            $portfolioId = DB::connection('bk_db')->table('portfolio')->insertGetId([
                'Name' => 'Main Portfolio',
                'UserId' => $investor->Id,
                'ValueDate' => $now,
                'created_on' => $now,
            ]);

            // 2. Add some Bonds to Portfolio
            $selectedBonds = $bonds->random(min(2, $bonds->count()));
            foreach ($selectedBonds as $bond) {
                DB::connection('bk_db')->table('portfoliodata')->insert([
                    'PortfolioId' => $portfolioId,
                    'BondId' => $bond->Id,
                    'User' => $investor->Id,
                    'Type' => 'Buy',
                    'BuyingDate' => $now->subMonths(6),
                    'BuyingPrice' => $bond->DirtyPrice - 2.0,
                    'FaceValueBAL' => 500000,
                    'ClosingPrice' => $bond->DirtyPrice,
                    'UnrealizedPNL' => 10000,
                    'IsActive' => 1,
                    'created_on' => $now,
                ]);
            }

            // 3. Create Active Subscription for middleware
            DB::connection('bk_db')->table('subscriptions')->insert([
                'User' => $investor->Id,
                'PlanId' => 1, // Basic/Pro
                'DueDate' => $now->addMonth(),
                'AmountPaid' => 1500.00,
                'SubscriptionStatus' => 1, // ACTIVE
                'created_on' => $now,
            ]);

            // 4. Create Broker Sponsorship (Self-sponsorship or assigned)
            $broker = DB::connection('bk_db')->table('portaluserlogoninfo')
                ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
                ->where('userroles.Role', 5)
                ->select('portaluserlogoninfo.Id')
                ->first();

            if ($broker) {
                DB::connection('bk_db')->table('portalintermediary')->insert([
                    'User' => $investor->Id,
                    'IntermediaryId' => $broker->Id,
                    'IsActive' => 1,
                    'created_on' => $now,
                ]);
            }

            // 5. Create mock payments (include user_id when available)
            $userRecord = DB::table('users')->whereRaw('LOWER(email) = ?', [trim(strtolower($investor->Email))])->first();
            $userId = $userRecord ? $userRecord->id : null;

            DB::table('payments')->insert([
                [
                    'user_email' => $investor->Email,
                    'user_id' => $userId,
                    'plan_id' => 1,
                    'payment_method' => 'mpesa',
                    'amount' => 1500.00,
                    'currency' => 'KES',
                    'status' => 'completed',
                    'reference' => 'QK' . strtoupper(bin2hex(random_bytes(4))),
                    'created_at' => $now->subDays(10),
                    'updated_at' => $now->subDays(10),
                ],
                [
                    'user_email' => $investor->Email,
                    'user_id' => $userId,
                    'plan_id' => 2,
                    'payment_method' => 'paypal',
                    'amount' => 50.00,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))),
                    'created_at' => $now->subMonths(1),
                    'updated_at' => $now->subMonths(1),
                ]
            ]);
        }
    }
}
