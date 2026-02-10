<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    // Cache durations in minutes
    const USER_CACHE_DURATION = 60; // 1 hour
    const NOTIFICATIONS_CACHE_DURATION = 15; // 15 minutes
    const MESSAGES_CACHE_DURATION = 10; // 10 minutes
    const USER_ROLES_CACHE_DURATION = 120; // 2 hours
    const GENERAL_DATA_CACHE_DURATION = 30; // 30 minutes

    /**
     * Get cached user details
     */
    public static function getUserDetails($email)
    {
        $cacheKey = "user_details:" . md5($email);

        return Cache::remember($cacheKey, self::USER_CACHE_DURATION, function () use ($email) {
            $db = DB::connection('bk_db');
            return $db->table('portaluserlogoninfo')
                ->where('Email', $email)
                ->first();
        });
    }

    /**
     * Get cached user role
     */
    public static function getUserRole($userId)
    {
        $cacheKey = "user_role:" . $userId;

        return Cache::remember($cacheKey, self::USER_ROLES_CACHE_DURATION, function () use ($userId) {
            $db = DB::connection('bk_db');
            return $db->table('userroles')
                ->where('User', $userId)
                ->value('Role');
        });
    }

    /**
     * Get cached notifications for a user
     */
    public static function getUserNotifications($userId, $unreadOnly = false)
    {
        $cacheKey = "user_notifications:" . $userId . ($unreadOnly ? ':unread' : ':all');

        return Cache::remember($cacheKey, self::NOTIFICATIONS_CACHE_DURATION, function () use ($userId, $unreadOnly) {
            $db = DB::connection('bk_db');
            $query = $db->table('notificationservices as ns')
                ->join('notificationtypes as nt', 'ns.Type', '=', 'nt.Id')
                ->leftJoin('portaluserlogoninfo as nu', 'ns.ActionRecipientId', '=', 'nu.Id')
                ->where('ns.Recipient', $userId);

            if ($unreadOnly) {
                $query->where('ns.IsRead', 0);
            }

            return $query->orderBy('ns.created_on', 'desc')
                ->select([
                    'ns.Id as notification_id',
                    'ns.Type as notification_type',
                    'ns.Message as notification_message',
                    'ns.created_on as notification_date',
                    'ns.Link as notification_link',
                    'ns.IsRead as IsRead',
                    'ns.IsArchive as IsArchive',
                    'ns.IsFavorite as IsFavorite',
                    'nu.Email as client_email',
                ])
                ->get();
        });
    }

    /**
     * Get cached messages for a user
     */
    public static function getUserMessages($userId, $roleId)
    {
        $cacheKey = "user_messages:" . $userId . ":" . $roleId;

        return Cache::remember($cacheKey, self::MESSAGES_CACHE_DURATION, function () use ($userId, $roleId) {
            $db = DB::connection('bk_db');

            if ($roleId == 1) {
                // Admin - get all messages not created by them
                $messages = $db->table('Message')
                    ->where('created_by', '!=', $userId)
                    ->where('IsRead', 0)
                    ->orderBy('created_on', 'desc')
                    ->get();
            } else {
                // Regular user - get messages assigned to them
                $messages = $db->table('Message')
                    ->where('AssignedTo', $userId)
                    ->where('IsRead', 0)
                    ->orderBy('created_on', 'desc')
                    ->get();
            }

            // Process each message to get thread information
            foreach ($messages as $message) {
                // Get message creator details
                $message->created_by = $db->table('portaluserlogoninfo')
                    ->where('Id', $message->created_by)
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // Get assigned user details
                $message->assigned_to = $db->table('portaluserlogoninfo')
                    ->where('Id', $message->AssignedTo)
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // Get thread information
                $thread = $db->table('messagereplies')
                    ->where('MessageId', $message->Id)
                    ->orderBy('created_on', 'desc')
                    ->first();

                if ($thread) {
                    $thread->created_by = $db->table('portaluserlogoninfo')
                        ->where('Id', $thread->created_by)
                        ->select('Id', 'Email', 'FirstName', 'OtherNames')
                        ->first();

                    $message->Description = $thread->ReplyDescription;
                    $message->created_on = $thread->created_on;
                    $message->created_by = $thread->created_by;
                    $message->is_thread_message = true;
                    $message->thread_count = $db->table('messagereplies')
                        ->where('MessageId', $message->Id)
                        ->count();
                } else {
                    $message->is_thread_message = false;
                    $message->thread_count = 0;
                }
            }

            return $messages;
        });
    }

    /**
     * Get cached message thread
     */
    public static function getMessageThread($messageId)
    {
        $cacheKey = "message_thread:" . $messageId;

        return Cache::remember($cacheKey, self::MESSAGES_CACHE_DURATION, function () use ($messageId) {
            $db = DB::connection('bk_db');

            // Fetch the message
            $message = $db->table('Message')->where('Id', $messageId)->first();
            if (!$message) {
                return null;
            }

            // Create thread collection starting with the initial message
            $thread = collect([
                [
                    'Id' => $message->Id,
                    'Description' => $message->Description,
                    'created_on' => $message->created_on,
                    'created_by' => $db->table('portaluserlogoninfo')
                        ->where('Id', $message->created_by)
                        ->select('Id', 'Email', 'FirstName', 'OtherNames')
                        ->first(),
                    'attachments' => $db->table('SystemRefDocuments')
                        ->select('Id', 'DocumentId', 'PageId', 'LocationUrl', 'DocumentName')
                        ->where('MessageId', $message->Id)
                        ->get(),
                    'is_initial_message' => true
                ]
            ]);

            // Fetch all replies
            $replies = $db->table('messagereplies')
                ->where('MessageId', $messageId)
                ->orderBy('created_on', 'asc')
                ->get()
                ->map(function($reply) use ($db) {
                    return [
                        'Id' => $reply->Id,
                        'Description' => $reply->ReplyDescription,
                        'created_on' => $reply->created_on,
                        'created_by' => $db->table('portaluserlogoninfo')
                            ->where('Id', $reply->created_by)
                            ->select('Id', 'Email', 'FirstName', 'OtherNames')
                            ->first(),
                        'attachments' => $db->table('SystemRefDocuments')
                            ->select('Id', 'DocumentId', 'PageId', 'LocationUrl', 'DocumentName')
                            ->where('MessageReplyId', $reply->Id)
                            ->get(),
                        'is_initial_message' => false
                    ];
                });

            return $thread->concat($replies);
        });
    }

    /**
     * Get cached message participants
     */
    public static function getMessageParticipants()
    {
        $cacheKey = "message_participants";

        return Cache::remember($cacheKey, self::GENERAL_DATA_CACHE_DURATION, function () {
            $db = DB::connection('bk_db');

            // Get regular users
            $participants = $db->table('userroles as ur')
                ->join('portaluserlogoninfo as pu', 'ur.User', '=', 'pu.Id')
                ->where('ur.Role', '!=', 1)
                ->select('ur.Id', 'pu.Id as details_id', 'pu.Email as details_email',
                        'pu.FirstName as details_first_name', 'pu.OtherNames as details_other_names')
                ->get()
                ->map(function($participant) {
                    return [
                        'Id' => $participant->Id,
                        'details' => [
                            'Id' => $participant->details_id,
                            'Email' => $participant->details_email,
                            'FirstName' => $participant->details_first_name,
                            'OtherNames' => $participant->details_other_names,
                        ]
                    ];
                });

            // Get admins
            $admins = $db->table('userroles as ur')
                ->join('portaluserlogoninfo as pu', 'ur.User', '=', 'pu.Id')
                ->where('ur.Role', 1)
                ->select('ur.Id', 'pu.Id as details_id', 'pu.Email as details_email',
                        'pu.FirstName as details_first_name', 'pu.OtherNames as details_other_names')
                ->get()
                ->map(function($admin) {
                    return [
                        'Id' => $admin->Id,
                        'details' => [
                            'Id' => $admin->details_id,
                            'Email' => $admin->details_email,
                            'FirstName' => $admin->details_first_name,
                            'OtherNames' => $admin->details_other_names,
                        ]
                    ];
                });

            return [
                'data' => $participants,
                'admins' => $admins
            ];
        });
    }

    /**
     * Clear user-specific caches
     */
    public static function clearUserCache($userId, $email = null)
    {
        $keysToForget = [
            "user_role:" . $userId,
            "user_notifications:" . $userId . ":unread",
            "user_notifications:" . $userId . ":all",
        ];

        if ($email) {
            $keysToForget[] = "user_details:" . md5($email);
        }

        // Also clear message caches for this user
        $roleId = self::getUserRole($userId);
        if ($roleId) {
            $keysToForget[] = "user_messages:" . $userId . ":" . $roleId;
        }

        foreach ($keysToForget as $key) {
            Cache::forget($key);
        }

        Log::info('Cleared cache for user: ' . $userId);
    }

    /**
     * Clear notification caches for a user
     */
    public static function clearNotificationCache($userId)
    {
        Cache::forget("user_notifications:" . $userId . ":unread");
        Cache::forget("user_notifications:" . $userId . ":all");

        Log::info('Cleared notification cache for user: ' . $userId);
    }

    /**
     * Clear message caches for a user
     */
    public static function clearMessageCache($userId, $roleId = null)
    {
        if (!$roleId) {
            $roleId = self::getUserRole($userId);
        }

        Cache::forget("user_messages:" . $userId . ":" . $roleId);

        Log::info('Cleared message cache for user: ' . $userId);
    }

    /**
     * Clear message thread cache
     */
    public static function clearMessageThreadCache($messageId)
    {
        Cache::forget("message_thread:" . $messageId);

        Log::info('Cleared message thread cache for message: ' . $messageId);
    }

    /**
     * Clear all caches (use sparingly)
     */
    public static function clearAllCache()
    {
        Cache::flush();
        Log::info('Cleared all cache');
    }

    /**
     * Get cache statistics (for monitoring)
     */
    public static function getCacheStats()
    {
        try {
            $cacheDriver = config('cache.default');

            if ($cacheDriver === 'redis') {
                $redis = Cache::getRedis();
                $info = $redis->info();

                return [
                    'driver' => 'redis',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0B',
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'hit_rate' => $info['keyspace_hits'] && $info['keyspace_misses']
                        ? round(($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2) . '%'
                        : '0%'
                ];
            } else {
                // For file-based or other cache drivers
                return [
                    'driver' => $cacheDriver,
                    'status' => 'active',
                    'message' => 'Cache statistics not available for ' . $cacheDriver . ' driver'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error getting cache stats: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve cache statistics'];
        }
    }
}
