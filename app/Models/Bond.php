<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bond extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'bk_db';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'statstable';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the quotes for the bond.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'BondIssueNo', 'Id');
    }
}
