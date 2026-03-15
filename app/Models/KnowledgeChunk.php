<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'source_file',
        'section_title',
        'content',
        'embedding',
    ];

    /**
     * Vector similarity search scope.
     * Use Cosine Distance operator (<=>) in PostgreSQL pgvector.
     */
    public function scopeSearch(Builder $query, array $embedding, int $limit = 5): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $vector = '[' . implode(',', $embedding) . ']';
            $query->orderByRaw("embedding <=> ?::vector", [$vector])
                  ->limit($limit);
        } else {
            // No-op for other drivers (would need custom distance logic)
            $query->limit($limit);
        }
    }
}
