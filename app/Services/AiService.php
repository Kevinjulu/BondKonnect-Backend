<?php

namespace App\Services;

use App\Services\BondMathService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiService
{
    protected $apiKey;
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    protected $embeddingUrl = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent';
    protected $bk_db;
    protected $bondMathService;

    public function __construct(BondMathService $bondMathService)
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->bondMathService = $bondMathService;
        // Use default connection in testing to avoid multi-connection sqlite issues
        $isTesting = defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__') || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');
        $this->bk_db = $isTesting ? DB::connection() : DB::connection('bk_db');
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
        // 0. Tool-first handling: if the user is asking for a calculation or quick route guidance,
        // we can answer directly without calling the external Gemini model.
        $toolResponse = $this->tryToolIntent($prompt, $context);
        if ($toolResponse !== null) {
            return $toolResponse;
        }

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
                'ActivityType' => 'AI_ASSISTANT',
                'Action' => 'CHAT_INTERACTION',
                'IpAddress' => request()->ip(),
                'RequestMethod' => 'POST',
                'RequestUrl' => '/V1/ai/chat',
                'StatusCode' => '200',
                'Description' => substr("Q: $prompt | A: $response", 0, 500),
                'created_on' => now(),
                // 'Email' column is missing from migration, using Description to include email context if needed
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI interaction: ' . $e->getMessage());
        }
    }

    protected function tryToolIntent(string $prompt, array $context = []): ?array
    {
        $toolQuery = strtolower($prompt);

        if (preg_match('/\b(ytm|yield to maturity|yield.*maturity)\b/i', $toolQuery)) {
            $params = $this->extractBondYieldParameters($prompt);
            if ($params['dirty_price'] !== null && $params['coupon'] !== null) {
                return $this->runTool('bond_yield', $params);
            }
        }

        if (preg_match('/\b(bond price|dirty price|price.*bond|price.*yield)\b/i', $toolQuery)) {
            $params = $this->extractBondPriceParameters($prompt);
            if ($params['yield_tm'] !== null && $params['coupon'] !== null) {
                return $this->runTool('bond_price', $params);
            }
        }

        if (preg_match('/\b(bid|offer|quote adjustment|trading cost|trade cost|spread)\b/i', $toolQuery)) {
            $params = $this->extractBidAdjustmentParameters($prompt);
            if ($params['quote_bid_amount'] !== null && $params['quote_offer_amount'] !== null && ($params['requested_bid_amount'] !== null || $params['requested_offer_amount'] !== null)) {
                return $this->runTool('bid_adjustment', $params);
            }
        }

        return null;
    }

    public function runTool(string $tool, array $parameters = []): array
    {
        switch ($tool) {
            case 'bond_yield':
                return $this->runBondYieldTool($parameters);
            case 'bond_price':
                return $this->runBondPriceTool($parameters);
            case 'bid_adjustment':
                return $this->runBidAdjustmentTool($parameters);
            default:
                return [
                    'success' => false,
                    'message' => "Unsupported AI tool: {$tool}."
                ];
        }
    }

    protected function runBondYieldTool(array $parameters): array
    {
        $dirtyPrice = $this->normalizeFloat($parameters['dirty_price'] ?? null);
        $coupon = $this->normalizePercentage($parameters['coupon'] ?? null);
        $couponsDue = isset($parameters['coupons_due']) ? intval($parameters['coupons_due']) : 8;
        $nextCouponDays = isset($parameters['next_coupon_days']) ? intval($parameters['next_coupon_days']) : 182;

        if ($dirtyPrice === null || $coupon === null) {
            return [
                'success' => false,
                'message' => 'Insufficient data to compute YTM. Please provide dirty price and coupon rate.'
            ];
        }

        $ytm = $this->bondMathService->calculateBondYield($dirtyPrice, $coupon, $couponsDue, $nextCouponDays);
        $text = "Calculated Yield to Maturity (YTM): {$ytm}%.\n" .
                "Inputs used: Dirty Price={$dirtyPrice}, Coupon={$coupon}%, Coupons Due={$couponsDue}, Next Coupon Days={$nextCouponDays}.\n" .
                "This estimate is based on BondKonnect standard semi-annual cash flow assumptions.";

        return [
            'success' => true,
            'data' => $text
        ];
    }

    protected function runBondPriceTool(array $parameters): array
    {
        $yieldTM = $this->normalizePercentage($parameters['yield_tm'] ?? null);
        $coupon = $this->normalizePercentage($parameters['coupon'] ?? null);
        $couponsDue = isset($parameters['coupons_due']) ? intval($parameters['coupons_due']) : 8;
        $nextCouponDays = isset($parameters['next_coupon_days']) ? intval($parameters['next_coupon_days']) : 182;

        if ($yieldTM === null || $coupon === null) {
            return [
                'success' => false,
                'message' => 'Insufficient data to compute bond price. Please provide YTM and coupon rate.'
            ];
        }

        $price = $this->bondMathService->calculateBondPrice($yieldTM, $coupon, $couponsDue, $nextCouponDays);
        $text = "Calculated Dirty Price: KES {$price}.\n" .
                "Inputs used: YTM={$yieldTM}%, Coupon={$coupon}%, Coupons Due={$couponsDue}, Next Coupon Days={$nextCouponDays}.\n" .
                "Use this result to compare the current market price with BondKonnect bond analytics.";

        return [
            'success' => true,
            'data' => $text
        ];
    }

    protected function runBidAdjustmentTool(array $parameters): array
    {
        $quoteBidAmount = $this->normalizeFloat($parameters['quote_bid_amount'] ?? null);
        $quoteOfferAmount = $this->normalizeFloat($parameters['quote_offer_amount'] ?? null);
        $requestedBidAmount = $this->normalizeFloat($parameters['requested_bid_amount'] ?? null);
        $requestedOfferAmount = $this->normalizeFloat($parameters['requested_offer_amount'] ?? null);
        $isBidQuote = isset($parameters['is_bid_quote']) ? boolval($parameters['is_bid_quote']) : true;

        if ($quoteBidAmount === null || $quoteOfferAmount === null) {
            return [
                'success' => false,
                'message' => 'Please provide both quote bid and quote offer amounts for bid adjustment analysis.'
            ];
        }

        $adjustment = $this->bondMathService->calculateTransactionAdjustments(
            $isBidQuote,
            $quoteBidAmount,
            $quoteOfferAmount,
            $requestedBidAmount ?? 0,
            $requestedOfferAmount ?? 0
        );

        $text = "Bid / Offer Adjustment Summary:\n" .
                "- Quote Bid Amount: KES {$quoteBidAmount}\n" .
                "- Quote Offer Amount: KES {$quoteOfferAmount}\n" .
                "- Requested Bid Amount: KES " . ($requestedBidAmount !== null ? $requestedBidAmount : 'N/A') . "\n" .
                "- Requested Offer Amount: KES " . ($requestedOfferAmount !== null ? $requestedOfferAmount : 'N/A') . "\n" .
                "- Final Bid Amount: KES {$adjustment['finalBidAmount']}\n" .
                "- Final Offer Amount: KES {$adjustment['finalOfferAmount']}\n" .
                "- Additional Amount Required: KES {$adjustment['additionalAmount']}\n" .
                "- Requires new quote: " . ($adjustment['requiresNewQuote'] ? 'Yes' : 'No') . "\n" .
                "This analysis uses BondKonnect quote adjustment rules to estimate execution requirements.";

        return [
            'success' => true,
            'data' => $text
        ];
    }

    protected function extractBondYieldParameters(string $prompt): array
    {
        return [
            'dirty_price' => $this->extractValue($prompt, [
                '/dirty price\s*(?:is|=|:)?\s*([0-9]+(?:\.[0-9]+)?)/i',
                '/price\s*(?:is|=|:)?\s*([0-9]+(?:\.[0-9]+)?)/i'
            ]),
            'coupon' => $this->extractValue($prompt, [
                '/coupon\s*(?:rate)?\s*(?:is|=|:)?\s*([0-9]+(?:\.[0-9]+)?)/i',
                '/([0-9]+(?:\.[0-9]+)?)\s*%\s*coupon/i'
            ]),
            'coupons_due' => $this->extractInt($prompt, [
                '/([0-9]+)\s*(?:coupons|periods)\s*(?:remaining|due)/i',
                '/([0-9]+)\s*coupons/i'
            ]),
            'next_coupon_days' => $this->extractInt($prompt, [
                '/next coupon\s*(?:in)?\s*(?:is|=|:)?\s*([0-9]+)\s*days/i',
                '/([0-9]+)\s*days.*next coupon/i'
            ]),
        ];
    }

    protected function extractBondPriceParameters(string $prompt): array
    {
        return [
            'yield_tm' => $this->extractValue($prompt, [
                '/yield\s*(?:to maturity|tm)?\s*(?:is|=|:)?\s*([0-9]+(?:\.[0-9]+)?)/i',
                '/([0-9]+(?:\.[0-9]+)?)\s*%\s*yield/i'
            ]),
            'coupon' => $this->extractValue($prompt, [
                '/coupon\s*(?:rate)?\s*(?:is|=|:)?\s*([0-9]+(?:\.[0-9]+)?)/i',
                '/([0-9]+(?:\.[0-9]+)?)\s*%\s*coupon/i'
            ]),
            'coupons_due' => $this->extractInt($prompt, [
                '/([0-9]+)\s*(?:coupons|periods)\s*(?:remaining|due)/i'
            ]),
            'next_coupon_days' => $this->extractInt($prompt, [
                '/next coupon\s*(?:in)?\s*(?:is|=|:)?\s*([0-9]+)\s*days/i',
                '/([0-9]+)\s*days.*next coupon/i'
            ]),
        ];
    }

    protected function extractBidAdjustmentParameters(string $prompt): array
    {
        return [
            'quote_bid_amount' => $this->extractValue($prompt, [
                '/quote bid.*?([0-9]+(?:\.[0-9]+)?)/i',
                '/bid.*?([0-9]+(?:\.[0-9]+)?)/i'
            ]),
            'quote_offer_amount' => $this->extractValue($prompt, [
                '/quote offer.*?([0-9]+(?:\.[0-9]+)?)/i',
                '/offer.*?([0-9]+(?:\.[0-9]+)?)/i'
            ]),
            'requested_bid_amount' => $this->extractValue($prompt, [
                '/requested bid.*?([0-9]+(?:\.[0-9]+)?)/i'
            ]),
            'requested_offer_amount' => $this->extractValue($prompt, [
                '/requested offer.*?([0-9]+(?:\.[0-9]+)?)/i'
            ]),
            'is_bid_quote' => preg_match('/\b(bid quote|quote.*bid)\b/i', $prompt) === 1,
        ];
    }

    protected function extractValue(string $prompt, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                return floatval(str_replace(',', '', $matches[1] ?? ''));
            }
        }

        return null;
    }

    protected function extractInt(string $prompt, array $patterns): ?int
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                return intval($matches[1] ?? 0);
            }
        }

        return null;
    }

    protected function normalizeFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }

        return floatval(str_replace(',', '', $value));
    }

    protected function normalizePercentage($value): ?float
    {
        $numeric = $this->normalizeFloat($value);
        if ($numeric === null) {
            return null;
        }

        return $numeric <= 1 ? $numeric * 100 : $numeric;
    }

    protected function buildNavigationContext(): string
    {
        try {
            $chunks = \App\Models\KnowledgeChunk::where('source_file', 'ui_map.md')->get();
            if ($chunks->isEmpty()) {
                return "No route reference available.";
            }

            $result = [];
            foreach ($chunks as $chunk) {
                $result[] = strtoupper(trim($chunk->section_title)) . ":\n" . trim($chunk->content);
            }

            return implode("\n\n", $result);
        } catch (\Exception $e) {
            Log::warning('Navigation Context Error: ' . $e->getMessage());
            return "Route reference content currently unavailable.";
        }
    }

    protected function getSystemPrompt($context = [], $marketContext = '', $siteContext = '')
    {
        $currentPage = $context['page'] ?? 'Dashboard';
        $navigationContext = $this->buildNavigationContext();

        return "You are 'BondKonnect AI Concierge', a specialized assistant for the BondKonnect platform.
        Your MISSION is to help users navigate and understand the BondKonnect website and the Kenyan Bond Market using ONLY the provided knowledge.

        STRICT RULES:
        1. NO EXTERNAL KNOWLEDGE: Only answer based on the 'WEBSITE KNOWLEDGE', 'PAGE ROUTES', and 'MARKET SNAPSHOT' below.
        2. If the answer is NOT in the provided context, politely say: 'I apologize, but I only have information regarding BondKonnect and the Kenyan Bond Market. I cannot answer that question.'
        3. DO NOT talk about other websites, general news, or non-bond financial topics (e.g., crypto, stocks).
        4. When giving directions, use the exact Page Routes and UI names from the context.
        5. If the user asks for bond math or bid analysis, answer using BondKonnect calculator conventions and the available market data.

        WEBSITE KNOWLEDGE (Source: Ground Truth):
        {$siteContext}

        PAGE ROUTES AND NAVIGATION:
        {$navigationContext}

        REAL-TIME MARKET SNAPSHOT (Source: Live Data):
        {$marketContext}

        USER CONTEXT:
        - Current Page: {$currentPage}

        Format responses in clean Markdown. Use KES currency. Maintain a professional, terminal-like tone.";
    }
    }