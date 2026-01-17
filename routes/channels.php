<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    Log::info('Channel auth request for notifications', [
        'requested_user_id' => $userId,
        'authenticated_user_id' => $user ? $user->id : null,
        'user_authenticated' => $user !== null,
    ]);

    // Check if user is authenticated and requesting their own channel
    if (! $user) {
        Log::warning('Unauthenticated user trying to access notifications channel');

        return false;
    }

    $canAccess = (int) $user->id === (int) $userId;
    Log::info('Notifications channel access', [
        'user_id' => $user->id,
        'requested_channel_user_id' => $userId,
        'access_granted' => $canAccess,
    ]);

    return $canAccess;
});

// Private channel for messages
Broadcast::channel('messages.{userId}', function ($user, $userId) {
    Log::info('Channel auth request for messages', [
        'requested_user_id' => $userId,
        'authenticated_user_id' => $user ? $user->id : null,
        'user_authenticated' => $user !== null,
    ]);

    // Check if user is authenticated and requesting their own channel
    if (! $user) {
        Log::warning('Unauthenticated user trying to access messages channel');

        return false;
    }

    $canAccess = (int) $user->id === (int) $userId;
    Log::info('Messages channel access', [
        'user_id' => $user->id,
        'requested_channel_user_id' => $userId,
        'access_granted' => $canAccess,
    ]);

    return $canAccess;
});
