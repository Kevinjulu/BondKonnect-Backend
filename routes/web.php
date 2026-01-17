<?php

use Illuminate\Support\Facades\Route;

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
