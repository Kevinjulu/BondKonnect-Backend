<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    public function test_chat_endpoint_validation()
    {
        $response = $this->postJson('/api/V1/ai/chat', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['prompt']);
    }

    public function test_chat_endpoint_success()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Market Assistant Response']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/V1/ai/chat', [
            'prompt' => 'Tell me about IFB bonds',
            'context' => ['page' => 'Bond Stats']
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response['success']);
        $this->assertEquals('Market Assistant Response', $response['data']);
    }
}
