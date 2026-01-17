<?php

namespace App\Http\Controllers\V1\Defaults;
use Carbon\Carbon;
use App\Mail\OtpMail;
use App\Mail\GeneralMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\PromotionalMail;
use App\Mail\Verificationlink;
use App\Mail\SubscriptionsMail;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Mail\VerifyIntermediaryMail;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\v1\Defaults\StandardFunctions;

class CommunicationManagement extends Controller
{


    public function composeMail($email, $firstName, $isOtp,  $isVerification,$isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
    {
        try {
            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'User has no email',
                    'data' => null,
                ];
            }

            // use the email to get the first name

            // Send verification email
            if ($isVerification) {
                // $stdfns = new StandardFunctions();
                // $user = $stdfns->get_user_id($email);
                // $role =  $stdfns->get_user_role($user->Id);

                $verification_result = $this->generate_verification_link($email);

                if ($verification_result['success'] == false) {
                    return $verification_result;
                }

                Log::info('Verification result: ' . json_encode($verification_result), [
                    // 'file' => __FILE__,
                    // 'line' => __LINE__
                ]);
                $verification_link = $verification_result['data'];

                if($firstName){
                    //means this is an Individual
                    $name = $firstName;

                }else{
                    //processes brokers, authorized dealers, corporates, agents
                    $name = $this->bk_db->table('portaluserlogoninfo')
                    ->select('CompanyName')
                    ->where('email', $email)
                    ->first();
                    $name = $name->CompanyName;
                }

                // log::info(($name->FirstName));

                $data = [
                    "verification_link" => $verification_link,
                    "name" => $name,
                    "email" => $email,
                ];
                log::info("email details",$data);

                // new mailer
                Mail::to($email)
                // ->html($email_body);
                    ->send(new Verificationlink($data));

            }

                // Send intermediary verification email
            else if ($isIntermediaryVerification) {
                $verification_result = $this->generate_intermediary_verification_link($email);

                if ($verification_result['success'] == false) {
                    return $verification_result;
                }

                Log::info(json_encode($verification_result));
                $verification_link = $verification_result['data'];

                // log::info(($name->FirstName));

                $data = [
                    "verification_link" => $verification_link,
                    // "name" => $name,
                    "email" => $email,
                ];
                log::info("intermediary email details",$data);

                // new mailer
                Mail::to($email)
                // ->html($email_body);
                    ->send(new VerifyIntermediaryMail($data));

            }

            // Send OTP email
            else if ($isOtp) {
                $otp = $this->generate_send_otp($email);

                if ($otp['success'] == false) {
                    return $otp;
                }

                $otp = $otp['data'];
                Log::info('OTP: ' . $otp);

                if($firstName){
                    //means this is an Individual
                    $name = $firstName;

                }else{
                    //processes brokers, authorized dealers, corporates, agents
                    $name = $this->bk_db->table('portaluserlogoninfo')
                    ->select('CompanyName')
                    ->where('email', $email)
                    ->first();
                    $name = $name->CompanyName;
                }

                $data = [
                    "name" => $name,
                    "otp" => $otp,
                    "email" => $email,
                ];
                Mail::to($email)->send(new OtpMail($data));


            }

            //Send Forgot Password email

            else if ($isForgot) {

                $verification_result = $this->generate_verification_link($email);

                if ($verification_result['success'] == false) {
                    return $verification_result;
                }

                Log::info(json_encode($verification_result));
                $verification_link = $verification_result['data'];

                if($firstName){
                    //means this is an Individual
                    $name = $firstName;

                }else{
                    //processes brokers, authorized dealers, corporates, agents
                    $name = $this->bk_db->table('portaluserlogoninfo')
                    ->select('CompanyName')
                    ->where('email', $email)
                    ->first();
                    $name = $name->CompanyName;
                }

                $data = [
                    "name" => $name,
                    "verification_link" => $verification_link,
                    "email" => $email,
                ];
                Mail::to($email)->send(new ForgotPasswordMail($data));


            }

            // Send general email
            else if ($isGeneral) {
                if (!$generalSubject || !$generalBody) {
                    return [
                        'success' => false,
                        'message' => 'General email requires subject and body',
                        'data' => null,
                    ];
                }

                //fetch name
                if($firstName){
                    //means this is an Individual
                    $name = $firstName;

                }else{
                    //processes brokers, authorized dealers, corporates, agents
                    $name = $this->bk_db->table('portaluserlogoninfo')
                    ->select('CompanyName')
                    ->where('email', $email)
                    ->first();
                    $name = $name->CompanyName;
                }

                if ($generalSubject && $generalBody && $isPromo) {
                    //send promo email

                    $data = [
                        "name" => $name,
                        "general_subject" => $generalSubject,
                        "general_body" => $generalBody,
                        // "verification_link" => $verification_link,
                        "email" => $email,
                    ];
                    Mail::to($email)->send(new PromotionalMail($data));
                }
                else if ($generalSubject && $generalBody && $isSubscription) {
                // send subscription email

                    $data = [
                        "name" => $name,
                        "general_subject" => $generalSubject,
                        "general_body" => $generalBody,
                        // "verification_link" => $verification_link,
                        "email" => $email,
                    ];
                    Mail::to($email)->send(new SubscriptionsMail($data));
                }

                else {
                    // send pure general mail
                    $data = [
                        "name" => $name,
                        "general_subject" => $generalSubject,
                        "general_body" => $generalBody,
                        // "verification_link" => $verification_link,
                        "email" => $email,
                    ];
                    Mail::to($email)->send(new GeneralMail($data));

                }

            }
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => null,
            ];

        } catch (\Throwable $th) {
            //throw $th;
            return [
                'success' => false,
                'message' => 'Error sending email',
                'error' => [
                    'message' => $th->getMessage(),
                    'code' => $th->getCode(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                ],
            ];
        } catch (\Exception $th) {
            return [
                'success' => false,
                'message' => 'Error sending email',
                'error' => [
                    'message' => $th->getMessage(),
                    'code' => $th->getCode(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                ],
            ];
        }
    }

    public function generate_verification_link($email)
    {
        try {
            // $domain = 'http://localhost:3000';
            $domain = config('app.web_url');
            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'User has no email',
                    'data' => null,
                ];
            }
            // if (!$role) {
            //     return [
            //         'success' => false,
            //         'message' => 'User has no role',
            //         'data' => null,
            //     ];
            // }

            $standard = new StandardFunctions();
            $user = $standard->get_user_id($email);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => 'User ' . $email . ' not found',
                ];
            }


            $role = $this->bk_db->table('userroles')
            ->where('User', $user->Id)
            ->first();

            $role_id = $role->Role;
            $is_reset = $user->IsActive == 1 ? true  :  false;
            if ($role_id === 1)    {

            // make the link properties
            $link_properties = [
                'e' => $email, // email
                'pe' => $role->Role, //$user_type, // user type
                't' => time(), // timestamp
                //'s' => hash_hmac('sha256', $email . $user_type . time(), env('APP_KEY')), // signature
                's' => hash_hmac('sha256', $email . time(), env('APP_KEY')), // signature
                'd' => $domain, // domain
                'pt' => '/admin/set-password', // path
                'ex' => strtotime(datetime: '+1 day'), // expires
                'is_res' => $is_reset, // is reset
            ];
            // generate the link
            $verification_link = $link_properties['d'] . $link_properties['pt'] . '?' . http_build_query($link_properties);
            Log::info($verification_link);

            // add the details to the database under UserEmailVerification
            $this->bk_db->table('portaluseremailverification')->insert([
                'User' => $user->Id,
                'created_on' => date('Y-m-d H:i:s'),
                'ExpiresAt' => date('Y-m-d H:i:s', $link_properties['ex']),
                'IsVerified' => false,
                'Signature' => $link_properties['s'],
            ]);


            } else{
            // make the link properties
            $link_properties = [
                'e' => $email, // email
                'pe' => $role->Role, //$user_type, // user type
                't' => time(), // timestamp
                //'s' => hash_hmac('sha256', $email . $user_type . time(), env('APP_KEY')), // signature
                's' => hash_hmac('sha256', $email . time(), env('APP_KEY')), // signature
                'd' => $domain, // domain
                'pt' => '/auth/set-password', // path
                'ex' => strtotime(datetime: '+1 day'), // expires
                'is_res' => $is_reset, // is reset
            ];
            // generate the link
            $verification_link = $link_properties['d'] . $link_properties['pt'] . '?' . http_build_query($link_properties);
            Log::info($verification_link);

            // add the details to the database under UserEmailVerification
            $this->bk_db->table('portaluseremailverification')->insert([
                'User' => $user->Id,
                'created_on' => date('Y-m-d H:i:s'),
                'ExpiresAt' => date('Y-m-d H:i:s', $link_properties['ex']),
                'IsVerified' => false,
                'Signature' => $link_properties['s'],
            ]);

            }


            return [
                'success' => true,
                'message' => 'Verification link generated successfully',
                'data' => $verification_link,
            ];

        } catch (\Throwable $th) {
            //throw $th;
            return [
                'success' => false,
                'message' => 'Error generating verification link',
                'data' => $th->getMessage(),
            ];
        }

    }

    public function generate_intermediary_verification_link($email)
    {
        try {
            // $domain = 'http://localhost:3000';
            $domain = config('app.web_url');
            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'User has no email',
                    'data' => null,
                ];
            }
            // if (!$role) {
            //     return [
            //         'success' => false,
            //         'message' => 'User has no role',
            //         'data' => null,
            //     ];
            // }

            $standard = new StandardFunctions();
            $user = $standard->get_user_id($email);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => 'User ' . $email . ' not found',
                ];
            }


            $role = $this->bk_db->table('userroles')
            ->where('User', $user->Id)
            ->first();

            $role_id = $role->Role;
            $is_reset = $user->IsActive == 1 ? true  :  false;
            // if ($role_id === 1)    {

            // // make the link properties
            // $link_properties = [
            //     'e' => $email, // email
            //     'pe' => $role->Role, //$user_type, // user type
            //     't' => time(), // timestamp
            //     //'s' => hash_hmac('sha256', $email . $user_type . time(), env('APP_KEY')), // signature
            //     's' => hash_hmac('sha256', $email . time(), env('APP_KEY')), // signature
            //     'd' => $domain, // domain
            //     'pt' => '/admin/set-password', // path
            //     'ex' => strtotime(datetime: '+1 day'), // expires
            //     'is_res' => $is_reset, // is reset
            // ];
            // // generate the link
            // $verification_link = $link_properties['d'] . $link_properties['pt'] . '?' . http_build_query($link_properties);
            // Log::info($verification_link);

            // // add the details to the database under UserEmailVerification
            // $this->bk_db->table('portaluseremailverification')->insert([
            //     'User' => $user->Id,
            //     'created_on' => date('Y-m-d H:i:s'),
            //     'ExpiresAt' => date('Y-m-d H:i:s', $link_properties['ex']),
            //     'IsVerified' => false,
            //     'Signature' => $link_properties['s'],
            // ]);


            // } else{
            // make the link properties
            $link_properties = [
                'e' => $email, // email
                'pe' => $role->Role, //$user_type, // user type
                't' => time(), // timestamp
                //'s' => hash_hmac('sha256', $email . $user_type . time(), env('APP_KEY')), // signature
                's' => hash_hmac('sha256', $email . time(), env('APP_KEY')), // signature
                'd' => $domain, // domain
                'pt' => '/auth/intermediary', // path
                'ex' => strtotime(datetime: '+1 day'), // expires
                'is_res' => $is_reset, // is reset
            ];
            // generate the link
            $verification_link = $link_properties['d'] . $link_properties['pt'] . '?' . http_build_query($link_properties);
            Log::info($verification_link);

            // add the details to the database under UserEmailVerification
            $this->bk_db->table('portaluseremailverification')->insert([
                'User' => $user->Id,
                'created_on' => date('Y-m-d H:i:s'),
                'ExpiresAt' => date('Y-m-d H:i:s', $link_properties['ex']),
                'IsVerified' => false,
                'Signature' => $link_properties['s'],
            ]);

            // }


            return [
                'success' => true,
                'message' => 'Verification link generated successfully',
                'data' => $verification_link,
            ];

        } catch (\Throwable $th) {
            //throw $th;
            return [
                'success' => false,
                'message' => 'Error generating verification link',
                'data' => $th->getMessage(),
            ];
        }

    }
    public function generate_send_otp($email)
    {
        try {
            // generate OTP

            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'User has no email',
                    'data' => null,
                ];
            }

            $otp = rand(100000, 999999);

            // get user id from email
            $standard = new StandardFunctions();
            $login_id = $standard->get_user_id($email);

            if (!$login_id) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            // Clear any previous active OTPs for this user
            // $this->bk_db->table('portaluserotphistory')
            //     ->where('User', $login_id->Id)
            //     ->where('IsActive',  false)
            //     ->update(['IsActive' => true]);

            // save OTP to database under UserOtpHistory
            $this->bk_db->table('portaluserotphistory')->insert([
                'Otp' => $otp,
                'User' => $login_id->Id,
                'created_on' => date('Y-m-d H:i:s'),
                'OtpExpiry' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'IsActive' => false,
            ]);

            // Log OTP generation for debugging
            Log::info('OTP Generated Successfully', [
                'email' => $email,
                'user_id' => $login_id->Id,
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(5),
                'current_time' => Carbon::now()
            ]);

            return [
                'success' => true,
                'message' => 'OTP generated successfully',
                'data' => $otp,
            ];

        } catch (\Throwable $th) {
            Log::error('Error generating OTP', ['error' => $th->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error generating OTP',
                'data' => $th->getMessage(),
            ];
        }
    }

    public function createEmail(Request $request)
    {
            $request->validate([
                'subject' => 'required|string',
                'body' => 'required|string',
                'recipients' => 'required|array',
                'recipients.*' => 'email',
                'cc' => 'nullable|array',
                'cc.*' => 'email',
                'bcc' => 'nullable|array',
                'bcc.*' => 'email',
                'template_type' => 'nullable|string',
                'schedule_date' => 'nullable|date',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240',
                'send_to_role' => 'nullable|string',
                // 'send_to_group' => 'nullable|numeric',
                'created_by' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
            ]);

        try {
            $this->bk_db->beginTransaction();

            // Get sender details
            $sender = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->created_by)
                ->first();

            if (!$sender) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sender not found'
                ], 404);
            }

            $emailId = $this->bk_db->table('emaillogs')->insertGetId([
                'created_by' => $sender->Id,
                'created_on' => Carbon::now(),
                'Subject' => $request->subject,
                'Body' => $request->body,
                'CC' => json_encode($request->cc),
                'BCC' => json_encode($request->bcc),
                'IsBulkEmail' => $request->send_to_role ? true  :  false,
                'IsDraft' => $request->schedule_date ? true  :  false,
                'IsSent' => $request->schedule_date ? false : true,
                'ScheduleDate' => $request->schedule_date,
                'RoleGroupSendingTo' => $request->send_to_role
            ]);

            // Handle attachments if present
            $attachmentIds = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachmentId = $this->handleEmailAttachment($file, $sender->Id, $emailId);
                    if ($attachmentId) {
                        $attachmentIds[] = $attachmentId;
                    }
                }
            }

            // Insert into emaillogs

            // Send email immediately if not scheduled
            if (!$request->schedule_date) {
                $recipients = $request->send_to_role
                    ? $this->getRecipientsByRole($request->send_to_role)
                    : $request->recipients;

                foreach ($recipients as $recipient) {

                    $name = $this->bk_db->table('portaluserlogoninfo')
                    // ->select('FirstName')
                    ->where('Email',  $recipient)
                    ->first();


                    $firstname = $name->FirstName;

                    if($firstname){
                        //means this is an Individual
                        $name = $name->FirstName;

                    }else{
                        //processes brokers, authorized dealers, corporates, agents

                        $name = $name->CompanyName;
                    }

                    $data = [
                        "name" => $name,  // You might want to get this from user data
                        "general_subject" => $request->subject,
                        "general_body" => $request->body,
                        "email" => $recipient
                    ];

                    $mailClass = match($request->template_type) {
                        'otp' => new OtpMail($data),
                        'verification' => new Verificationlink($data),
                        'intermediary' => new VerifyIntermediaryMail($data),
                        'forgot' => new ForgotPasswordMail($data),
                        'promotional' => new PromotionalMail($data),
                        'subscription' => new SubscriptionsMail($data),
                        default => new GeneralMail($data)
                    };

                    Mail::to($recipient)
                        ->cc($request->cc ?? [])
                        ->bcc($request->bcc ?? [])
                        ->send($mailClass);


                }

                //Insert all recipients to DB
                $this->bk_db->table('emaillogs')
                ->where('Id', $emailId)
                ->update(['AllRecipientsEmails' => json_encode($recipients)]);

                Log::info("AllRecipient emails inserted");

            }

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => $request->schedule_date ? 'Email scheduled successfully' : 'Email sent successfully',
                'data' => ['email_id' => $emailId]
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function handleEmailAttachment($file, $userId,$emailId)
    {
        try {
            $uploadPath = storage_path('resources/dms');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $uniqueFileName = time() . '_' . $file->getClientOriginalName();
            $file->move($uploadPath, $uniqueFileName);

            return $this->bk_db->table('SystemRefDocuments')->insertGetId([
                'DocumentName' => $file->getClientOriginalName(),
                'DocumentId' => Str::random(10),
                'LocationUrl' => $uploadPath . '/' . $uniqueFileName,
                'Extension' => $file->getClientOriginalExtension(),
                'EmailId' => $emailId,
                'IsEmailAttachment' => true,
                'IsMessageAttachment' => false,
                'IsServiceRequestAttachment' => false,
                'IsInvoice' => false,
                'IsForAdmin' => false,
                'created_by' => $userId,
                'created_on' => Carbon::now()
            ]);
        } catch (\Throwable $th) {
            Log::error('Error handling email attachment:', ['error' => $th->getMessage()]);
            return null;
        }
    }

    public function getEmailTemplates()
    {
        try {
            $templatePath = resource_path('views/emails');
            $templates = [];

            foreach (glob($templatePath . '/*.blade.php') as $file) {
                $templates[] = [
                    'name' => basename($file, '.blade.php'),
                    'path' => str_replace(resource_path('views/'), '', $file)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email templates',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function sendEmail($params)
    {
        try {
            $data = [
                "name" => "",  // You might want to get this from your user data
                "general_subject" => $params['subject'],
                "general_body" => $params['body'],
                "email" => $params['recipient']
            ];

            Mail::to($params['recipient'])
                ->cc($params['cc'] ?? [])
                ->bcc($params['bcc'] ?? [])
                ->send(new GeneralMail($data));

            return true;
        } catch (\Throwable $th) {
            Log::error('Error sending email:', ['error' => $th->getMessage()]);
            return false;
        }
    }

    public function getRecipientsByRole($role)
    {
        try {
            $query = $this->bk_db->table('portaluserlogoninfo');

            if ($role === 'all') {
                // No filters
            } elseif ($role === 'active') {
                $query->where('portaluserlogoninfo.IsActive', true);
            } elseif ($role === 'inactive') {
                $query->where('portaluserlogoninfo.IsActive', false);
            } else {
                $query->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
                      ->join('roles', 'userroles.Role', '=', 'roles.Id')
                      ->where('roles.Role', $role);
            }

            $recipients = $query->select(
                'portaluserlogoninfo.Email',
                'portaluserlogoninfo.FirstName',
                'portaluserlogoninfo.OtherNames'
            )->get();

            Log::info ("Recipients fetched",$recipients);

            return response()->json([
                'success' => true,
                'data' => $recipients
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recipients',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function previewTemplate(Request $request)
    {
        try {
            $request->validate([
                'template' => 'required|string',
                'data' => 'required|array'
            ]);

            $templateName = $request->template;
            $templateData = $request->data;

            // Check if template exists
            $templatePath = resource_path("views/emails/{$templateName}.blade.php");
            if (!file_exists($templatePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                    'data' => null
                ], 404);
            }

            // Render template with data
            $html = view("emails.{$templateName}", $templateData)->render();

            // Strip scripts for security
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

            // Get plain text version
            $text = strip_tags($html);

            return response()->json([
                'success' => true,
                'message' => 'Template preview generated successfully',
                'data' => [
                    'html' => $html,
                    'text' => $text
                ]
            ]);

        } catch (\Throwable $th) {
            Log::error('Error previewing template:', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating template preview',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function getTemplateVariables($templateName)
    {
        try {
            $templatePath = resource_path("views/emails/{$templateName}.blade.php");
            if (!file_exists($templatePath)) {
                return null;
            }

            // Read template content
            $content = file_get_contents($templatePath);

            // Extract variables using regex
            preg_match_all('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $content, $matches);

            // Return unique variables
            return array_unique($matches[1]);

        } catch (\Throwable $th) {
            Log::error('Error getting template variables:', [
                'error' => $th->getMessage(),
                'template' => $templateName
            ]);
            return null;
        }
    }
}
