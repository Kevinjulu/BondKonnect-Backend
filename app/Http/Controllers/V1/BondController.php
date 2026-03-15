<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Bond;
use App\Models\Portfolio;
use App\Services\BondMathService;
use App\Services\PortfolioService;
use App\Events\BondMarketUpdated;
use App\Http\Controllers\V1\Logs\ActivityLogging;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BondController extends Controller
{
    protected $bondMath;
    protected $portfolioService;

    public function __construct(BondMathService $bondMath, PortfolioService $portfolioService)
    {
        $this->bondMath = $bondMath;
        $this->portfolioService = $portfolioService;
    }

    /**
     * Display a listing of the bonds.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 50);
        $bonds = Bond::orderBy('MaturityDate', 'desc')->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $bonds
        ]);
    }

    /**
     * Display the specified bond.
     */
    public function show(string $id): JsonResponse
    {
        $bond = Bond::with('quotes')->find($id);

        if (!$bond) {
            return response()->json([
                'success' => false,
                'message' => 'Bond not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bond
        ]);
    }

    /**
     * Update bond price/yield and broadcast.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $bond = Bond::find($id);

        if (!$bond) {
            return response()->json(['success' => false, 'message' => 'Bond not found'], 404);
        }

        $validated = $request->validate([
            'SpotYield' => 'nullable|numeric',
            'DirtyPrice' => 'nullable|numeric',
        ]);

        $oldYield = $bond->SpotYield;
        $oldPrice = $bond->DirtyPrice;

        if (isset($validated['SpotYield'])) {
            $bond->SpotYield = $validated['SpotYield'];
            $bond->DirtyPrice = $this->bondMath->calculateBondPrice(
                $bond->SpotYield,
                (float)$bond->Coupon,
                (int)$bond->coupons_due ?? 10,
                (int)$bond->next_coupon_days ?? 182,
                (int)$bond->Basis ?? 364
            );
        } elseif (isset($validated['DirtyPrice'])) {
            $bond->DirtyPrice = $validated['DirtyPrice'];
            $bond->SpotYield = $this->bondMath->calculateBondYield(
                $bond->DirtyPrice,
                (float)$bond->Coupon,
                (int)$bond->coupons_due ?? 10,
                (int)$bond->next_coupon_days ?? 182,
                (int)$bond->Basis ?? 364
            );
        }

        $bond->save();

        // Audit Trail Logging
        try {
            $logger = new ActivityLogging();
            // In a real app, we'd use auth()->id(). For system/admin updates, we use a fallback or specific system ID.
            $userId = auth()->id();
            
            // If no user is logged in (e.g. API key or system task), we might use a dedicated System User ID.
            // For now, if we are in testing or no auth, let's use 1 as a fallback IF it exists.
            if (!$userId) {
                $userId = 1; // Default System/Admin User ID for logging system-level updates
            }

            $logger->logUserActivity(
                $userId,
                ActivityLogging::ACTIVITIES['MODIFY']['code'],
                'bond_price_update',
                [
                    'bond_issue' => $bond->BondIssueNo,
                    'old_yield' => $oldYield,
                    'new_yield' => $bond->SpotYield,
                    'old_price' => $oldPrice,
                    'new_price' => $bond->DirtyPrice,
                    'updated_by' => auth()->check() ? 'User' : 'System/Admin API'
                ],
                ActivityLogging::SEVERITIES['INFO']['level']
            );
        } catch (\Throwable $th) {
            Log::error('Failed to log bond update: ' . $th->getMessage());
        }

        event(new BondMarketUpdated($bond));

        return response()->json([
            'success' => true,
            'data' => $bond,
            'message' => 'Market data updated and broadcasted'
        ]);
    }

    /**
     * Get portfolio summary analytics.
     */
    public function getPortfolioSummary(string $id): JsonResponse
    {
        $summary = $this->portfolioService->getPortfolioSummary((int)$id);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
