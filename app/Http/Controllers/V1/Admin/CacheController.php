<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        try {
            $stats = CacheService::getCacheStats();

            return response()->json([
                'success' => true,
                'message' => 'Cache statistics retrieved successfully.',
                'data' => $stats,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving cache statistics.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Check if user is admin
            $user_id = CacheService::getUserDetails($request->email);
            $role_id = CacheService::getUserRole($user_id->Id);

            if ($role_id != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'data' => null,
                ], 403);
            }

            CacheService::clearAllCache();

            return response()->json([
                'success' => true,
                'message' => 'All cache cleared successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for specific user
     */
    public function clearUserCache(Request $request)
    {
        try {
            $request->validate([
                'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Check if admin user is authorized
            $admin_user = CacheService::getUserDetails($request->admin_email);
            $admin_role = CacheService::getUserRole($admin_user->Id);

            if ($admin_role != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'data' => null,
                ], 403);
            }

            // Clear cache for target user
            $target_user = CacheService::getUserDetails($request->user_email);
            CacheService::clearUserCache($target_user->Id, $request->user_email);

            return response()->json([
                'success' => true,
                'message' => 'User cache cleared successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing user cache.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear notification cache for specific user
     */
    public function clearNotificationCache(Request $request)
    {
        try {
            $request->validate([
                'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Check if admin user is authorized
            $admin_user = CacheService::getUserDetails($request->admin_email);
            $admin_role = CacheService::getUserRole($admin_user->Id);

            if ($admin_role != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'data' => null,
                ], 403);
            }

            // Clear notification cache for target user
            $target_user = CacheService::getUserDetails($request->user_email);
            CacheService::clearNotificationCache($target_user->Id);

            return response()->json([
                'success' => true,
                'message' => 'Notification cache cleared successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing notification cache.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear message cache for specific user
     */
    public function clearMessageCache(Request $request)
    {
        try {
            $request->validate([
                'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Check if admin user is authorized
            $admin_user = CacheService::getUserDetails($request->admin_email);
            $admin_role = CacheService::getUserRole($admin_user->Id);

            if ($admin_role != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'data' => null,
                ], 403);
            }

            // Clear message cache for target user
            $target_user = CacheService::getUserDetails($request->user_email);
            $target_role = CacheService::getUserRole($target_user->Id);
            CacheService::clearMessageCache($target_user->Id, $target_role);

            return response()->json([
                'success' => true,
                'message' => 'Message cache cleared successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing message cache.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Warm up cache for a user (preload commonly used data)
     */
    public function warmUpUserCache(Request $request)
    {
        try {
            $request->validate([
                'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Check if admin user is authorized
            $admin_user = CacheService::getUserDetails($request->admin_email);
            $admin_role = CacheService::getUserRole($admin_user->Id);

            if ($admin_role != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                    'data' => null,
                ], 403);
            }

            // Warm up cache for target user
            $target_user = CacheService::getUserDetails($request->user_email);
            $target_role = CacheService::getUserRole($target_user->Id);

            // Preload notifications
            CacheService::getUserNotifications($target_user->Id, true);  // unread
            CacheService::getUserNotifications($target_user->Id, false); // all

            // Preload messages
            CacheService::getUserMessages($target_user->Id, $target_role);

            // Preload message participants
            CacheService::getMessageParticipants();

            return response()->json([
                'success' => true,
                'message' => 'User cache warmed up successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error warming up user cache.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }
}
