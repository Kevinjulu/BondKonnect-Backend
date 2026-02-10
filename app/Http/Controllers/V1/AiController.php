<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|max:1000',
            'context' => 'nullable|array',
            'context.page' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters.',
                'errors' => $validator->errors()
            ], 422);
        }

        $prompt = $request->input('prompt');
        $context = $request->input('context', []);
        
        // Add authenticated user details to context
        if ($request->has('user_email')) {
             $context['user_email'] = $request->input('user_email');
        }

        $result = $this->aiService->ask($prompt, $context);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }
}
