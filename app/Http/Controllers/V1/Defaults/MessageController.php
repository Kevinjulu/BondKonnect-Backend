<?php

namespace App\Http\Controllers\V1\Defaults;
use Carbon\Carbon;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Http\Controllers\V1\Notifications\NotificationController;

class MessageController extends Controller
{

    private function createMessage($request)
    {
        $notifs = new NotificationController();
        $default = new StandardFunctions();
        $created_by = $default->get_user_id($request->message_created_by);

        return $this->bk_db->table('message')->insertGetId([
            // 'ServiceRequestType' => $request->service_request_type,
            'Description' => $request->message_description,
            // 'RequestStatus' => $request->message_status,
            // 'RequestPriority' => $request->request_priority,
            'AssignedTo' => $request->message_assigned_to, //
            // 'DueDate' => $request->message_due_date,
            'IsRead' => false,
            'IsEdited' => false,
            'IsDelete' => false,
            'created_by' => $created_by->Id,
            'created_on' => Carbon::now()
        ], 'Id');
    }

    private function createMessageReply($request)
    {

        $default = new StandardFunctions();
        $created_by = $default->get_user_id($request->reply_created_by);

        return $this->bk_db->table('messagereplies')->insertGetId([
            'MessageId' => $request->message_id,
            'ReplyDescription' => $request->reply_description,
            // 'ReplyStatus' => $request->reply_status,
            'IsRead' => false,
            'IsEdited' => false,
            'IsDelete' => false,
            'created_on' => Carbon::now(),
            'created_by' => $created_by->Id,
        ], 'Id');
    }

    private function handleAttachments($attachments, $message_id, $created_by, $is_reply, $is_main_message)
    {
        // Log::info('Starting handleAttachments function');
        Log::info('Raw attachments:', ['attachments' => $attachments]);

        try {
            $default = new StandardFunctions();

            // Ensure upload directory exists
            $uploadPath = storage_path('resources/dms');
            // Log::info('Upload path: ' . $uploadPath);

            if (!file_exists($uploadPath)) {
                Log::info('Creating upload directory');
                if (!mkdir($uploadPath, 0777, true)) {
                    Log::error('Failed to create upload directory');
                    throw new \Exception('Failed to create upload directory');
                }
            }

            // If single file is passed, convert to array
            if (!is_array($attachments)) {
                $attachments = [$attachments];
            }

            // Log::info('Processing attachments count:', ['count' => count($attachments)]);

            foreach ($attachments as $attachment) {
                Log::info('Processing attachment:', [
                    'name' => $attachment->getClientOriginalName(),
                    'size' => $attachment->getSize(),
                    'mime' => $attachment->getMimeType()
                ]);

                if (!$attachment->isValid()) {
                    Log::error('Invalid file upload:', [
                        'error' => $attachment->getError(),
                        'name' => $attachment->getClientOriginalName()
                    ]);
                    continue;
                }

                $unique_file_name = time() . '_' . $attachment->getClientOriginalName();
                // Log::info('Generated unique filename: ' . $unique_file_name);

                try {
                    // Move file to storage
                    // Log::info('Moving file to: ' . $uploadPath . '/' . $unique_file_name);
                    if (!$attachment->move($uploadPath, $unique_file_name)) {
                        throw new \Exception('Failed to move uploaded file');
                    }

                    $user_id = $default->get_user_id($created_by);
                    $location_url = 'resources/dms/' . $unique_file_name;

                    // Store file reference in database
                    $documentData = [
                        'DocumentId' => $attachment->getClientOriginalName(),
                        'PageId' => uniqid(),
                        'LocationUrl' => $location_url,
                        'Extension' => $attachment->getClientOriginalExtension(),
                        'DocumentName' => $unique_file_name,
                        'created_by' => $user_id->Id,
                        'created_on' => now(),
                    ];

                    if ($is_reply) {
                        $documentData['MessageReplyId'] = $message_id;
                    } else if ($is_main_message) {
                        $documentData['MessageId'] = $message_id;
                    }

                    // Log::info('Inserting document record:', $documentData);

                    // Insert record
                    $inserted = $this->bk_db->table('SystemRefDocuments')->insert($documentData);
                    if (!$inserted) {
                        Log::error('Failed to insert document record');
                        throw new \Exception('Failed to save file reference to database');
                    }
                    // Log::info('Document record inserted successfully');

                } catch (\Throwable $th) {
                    Log::error('Error processing file:', [
                        'file' => $unique_file_name,
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString()
                    ]);
                    // Clean up file if database insert failed
                    if (file_exists($uploadPath . '/' . $unique_file_name)) {
                        unlink($uploadPath . '/' . $unique_file_name);
                    }
                    throw $th;
                }
            }

            return [
                'success' => true,
                'message' => 'Files uploaded successfully.'
            ];

        } catch (\Throwable $th) {
            Log::error('HandleAttachments error:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'An error occurred while processing attachments.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ];
        }
    }

