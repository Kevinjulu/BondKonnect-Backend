<?php

namespace App\Http\Controllers\V1\Ai;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Handle AI chat prompts from the user.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'context' => 'nullable|array', // Page user is on, etc.
        ]);

        $prompt = $request->input('prompt');
        $context = $request->input('context', []);
        
        // Add user info to context for logging
        $context['user_email'] = $request->user()?->email ?? 'guest';

        $result = $this->aiService->ask($prompt, $context);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'response' => $result['data'],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
        ], 500);
    }
}
