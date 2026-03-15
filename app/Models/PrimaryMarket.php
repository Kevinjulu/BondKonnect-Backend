<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaryMarket extends Model
{
    protected $connection = 'bk_db';
    protected $table = 'primarymarkettable';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $guarded = [];
}
