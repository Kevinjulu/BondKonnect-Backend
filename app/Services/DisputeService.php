<?php

namespace App\Services;

use App\Models\RatingDispute;
use App\Models\UserRating;
use Exception;

class DisputeService
{
    /**
     * File a dispute on a rating
     */
    public function fileDispute(int $ratingId, int $disputedBy, string $reason): RatingDispute
    {
        $rating = UserRating::find($ratingId);

        if (!$rating) {
            throw new Exception('Rating not found');
        }

        // Can only dispute published ratings
        if ($rating->rating_status !== 'published') {
            throw new Exception('Can only dispute published ratings');
        }

        // Cannot dispute your own rating
        if ($rating->rater_id === $disputedBy) {
            throw new Exception('You cannot dispute your own rating');
        }

        // Only the ratee can dispute a rating
        if ($rating->ratee_id !== $disputedBy) {
            throw new Exception('Only the rated user can dispute a rating');
        }

        // Check if dispute already exists
        $existingDispute = RatingDispute::where('rating_id', $ratingId)
                                        ->where('resolution_status', 'open')
                                        ->first();

        if ($existingDispute) {
            throw new Exception('A dispute for this rating is already open');
        }

        // Validate reason
        if (strlen($reason) < 10 || strlen($reason) > 500) {
            throw new Exception('Dispute reason must be between 10 and 500 characters');
        }

        // Create dispute
        $dispute = RatingDispute::create([
            'rating_id' => $ratingId,
            'disputed_by' => $disputedBy,
            'dispute_reason' => $reason,
            'resolution_status' => 'open',
        ]);

        // Update rating status to disputed
        $rating->rating_status = 'disputed';
        $rating->save();

        return $dispute;
    }

    /**
     * Resolve a dispute - uphold the rating
     */
    public function upholdRating(int $disputeId, int $resolvedBy, string $notes = ''): RatingDispute
    {
        $dispute = RatingDispute::find($disputeId);

        if (!$dispute) {
            throw new Exception('Dispute not found');
        }

        if ($dispute->resolution_status !== 'open') {
            throw new Exception('This dispute has already been resolved');
        }

        // Mark as upheld
        $dispute->markAsUpheld($resolvedBy);
        $dispute->resolution_notes = $notes;
        $dispute->save();

        // Rating status remains published (dispute is just noted)
        $dispute->rating->rating_status = 'published';
        $dispute->rating->save();

        return $dispute;
    }

    /**
     * Resolve a dispute - reverse the rating
     */
    public function reverseRating(int $disputeId, int $resolvedBy, string $notes = ''): RatingDispute
    {
        $dispute = RatingDispute::find($disputeId);

        if (!$dispute) {
            throw new Exception('Dispute not found');
        }

        if ($dispute->resolution_status !== 'open') {
            throw new Exception('This dispute has already been resolved');
        }

        // Mark as reversed
        $dispute->markAsReversed($resolvedBy);
        $dispute->resolution_notes = $notes;
        $dispute->save();

        // Update rating status to removed
        $rating = $dispute->rating;
        $rating->rating_status = 'removed';
        $rating->save();

        // Recalculate credibility score (remove this rating's impact)
        $credibilityService = new CredibilityScoreService();
        $credibilityService->updateUserCredibility($rating->ratee_id, 'disputed_rating_removed');

        return $dispute;
    }

    /**
     * Get open disputes (for admin)
     */
    public function getOpenDisputes(int $limit = 50): array
    {
        return RatingDispute::open()
                            ->with(['rating', 'disputedBy', 'resolvedBy'])
                            ->orderBy('created_at', 'asc')
                            ->limit($limit)
                            ->get()
                            ->map(function ($dispute) {
                                return $this->formatDispute($dispute);
                            })
                            ->toArray();
    }

    /**
     * Get all disputes
     */
    public function getAllDisputes(array $filters = []): array
    {
        $query = RatingDispute::with(['rating', 'disputedBy', 'resolvedBy']);

        if ($filters['status'] ?? null) {
            $query->where('resolution_status', $filters['status']);
        }

        if ($filters['user_id'] ?? null) {
            $query->where('disputed_by', $filters['user_id']);
        }

        $disputes = $query->orderBy('created_at', 'desc')
                          ->paginate($filters['per_page'] ?? 25);

        return [
            'data' => $disputes->map(fn ($d) => $this->formatDispute($d))->toArray(),
            'total' => $disputes->total(),
            'page' => $disputes->currentPage(),
            'per_page' => $disputes->perPage(),
        ];
    }

    /**
     * Get dispute details
     */
    public function getDisputeDetails(int $disputeId): ?array
    {
        $dispute = RatingDispute::with(['rating', 'disputedBy', 'resolvedBy'])->find($disputeId);

        if (!$dispute) {
            return null;
        }

        return $this->formatDispute($dispute);
    }

    /**
     * Format dispute data for API response
     */
    private function formatDispute(RatingDispute $dispute): array
    {
        $rating = $dispute->rating;

        return [
            'id' => $dispute->id,
            'rating_id' => $dispute->rating_id,
            'status' => $dispute->resolution_status,
            'reason' => $dispute->dispute_reason,
            'notes' => $dispute->resolution_notes,
            'disputed_by_id' => $dispute->disputed_by,
            'disputed_by_name' => $dispute->disputedBy->name ?? $dispute->disputedBy->email,
            'resolved_by_id' => $dispute->resolved_by,
            'resolved_by_name' => $dispute->resolvedBy?->name ?? $dispute->resolvedBy?->email,
            'created_at' => $dispute->created_at,
            'resolved_at' => $dispute->resolved_at,
            'rating_details' => [
                'id' => $rating->id,
                'rater_name' => $rating->rater->name ?? $rating->rater->email,
                'ratee_name' => $rating->ratee->name ?? $rating->ratee->email,
                'overall_rating' => $rating->overall_rating,
                'review_text' => $rating->review_text,
                'tags' => $rating->tags,
                'published_at' => $rating->published_at,
            ],
        ];
    }

    /**
     * Check if a rating has a pending dispute
     */
    public function hasOpenDispute(int $ratingId): bool
    {
        return RatingDispute::where('rating_id', $ratingId)
                            ->where('resolution_status', 'open')
                            ->exists();
    }

    /**
     * Get dispute statistics
     */
    public function getDisputeStatistics(): array
    {
        return [
            'total_disputes' => RatingDispute::count(),
            'open_disputes' => RatingDispute::where('resolution_status', 'open')->count(),
            'upheld_disputes' => RatingDispute::where('resolution_status', 'upheld')->count(),
            'reversed_disputes' => RatingDispute::where('resolution_status', 'reversed')->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime(),
        ];
    }

    /**
     * Calculate average time to resolve disputes (in hours)
     */
    private function calculateAverageResolutionTime(): float
    {
        $resolvedDisputes = RatingDispute::whereNotNull('resolved_at')
                                         ->get();

        if ($resolvedDisputes->isEmpty()) {
            return 0;
        }

        $totalHours = 0;
        foreach ($resolvedDisputes as $dispute) {
            $hours = $dispute->created_at->diffInHours($dispute->resolved_at);
            $totalHours += $hours;
        }

        return round($totalHours / $resolvedDisputes->count(), 2);
    }
}
