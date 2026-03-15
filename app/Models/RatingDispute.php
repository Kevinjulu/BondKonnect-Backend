<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingDispute extends Model
{
    use HasFactory;

    protected $table = 'rating_disputes';

    protected $fillable = [
        'rating_id',
        'disputed_by',
        'dispute_reason',
        'resolution_notes',
        'resolution_status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the rating associated with this dispute
     */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(UserRating::class);
    }

    /**
     * Get the user who raised the dispute
     */
    public function disputedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disputed_by');
    }

    /**
     * Get the admin who resolved the dispute
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Mark dispute as upheld
     */
    public function markAsUpheld(int $resolvedBy): self
    {
        $this->resolution_status = 'upheld';
        $this->resolved_by = $resolvedBy;
        $this->resolved_at = now();

        // Update rating status
        if ($this->rating) {
            $this->rating->rating_status = 'disputed';
            $this->rating->save();
        }

        return $this;
    }

    /**
     * Mark dispute as reversed (rating removed)
     */
    public function markAsReversed(int $resolvedBy): self
    {
        $this->resolution_status = 'reversed';
        $this->resolved_by = $resolvedBy;
        $this->resolved_at = now();

        // Update rating status to removed
        if ($this->rating) {
            $this->rating->rating_status = 'removed';
            $this->rating->save();
        }

        return $this;
    }

    /**
     * Scope to get open disputes
     */
    public function scopeOpen($query)
    {
        return $query->where('resolution_status', 'open');
    }

    /**
     * Scope to get resolved disputes
     */
    public function scopeResolved($query)
    {
        return $query->whereIn('resolution_status', ['upheld', 'reversed']);
    }
}
