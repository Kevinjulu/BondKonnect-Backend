<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsPathsTest extends TestCase
{
    public function test_broadcasting_auth_is_allowed_in_cors_paths()
    {
        $paths = config('cors.paths');

        $this->assertIsArray($paths);
        $this->assertContains('broadcasting/auth', $paths);
    }
}
