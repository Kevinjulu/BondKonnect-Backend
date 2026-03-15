<?php

namespace App\Services;

use App\Models\UserCredibilityScore;
use App\Models\CredibilityScoreHistory;
use App\Models\UserRating;
use Carbon\Carbon;

class CredibilityScoreService
{
    /**
     * Update user credibility score
     */
    public function updateUserCredibility(int $userId, string $reason): UserCredibilityScore
    {
        $score = UserCredibilityScore::firstOrCreate(
            ['user_id' => $userId],
            [
                'credibility_index' => 0,
                'credibility_badge' => 'unrated',
                'is_new_user' => true,
            ]
        );

        // Store previous score
        $previousScore = $score->credibility_index;

        // Recalculate all components
        $this->updateRatingScore($score);
        $this->updateActivityScore($score);
        $this->updateVerificationScore($score);
        $this->updateSettlementScore($score);
        $this->updateResponseTimeScore($score);

        // Update the composite credibility index and badge
        $score->updateCredibilityMetrics();

        // Update is_new_user flag
        if ($score->total_transactions >= 3) {
            $score->is_new_user = false;
        }

        // Save the updated score
        $score->save();

        // Log the score change
        $this->recordScoreChange($userId, $previousScore, $score->credibility_index, $reason);

        return $score;
    }

    /**
     * Calculate rating score (0-100)
     */
    private function updateRatingScore(UserCredibilityScore $score): void
    {
        $ratings = UserRating::where('ratee_id', $score->user_id)->published()->get();

        if ($ratings->isEmpty()) {
            $score->rating_score = 0;
            $score->total_ratings_count = 0;
            $score->average_overall_rating = 0;
            return;
        }

        // Minimum 3 ratings for credibility
        if ($ratings->count() < 3) {
            $score->rating_score = 0;
            $score->total_ratings_count = $ratings->count();
            $score->average_overall_rating = $ratings->avg('overall_rating');
            return;
        }

        $score->total_ratings_count = $ratings->count();
        $score->average_overall_rating = round($ratings->avg('overall_rating'), 2);

        // Convert 1-5 star rating to 0-100 scale
        $score->rating_score = ($score->average_overall_rating / 5) * 100;

        // Calculate rating distribution
        $score->positive_rating_count = $ratings->where('overall_rating', '>=', 4)->count();
        $score->neutral_rating_count = $ratings->where('overall_rating', 3)->count();
        $score->negative_rating_count = $ratings->where('overall_rating', '<=', 2)->count();
    }

    /**
     * Calculate activity score (0-100)
     */
    private function updateActivityScore(UserCredibilityScore $score): void
    {
        // Score based on transaction count and volume
        // Max score at 100+ transactions or 50M+ KES volume
        
        $transactionCount = $score->total_transactions;
        $transactionVolume = $score->total_transaction_volume;

        // Base on transaction count (0-50 points)
        $countScore = min(50, ($transactionCount / 100) * 50);

        // Base on transaction volume (0-50 points)
        // 50M KES = max score
        $volumeScore = min(50, ($transactionVolume / 50000000) * 50);

        $score->activity_score = min(100, $countScore + $volumeScore);
    }

    /**
     * Calculate verification score (0, 30, or 100)
     */
    private function updateVerificationScore(UserCredibilityScore $score): void
    {
        // This would check against a verification/KYC status in the users table
        // For now, default to 0 (unverified)
        // Can be updated to 30 for email/ID verified, or 100 for full KYC
        $score->verification_score = 0; // To be implemented with actual verification logic
    }

    /**
     * Calculate settlement score (0-100)
     */
    private function updateSettlementScore(UserCredibilityScore $score): void
    {
        if ($score->total_transactions === 0) {
            $score->settlement_score = 0;
            return;
        }

        // Percentage of transactions settled without disputes
        $settledCount = $score->total_transactions - $score->total_disputes;
        $score->settlement_score = ($settledCount / $score->total_transactions) * 100;
    }

    /**
     * Calculate response time score (0-100)
     */
    private function updateResponseTimeScore(UserCredibilityScore $score): void
    {
        // This would be based on average message response time
        // For now, default to 50 (average)
        // Can be refined with actual messaging data
        $score->response_time_score = 50;
    }

