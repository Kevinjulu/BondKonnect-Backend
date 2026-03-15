<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    protected $embeddingUrl = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent';
    protected $bk_db;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->bk_db = DB::connection('bk_db');
    }

    /**
     * Generate vector embedding for a given text.
     */
    public function getEmbedding($text)
    {
        if (!$this->apiKey) {
            Log::error('AI Service: GEMINI_API_KEY is missing in .env');
            return null;
        }

        try {
            // Simplified request for v1beta embedContent
            $response = Http::post($this->embeddingUrl . '?key=' . $this->apiKey, [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['embedding']['values'])) {
                    return $data['embedding']['values'];
                }
            }
            
            Log::error('Gemini Embedding Error: Status ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Embedding Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function ask($prompt, $context = [])
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'AI Service API Key not configured.'
            ];
        }

        // 1. Vector Search: Find relevant knowledge chunks
        $embedding = $this->getEmbedding($prompt);
        $siteContext = "";
        if ($embedding) {
            $chunks = \App\Models\KnowledgeChunk::search($embedding, 3)->get();
            foreach ($chunks as $chunk) {
                $siteContext .= "KNOWLEDGE FROM WEBSITE ({$chunk->section_title}):\n{$chunk->content}\n\n";
            }
        }

        // 2. Gather real-time market context
        $marketContext = $this->getMarketContext();
        $systemPrompt = $this->getSystemPrompt($context, $marketContext, $siteContext);

        try {
            $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nUser Question: " . $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2, // Lower temperature for higher accuracy based on context
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if candidates exists and is not empty
                if (!isset($data['candidates']) || empty($data['candidates'])) {
                    // Check for safety ratings or other reasons for blocked content
                    $reason = $data['promptFeedback']['blockReason'] ?? 'Content blocked by safety filters or unknown reason.';
                    Log::warning('Gemini API returned no candidates. Reason: ' . $reason);
                    return [
                        'success' => false,
                        'message' => 'The AI assistant was unable to generate a response for this prompt due to safety restrictions.'
                    ];
                }

                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'I am sorry, I could not generate a response.';
                
                // Log the interaction
                $this->logInteraction($prompt, $text, $context);

                return [
                    'success' => true,
                    'data' => $text
                ];
            }

            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? 'Failed to communicate with AI service.';
            Log::error('Gemini API Error: ' . $response->status() . ' - ' . $response->body());
            
            return [
                'success' => false,
                'message' => 'AI Service Error: ' . $errorMessage
            ];

        } catch (\Exception $e) {
            Log::error('AI Service Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request.'
            ];
        }
    }

    protected function getMarketContext()
    {
        try {
            // Get top 5 bonds by Yield to Maturity (Spot Yield) from statstable
            $topBonds = $this->bk_db->table('statstable')
                ->orderBy('SpotYield', 'desc')
                ->limit(5)
                ->get();

            if ($topBonds->isEmpty()) {
                return "No active market data found in the statstable.";
            }

            $contextStr = "CURRENT LIVE MARKET DATA (NSE):\n";
            foreach ($topBonds as $bond) {
                // Safely access properties as array or object
                $issue = $bond->{'Bond Issue No'} ?? $bond->BondIssueNo ?? 'Unknown';
                $yield = $bond->SpotYield ?? 'N/A';
                $coupon = $bond->Coupon ?? 'N/A';
                $maturity = $bond->MaturityDate ?? 'N/A';
                $price = $bond->DirtyPrice ?? 'N/A';
                
                $contextStr .= "- {$issue}: Yield {$yield}%, Coupon {$coupon}%, Matures {$maturity}, Price {$price}\n";
            }

            return $contextStr;
        } catch (\Exception $e) {
            Log::warning('AI Context Fetch Error: ' . $e->getMessage());
            return "Market data currently unavailable due to system error.";
        }
    }

    protected function logInteraction($prompt, $response, $context)
    {
        try {
            $email = $context['user_email'] ?? 'anonymous';
            
            $this->bk_db->table('activitylogs')->insert([
                'NodeName' => 'AI_ASSISTANT',
                'IpAddress' => request()->ip(),
                'RequestMethod' => 'POST',
                'RequestUrl' => '/V1/ai/chat',
                'StatusCode' => '200',
                'Response' => substr("Q: $prompt | A: $response", 0, 500),
                'created_on' => now(),
                'Email' => $email
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI interaction: ' . $e->getMessage());
        }
    }

    protected function getSystemPrompt($context = [], $marketContext = '', $siteContext = '')
    {
        $currentPage = $context['page'] ?? 'Dashboard';

        return "You are 'BondKonnect AI Concierge', a specialized assistant for the BondKonnect platform.
        Your MISSION is to help users navigate and understand the BondKonnect website and the Kenyan Bond Market using ONLY the provided knowledge.

        STRICT RULES:
        1. NO EXTERNAL KNOWLEDGE: Only answer based on the 'WEBSITE KNOWLEDGE' and 'MARKET SNAPSHOT' below. 
        2. If the answer is NOT in the provided context, politely say: 'I apologize, but I only have information regarding BondKonnect and the Kenyan Bond Market. I cannot answer that question.'
        3. DO NOT talk about other websites, general news, or non-bond financial topics (e.g., crypto, stocks).
        4. When giving directions, use the exact Page Routes and UI names from the context.

        WEBSITE KNOWLEDGE (Source: Ground Truth):
        {$siteContext}

        REAL-TIME MARKET SNAPSHOT (Source: Live Data):
        {$marketContext}

        USER CONTEXT:
        - Current Page: {$currentPage}

        Format responses in clean Markdown. Use KES currency. Maintain a professional, terminal-like tone.";
    }
    }