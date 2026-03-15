<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Broadcasting\AuthController;

// Route::get('/', function () {
//     return ['Laravel' => app()->version()];
// });

// require __DIR__.'/auth.php';

Route::get('/', function () {
    return response()
        ->json([
            'status' => 'Connected',
            'message' => 'This is not the page you are looking for. Check the documentation for the API you are trying to access.',
        ], 200);
});

// Health check endpoint used by deployment/uptime monitors
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toDateTimeString()], 200);
});

// Broadcasting auth endpoint used by client-side Echo/Pusher
// Route::post('/broadcasting/auth', [AuthController::class, 'authenticate'])->name('broadcasting.auth');
