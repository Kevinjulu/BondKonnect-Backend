<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    protected $connection = 'bk_db';
    protected $table = 'portfolio';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $guarded = [];

    public function data(): HasMany
    {
        return $this->hasMany(PortfolioData::class, 'PortfolioId', 'Id');
    }
}
