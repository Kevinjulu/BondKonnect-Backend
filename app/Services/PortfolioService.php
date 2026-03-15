<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioData;
use App\Models\Bond;

class PortfolioService
{
    /**
     * Calculate Realized P&L for a portfolio position.
     * (Selling Price - Buying Price) * Face Value / 100
     */
    public function calculateRealizedPnL(float $buyingPrice, float $sellingPrice, float $faceValueSales): float
    {
        return ($sellingPrice - $buyingPrice) * ($faceValueSales / 100);
    }

    /**
     * Calculate Unrealized P&L for a portfolio position.
     * (Market Price - Buying Price) * Remaining Face Value / 100
     */
    public function calculateUnrealizedPnL(float $buyingPrice, float $marketPrice, float $faceValueBalance): float
    {
        return ($marketPrice - $buyingPrice) * ($faceValueBalance / 100);
    }

    /**
     * Calculate Weighted Portfolio Metrics.
     */
    public function getPortfolioSummary(int $portfolioId): array
    {
        $data = PortfolioData::where('PortfolioId', $portfolioId)->where('IsActive', 1)->get();
        
        if ($data->isEmpty()) {
            return [
                'totalPortfolioValue' => 0,
                'totalRealizedPnL' => 0,
                'totalUnrealizedPnL' => 0,
                'weightedAverageYield' => 0,
                'weightedAverageDuration' => 0,
            ];
        }

        $totalPortfolioValue = 0;
        $totalRealizedPnL = 0;
        $totalUnrealizedPnL = 0;
        
        // Sums for weighted averages
        $sumYield = 0;
        $sumDuration = 0;

        foreach ($data as $item) {
            $value = ($item->FaceValueBAL * $item->DirtyPrice) / 100;
            $totalPortfolioValue += $value;
            $totalRealizedPnL += $item->RealizedPNL;
            $totalUnrealizedPnL += $item->UnrealizedPNL;
            
            $sumYield += ($item->SpotYTM * $value);
            $sumDuration += ($item->Duration * $value);
        }

        return [
            'totalPortfolioValue' => round($totalPortfolioValue, 2),
            'totalRealizedPnL' => round($totalRealizedPnL, 2),
            'totalUnrealizedPnL' => round($totalUnrealizedPnL, 2),
            'weightedAverageYield' => $totalPortfolioValue > 0 ? round($sumYield / $totalPortfolioValue, 4) : 0,
            'weightedAverageDuration' => $totalPortfolioValue > 0 ? round($sumDuration / $totalPortfolioValue, 4) : 0,
        ];
    }
}
