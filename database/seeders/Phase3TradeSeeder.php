<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\V1\Defaults\StandardFunctions;

class Phase3TradeSeeder extends Seeder
{
    public function run(): void
    {
        $stdfn = new StandardFunctions();
        $now = Carbon::now();

        // Get some seeded data
        $bonds = DB::connection('bk_db')->table('statstable')->get();
        $investors = DB::connection('bk_db')->table('portaluserlogoninfo')
            ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
            ->where('userroles.Role', 2) // Individual
            ->select('portaluserlogoninfo.Id')
            ->get();
        $brokers = DB::connection('bk_db')->table('portaluserlogoninfo')
            ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
            ->where('userroles.Role', 5) // Broker
            ->select('portaluserlogoninfo.Id')
            ->get();

        if ($bonds->isEmpty() || $investors->isEmpty() || $brokers->isEmpty()) {
            return;
        }

        foreach ($bonds as $index => $bond) {
            // Create a Bid from an Investor
            $investor = $investors->random();
            $broker = $brokers->random();

            $quoteId = DB::connection('bk_db')->table('quotebook')->insertGetId([
                'PlacementNo' => $stdfn->generatePlacementNumber(),
                'AssignedBy' => $broker->Id,
                'ViewingParty' => $investor->Id,
                'BondIssueNo' => $bond->Id,
                'SettlementDate' => $now->addDays(3),
                'BidPrice' => $bond->DirtyPrice - 0.5,
                'BidYield' => $bond->SpotYield + 0.1,
                'BidAmount' => 1000000, // 1M KES
                'IsBid' => 1,
                'IsOffer' => 0,
                'IsActive' => 1,
                'created_on' => $now,
            ]);

            // Create an Offer from a Broker
            DB::connection('bk_db')->table('quotebook')->insert([
                'PlacementNo' => $stdfn->generatePlacementNumber(),
                'AssignedBy' => $broker->Id,
                'ViewingParty' => null, // Public offer
                'BondIssueNo' => $bond->Id,
                'SettlementDate' => $now->addDays(2),
                'OfferPrice' => $bond->DirtyPrice + 0.2,
                'OfferYield' => $bond->SpotYield - 0.05,
                'OfferAmount' => 5000000, // 5M KES
                'IsBid' => 0,
                'IsOffer' => 1,
                'IsActive' => 1,
                'created_on' => $now,
            ]);

            // Create a Transaction (Historical)
            if ($index % 2 == 0) {
                DB::connection('bk_db')->table('quotetransactions')->insert([
                    'QuoteId' => $quoteId,
                    'InitiatedBy' => $investor->Id,
                    'TransactionNo' => 'TXN-' . strtoupper(bin2hex(random_bytes(4))),
                    'BidAmount' => 1000000,
                    'BidYield' => $bond->SpotYield + 0.1,
                    'BidPrice' => $bond->DirtyPrice - 0.5,
                    'IsAccepted' => 1,
                    'IsPending' => 0,
                    'created_on' => $now->subMonths(1),
                ]);
            }
        }
    }
}
