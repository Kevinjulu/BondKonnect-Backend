<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Phase6MarketGraphSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2021-04-01');
        
        // 1. Seed GraphTable (Spot Yield Curve & Bands)
        $dates = [
            '2021-04-01', // Current
            '2021-03-25', // 1 week ago
            '2021-03-01', // 1 month ago
            '2020-04-01', // 1 year ago
        ];

        $years = [0.1, 0.5, 1, 2, 5, 10, 15, 20, 25, 30];

        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);
            foreach ($years as $yr) {
                // Generate some realistic-looking yield data
                $baseRate = 0.08 + ($yr * 0.003); // Upward sloping curve
                if ($dateString == '2020-04-01') $baseRate -= 0.01; // Lower a year ago
                
                DB::connection('bk_db')->table('graphtable')->insert([
                    'Date' => $date,
                    'Year' => (string)$yr,
                    'SpotRate' => (string)($baseRate + (rand(-10, 10) / 1000)),
                    'NseRate' => (string)($baseRate + 0.005),
                    'UpperBand' => (string)($baseRate + 0.02),
                    'LowerBand' => (string)($baseRate - 0.02),
                    'created_on' => Carbon::now(),
                ]);
            }
        }

        // 2. Seed ObiTable & YtmTable for Performance Graph
        // The graph shows "Bond Market Performance vs Economic Indicators"
        // It joins obitable and ytmtable on Date.
        
        $startDate = Carbon::parse('2020-04-01');
        $endDate = Carbon::parse('2021-04-01');
        
        $currentDate = $startDate->copy();
        $obiValue = 150.0;
        
        while ($currentDate <= $endDate) {
            // Trend OBITR upwards with some volatility
            $obiValue += rand(-5, 15) / 10;
            
            DB::connection('bk_db')->table('obitable')->insert([
                'Date' => $currentDate,
                'ObiTr' => (string)$obiValue,
                'created_on' => Carbon::now(),
            ]);

            // Ensure YTM Table has matching dates
            DB::connection('bk_db')->table('ytmtable')->updateOrInsert(
                ['Date' => $currentDate],
                [
                    'Cbr' => '13.0%',
                    'Inflation' => (string)(6.0 + (rand(-10, 10) / 10)),
                    'created_on' => Carbon::now(),
                ]
            );

            $currentDate->addWeek();
        }
    }
}
