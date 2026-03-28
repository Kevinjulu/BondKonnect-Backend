<?php

namespace Tests\Unit;

use App\Services\AiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\DB;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we already set up config in TestCase, we can just instantiate AiService
        $this->aiService = new AiService();
    }

    public function test_get_embedding_returns_values_on_success()
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent*' => Http::response([
                'embedding' => [
                    'values' => [0.1, 0.2, 0.3]
                ]
            ], 200)
        ]);

        $embedding = $this->aiService->getEmbedding('test text');

        $this->assertEquals([0.1, 0.2, 0.3], $embedding);
    }

    public function test_ask_returns_success_with_text()
    {
        // Setup some mock market data
        DB::table('statstable')->insert([
            'BondIssueNo' => 'IFB1/2023/17',
            'SpotYield' => 17.5,
            'Coupon' => 17.0,
            'MaturityDate' => '2040-01-01',
            'DirtyPrice' => 101.5
        ]);

        // Mock embedding call and generateContent call
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent*' => Http::response([
                'embedding' => [
                    'values' => array_fill(0, 768, 0.1)
                ]
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'This is a mock response from Gemini.']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->aiService->ask('What is the yield of IFB1/2023/17?');

        $this->assertTrue($response['success']);
        $this->assertEquals('This is a mock response from Gemini.', $response['data']);
        
        // Verify activity log
        $this->assertDatabaseHas('activitylogs', [
            'ActivityType' => 'AI_ASSISTANT',
            'Action' => 'CHAT_INTERACTION',
            'RequestMethod' => 'POST'
        ]);
    }

    public function test_ask_handles_empty_candidates_from_api()
    {
         Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent*' => Http::response([
                'embedding' => [
                    'values' => array_fill(0, 768, 0.1)
                ]
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent*' => Http::response([
                'candidates' => [],
                'promptFeedback' => ['blockReason' => 'SAFETY']
            ], 200)
        ]);

        $response = $this->aiService->ask('Safety violation');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('The AI assistant was unable to generate a response', $response['message']);
    }

    public function test_ask_handles_api_failure()
    {
         Http::fake([
            '*' => Http::response(['error' => ['message' => 'API Error']], 500)
        ]);

        $response = $this->aiService->ask('Error query');

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('AI Service Error', $response['message']);
    }
}
