<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredibilityScoreHistory extends Model
{
    use HasFactory;

    protected $table = 'credibility_score_history';

    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'user_id',
        'previous_score',
        'new_score',
        'score_change_reason',
        'triggered_by_rating_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user associated with this history record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rating that triggered this score change
     */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(UserRating::class, 'triggered_by_rating_id');
    }

    /**
     * Get score change amount
     */
    public function getScoreChangeAmount(): ?float
    {
        if (is_null($this->previous_score)) {
            return $this->new_score;
        }

        return $this->new_score - $this->previous_score;
    }

    /**
     * Scope to get history for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->latest();
    }

    /**
     * Scope to get history by reason
     */
    public function scopeByReason($query, $reason)
    {
        return $query->where('score_change_reason', $reason);
    }
}
