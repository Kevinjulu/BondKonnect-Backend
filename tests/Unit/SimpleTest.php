<?php

namespace Tests\Unit;

use Tests\TestCase;

class SimpleTest extends TestCase
{
    public function test_simple()
    {
        $this->assertTrue(true);
    }

    public function test_bk_db_driver()
    {
        dump('APP_ENV env: ' . env('APP_ENV'));
        dump('DB_CONNECTION env: ' . env('DB_CONNECTION'));
        dump('database.default config: ' . config('database.default'));
        dd(config('database.connections.bk_db'));
        $this->assertEquals('sqlite', \Illuminate\Support\Facades\DB::connection('bk_db')->getDriverName());
    }
}
