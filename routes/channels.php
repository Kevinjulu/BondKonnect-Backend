<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    Log::info('Channel auth request for notifications (BYPASSED FOR TESTING)', [
        'requested_user_id' => $userId
    ]);

    // Temporarily bypass database check since DB is down
    return true; 
});

// Private channel for messages
Broadcast::channel('messages.{userId}', function ($user, $userId) {
    Log::info('Channel auth request for messages', [
        'requested_user_id' => $userId,
        'authenticated_user_id' => $user ? $user->id : null,
        'user_authenticated' => $user !== null
    ]);

    // Check if user is authenticated and requesting their own channel
    if (!$user) {
        Log::warning('Unauthenticated user trying to access messages channel');
        return false;
    }

    $canAccess = (int) $user->id === (int) $userId;
    Log::info('Messages channel access', [
        'user_id' => $user->id,
        'requested_channel_user_id' => $userId,
        'access_granted' => $canAccess
    ]);

    return $canAccess;
});
