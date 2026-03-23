<?php

namespace App\Services;

use DateTime;
use DateInterval;
use Exception;

class BondMathService
{
    /**
     * Calculate business days between two dates.
     */
    public function addBusinessDays(DateTime $date, int $days): DateTime
    {
        $result = clone $date;
        $added = 0;
        while ($added < $days) {
            $result->add(new DateInterval('P1D'));
            if ($result->format('N') < 6) { // 1 (Mon) to 5 (Fri)
                $added++;
            }
        }
        return $result;
    }

    /**
     * Calculate the previous coupon date.
     */
    public function calculatePreviousCoupon(DateTime $issueDate, DateTime $settlementDate, int $basis = 364): DateTime
    {
        $couponIntervalDays = ($basis === 364) ? 182 : 182.5;
        $current = clone $issueDate;
        
        while ($current < $settlementDate) {
            $next = clone $current;
            $next->add(new DateInterval('P' . floor($couponIntervalDays) . 'D'));
            if ($next > $settlementDate) {
                break;
            }
            $current = $next;
        }
        
        return $current;
    }

    /**
     * Calculate the next coupon date.
     */
    public function calculateNextCouponDate(DateTime $issueDate, DateTime $settlementDate, DateTime $maturityDate, int $basis = 364): DateTime
    {
        $prev = $this->calculatePreviousCoupon($issueDate, $settlementDate, $basis);
        $couponIntervalDays = ($basis === 364) ? 182 : 182.5;
        
        $next = clone $prev;
        $next->add(new DateInterval('P' . floor($couponIntervalDays) . 'D'));
        
        return ($next > $maturityDate) ? $maturityDate : $next;
    }

    /**
     * Calculate accrued interest.
     */
    public function calculateAccruedInterest(DateTime $settlementDate, DateTime $prevCoupon, DateTime $nextCoupon, float $couponRate, int $basis = 364): float
    {
        $daysAccrued = $settlementDate->diff($prevCoupon)->days;
        return ($daysAccrued / $basis) * $couponRate;
    }

    /**
     * Calculate Bond Price (Dirty Price) using standard PV of Cash Flows.
     */
    public function calculateBondPrice(float $yieldTM, float $coupon, int $couponsDue, int $nextCouponDays, int $basis = 364): float
    {
        if ($yieldTM <= 0) return 100.0; // Avoid division by zero

        $y = $yieldTM / 100;
        $c = $coupon / 100;
        $f = 2; // Semi-annual
        $n = $couponsDue;
        $t = $nextCouponDays / ($basis / $f);
        
        // Price Formula: [C/y * (1 - 1/(1+y/f)^(n-1+t))] + [100 / (1+y/f)^(n-1+t)]
        $v = 1 / (1 + $y / $f);
        $exponent = $n - 1 + $t;
        
        $price = (($c * 100 / $y) * (1 - pow($v, $exponent))) + (100 * pow($v, $exponent));
        
        return round($price, 8);
    }

    /**
     * Calculate Bond Yield (YTM) using Newton-Raphson solver.
     */
    public function calculateBondYield(float $dirtyPrice, float $coupon, int $couponsDue, int $nextCouponDays, int $basis = 364): float
    {
        $y = $coupon / 100; // Initial guess
        if ($y <= 0) $y = 0.05;

        for ($i = 0; $i < 50; $i++) {
            $p = $this->calculateBondPrice($y * 100, $coupon, $couponsDue, $nextCouponDays, $basis);
            $diff = $p - $dirtyPrice;
            if (abs($diff) < 0.000001) break;
            
            // Derivative approximation
            $p2 = $this->calculateBondPrice(($y + 0.0001) * 100, $coupon, $couponsDue, $nextCouponDays, $basis);
            $dy = ($p2 - $p) / 0.0001;
            
            if ($dy == 0) break;
            $y = $y - $diff / $dy;
        }
        
        return round($y * 100, 6);
    }

