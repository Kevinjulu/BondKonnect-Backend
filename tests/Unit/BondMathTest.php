<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BondMathService;
use DateTime;

class BondMathTest extends TestCase
{
    private BondMathService $bondMath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bondMath = new BondMathService();
    }

    /**
     * Test basic Accrued Interest calculation.
     */
    public function test_accrued_interest_calculation()
    {
        $settlementDate = new DateTime('2026-03-15');
        $prevCoupon = new DateTime('2026-01-01');
        $nextCoupon = new DateTime('2026-07-01');
        $couponRate = 12.0; // 12%
        $basis = 364;

        $accrued = $this->bondMath->calculateAccruedInterest(
            $settlementDate,
            $prevCoupon,
            $nextCoupon,
            $couponRate,
            $basis
        );

        // (73 days elapsed / 364 basis) * 12% = ~2.406
        $this->assertEqualsWithDelta(2.40659, $accrued, 0.0001);
    }

    /**
     * Test Financial Levies application (Commissions and Taxes).
     */
    public function test_financial_levies_calculation()
    {
        $faceValue = 1000000; // 1M KES
        $dirtyPrice = 102.5;  // Trading at a premium
        $bondIssueNo = 'FXD1/2024/10';
        
        $rates = [
            'nseCommission' => 0.0008, // 0.08%
            'nseMinCommission' => 75.0,
            'cmaLevies' => 0.0002,     // 0.02%
        ];

        $values = $this->bondMath->calculateFinancialValues(
            $faceValue,
            $dirtyPrice,
            $bondIssueNo,
            $rates
        );

        // Consideration = (1,000,000 * 102.5) / 100 = 1,025,000
        $this->assertEquals(1025000, $values['consideration']);
        
        // NSE Commission = 1,025,000 * 0.0008 = 820
        $this->assertEquals(820, $values['commissionNSE']);
        
        // CMA Levies = 1,025,000 * 0.0002 = 205
        $this->assertEquals(205, $values['otherLevies']);
        
        // Total Payable = 1,025,000 + 820 + 205 = 1,026,025
        $this->assertEquals(1026025, $values['totalPayable']);
    }

    /**
     * Test that IFB (Infrastructure Bonds) are correctly identified as tax-free.
     */
    public function test_ifb_is_tax_free()
    {
        $rates = ['nseCommission' => 0.0008, 'cmaLevies' => 0.0002];
        
        $ifb = $this->bondMath->calculateFinancialValues(100000, 100, 'IFB1/2023/17', $rates);
        $fxd = $this->bondMath->calculateFinancialValues(100000, 100, 'FXD1/2024/05', $rates);

        $this->assertTrue($ifb['isTaxFree'], "IFB bonds should be tax-free.");
        $this->assertFalse($fxd['isTaxFree'], "FXD bonds should not be tax-free.");
    }
}
