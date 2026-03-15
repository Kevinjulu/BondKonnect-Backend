<?php

namespace App\Services;

use App\Models\UserRating;
use App\Models\UserCredibilityScore;
use App\Models\CredibilityScoreHistory;
use Illuminate\Validation\ValidationException;
use Exception;
use Carbon\Carbon;

class RatingService
{
    /**
     * Submit a new rating
     */
    public function submitRating(array $data): UserRating
    {
        // Validate rating data
        $this->validateRatingData($data);

        // Prevent self-rating
        if ($data['rater_id'] === $data['ratee_id']) {
            throw new Exception('Users cannot rate themselves');
        }

        // Check if rating for this transaction already exists
        if ($data['transaction_id'] ?? null) {
            $existing = UserRating::where('transaction_id', $data['transaction_id'])
                                  ->where('rater_id', $data['rater_id'])
                                  ->first();

            if ($existing) {
                throw new Exception('You have already rated this transaction');
            }
        }

        // Create rating
        $rating = new UserRating([
            'rater_id' => $data['rater_id'],
            'ratee_id' => $data['ratee_id'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'quote_id' => $data['quote_id'] ?? null,
            'reliability_rating' => $data['reliability_rating'] ?? null,
            'response_speed_rating' => $data['response_speed_rating'] ?? null,
            'professionalism_rating' => $data['professionalism_rating'] ?? null,
            'fairness_rating' => $data['fairness_rating'] ?? null,
            'settlement_rating' => $data['settlement_rating'] ?? null,
            'review_text' => $data['review_text'] ?? null,
            'tags' => $data['tags'] ?? [],
            'rating_status' => 'pending',
        ]);

        // Calculate overall rating
        $rating->overall_rating = $rating->calculateOverallRating();
        $rating->save();

        // Schedule publishing after 48 hours
        $this->schedulePublishing($rating);

        return $rating;
    }

    /**
     * Update an existing rating (within 30 days)
     */
    public function updateRating(UserRating $rating, array $data): UserRating
    {
        // Can only edit pending or newly published ratings (48 hours)
        $hoursOld = $rating->created_at->diffInHours(now());
        if ($hoursOld > 720) { // 30 days
            throw new Exception('Ratings can only be edited within 30 days of submission');
        }

        // Can only edit if not disputed
        if ($rating->rating_status === 'disputed') {
            throw new Exception('Cannot edit disputed ratings');
        }

        // Update allowed fields
        if (isset($data['reliability_rating'])) $rating->reliability_rating = $data['reliability_rating'];
        if (isset($data['response_speed_rating'])) $rating->response_speed_rating = $data['response_speed_rating'];
        if (isset($data['professionalism_rating'])) $rating->professionalism_rating = $data['professionalism_rating'];
        if (isset($data['fairness_rating'])) $rating->fairness_rating = $data['fairness_rating'];
        if (isset($data['settlement_rating'])) $rating->settlement_rating = $data['settlement_rating'];
        if (isset($data['review_text'])) $rating->review_text = $data['review_text'];
        if (isset($data['tags'])) $rating->tags = $data['tags'];

        // Recalculate overall rating
        $rating->overall_rating = $rating->calculateOverallRating();
        $rating->save();

        // Update credibility score for ratee
        if ($rating->rating_status === 'published') {
            $credibilityService = new CredibilityScoreService();
            $credibilityService->updateUserCredibility($rating->ratee_id, 'rating_updated');
        }

        return $rating;
    }

    /**
     * Get published ratings for a user
     */
    public function getUserRatings(int $userId, array $filters = []): array
    {
        $query = UserRating::where('ratee_id', $userId)->published();

        // Apply filters
        if ($filters['min_rating'] ?? null) {
            $query->where('overall_rating', '>=', $filters['min_rating']);
        }

        if ($filters['sort_by'] === 'recent') {
            $query->orderByDesc('published_at');
        } elseif ($filters['sort_by'] === 'highest_rated') {
            $query->orderByDesc('overall_rating');
        } elseif ($filters['sort_by'] === 'lowest_rated') {
            $query->orderBy('overall_rating');
        }

        $ratings = $query->paginate($filters['per_page'] ?? 20);

        return [
            'data' => $ratings->items(),
            'total' => $ratings->total(),
            'page' => $ratings->currentPage(),
            'per_page' => $ratings->perPage(),
        ];
    }

    /**
     * Get all ratings submitted by a user
     */
    public function getRatingsByUser(int $userId): array
    {
        return UserRating::where('rater_id', $userId)
                         ->published()
                         ->orderByDesc('published_at')
                         ->get()
                         ->toArray();
    }

    /**
     * Get rating for specific transaction
     */
    public function getRatingForTransaction(int $transactionId, int $raterId): ?UserRating
    {
        return UserRating::where('transaction_id', $transactionId)
                         ->where('rater_id', $raterId)
                         ->first();
    }

    /**
     * Check if user can rate a transaction
     */
    public function canRateTransaction(int $userId, int $transactionId): bool
    {
        // Check if user participated in transaction
        // This logic depends on your transaction table structure
        // For now, returning true as we need more context
        return true;
    }

    /**
     * Validate rating data
     */
    private function validateRatingData(array $data): void
    {
        $rules = [
            'rater_id' => 'required|integer|exists:users,id',
            'ratee_id' => 'required|integer|exists:users,id',
            'transaction_id' => 'nullable|integer',
        ];

        // At least one rating dimension must be provided
        $ratingDimensions = [
            'reliability_rating',
            'response_speed_rating',
            'professionalism_rating',
            'fairness_rating',
            'settlement_rating',
        ];

        $hasAtLeastOneRating = false;
        foreach ($ratingDimensions as $dimension) {
            if (isset($data[$dimension]) && $data[$dimension] !== null) {
                $rules[$dimension] = 'integer|between:1,5';
                $hasAtLeastOneRating = true;
            }
        }

        if (!$hasAtLeastOneRating) {
            throw new Exception('At least one rating dimension must be provided');
        }

        $rules['review_text'] = 'nullable|string|max:500';
        $rules['tags'] = 'nullable|array';
        $rules['tags.*'] = 'string';

        validator($data, $rules)->validate();
    }

    /**
     * Schedule publishing of rating after 48 hours
     */
    private function schedulePublishing(UserRating $rating): void
    {
        // In production, use a job queue
        // For now, we'll use a simple approach to publish immediately for demo
        // In real implementation, schedule a job to run after 48 hours
    }

    /**
     * Publish pending ratings that are ready (48+ hours old)
     */
    public function publishPendingRatings(): int
    {
        $readyRatings = UserRating::where('rating_status', 'pending')
                                   ->where('created_at', '<=', now()->subHours(48))
                                   ->get();

        $count = 0;
        foreach ($readyRatings as $rating) {
            $rating->rating_status = 'published';
            $rating->published_at = now();
            $rating->save();

            // Update credibility score
            $credibilityService = new CredibilityScoreService();
            $credibilityService->updateUserCredibility($rating->ratee_id, 'new_rating');

            $count++;
        }

        return $count;
    }

    /**
     * Get rating statistics for a user
     */
    public function getUserRatingStatistics(int $userId): array
    {
        $ratings = UserRating::where('ratee_id', $userId)->published()->get();

        if ($ratings->isEmpty()) {
            return [
                'total_ratings' => 0,
                'average_rating' => 0,
                'rating_distribution' => [],
                'dimension_averages' => [],
            ];
        }

        return [
            'total_ratings' => $ratings->count(),
            'average_rating' => round($ratings->avg('overall_rating'), 2),
            'rating_distribution' => $this->getRatingDistribution($ratings),
            'dimension_averages' => $this->getDimensionAverages($ratings),
        ];
    }

    /**
     * Get distribution of ratings
     */
    private function getRatingDistribution($ratings): array
    {
        $distribution = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        foreach ($ratings as $rating) {
            $star = (int) $rating->overall_rating;
            if ($star >= 1 && $star <= 5) {
                $distribution[$star]++;
            }
        }

        return $distribution;
    }

    /**
     * Get average ratings per dimension
     */
    private function getDimensionAverages($ratings): array
    {
        return [
            'reliability' => round($ratings->avg('reliability_rating'), 2),
            'response_speed' => round($ratings->avg('response_speed_rating'), 2),
            'professionalism' => round($ratings->avg('professionalism_rating'), 2),
            'fairness' => round($ratings->avg('fairness_rating'), 2),
            'settlement' => round($ratings->avg('settlement_rating'), 2),
        ];
    }
}
