<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

abstract class Controller
{
    // Do not define properties as they prevent __get from being called if they are null
    
    /**
     * Lazy load database connections
     */
    public function __get($name)
    {
        if ($name === 'bk_api_db') {
            return $this->bk_api_db = DB::connection('bk_api_db');
        }
        if ($name === 'bk_db') {
            return $this->bk_db = DB::connection('bk_db');
        }
        
        // Handle other property access if needed, or return null
        return null;
    }
}
