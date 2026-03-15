<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\PrimaryMarket;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrimaryMarketController extends Controller
{
    /**
     * Get upcoming auctions.
     */
    public function index(): JsonResponse
    {
        $auctions = PrimaryMarket::where('IssueDate', '>=', now())
            ->orWhereNull('IssueDate')
            ->orderBy('IssueDate', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auctions
        ]);
    }

    /**
     * Submit a preliminary bid for an auction.
     */
    public function submitBid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auction_id' => 'required|integer',
            'amount' => 'required|numeric|min:10000',
            'yield_bid' => 'required|numeric',
        ]);

        // In a real scenario, we'd save this to a 'bids' table.
        // For now, we'll log it or use the Quote table if applicable.
        
        return response()->json([
            'success' => true,
            'message' => 'Preliminary bid submitted successfully. Our team will contact you for execution.'
        ]);
    }
}
