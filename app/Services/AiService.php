<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    protected $bk_db;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->bk_db = DB::connection('bk_db');
    }

    public function ask($prompt, $context = [])
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'AI Service API Key not configured.'
            ];
        }

        // Gather real-time market context
        $marketContext = $this->getMarketContext();
        $systemPrompt = $this->getSystemPrompt($context, $marketContext);

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
                    'temperature' => 0.7,
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

    protected function getSystemPrompt($context = [], $marketContext = '')
    {
        $currentPage = $context['page'] ?? 'Dashboard';
        
        return "You are 'BondKonnect AI', a professional financial assistant specializing in the Kenyan Bond Market (NSE).
        Your tone is professional, sophisticated, and helpful. You match the monochrome, high-performance 'Terminal' aesthetic of the BondKonnect platform.

        CORE KNOWLEDGE:
        - Expert on Kenyan Treasury Bonds (FXD, IFB, SSDK).
        - Expert on Bond Math: Clean/Dirty Price, Accrued Interest, YTM.
        - Tax knowledge: 15% for <10 yrs, 10% for >10 yrs, tax-free for IFBs.

        REAL-TIME MARKET SNAPSHOT:
        {$marketContext}

        USER CONTEXT:
        - The user is currently on the: {$currentPage}.
        - Help the user interpret the data they might be seeing on this page.

        GUIDELINES:
        - Be concise. Traders value speed.
        - Use the REAL-TIME MARKET SNAPSHOT data whenever relevant to be more accurate.
        - If asked about calculations, explain the logic.
        - NEVER give definitive financial advice. Always include a subtle disclaimer.
        - Use KES as primary currency.

        Keep responses formatted in clean Markdown.";
    }
}