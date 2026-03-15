<?php

namespace App\Http\Controllers\V1\Ratings;

use App\Http\Controllers\Controller;
use App\Services\RatingService;
use App\Services\CredibilityScoreService;
use App\Services\DisputeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RatingsController extends Controller
{
    private RatingService $ratingService;
    private CredibilityScoreService $credibilityService;
    private DisputeService $disputeService;

    public function __construct(
        RatingService $ratingService,
        CredibilityScoreService $credibilityService,
        DisputeService $disputeService
    ) {
        $this->ratingService = $ratingService;
        $this->credibilityService = $credibilityService;
        $this->disputeService = $disputeService;
    }

    /**
     * Submit a new rating
     * POST /V1/ratings/submit-rating
     */
    public function submitRating(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'rater_id' => 'required|integer|exists:users,id',
                'ratee_id' => 'required|integer|exists:users,id',
                'transaction_id' => 'nullable|integer',
                'quote_id' => 'nullable|integer',
                'reliability_rating' => 'nullable|integer|between:1,5',
                'response_speed_rating' => 'nullable|integer|between:1,5',
                'professionalism_rating' => 'nullable|integer|between:1,5',
                'fairness_rating' => 'nullable|integer|between:1,5',
                'settlement_rating' => 'nullable|integer|between:1,5',
                'review_text' => 'nullable|string|max:500',
                'tags' => 'nullable|array',
            ]);

            $rating = $this->ratingService->submitRating($validated);

            return response()->json([
                'success' => true,
                'message' => 'Rating submitted successfully. It will be published after verification.',
                'data' => [
                    'id' => $rating->id,
                    'status' => $rating->rating_status,
                    'overall_rating' => $rating->overall_rating,
                    'created_at' => $rating->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update an existing rating
     * PUT /V1/ratings/{ratingId}/edit
     */
    public function updateRating(Request $request, int $ratingId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reliability_rating' => 'nullable|integer|between:1,5',
                'response_speed_rating' => 'nullable|integer|between:1,5',
                'professionalism_rating' => 'nullable|integer|between:1,5',
                'fairness_rating' => 'nullable|integer|between:1,5',
                'settlement_rating' => 'nullable|integer|between:1,5',
                'review_text' => 'nullable|string|max:500',
                'tags' => 'nullable|array',
            ]);

            $rating = \App\Models\UserRating::findOrFail($ratingId);
            $updatedRating = $this->ratingService->updateRating($rating, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'data' => [
                    'id' => $updatedRating->id,
                    'overall_rating' => $updatedRating->overall_rating,
                    'updated_at' => $updatedRating->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get published ratings for a user
     * GET /V1/ratings/user-credibility/{userId}
     */
    public function getUserCredibility(int $userId): JsonResponse
    {
        try {
            $profile = $this->credibilityService->getUserCredibilityProfile($userId);

            if (!$profile) {
                return response()->json([
                    'success' => true,
                    'message' => 'User has no credibility data yet',
                    'data' => [
                        'user_id' => $userId,
                        'credibility_index' => 0,
                        'credibility_badge' => 'unrated',
                        'is_new_user' => true,
                        'total_ratings_count' => 0,
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $profile
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all ratings for a user
     * GET /V1/ratings/user-ratings/{userId}
     */
    public function getUserRatings(Request $request, int $userId): JsonResponse
    {
        try {
            $filters = [
                'min_rating' => $request->input('min_rating'),
                'sort_by' => $request->input('sort_by', 'recent'),
                'per_page' => $request->input('per_page', 20),
            ];

            $ratings = $this->ratingService->getUserRatings($userId, $filters);

            return response()->json([
                'success' => true,
                'data' => $ratings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get rating statistics for a user
     * GET /V1/ratings/user-stats/{userId}
     */
    public function getUserRatingStats(int $userId): JsonResponse
    {
        try {
            $stats = $this->ratingService->getUserRatingStatistics($userId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * File a dispute on a rating
     * POST /V1/ratings/{ratingId}/dispute
     */
    public function disputeRating(Request $request, int $ratingId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'disputed_by' => 'required|integer|exists:users,id',
                'reason' => 'required|string|min:10|max:500',
            ]);

            $dispute = $this->disputeService->fileDispute(
                $ratingId,
                $validated['disputed_by'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispute filed successfully. An admin will review your case.',
                'data' => [
                    'dispute_id' => $dispute->id,
                    'rating_id' => $dispute->rating_id,
                    'status' => $dispute->resolution_status,
                    'created_at' => $dispute->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get open disputes (admin only)
     * GET /V1/ratings/admin/disputes?status=open
     */
    public function getDisputes(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'per_page' => $request->input('per_page', 25),
            ];

            $disputes = $this->disputeService->getAllDisputes($filters);

            return response()->json([
                'success' => true,
                'data' => $disputes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get single dispute details (admin only)
     * GET /V1/ratings/admin/disputes/{disputeId}
     */
    public function getDisputeDetails(int $disputeId): JsonResponse
    {
        try {
            $dispute = $this->disputeService->getDisputeDetails($disputeId);

            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $dispute
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resolve dispute - uphold the rating (admin only)
     * POST /V1/ratings/admin/disputes/{disputeId}/uphold
     */
    public function upholdRating(Request $request, int $disputeId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'resolved_by' => 'required|integer|exists:users,id',
                'notes' => 'nullable|string|max:500',
            ]);

            $dispute = $this->disputeService->upholdRating(
                $disputeId,
                $validated['resolved_by'],
                $validated['notes'] ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispute resolved - rating upheld',
                'data' => [
                    'dispute_id' => $dispute->id,
                    'status' => $dispute->resolution_status,
                    'resolved_at' => $dispute->resolved_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resolve dispute - reverse/remove the rating (admin only)
     * POST /V1/ratings/admin/disputes/{disputeId}/reverse
     */
    public function reverseRating(Request $request, int $disputeId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'resolved_by' => 'required|integer|exists:users,id',
                'notes' => 'nullable|string|max:500',
            ]);

            $dispute = $this->disputeService->reverseRating(
                $disputeId,
                $validated['resolved_by'],
                $validated['notes'] ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispute resolved - rating removed',
                'data' => [
                    'dispute_id' => $dispute->id,
                    'status' => $dispute->resolution_status,
                    'resolved_at' => $dispute->resolved_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get dispute statistics (admin only)
     * GET /V1/ratings/admin/dispute-stats
     */
    public function getDisputeStats(): JsonResponse
    {
        try {
            $stats = $this->disputeService->getDisputeStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish pending ratings (background job or scheduled task)
     * POST /V1/ratings/admin/publish-pending
     */
    public function publishPendingRatings(): JsonResponse
    {
        try {
            $count = $this->ratingService->publishPendingRatings();

            return response()->json([
                'success' => true,
                'message' => "Published $count pending ratings",
                'data' => ['published_count' => $count]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