    /**
     * Format the indicative range for a bond.
     */
    public function formatIndicativeRange(?float $spotYTM, ?float $spread): string
    {
        if ($spotYTM === null || $spread === null) {
            return "N/A";
        }

        $lowerBound = max(0, round(($spotYTM - $spread) * 100, 2));
        $upperBound = round(($spotYTM + $spread) * 100, 2);

        return number_format($lowerBound, 2) . '% - ' . number_format($upperBound, 2) . '%';
    }

    /**
     * Calculate the rate outlook based on YTM TR and Inflation.
     */
    public function calculateRateOutlook(float $ytmTr, float $inflation): float
    {
        return $ytmTr > $inflation ? ceil($ytmTr / 0.0025) * 0.0025 : $inflation;
    }

    /**
     * Calculate transaction adjustments for partial fills or over-fills.
     * Returns the adjusted amounts and any remaining balance for a new quote.
     */
    public function calculateTransactionAdjustments(
        bool $isBidQuote, 
        float $quoteBidAmount, 
        float $quoteOfferAmount, 
        float $requestedBidAmount, 
        float $requestedOfferAmount
    ): array {
        $result = [
            'finalBidAmount' => $requestedBidAmount,
            'finalOfferAmount' => $requestedOfferAmount,
            'additionalAmount' => 0,
            'requiresNewQuote' => false
        ];

        if ($isBidQuote) {
            // Original quote was a BID. Someone is OFFERING to it.
            if ($requestedOfferAmount > $quoteBidAmount) {
                $result['finalOfferAmount'] = $quoteBidAmount;
                $result['finalBidAmount'] = $requestedOfferAmount; // The full requested amount for the txn record
                $result['additionalAmount'] = $requestedOfferAmount - $quoteBidAmount;
                $result['requiresNewQuote'] = true;
            }
        } else {
            // Original quote was an OFFER. Someone is BIDDING to it.
            if ($requestedBidAmount > $quoteOfferAmount) {
                $result['finalBidAmount'] = $quoteOfferAmount;
                $result['finalOfferAmount'] = $requestedBidAmount; // The full requested amount for the txn record
                $result['additionalAmount'] = $requestedBidAmount - $quoteOfferAmount;
                $result['requiresNewQuote'] = true;
            }
        }

        return $result;
    }

    /**
     * Map bond data to a standard portfolio snapshot structure.
     */
    public function mapBondToPortfolioSnapshot(object $bond, array $params, int $userId): array
    {
        return [
            'PortfolioId' => $params['portfolio_id'],
            'BondId' => $bond->Id,
            'User' => $userId,
            'Type' => $params['type'],
            'BuyingDate' => $params['buying_date'],
            'SellingDate' => $params['selling_date'] ?? null,
            'BuyingPrice' => $params['buying_price'],
            'SellingPrice' => $params['selling_price'] ?? null,
            'BuyingWAP' => $params['buying_wap'],
            'SellingWAP' => $params['selling_wap'] ?? null,
            'FaceValueBuys' => $params['face_value_buys'],
            'FaceValueSales' => $params['face_value_sales'] ?? 0,
            'FaceValueBAL' => $params['face_value_bal'],
            'ClosingPrice' => $params['closing_price'],
            'CouponNET' => $params['coupon_net'],
            'NextCpnDays' => $params['next_cpn_days'],
            'RealizedPNL' => $params['realized_pnl'],
            'UnrealizedPNL' => $params['unrealized_pnl'],
            'OneYrTotalReturn' => $params['one_yr_total_return'],
            'PortfolioValue' => $params['portfolio_value'],
            'IsActive' => true,
            'SpotYTM' => $bond->SpotYield ?? 0,
            'Coupon' => $bond->Coupon ?? 0,
            'Duration' => $bond->Duration ?? 0,
            'MDuration' => $bond->MDuration ?? 0,
            'Dv01' => $bond->Dv01 ?? 0,
            'ExpectedShortfall' => $bond->ExpectedShortfall ?? 0,
            'DirtyPrice' => $bond->DirtyPrice ?? 0,
            'created_by' => $userId,
            'created_on' => now()
        ];
    }
}
