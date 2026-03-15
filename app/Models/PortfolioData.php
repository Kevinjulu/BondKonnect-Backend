<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioData extends Model
{
    protected $connection = 'bk_db';
    protected $table = 'portfoliodata';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $guarded = [];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class, 'PortfolioId', 'Id');
    }

    public function bond(): BelongsTo
    {
        return $this->belongsTo(Bond::class, 'BondId', 'Id');
    }
}