    // create a message

    public function sendMessage(Request $request)
    {
        Log::info('Received message request');
        Log::info('Request files:', $request->allFiles());
        Log::info('Has file message_attachments:', [$request->hasFile('message_attachments')]);

        // Validate the incoming request
        $request->validate([
            // 'service_request_type' => 'required|integer|exists:bk_db.ServiceRequestTypes,Id',
            'message_description' => 'required|string|max:1000',
            // 'request_status' => 'required|integer',
            // 'request_priority' => 'required|in:1,2,3', // 1 = Low, 2 = Medium, 3 = High
            'message_assigned_to' => 'nullable|integer|exists:bk_db.portaluserlogoninfo,Id',
            // 'message_due_date' => 'nullable|date|after:today',
            'message_attachments.*' => 'nullable|file',
            'message_created_by' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
        ]);

        try {
            Log::info('Validation passed');
            // Start transaction
            $this->bk_db->beginTransaction();
            // get the user id from the email
            $default = new StandardFunctions();

            $user_id = $default->get_user_id($request->message_created_by);

            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            $role_id = $this->bk_db->table('userroles')
                            ->where('User', $user_id->Id)
                            ->value('Role');

            // if message_assigned_to is not set and the created by is an admin, then request for it
            if (!$request->message_assigned_to && $role_id == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please assign the request to a user.',
                    'data' => null
                ]);
            }

            //print_r($user_id);
            // Insert message
            $message_id = $this->createMessage($request);
            Log::info('Message created with ID: ' . $message_id);