    /**
     * Get user credibility profile
     */
    public function getUserCredibilityProfile(int $userId): ?array
    {
        $score = UserCredibilityScore::where('user_id', $userId)->first();

        if (!$score) {
            return null;
        }

        $ratings = UserRating::where('ratee_id', $userId)->published()->get();

        return [
            'user_id' => $userId,
            'credibility_index' => $score->credibility_index,
            'credibility_badge' => $score->credibility_badge,
            'badge_color' => $score->getBadgeColor(),
            'is_trusted' => $score->is_trusted,
            'is_new_user' => $score->is_new_user,
            'total_ratings_count' => $score->total_ratings_count,
            'average_overall_rating' => $score->average_overall_rating,
            'rating_score' => $score->rating_score,
            'activity_score' => $score->activity_score,
            'verification_score' => $score->verification_score,
            'settlement_score' => $score->settlement_score,
            'response_time_score' => $score->response_time_score,
            'sentiment_distribution' => $score->getSentimentPercentages(),
            'total_transactions' => $score->total_transactions,
            'total_transaction_volume' => $score->total_transaction_volume,
            'total_disputes' => $score->total_disputes,
            'resolved_disputes' => $score->resolved_disputes,
            'last_transaction_date' => $score->last_transaction_date,
            'recent_ratings' => $ratings->take(5)->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'rater_name' => $rating->rater->name ?? $rating->rater->email,
                    'overall_rating' => $rating->overall_rating,
                    'review_text' => $rating->review_text,
                    'tags' => $rating->tags,
                    'published_at' => $rating->published_at,
                ];
            })->toArray(),
        ];
    }

    /**
     * Record a credibility score change in history
     */
    private function recordScoreChange(int $userId, float $previousScore, float $newScore, string $reason, int $ratingId = null): void
    {
        CredibilityScoreHistory::create([
            'user_id' => $userId,
            'previous_score' => $previousScore ?: null,
            'new_score' => $newScore,
            'score_change_reason' => $reason,
            'triggered_by_rating_id' => $ratingId,
            'created_at' => now(),
        ]);
    }

    /**
     * Get credibility score history for a user
     */
    public function getCredibilityHistory(int $userId, int $limit = 50): array
    {
        return CredibilityScoreHistory::where('user_id', $userId)
                                      ->latest()
                                      ->limit($limit)
                                      ->get()
                                      ->map(function ($history) {
                                          return [
                                              'id' => $history->id,
                                              'date' => $history->created_at,
                                              'previous_score' => $history->previous_score,
                                              'new_score' => $history->new_score,
                                              'change_amount' => $history->getScoreChangeAmount(),
                                              'reason' => $history->score_change_reason,
                                              'triggered_by_rating_id' => $history->triggered_by_rating_id,
                                          ];
                                      })
                                      ->toArray();
    }

    /**
     * Calculate recency-weighted credibility score
     * Gives more weight to recent ratings: 70% recent 50, 20% mid 50, 10% older
     */
    public function calculateRecencyWeightedScore(int $userId): float
    {
        $score = UserCredibilityScore::where('user_id', $userId)->first();
        if (!$score) {
            return 0;
        }

        $ratings = UserRating::where('ratee_id', $userId)
            ->published()
            ->orderByDesc('created_at')
            ->get();

        if ($ratings->count() === 0) {
            return 0;
        }

        // Split ratings into three groups
        $recentRatings = $ratings->slice(0, 50);
        $midRatings = $ratings->slice(50, 50);
        $olderRatings = $ratings->slice(100);

        // Calculate average for each group
        $recent50Avg = $recentRatings->count() > 0 
            ? ($recentRatings->avg('overall_rating') / 5) * 100 
            : 0;
        
        $mid50Avg = $midRatings->count() > 0 
            ? ($midRatings->avg('overall_rating') / 5) * 100 
            : 0;
        
        $olderAvg = $olderRatings->count() > 0 
            ? ($olderRatings->avg('overall_rating') / 5) * 100 
            : 0;

        // Apply weighting: 70% recent, 20% mid, 10% older
        $weightedScore = ($recent50Avg * 0.70) + ($mid50Avg * 0.20) + ($olderAvg * 0.10);

        // Update score record
        $score->update([
            'recent_50_average' => round($recent50Avg, 2),
            'mid_50_average' => round($mid50Avg, 2),
            'older_average' => round($olderAvg, 2),
            'recency_weighted_score' => round($weightedScore, 2),
            'recency_weighted_updated_at' => now(),
        ]);

        return $weightedScore;
    }

    /**
     * Calculate improvement trend indicator
     * Shows if user is improving (↑), stable (→), or declining (↓)
     */
    public function calculateTrendIndicator(int $userId): array
    {
        $score = UserCredibilityScore::where('user_id', $userId)->first();
        if (!$score) {
            return [
                'trend_direction' => 'stable',
                'improvement_trend' => '→',
                'last_6_months_change' => 0,
                'observation_status' => 'normal',
            ];
        }

        // Get ratings from last 6 months and before
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        
        $recentMonthRatings = UserRating::where('ratee_id', $userId)
            ->published()
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        $olderMonthRatings = UserRating::where('ratee_id', $userId)
            ->published()
            ->where('created_at', '<', $sixMonthsAgo)
            ->get();

        $recentMonthAvg = $recentMonthRatings->count() > 0 
            ? $recentMonthRatings->avg('overall_rating') 
            : 0;
        
        $olderMonthAvg = $olderMonthRatings->count() > 0 
            ? $olderMonthRatings->avg('overall_rating') 
            : 0;

        // Calculate change
        $change = $recentMonthAvg - $olderMonthAvg;
        $trendScore = ($change / 5) * 100; // Convert to 0-100 scale

        // Determine trend direction
        if ($change >= 0.5) {
            $direction = 'improving';
            if ($change >= 1.5) {
                $trend = '↑'; // Strong improvement
            } else {
                $trend = '↗'; // Slight improvement
            }
        } elseif ($change <= -0.5) {
            $direction = 'declining';
            if ($change <= -1.5) {
                $trend = '↓'; // Strong decline
            } else {
                $trend = '↘'; // Slight decline
            }
        } else {
            $direction = 'stable';
            $trend = '→';
        }

        // Determine observation status based on trend
        $observationStatus = 'normal';
        $observationNotes = null;
        
        if ($direction === 'declining' && $change <= -1.5) {
            $observationStatus = 'watch';
            $observationNotes = "User showing declining trend in recent ratings. Last 6 months average: " . round($recentMonthAvg, 2) . "/5";
        } elseif ($direction === 'declining' && $change <= -0.5 && $change > -1.5) {
            $observationStatus = 'observation';
            $observationNotes = "User showing slight declining trend. Monitor for patterns.";
        } elseif ($direction === 'improving') {
            $observationStatus = 'normal';
            $observationNotes = "User demonstrating improvement trajectory. Recovery path indicated.";
        }

        // Update score record
        $score->update([
            'trend_direction' => $direction,
            'improvement_trend' => $trend,
            'last_6_months_change' => round($trendScore, 2),
            'observation_status' => $observationStatus,
            'observation_notes' => $observationNotes,
            'trend_calculated_at' => now(),
        ]);

        return [
            'trend_direction' => $direction,
            'improvement_trend' => $trend,
            'last_6_months_change' => round($trendScore, 2),
            'observation_status' => $observationStatus,
            'observation_notes' => $observationNotes,
            'recent_6m_average' => round($recentMonthAvg, 2),
            'older_6m_average' => round($olderMonthAvg, 2),
        ];
    }

    /**
     * Get comprehensive trust metrics for decision support
     */
    public function getTrustMetrics(int $userId): array
    {
        $score = UserCredibilityScore::where('user_id', $userId)->first();
        
        if (!$score) {
            return [
                'credibility_index' => 0,
                'recency_weighted_score' => 0,
                'credibility_badge' => 'unrated',
                'trend' => $this->calculateTrendIndicator($userId),
                'observation_status' => 'normal',
                'is_new_user' => true,
                'total_ratings' => 0,
                'positive_percentage' => 0,
            ];
        }

        // Recalculate to ensure fresh data
        $this->calculateRecencyWeightedScore($userId);
        $trend = $this->calculateTrendIndicator($userId);

        $positivePercentage = $score->total_ratings_count > 0
            ? round(($score->positive_rating_count / $score->total_ratings_count) * 100, 1)
            : 0;

        return [
            'credibility_index' => $score->credibility_index,
            'recency_weighted_score' => $score->recency_weighted_score,
            'credibility_badge' => $score->credibility_badge,
            'trend' => $trend,
            'observation_status' => $score->observation_status,
            'observation_notes' => $score->observation_notes,
            'is_new_user' => $score->is_new_user,
            'total_ratings' => $score->total_ratings_count,
            'positive_percentage' => $positivePercentage,
            'recent_50_average' => $score->recent_50_average,
            'mid_50_average' => $score->mid_50_average,
            'older_average' => $score->older_average,
            'is_trusted' => $score->is_trusted,
        ];
    }
}
