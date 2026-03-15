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
     * Calculate financial values (Commissions, Levies, etc.)
     */
    public function calculateFinancialValues(float $faceValue, float $dirtyPrice, string $bondIssueNo, array $rates): array
    {
        $consideration = ($faceValue * $dirtyPrice) / 100;
        
        // NSE Commission
        $commissionNSE = $consideration * ($rates['nseCommission'] ?? 0.0008);
        $minCommission = $rates['nseMinCommission'] ?? 75.0;
        if ($commissionNSE < $minCommission) {
            $commissionNSE = $minCommission;
        }
        
        // CMA Levies
        $otherLevies = $consideration * ($rates['cmaLevies'] ?? 0.0002);
        
        // Tax (Simplified: IFB is tax-free)
        $isTaxFree = str_starts_with(strtoupper($bondIssueNo), 'IFB');
        $withholdingTax = 0; // Usually handled at coupon payment, but for trade it might be relevant for some contexts

        $totalPayable = $consideration + $commissionNSE + $otherLevies;
        $totalReceivable = $consideration - $commissionNSE - $otherLevies;

        return [
            'consideration' => round($consideration, 2),
            'commissionNSE' => round($commissionNSE, 2),
            'otherLevies' => round($otherLevies, 2),
            'withholdingTax' => round($withholdingTax, 2),
            'totalPayable' => round($totalPayable, 2),
            'totalReceivable' => round($totalReceivable, 2),
            'isTaxFree' => $isTaxFree
        ];
    }
}