            if ($request->hasFile('message_attachments')) {
                Log::info('Processing message attachments');
                $response = $this->handleAttachments(
                    $request->file('message_attachments'),
                    $message_id,
                    $request->message_created_by,
                    false,
                    true
                );

                Log::info('Attachment handling response:', ['response' => $response]);

                if ($response['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($response);
                }
            } else {
                Log::info('No attachments found in request');
            }

            $notifs = new NotificationController();

            if ($role_id != 1) {
                // since it is going to admins, all admins will be assigned
                $admins = $this->bk_db->table('userroles')
                    ->where('Role', 1)
                    ->get();



                foreach ($admins as $admin) {

                    // create a notification for the admin
                    $notification_status = $notifs->createNotification(
                        // $admin->Email,
                        $admin->User,
                        2,
                        'A new message has been sent by ' . $user_id->FirstName . ' ' . $user_id->OtherNames,
                        $message_id,
                        null
                    );

                    if ($notification_status['success'] == false) {
                        $this->bk_db->rollBack();
                        return response()->json($notification_status);
                    }

                    // send email notifications to admins too
                    // $notify_admin = $default->compose_and_send_email(
                    //     $admin->Email,
                    //     false,
                    //     false,
                    //     true,
                    //     "message submitted successfully",
                    //     "A new message has been submitted to you by " . $user_id->FirstName . ' ' . $user_id->OtherNames,
                    //     $admin->FirstName . ' ' . $admin->OtherNames
                    // );

                    // if ($notify_admin['success'] == false) {
                    //     // roll back
                    //     $this->bk_db->rollBack();
                    //     return response()->json($notify_admin);
                    // }
                }
            }

            // notify the user submitting the request that the request has been submitted
            // $notification_status = $notifs->createNotification(
            //     // $request->message_created_by,
            //     $user_id->Id,
            //     1,
            //     'Your message has been submitted successfully.',
            //     $message_id
            // );

            $notification_status_recipient = $notifs->createNotification(
                // $request->message_created_by,
                $request->message_assigned_to ,
                1,
                'You have a new message.',
                $message_id,
                null
            );

            // Get the created message details for broadcasting
            $messageDetails = $this->bk_db->table('message as m')
                ->join('portaluserlogoninfo as creator', 'm.created_by', '=', 'creator.Id')
                ->leftJoin('portaluserlogoninfo as assignee', 'm.AssignedTo', '=', 'assignee.Id')
                ->where('m.Id', $message_id)
                ->select([
                    'm.Id',
                    'm.Description',
                    'm.created_on',
                    'm.IsRead',
                    'creator.Id as created_by_id',
                    'creator.Email as created_by_email',
                    'creator.FirstName as created_by_first_name',
                    'creator.OtherNames as created_by_other_names',
                    'assignee.Id as assigned_to_id',
                    'assignee.Email as assigned_to_email',
                    'assignee.FirstName as assigned_to_first_name',
                    'assignee.OtherNames as assigned_to_other_names'
                ])
                ->first();

            if ($messageDetails && $messageDetails->assigned_to_id) {
                // Prepare message data for broadcasting
                $messageData = [
                    'Id' => $messageDetails->Id,
                    'Description' => $messageDetails->Description,
                    'created_on' => $messageDetails->created_on,
                    'IsRead' => (bool)$messageDetails->IsRead,
                    'created_by' => [
                        'Id' => $messageDetails->created_by_id,
                        'Email' => $messageDetails->created_by_email,
                        'FirstName' => $messageDetails->created_by_first_name,
                        'OtherNames' => $messageDetails->created_by_other_names,
                    ],
                    'assigned_to' => [
                        'Id' => $messageDetails->assigned_to_id,
                        'Email' => $messageDetails->assigned_to_email,
                        'FirstName' => $messageDetails->assigned_to_first_name,
                        'OtherNames' => $messageDetails->assigned_to_other_names,
                    ],
                    'is_thread_message' => false,
                    'thread_count' => 0,
                ];

                // Broadcast to the recipient
                broadcast(new MessageSent($messageData, $messageDetails->assigned_to_id, $messageDetails->created_by_id));

                // Clear message cache for both sender and recipient
                $senderRoleId = CacheService::getUserRole($messageDetails->created_by_id);
                $recipientRoleId = CacheService::getUserRole($messageDetails->assigned_to_id);

                CacheService::clearMessageCache($messageDetails->created_by_id, $senderRoleId);
                CacheService::clearMessageCache($messageDetails->assigned_to_id, $recipientRoleId);
            }

            // Commit transaction
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'message submitted successfully with attachments.',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
        //url: http://localhost:8000/api/V1/common/submit-service-request
    }

