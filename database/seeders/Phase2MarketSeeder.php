<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Phase2MarketSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        // 1. Seed StatsTable (Secondary Market Instruments)
        // Removed 'IsActive' as it doesn't exist in the bkdec.sql schema for statstable
        $bonds = [
            [
                'BondIssueNo' => 'IFB1/2023/17',
                'IssueDate' => '2023-03-15',
                'MaturityDate' => '2040-03-15',
                'Coupon' => 14.399,
                'DirtyPrice' => 102.50,
                'SpotYield' => 13.85,
                'QuotedYield' => '13.85%',
                'DtmYrs' => 16.25,
                'Last91Days' => 45,
                'Otr' => 'OTR',
                'Duration' => 16.25,
                'MDuration' => 15.80,
                'ExpectedReturn' => 14.50,
                'ExpectedShortfall' => 0.05,
                'LqdRank' => 'HIGH',
                'Filter1' => 2,
            ],
            [
                'BondIssueNo' => 'FXD1/2024/03',
                'IssueDate' => '2024-01-10',
                'MaturityDate' => '2027-01-10',
                'Coupon' => 16.50,
                'DirtyPrice' => 100.10,
                'SpotYield' => 16.45,
                'QuotedYield' => '16.45%',
                'DtmYrs' => 2.85,
                'Last91Days' => 12,
                'Otr' => 'OTR',
                'Duration' => 2.85,
                'MDuration' => 2.75,
                'ExpectedReturn' => 16.80,
                'ExpectedShortfall' => 0.02,
                'LqdRank' => 'MED',
                'Filter1' => 2,
            ],
            [
                'BondIssueNo' => 'FXD1/2019/20',
                'IssueDate' => '2019-05-20',
                'MaturityDate' => '2039-05-20',
                'Coupon' => 12.87,
                'DirtyPrice' => 85.40,
                'SpotYield' => 15.20,
                'QuotedYield' => '15.20%',
                'DtmYrs' => 14.35,
                'Last91Days' => 8,
                'Otr' => 'OTR',
                'Duration' => 14.35,
                'MDuration' => 13.95,
                'ExpectedReturn' => 13.20,
                'ExpectedShortfall' => 0.08,
                'LqdRank' => 'LOW',
                'Filter1' => 2,
            ],
            [
                'BondIssueNo' => 'Safaricom/2025/MTN',
                'IssueDate' => '2021-10-01',
                'MaturityDate' => '2025-10-01',
                'Coupon' => 11.50,
                'DirtyPrice' => 99.50,
                'SpotYield' => 12.00,
                'QuotedYield' => '12.00%',
                'DtmYrs' => 0.65,
                'Last91Days' => 2,
                'Otr' => 'OTR',
                'Duration' => 0.65,
                'MDuration' => 0.62,
                'ExpectedReturn' => 11.80,
                'ExpectedShortfall' => 0.01,
                'LqdRank' => 'LOW',
                'Filter1' => 2,
            ],
        ];

        foreach ($bonds as $bond) {
            $bond['created_on'] = $now;
            DB::connection('bk_db')->table('statstable')->insert($bond);
        }

        // 2. Seed PrimaryMarketTable (Issuance Calendar)
        $primaryMarket = [
            [
                'BondIssueNo' => 'FXD1/2025/05',
                'IssueDate' => '2025-02-15',
                'MaturityDate' => '2030-02-15',
                'PricingMethod' => 'Auction',
                'DayCount' => 365,
                'created_on' => $now,
            ],
            [
                'BondIssueNo' => 'IFB1/2025/08',
                'IssueDate' => '2025-05-10',
                'MaturityDate' => '2033-05-10',
                'PricingMethod' => 'Fixed Rate',
                'DayCount' => 365,
                'created_on' => $now,
            ],
        ];

        foreach ($primaryMarket as $issue) {
            DB::connection('bk_db')->table('primarymarkettable')->insert($issue);
        }

        // 3. Seed YtmTable (Yield Curve / Market Indicators)
        DB::connection('bk_db')->table('ytmtable')->insert([
            'Date' => $now,
            'Inflation' => '6.3%',
            'Cbr' => '13.0%',
            'Level' => '14.5',
            'Slope' => '1.2',
            'created_on' => $now,
        ]);
    }
}