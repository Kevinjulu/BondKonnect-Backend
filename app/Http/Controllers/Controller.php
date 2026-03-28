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
        $isTesting = defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__') || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');
        dump('isTesting in Controller: ' . ($isTesting ? 'true' : 'false'));

        if ($name === 'bk_api_db') {
            return $this->bk_api_db = $isTesting ? DB::connection() : DB::connection('bk_api_db');
        }
        if ($name === 'bk_db') {
            return $this->bk_db = $isTesting ? DB::connection() : DB::connection('bk_db');
        }
        
        // Handle other property access if needed, or return null
        return null;
    }
}
