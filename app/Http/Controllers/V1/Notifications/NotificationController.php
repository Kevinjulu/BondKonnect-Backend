<?php

namespace App\Http\Controllers\V1\Notifications;

use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function getUnreadNotifications(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);
            if (! $user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            // Use cached notifications
            $notifications = CacheService::getUserNotifications($user_id->Id, true);

            if ($notifications->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No unread notifications found.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications fetched successfully.',
                'data' => $notifications,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching notifications.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllNotifications(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            // Use cached notifications (all notifications)
            $notifications = CacheService::getUserNotifications($user_id->Id, false);

            if ($notifications->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No notifications found.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications fetched successfully.',
                'data' => $notifications,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching notifications.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function markAllNotificationsAsRead(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            $this->bk_db
                ->table('notificationservices')
                ->where('Recipient', $user_id->Id)
                ->update([
                    'IsRead' => true,
                ]);

            // Clear notification cache after update
            CacheService::clearNotificationCache($user_id->Id);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read successfully.',
                'data' => null,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking all notifications as read.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    // send notification
    public function sendNotification($recipient, $type, $message, $link, Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            ]);

            // gget the user id
            $default = new StandardFunctions;
            $user_id = $default->get_user_id($request->email);

            $this->bk_db
                ->table('notificationservices')
                ->insert([
                    'Recipient' => $recipient,
                    'Type' => $type,
                    'Message' => $message,
                    'Link' => $link,
                    'IsRead' => false,
                    'created_by' => $user_id->Id,
                    'created_on' => date('Y-m-d H:i:s'),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully.',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending notification.',
                'data' => $th->getMessage(),
            ], 500);
        }
        // url to send notification
        // http://localhost:8000/api/V1/common/send-notification
    }

    public function markOneNotificationsAsRead(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'id' => 'required|integer', // Assuming 'id' is an integer
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            // Get the notification ID from the request
            $notificationId = $request->id;

            // Check if the notification is already marked as read
            $isRead = $this->bk_db->table('notificationservices')
                ->where('Id', $notificationId)
                ->where('Recipient', $user_id->Id)
                ->value('IsRead');

            if ($isRead == 1) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification is already marked as read.',
                ]);
            }

            // Update notification as read
            $this->bk_db->table('notificationservices')
                ->where('Id', $notificationId)
                ->where('Recipient', $user_id->Id)
                ->update(['IsRead' => true]);

            // Clear notification cache after update
            CacheService::clearNotificationCache($user_id->Id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read successfully.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking the notification as read.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function markOneNotificationsAsFavoriteOrArchive(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'notif_id' => 'required|integer', // Assuming 'id' is an integer
                'action' => 'required|in:IsFavorite,IsArchive', // Action can be either IsFavorite or IsArchive
                'value' => 'required|integer', // Value can be either 1 or 0
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            // Get the notification ID from the request
            $notificationId = $request->notif_id;

            // Determine the field to update based on the action
            $field = $request->action;
            $value = $request->value; // Assuming we want to mark as favorite or archive

            // Log the update attempt
            Log::info('Attempting to update notification', [
                'notification_id' => $notificationId,
                'user_id' => $user_id->Id,
                'field' => $field,
                'value' => $value,
            ]);

            // First check if the notification exists
            $notification = $this->bk_db->table('notificationservices')
                ->where('Id', $notificationId)
                ->where('Recipient', $user_id->Id)
                ->first();

            if (! $notification) {
                Log::warning('Notification not found', [
                    'notification_id' => $notificationId,
                    'user_id' => $user_id->Id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or does not belong to this user.',
                ], 404);
            }

            // Check if the notification already has the specified action
            $currentValue = $notification->$field;

            Log::info('Current value of notification', [
                'notification_id' => $notificationId,
                'current_value' => $currentValue,
            ]);

            if ($currentValue == $value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification is already marked as '.$request->action.'.',
                ]);
            }

            // Update notification with the specified action
            $updateResult = $this->bk_db->table('notificationservices')
                ->where('Id', $notificationId)
                ->where('Recipient', $user_id->Id)
                ->update([$field => $value]);

            // Log the update result
            Log::info('Update result', [
                'notification_id' => $notificationId,
                'rows_affected' => $updateResult,
            ]);

            // Verify the update
            $updatedValue = $this->bk_db->table('notificationservices')
                ->where('Id', $notificationId)
                ->where('Recipient', $user_id->Id)
                ->value($field);

            Log::info('Updated value of notification', [
                'notification_id' => $notificationId,
                'updated_value' => $updatedValue,
            ]);

            // Clear notification cache after update
            CacheService::clearNotificationCache($user_id->Id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as '.$request->action.' successfully.',
                'data' => $request->action,
            ]);
        } catch (\Throwable $th) {
            Log::error('Error updating notification', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking the notification as '.$request->action.'.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    // private function to create a notification
    public function createNotification($user_id, $notification_type, $notification_message, $notification_link, $action_recipient_id)
    {
        try {
            $notificationId = $this->bk_db->table('notificationservices')->insertGetId([
                'Recipient' => $user_id,
                'Type' => $notification_type,
                'Message' => $notification_message,
                'Link' => $notification_link,
                'ActionRecipientId' => $action_recipient_id,
                'IsRead' => false,
                'IsArchive' => false,
                'IsFavorite' => false,
                'created_on' => Carbon::now(),
            ]);

            // Prepare notification data for broadcasting
            $notificationData = [
                'notification_id' => $notificationId,
                'notification_type' => $notification_type,
                'notification_message' => $notification_message,
                'notification_date' => Carbon::now()->toISOString(),
                'notification_link' => $notification_link,
                'IsRead' => false,
                'client_email' => $action_recipient_id ? $this->bk_db->table('portaluserlogoninfo')->where('Id', $action_recipient_id)->value('Email') : null,
            ];

            // Broadcast the notification event
            broadcast(new NotificationSent($notificationData, $user_id));

            // Clear notification cache for the recipient
            CacheService::clearNotificationCache($user_id);

            return [
                'success' => true,
                'message' => 'Notification created successfully',
                'data' => null,
            ];

        } catch (\Throwable $th) {
            // throw $th;
            return [
                'success' => false,
                'message' => 'Error creating notification',
                'data' => $th->getMessage(),
            ];
        }
    }

    // post request to create notification
    public function sendUserNotification(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'notification_type' => 'required|string',
                'notification_message' => 'required|string',
                'notification_link' => 'nullable|string',
                'action_recipient_id' => 'nullable|integer',
            ]);

            // Get the user id
            $user_id = CacheService::getUserDetails($request->email);

            if (! $user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null,
                ], 404);
            }

            // Insert notification into database
            $notificationId = $this->bk_db->table('notificationservices')->insertGetId([
                'Recipient' => $user_id->Id,
                'Type' => $request->notification_type,
                'Message' => $request->notification_message,
                'Link' => $request->notification_link,
                'ActionRecipientId' => $request->action_recipient_id,
                'IsRead' => false,
                'IsArchive' => false,
                'IsFavorite' => false,
                'created_on' => Carbon::now(),
            ]);

            // Prepare notification data for broadcasting
            $notificationData = [
                'notification_id' => $notificationId,
                'notification_type' => $request->notification_type,
                'notification_message' => $request->notification_message,
                'notification_date' => Carbon::now()->toISOString(),
                'notification_link' => $request->notification_link,
                'IsRead' => false,
                'client_email' => $request->action_recipient_id ? $this->bk_db->table('portaluserlogoninfo')->where('Id', $request->action_recipient_id)->value('Email') : null,
            ];

            // Broadcast the notification event
            broadcast(new NotificationSent($notificationData, $user_id->Id));

            // Clear notification cache for the recipient
            CacheService::clearNotificationCache($user_id->Id);

            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully.',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating notification.',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get unread notifications count for polling
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $user_id = $request->user_id ?? $request->header('user-id');

            if (! $user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required',
                ], 400);
            }

            $count = $this->bk_db->table('notificationservices')
                ->where('User', $user_id)
                ->where('IsRead', 0)
                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $count,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting unread notifications count: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
            ], 500);
        }
    }

    /**
     * Get recent notifications for polling
     */
    public function getRecentNotifications(Request $request)
    {
        try {
            $user_id = $request->user_id ?? $request->header('user-id');
            $since = $request->since; // timestamp to get notifications since

            if (! $user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required',
                ], 400);
            }

            $query = $this->bk_db->table('notificationservices')
                ->where('User', $user_id)
                ->orderBy('Id', 'desc')
                ->limit(10);

            if ($since) {
                $query->where('created_on', '>', $since);
            }

            $notifications = $query->get();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting recent notifications: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recent notifications',
            ], 500);
        }
    }
}
