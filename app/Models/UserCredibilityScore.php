<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCredibilityScore extends Model
{
    use HasFactory;

    protected $table = 'user_credibility_scores';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'total_ratings_count',
        'average_overall_rating',
        'rating_score',
        'activity_score',
        'verification_score',
        'settlement_score',
        'response_time_score',
        'credibility_index',
        'credibility_badge',
        'positive_rating_count',
        'neutral_rating_count',
        'negative_rating_count',
        'total_transactions',
        'total_transaction_volume',
        'last_transaction_date',
        'total_disputes',
        'resolved_disputes',
        'is_trusted',
        'is_new_user',
    ];

    protected $casts = [
        'last_transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this credibility score
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all ratings for this user
     */
    public function ratings(): HasMany
    {
        return $this->user->ratings();
    }

    /**
     * Get all rating history for this user
     */
    public function scoreHistory(): HasMany
    {
        return $this->hasMany(CredibilityScoreHistory::class, 'user_id', 'user_id');
    }

    /**
     * Update the credibility index and badge based on scores
     */
    public function updateCredibilityMetrics(): self
    {
        // Calculate weighted credibility index
        $this->credibility_index = (
            ($this->rating_score * 0.50) +
            ($this->activity_score * 0.20) +
            ($this->verification_score * 0.15) +
            ($this->settlement_score * 0.10) +
            ($this->response_time_score * 0.05)
        );

        // Assign badge based on credibility index
        $this->credibility_badge = $this->calculateBadge();
        $this->is_trusted = $this->credibility_index >= 75;

        return $this;
    }

    /**
     * Calculate the appropriate badge based on credibility index
     */
    private function calculateBadge(): string
    {
        if ($this->total_ratings_count < 3) {
            return 'unrated';
        }

        if ($this->credibility_index >= 90) {
            return 'platinum';
        } elseif ($this->credibility_index >= 75) {
            return 'gold';
        } elseif ($this->credibility_index >= 50) {
            return 'silver';
        } elseif ($this->credibility_index >= 25) {
            return 'bronze';
        }

        return 'unrated';
    }

    /**
     * Get sentiment distribution
     */
    public function getSentimentPercentages(): array
    {
        $total = $this->positive_rating_count + $this->neutral_rating_count + $this->negative_rating_count;

        if ($total === 0) {
            return [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ];
        }

        return [
            'positive' => round(($this->positive_rating_count / $total) * 100, 1),
            'neutral' => round(($this->neutral_rating_count / $total) * 100, 1),
            'negative' => round(($this->negative_rating_count / $total) * 100, 1),
        ];
    }

    /**
     * Get badge color for UI
     */
    public function getBadgeColor(): string
    {
        return match ($this->credibility_badge) {
            'platinum' => '#FFD700',
            'gold' => '#FFA500',
            'silver' => '#C0C0C0',
            'bronze' => '#CD7F32',
            default => '#808080',
        };
    }

    /**
     * Scope to get trusted users
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    /**
     * Scope to get new users
     */
    public function scopeNew($query)
    {
        return $query->where('is_new_user', true);
    }

    /**
     * Scope to get by badge
     */
    public function scopeByBadge($query, $badge)
    {
        return $query->where('credibility_badge', $badge);
    }
}
