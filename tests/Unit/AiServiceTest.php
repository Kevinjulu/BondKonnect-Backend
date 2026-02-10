<?php

namespace Tests\Unit;

use App\Services\AiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    public function test_ask_returns_success_with_valid_response()
    {
        // Mock the .env for the test
        config(['services.gemini.key' => 'test-key']);
        
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Test AI Response']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new AiService();
        // Manually set API key if env() fails in test environment
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('apiKey');
        $property->setAccessible(true);
        $property->setValue($service, 'test-key');

        $result = $service->ask('Hello', ['page' => 'Dashboard']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Test AI Response', $result['data']);
    }

    public function test_ask_returns_failure_on_api_error()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $service = new AiService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('apiKey');
        $property->setAccessible(true);
        $property->setValue($service, 'test-key');

        $result = $service->ask('Hello');

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to communicate with AI service.', $result['message']);
    }
}
