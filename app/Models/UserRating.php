<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class UserRating extends Model
{
    use HasFactory;

    protected $table = 'user_ratings';

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
        'rater_id',
        'ratee_id',
        'transaction_id',
        'quote_id',
        'reliability_rating',
        'response_speed_rating',
        'professionalism_rating',
        'fairness_rating',
        'settlement_rating',
        'overall_rating',
        'review_text',
        'tags',
        'rating_status',
        'dispute_reason',
        'dispute_resolved_by',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who gave this rating
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * Get the user who received this rating
     */
    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    /**
     * Get the dispute associated with this rating (if any)
     */
    public function dispute()
    {
        return $this->hasOne(RatingDispute::class);
    }

    /**
     * Calculate average rating from all dimensions
     */
    public function calculateOverallRating(): float
    {
        $ratings = [];
        
        if ($this->reliability_rating) $ratings[] = $this->reliability_rating;
        if ($this->response_speed_rating) $ratings[] = $this->response_speed_rating;
        if ($this->professionalism_rating) $ratings[] = $this->professionalism_rating;
        if ($this->fairness_rating) $ratings[] = $this->fairness_rating;
        if ($this->settlement_rating) $ratings[] = $this->settlement_rating;

        if (empty($ratings)) {
            return 0;
        }

        return round(array_sum($ratings) / count($ratings), 2);
    }

    /**
     * Determine if this is a positive rating (>= 4 stars)
     */
    public function isPositive(): bool
    {
        return $this->overall_rating >= 4;
    }

    /**
     * Determine if this is a neutral rating (3 stars)
     */
    public function isNeutral(): bool
    {
        return $this->overall_rating === 3;
    }

    /**
     * Determine if this is a negative rating (<= 2 stars)
     */
    public function isNegative(): bool
    {
        return $this->overall_rating <= 2;
    }

    /**
     * Scope to get published ratings only
     */
    public function scopePublished($query)
    {
        return $query->where('rating_status', 'published')
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    /**
     * Scope to get ratings for a specific user (as ratee)
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('ratee_id', $userId)->published();
    }

    /**
     * Scope to get ratings by a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('rater_id', $userId);
    }

    /**
     * Scope to get undisputed ratings
     */
    public function scopeUndisputed($query)
    {
        return $query->whereNot('rating_status', 'disputed');
    }
}
