<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            Phase1UserSeeder::class,
            Phase2MarketSeeder::class,
            Phase3TradeSeeder::class,
            Phase4FinancialSeeder::class,
            Phase5LogSeeder::class,
            Phase6MarketGraphSeeder::class,
        ]);
    }
}
