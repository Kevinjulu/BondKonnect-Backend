<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BroadcastingAuthRouteTest extends TestCase
{
    public function test_broadcasting_auth_route_is_registered()
    {
        $this->assertTrue(Route::has('broadcasting.auth'));

        $route = Route::getRoutes()->getByName('broadcasting.auth');
        $this->assertNotNull($route);
        $this->assertTrue(in_array('POST', $route->methods(), true));
    }
}