    public function replyMessage(Request $request)
    {

        // Validate the incoming request
        $request->validate([
            'message_id' => 'required|integer|exists:bk_db.message,Id',
            'reply_description' => 'required|string|max:1000',
            'reply_attachments.*' => 'nullable|file',
            'reply_created_by' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',

            // change the status of the message
            // 'reply_status' => 'required|integer',
        ]);
        Log::info("Validation done");
        try {
            // Start transaction
            $this->bk_db->beginTransaction();
            // get the user id from the email

            $default = new StandardFunctions();

            $user_id = $default->get_user_id($request->reply_created_by);


            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            // Insert message reply
            $message_request_reply_id = $this->createMessageReply($request);

            // Handle multiple attachments (if provided)
            if ($request->hasFile('reply_attachments')) {
                $response = $this->handleAttachments(
                    $request->file('reply_attachments'),
                    $message_request_reply_id,
                    $request->reply_created_by,
                    true,
                    false
                );

                if ($response['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($response);
                }
            }

            // update the message status
            // $this->bk_db->table('message')
            //     ->where('Id', $request->message_id)
            //     ->update(['RequestStatus' => $request->reply_status]);

            // get the message
            $service_request = $this->bk_db->table('message')
                ->where('Id', $request->message_id)
                ->first();


            // notify the user that the message has been replied
            $notifs = new NotificationController();
            $notification_status = $notifs->createNotification(
                //  $service_request->created_by,
                $user_id->Id,
                5,
                'Your message has been replied.',
                $request->message_id,
                null
            );

            if ($notification_status['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json($notification_status);
            }

            // Get the message details for broadcasting
            $originalMessage = $this->bk_db->table('message as m')
                ->join('portaluserlogoninfo as creator', 'm.created_by', '=', 'creator.Id')
                ->leftJoin('portaluserlogoninfo as assignee', 'm.AssignedTo', '=', 'assignee.Id')
                ->where('m.Id', $request->message_id)
                ->select([
                    'm.Id',
                    'm.Description',
                    'm.created_on',
                    'm.IsRead',
                    'creator.Id as created_by_id',
                    'creator.Email as created_by_email',
                    'creator.FirstName as created_by_first_name',
                    'creator.OtherNames as created_by_other_names',
                    'assignee.Id as assigned_to_id',
                    'assignee.Email as assigned_to_email',
                    'assignee.FirstName as assigned_to_first_name',
                    'assignee.OtherNames as assigned_to_other_names'
                ])
                ->first();

            // Get the reply details
            $replyDetails = $this->bk_db->table('messagereplies as mr')
                ->join('portaluserlogoninfo as creator', 'mr.created_by', '=', 'creator.Id')
                ->where('mr.Id', $message_request_reply_id)
                ->select([
                    'mr.Id',
                    'mr.ReplyDescription',
                    'mr.created_on',
                    'creator.Id as created_by_id',
                    'creator.Email as created_by_email',
                    'creator.FirstName as created_by_first_name',
                    'creator.OtherNames as created_by_other_names'
                ])
                ->first();

            if ($originalMessage && $replyDetails) {
                // Get thread count
                $threadCount = $this->bk_db->table('messagereplies')
                    ->where('MessageId', $request->message_id)
                    ->count();

                // Prepare message data for broadcasting (showing the latest reply)
                $messageData = [
                    'Id' => $originalMessage->Id,
                    'Description' => $replyDetails->ReplyDescription, // Show the reply content
                    'created_on' => $replyDetails->created_on, // Show the reply time
                    'IsRead' => false, // New reply is unread
                    'created_by' => [
                        'Id' => $replyDetails->created_by_id,
                        'Email' => $replyDetails->created_by_email,
                        'FirstName' => $replyDetails->created_by_first_name,
                        'OtherNames' => $replyDetails->created_by_other_names,
                    ],
                    'assigned_to' => [
                        'Id' => $originalMessage->assigned_to_id,
                        'Email' => $originalMessage->assigned_to_email,
                        'FirstName' => $originalMessage->assigned_to_first_name,
                        'OtherNames' => $originalMessage->assigned_to_other_names,
                    ],
                    'is_thread_message' => true,
                    'thread_count' => $threadCount,
                ];

                // Broadcast to both the original creator and the assigned user
                $recipientId = ($replyDetails->created_by_id === $originalMessage->created_by_id)
                    ? $originalMessage->assigned_to_id
                    : $originalMessage->created_by_id;

                broadcast(new MessageSent($messageData, $recipientId, $replyDetails->created_by_id));

                // Clear message cache for both participants and clear thread cache
                $senderRoleId = CacheService::getUserRole($replyDetails->created_by_id);
                $recipientRoleId = CacheService::getUserRole($recipientId);

                CacheService::clearMessageCache($replyDetails->created_by_id, $senderRoleId);
                CacheService::clearMessageCache($recipientId, $recipientRoleId);
                CacheService::clearMessageThreadCache($request->message_id);
            }

            // Commit transaction
            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Message reply submitted successfully with attachments.',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function markMessageAsRead(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
                'id' => 'required|integer' // Assuming 'id' is an integer
            ]);

            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            // Get the message ID from the request
            $messageid = $request->id;

            // Check if the message is already marked as read
            $isRead = $this->bk_db->table('message')
                ->where('Id', $messageid)
                ->where('AssignedTo', $user_id->Id)
                ->value('IsRead');

            if ($isRead == 1) {
                return response()->json([
                    'success' => true,
                    'message' => 'message is already marked as read.'
                ]);
            }

            // Update message as read
            $this->bk_db->table('message')
                ->where('Id', $messageid)
                ->where('AssignedTo', $user_id->Id)
                ->update(['IsRead' => true]);

            // Clear message cache after update
            $role_id = CacheService::getUserRole($user_id->Id);
            CacheService::clearMessageCache($user_id->Id, $role_id);

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking the message as read.',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    // get the thread of a message
    public function messageThread(Request $request)
    {
        // Validate the message_id
        $request->validate([
            'message_id' => 'required|integer|exists:bk_db.message,Id',
        ]);

        try {
            // Use cached message thread
            $thread = CacheService::getMessageThread($request->message_id);

            if (!$thread) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found.',
                    'data' => null
                ], 404);
            }

            // Handle intermediary name for initial message
            // $default = new StandardFunctions();
            // $intermediary = $default->get_intermediary_id($thread->first()['created_by']->Email);
            // if ($intermediary) {
            //     $interm_details = $default->get_intermediary_details($intermediary->id);
            //     $thread->first()['created_by']->IntermediaryName = $interm_details->name;
            // }

            return response()->json([
                'success' => true,
                'message' => 'message thread fetched successfully.',
                'data' => [
                    'thread' => $thread
                ]
            ]);

        } catch (\Throwable $th) {
            // Error handling
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    //get message participants
    public function getMessageParticipants(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        try {
            // Use cached message participants
            $participants = CacheService::getMessageParticipants();

            return response()->json([
                'success' => true,
                'message' => 'message participants fetched successfully.',
                'data' => $participants['data'],
                'admins' => $participants['admins']
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 401);
        }
    }

    // get messages by user
    public function getMessageByUser(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        try {
            // get the user id
            $default = new StandardFunctions();

            $user_id = $default->get_user_id($request->email);

            $role_id = $this->bk_db->table('userroles')
                            ->where('User', $user_id->Id)
                            ->value('Role');

            // Fetch all messages by the user
            $messages = $this->bk_db->table('message')
                ->where('created_by', $user_id->Id)
                ->orderBy('created_on', 'desc')
                ->get();

            // attach the name of the user who created the request
            foreach ($messages as $message) {

                $message->created_by = $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $message->created_by)
                    // ->select('Id', 'Email', 'FirstName', 'OtherNames', 'Role')
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // assignedTo
                $message->assigned_to = $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $message->AssignedTo)
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // // if assigned to is available then get intermediary id
                // if ($message->assigned_to) {
                //     $intermediary = $default->get_intermediary_id($message->assigned_to->Email);

                //     if ($intermediary) {
                //         $message->assigned_to->IntermediaryName = $intermediary;

                //         $interm_details = $default->get_intermediary_details($intermediary->id);

                //         $message->assigned_to->IntermediaryName = $interm_details->name;
                //     }
                // }

                // we also need to tie intermediary name to the user, as they are contact people
                // $intermediary = $default->get_intermediary_id($message->created_by->Email);

                // if ($intermediary) {
                //     $message->created_by->IntermediaryName = $intermediary;

                //     $interm_details = $default->get_intermediary_details($intermediary->id);

                //     $message->created_by->IntermediaryName = $interm_details->name;
                // } else {
                //     $message->created_by->IntermediaryName = "Admin";
                // }

                // if there are documents attach them
                $message->message_attachments = $this->bk_db->table('SystemRefDocuments')
                    ->select('Id', 'DocumentId', 'PageId', 'LocationUrl', 'DocumentName')
                    ->where('MessageId', $message->Id)
                    ->get();
            }

            // all other requests submitted to other users who are not fellow admins
            if ($role_id == 1) {
                $other_requests = $this->bk_db->table('message')
                    ->where('created_by', '!=', $user_id->Id)
                    ->orderBy('created_on', 'desc')
                    ->get();
            } else {
                $other_requests = $this->bk_db->table('message')
                    ->where('AssignedTo', $user_id->Id)
                    ->orderBy('created_on', 'desc')
                    ->get();
            }

            // attach the name of the user who created the request
            foreach ($other_requests as $message) {

                $message->created_by = $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $message->created_by)
                    // ->select('Id', 'Email', 'FirstName', 'OtherNames', 'Role')
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // assignedTo
                $message->assigned_to = $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $message->AssignedTo)
                    ->select('Id', 'Email', 'FirstName', 'OtherNames')
                    ->first();

                // if assigned to is available then get intermediary id
                // if ($message->assigned_to) {
                //     $intermediary = $default->get_intermediary_id($message->assigned_to->Email);

                //     if ($intermediary) {
                //         $message->assigned_to->IntermediaryName = $intermediary;

                //         $interm_details = $default->get_intermediary_details($intermediary->id);

                //         $message->assigned_to->IntermediaryName = $interm_details->name;
                //     }
                // }

                // // we also need to tie intermediary name to the user, as they are contact people
                // $intermediary = $default->get_intermediary_id($message->created_by->Email);

                // if ($intermediary) {
                //     $message->created_by->IntermediaryName = $intermediary;

                //     $interm_details = $default->get_intermediary_details($intermediary->id);

                //     $message->created_by->IntermediaryName = $interm_details->name;
                // } else {
                //     $message->created_by->IntermediaryName = "Admin";
                // }

                // if there are documents attach them
                $message->message_attachments = $this->bk_db->table('SystemRefDocuments')
                    ->select('Id', 'DocumentId', 'PageId', 'LocationUrl', 'DocumentName')
                    ->where('MessageId', $message->Id)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'messages fetched successfully.',
                'data' => [
                    'created_messages' => $messages,
                    'received_messages' => $other_requests
                ]
            ]);


        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ],401);
        }
    }

    public function getAllUnreadMessagesForUser(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
        ]);

        try {
            // Use cached user details
            $user_id = CacheService::getUserDetails($request->email);

            // Use cached user role
            $role_id = CacheService::getUserRole($user_id->Id);

            // Use cached messages
            $messages = CacheService::getUserMessages($user_id->Id, $role_id);

            return response()->json([
                'success' => true,
                'message' => 'Unread messages with thread information fetched successfully.',
                'data' => $messages
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 401);
        }
    }

    // close chat
    public function closeChat(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'message_id' => 'required|integer|exists:bk_db.message,Id',
        ]);

        try {
            // Close the chat
            $this->bk_db->table('message')
                ->where('Id', $request->message_id)
                ->update(['RequestStatus' => 3]);

            return response()->json([
                'success' => true,
                'message' => 'message closed successfully.',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    /**
     * Get unread messages count for polling
     */
    public function getUnreadMessagesCount(Request $request)
    {
        try {
            $user_id = $request->user_id ?? $request->header('user-id');

            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            // Count unread messages where user is assigned to
            $unreadMessages = $this->bk_db->table('message')
                ->where('AssignedTo', $user_id)
                ->where('IsRead', 0)
                ->where('IsDelete', 0)
                ->count();

            // Count unread message replies
            $unreadReplies = $this->bk_db->table('messagereplies as mr')
                ->join('message as m', 'm.Id', '=', 'mr.MessageId')
                ->where(function($query) use ($user_id) {
                    $query->where('m.AssignedTo', $user_id)
                          ->orWhere('m.created_by', $user_id);
                })
                ->where('mr.IsRead', 0)
                ->where('mr.IsDelete', 0)
                ->where('mr.created_by', '!=', $user_id) // Don't count own replies
                ->count();

            $totalUnread = $unreadMessages + $unreadReplies;

            return response()->json([
                'success' => true,
                'unread_count' => $totalUnread,
                'unread_messages' => $unreadMessages,
                'unread_replies' => $unreadReplies,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting unread messages count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread messages count'
            ], 500);
        }
    }

    /**
     * Get recent messages for polling
     */
    public function getRecentMessages(Request $request)
    {
        try {
            $user_id = $request->user_id ?? $request->header('user-id');
            $since = $request->since; // timestamp to get messages since

            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            // Get recent messages
            $messagesQuery = $this->bk_db->table('message as m')
                ->leftJoin('portaluserlogoninfo as creator', 'creator.Id', '=', 'm.created_by')
                ->leftJoin('portaluserlogoninfo as assignee', 'assignee.Id', '=', 'm.AssignedTo')
                ->select([
                    'm.Id',
                    'm.Description',
                    'm.IsRead',
                    'm.created_on',
                    'creator.FirstName as creator_first_name',
                    'creator.LastName as creator_last_name',
                    'creator.Email as creator_email',
                    'assignee.FirstName as assignee_first_name',
                    'assignee.LastName as assignee_last_name',
                    'assignee.Email as assignee_email'
                ])
                ->where(function($query) use ($user_id) {
                    $query->where('m.AssignedTo', $user_id)
                          ->orWhere('m.created_by', $user_id);
                })
                ->where('m.IsDelete', 0)
                ->orderBy('m.created_on', 'desc')
                ->limit(10);

            if ($since) {
                $messagesQuery->where('m.created_on', '>', $since);
            }

            $messages = $messagesQuery->get();

            // Get recent message replies
            $repliesQuery = $this->bk_db->table('messagereplies as mr')
                ->join('message as m', 'm.Id', '=', 'mr.MessageId')
                ->leftJoin('portaluserlogoninfo as creator', 'creator.Id', '=', 'mr.created_by')
                ->select([
                    'mr.Id',
                    'mr.MessageId',
                    'mr.ReplyDescription',
                    'mr.IsRead',
                    'mr.created_on',
                    'm.Description as original_message',
                    'creator.FirstName as creator_first_name',
                    'creator.LastName as creator_last_name',
                    'creator.Email as creator_email'
                ])
                ->where(function($query) use ($user_id) {
                    $query->where('m.AssignedTo', $user_id)
                          ->orWhere('m.created_by', $user_id);
                })
                ->where('mr.IsDelete', 0)
                ->orderBy('mr.created_on', 'desc')
                ->limit(10);

            if ($since) {
                $repliesQuery->where('mr.created_on', '>', $since);
            }

            $replies = $repliesQuery->get();

            return response()->json([
                'success' => true,
                'messages' => $messages,
                'replies' => $replies,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting recent messages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recent messages'
            ], 500);
        }
    }

    /**
     * Get message thread updates for polling
     */
    public function getMessageThreadUpdates(Request $request)
    {
        try {
            $message_id = $request->message_id;
            $since = $request->since; // timestamp to get updates since

            if (!$message_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message ID is required'
                ], 400);
            }

            // Get new replies in the thread
            $repliesQuery = $this->bk_db->table('messagereplies as mr')
                ->leftJoin('portaluserlogoninfo as creator', 'creator.Id', '=', 'mr.created_by')
                ->select([
                    'mr.Id',
                    'mr.MessageId',
                    'mr.ReplyDescription',
                    'mr.IsRead',
                    'mr.created_on',
                    'creator.FirstName as creator_first_name',
                    'creator.LastName as creator_last_name',
                    'creator.Email as creator_email'
                ])
                ->where('mr.MessageId', $message_id)
                ->where('mr.IsDelete', 0)
                ->orderBy('mr.created_on', 'desc');

            if ($since) {
                $repliesQuery->where('mr.created_on', '>', $since);
            }

            $newReplies = $repliesQuery->get();

            return response()->json([
                'success' => true,
                'new_replies' => $newReplies,
                'count' => $newReplies->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting message thread updates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get message thread updates'
            ], 500);
        }
    }
}
