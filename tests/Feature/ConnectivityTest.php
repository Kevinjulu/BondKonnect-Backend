<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectivityTest extends TestCase
{
    /**
     * Test the basic web health check endpoint.
     */
    public function test_web_health_check_returns_ok()
    {
        $this->withoutExceptionHandling();
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'time'
        ]);
        $response->assertJsonFragment(['status' => 'ok']);
    }

    /**
     * Test the API health check endpoint (checks database).
     */
    public function test_api_health_check_returns_ok()
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'request_id',
            'timestamp',
            'status',
            'connection',
            'host',
            'connections'
        ]);
        $response->assertJsonFragment([
            'status' => 'ok',
        ]);
        $response->assertJsonPath('connections.bk_db', 'connected');
    }

    /**
     * Test the API ping endpoint.
     */
    public function test_api_ping_returns_pong()
    {
        $response = $this->get('/api/ping');

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'pong']);
    }

    /**
     * Test CORS configuration for frontend connectivity.
     */
    public function test_cors_headers_are_present()
    {
        // Simulate a request from an allowed origin
        $origin = 'http://localhost:3000';
        
        $response = $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    /**
     * Test that the API base route returns the Laravel version.
     */
    public function test_api_root_returns_version()
    {
        $response = $this->get('/api');

        $response->assertStatus(200);
        $response->assertJsonStructure(['Laravel']);
    }
}
