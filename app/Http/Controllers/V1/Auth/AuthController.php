<?php

namespace App\Http\Controllers\V1\Auth;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use HRTime\PerformanceCounter;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\V1\Logs\ActivityLogging;
use App\Http\Controllers\V1\Defaults\UserManagement;
use App\Http\Controllers\V1\Defaults\LoggingController;
use App\Http\Controllers\V1\Defaults\StandardFunctions;
use App\Http\Controllers\V1\Defaults\CommunicationManagement;
use App\Http\Controllers\V1\RoleActions\PermissionManagement;
use App\Http\Controllers\V1\Notifications\NotificationController;

class AuthController extends Controller
{

    public function dbDR(Request $request)
    {
        try {
            $tables = $this->bk_db->table('INFORMATION_SCHEMA.TABLES')
                ->select('TABLE_NAME')
                ->where('TABLE_TYPE', 'BASE TABLE')
                ->get();

            $tableNames = $tables->pluck('TABLE_NAME');

            Log::info('Retrieved tables successfully', ['tables' => $tableNames]);

            return response()->json([
                'success' => true,
                'message' => 'Retrieved all tables successfully',
                'data' => $tableNames,
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Error retrieving tables', ['error' => $th->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tables',
                'data' => $th->getMessage(),
            ], 500);
        }
    }
    public function registerUsers(Request $request)
    {
        $request->validate([
            'is_individual' => 'required|boolean',
            'is_agent' => 'required|boolean',
            'is_corporate' => 'required|boolean',
            'is_broker' => 'required|boolean',
            'is_dealer' => 'required|boolean',
            'is_admin' => 'required|boolean',
            'email' => 'required|email',
            'phone' => 'required|string',
            'company_name' => 'nullable|string',
            'first_name' => 'nullable|string',
            'other_names' => 'nullable|string',
            'cds_number' => 'required_if:is_individual,true|string|nullable',
            'broker_dealer' => 'nullable|array',
            'locality' => 'nullable|string',
            'category_type' => 'nullable|string',
            'alternate_dealer' => 'nullable|array',
            'new_dealer_emails' => 'nullable|array'
        ]);

        $is_individual = $request->is_individual;
        $is_agent = $request->is_agent;
        $is_corporate = $request->is_corporate;
        $is_broker = $request->is_broker;
        $is_dealer = $request->is_dealer;
        $is_admin = $request->is_admin;
        $email = $request->email;

        Log::info('register Email:', ['register Email' => $email]);

        if (($is_individual + $is_agent + $is_corporate + $is_admin) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please select exactly one role to register.',
                'data' => null,
            ], 400);
        }

                        // Validate email domain against a list of approved domains
        $validEmail = false;
        $websites = $this->bk_db->table('cmalist')->pluck('Website');
            foreach ($websites as $website) {
                $parsedUrl = parse_url($website);
                $domain = $parsedUrl['host'] ?? '';
                $domainWithoutWWW = str_replace('www.', '', $domain);
                if (str_ends_with($email, '@' . $domainWithoutWWW)) {
                    $validEmail = true;
                    break;
                }
            }
            if (!$validEmail) {
                Log::warning('Invalid email domain:', ['email' => $email]);
                Log::info('Invalid email domain:', ['email' => $email]);
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Email.  Only company emails are accepted.',
                    'data' => null
                    ], 400);

            }

        // $userMgt = new UserManagement();
        $userMgt = new UserManagement();
        $userExists = $userMgt->checkIfUserExists($email);

        if ($userExists == true) {
            // return response()->json($userExists, 400);
            return response()->json([
                'success' => false,
                'message' => 'User already exists in the system',
                'data' => null,
            ], 400);
        }
        if ($is_individual) {
            $role = 2;
        } else if ($is_agent) {
            $role = 3;
        } else if ($is_corporate) {
            $role = 4;
        } else if ($is_broker) {
            $role = 5;
        } else if ($is_dealer) {
            $role = 6;
        } else if ($is_admin) {
            $role = 1;
        }



        try {
            if ($is_individual) {
                $standardfn = new StandardFunctions();
                $accountid = $standardfn->generateUniqueAccountId();
                $userData = [
                    'created_on' => Carbon::now(),
                    'AccountId' => $accountid,
                    'FirstName' => $request->first_name ?? null,
                    'Othernames' => $request->other_names ?? null,
                    'CompanyName' => $request->company_name ?? null,
                    'Email' => $request->email,
                    'PhoneNumber' => $request->phone,
                    'CdsNo' => $request->cds_number ?? null,
                    'IsLocal' => $request->locality === 'local' ? true  :  false,
                    'IsForeign' => $request->locality === 'foreign' ? true  :  false,
                    // 'IsCmaLicensed' => $request->category_type === 'licensed' ? true  :  false,
                    // 'CmaLicenseNo' => $request->category_type === 'licensed' ? $request->cds_number : null,

                    // 'BrokerDealer' => json_encode($request->broker_dealer) ?? null,
                    // 'AlternateDealer' => json_encode($request->alternate_dealer) ?? null,
                    // 'NewDealerEmails' => json_encode($request->new_dealer_emails) ?? null,

                    'IsActive' => false,

                ];
                $logger = new ActivityLogging();
                $this->bk_db->beginTransaction();

                // insert and get the id
                $inserted = $this->bk_db->table('portaluserlogoninfo')->insertGetId($userData);
                if ($inserted) {

                    // Process broker_dealer, alternate_dealer, and new_dealer_emails
                    $dealers = [
                        'broker_dealer' => $request->broker_dealer,
                        'alternate_dealer' => $request->alternate_dealer,
                        'new_dealer_emails' => $request->new_dealer_emails,
                    ];

                    // $intermediariesResult = $userMgt->processIntermediaries($inserted, $dealers);
                    $intermediariesResult = $userMgt->processCorporateIntermediaries($inserted, $dealers);

                    $intermediariesData = json_decode($intermediariesResult->getContent(), true);
                    if ($intermediariesData['success'] == false) {
                        $this->bk_db->rollBack();
                        return response()->json($intermediariesData, 500);
                    }

                    //$userId = $this->bk_db->getPdo()->lastInsertId();

                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];

                    $roleSave = $this->bk_db->table('userroles')->insert($roleData);

                    if (!$roleSave) {
                        $this->bk_db->rollBack();

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_failed',
                            ['email' => $email, 'error' => 'Failed to assign role'],
                            ActivityLogging::SEVERITIES['ERROR']['level']
                        );
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to assign role',
                            'data' => null,
                        ], 400);
                    }

                    $communication_manager = new CommunicationManagement();
                    // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
                    $response = $communication_manager->composeMail(
                        $userData['Email'],
                        $userData['FirstName'],
                        false,
                        true,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false);
                    if ($response['success'] == false) {
                        // rollback transaction
                        $this->bk_db->rollBack();
                        return response()->json($response, 500);
                    } else {
                        // webhook to update default permissions per role
                        // $base_url = config('app.webhook_url');
                        // $url = $base_url . '/api/update-default-permissions';
                        $user_id = $inserted;
                        $role_id = $role;

                        $this->bk_db->commit();
                        $permissionmanager = new PermissionManagement();
                        $response = $permissionmanager->updateDefaultPermissions($user_id, $role_id);
                        // $response = $pension_user_manager->send_webhook($url, $user_id, $role_id);

                        // $logLogin = new LoggingController();
                        // $portalUser = $this->bk_db->table('portaluserlogoninfo')
                        //     ->select('Id')
                        //     ->where('email', $userData['Email'])
                        //     ->first();

                        // $logLogin->systemlog($portalUser->Id, 'Account Created');

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_success',
                            ['email' => $email],
                            ActivityLogging::SEVERITIES['INFO']['level']
                        );

                        return response()->json([
                            'success' => true,
                            'message' => 'Verification email sent successfully',
                            'data' => null,
                        ], 200);
                    }
                } else {

                    $logger->logUserActivity(
                        $inserted,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'registration_failed',
                        ['email' => $email, 'error' => 'Failed to register user'],
                        ActivityLogging::SEVERITIES['ERROR']['level']
                    );

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to register user',
                        'data' => null,
                    ], 400);
                }

            } else if ($is_agent) {
                $standardfn = new StandardFunctions();
                $logger = new ActivityLogging();
                $accountid = $standardfn->generateUniqueAccountId();
                $userData = [
                    'created_on' => Carbon::now(),
                    'AccountId' => $accountid,
                    'CompanyName' => $request->company_name ?? null,
                    'Email' => $request->email,
                    'PhoneNumber' => $request->phone,
                    // 'CdsNo' => $request->cds_number ?? null,
                    'IsLocal' => $request->locality === 'local' ? true  :  false,
                    'IsForeign' => $request->locality === 'foreign' ? true  :  false,
                    // 'IsCmaLicensed' => $request->category_type === 'licensed' ? true  :  false,
                    // 'CmaLicenseNo' => $request->category_type === 'licensed' ? $request->cds_number : null,

                    'IsActive' => false,

                ];

                $email = $request->email;
                $validEmail = false;
                $websites = $this->bk_db->table('cmalist')->pluck('Website');
                foreach ($websites as $website) {
                    $parsedUrl = parse_url($website);
                    $domain = $parsedUrl['host'] ?? '';
                    $domainWithoutWWW = str_replace('www.', '', $domain);
                    if (str_ends_with($email, '@' . $domainWithoutWWW)) {
                        $validEmail = true;
                        break;
                    }
                }
                if (!$validEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid email domain. Only company emails are accepted.',
                        'data' => null,
                    ], 400);
                }

                $this->bk_db->beginTransaction();

                // insert and get the id
                $inserted = $this->bk_db->table('portaluserlogoninfo')->insertGetId($userData);
                if ($inserted) {

                    // Process broker_dealer, alternate_dealer, and new_dealer_emails
                    $dealers = [
                        'broker_dealer' => $request->broker_dealer,
                        'alternate_dealer' => $request->alternate_dealer,
                        'new_dealer_emails' => $request->new_dealer_emails,
                    ];

                    $intermediariesResult = $userMgt->processCorporateIntermediaries($inserted, $dealers);
                    // if (!$intermediariesResult->getData()->success) {
                    //     $this->bk_db->rollBack();
                    //     return response()->json([
                    //         'success' => false,
                    //         'message' => 'Error processing intermediaries',
                    //         'data' => $intermediariesResult->getData()->error
                    //     ], 400);
                    // }
                    $intermediariesData = json_decode($intermediariesResult->getContent(), true);
                    if ($intermediariesData['success'] == false) {
                        $this->bk_db->rollBack();
                        return response()->json($intermediariesData, 500);
                    }

                    //$userId = $this->bk_db->getPdo()->lastInsertId();

                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];

                    $roleSave = $this->bk_db->table('userroles')->insert($roleData);

                    if (!$roleSave) {
                        $this->bk_db->rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to assign role',
                            'data' => null,
                        ], 400);
                    }

                    $communication_manager = new CommunicationManagement();
                    // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
                    $response = $communication_manager->composeMail(
                        $userData['Email'],
                        false,
                        false,
                        true,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false);
                    if ($response['success'] == false) {
                        // rollback transaction
                        $this->bk_db->rollBack();
                        return response()->json($response, 500);
                    } else {
                        // webhook to update default permissions per role
                        // $base_url = config('app.webhook_url');
                        // $url = $base_url . '/api/update-default-permissions';
                        $user_id = $inserted;
                        $role_id = $role;

                        $this->bk_db->commit();
                        $permissionmanager = new PermissionManagement();
                        $response = $permissionmanager->updateDefaultPermissions($user_id, $role_id);
                        // $response = $pension_user_manager->send_webhook($url, $user_id, $role_id);

                        // $logLogin = new LoggingController();
                        // $portalUser = $this->bk_db->table('portaluserlogoninfo')
                        //     ->select('Id')
                        //     ->where('email', $userData['Email'])
                        //     ->first();

                        // $logLogin->systemlog($portalUser->Id, 'Account Created');

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_success',
                            ['email' => $email],
                            ActivityLogging::SEVERITIES['INFO']['level']
                        );

                        return response()->json([
                            'success' => true,
                            'message' => 'User Registered & Verification email sent successfully',
                            'data' => null,
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to register user',
                        'data' => null,
                    ], 400);
                }

            } else if ($is_corporate) {

                $standardfn = new StandardFunctions();
                $logger = new ActivityLogging();
                $accountid = $standardfn->generateUniqueAccountId();
                $userData = [
                    'created_on' => Carbon::now(),
                    'AccountId' => $accountid,
                    'CompanyName' => $request->company_name ?? null,
                    'Email' => $request->email,
                    'PhoneNumber' => $request->phone,
                    // 'CdsNo' => $request->cds_number ?? null,
                    'IsLocal' => $request->locality === 'local' ? true  :  false,
                    'IsForeign' => $request->locality === 'foreign' ? true  :  false,
                    // 'IsCmaLicensed' => $request->category_type === 'licensed' ? true  :  false,
                    // 'CmaLicenseNo' => $request->category_type === 'licensed' ? $request->cds_number : null,

                    'IsActive' => false,

                ];

                $email = $request->email;
                $validEmail = false;
                $websites = $this->bk_db->table('cmalist')->pluck('Website');
                foreach ($websites as $website) {
                    $parsedUrl = parse_url($website);
                    $domain = $parsedUrl['host'] ?? '';
                    $domainWithoutWWW = str_replace('www.', '', $domain);
                    if (str_ends_with($email, '@' . $domainWithoutWWW)) {
                        $validEmail = true;
                        break;
                    }
                }
                if (!$validEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid email domain. Only company emails are accepted.',
                        'data' => null,
                    ], 400);
                }

                $this->bk_db->beginTransaction();

                // insert and get the id
                $inserted = $this->bk_db->table('portaluserlogoninfo')->insertGetId($userData);
                if ($inserted) {

                    // Process broker_dealer, alternate_dealer, and new_dealer_emails
                    $dealers = [
                        'broker_dealer' => $request->broker_dealer,
                        'alternate_dealer' => $request->alternate_dealer,
                        'new_dealer_emails' => $request->new_dealer_emails,
                    ];

                    $intermediariesResult = $userMgt->processCorporateIntermediaries($inserted, $dealers);
                    // if (!$intermediariesResult->getData()->success) {
                    //     $this->bk_db->rollBack();
                    //     return response()->json([
                    //         'success' => false,
                    //         'message' => 'Error processing intermediaries',
                    //         'data' => $intermediariesResult->getData()->error
                    //     ], 400);
                    // }
                    $intermediariesData = json_decode($intermediariesResult->getContent(), true);
                    if ($intermediariesData['success'] == false) {
                        $this->bk_db->rollBack();
                        return response()->json($intermediariesData, 500);
                    }

                    //$userId = $this->bk_db->getPdo()->lastInsertId();
                    if($is_broker) {
                        $role = 5;
                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];
                    } else if ($is_dealer) {
                        $role = 6;
                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];

                    } else {
                     Log::info("insert corporate role",$role);
                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];

                    }


                    $roleSave = $this->bk_db->table('userroles')->insert($roleData);

                    if (!$roleSave) {
                        $this->bk_db->rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to assign role',
                            'data' => null,
                        ], 400);
                    }

                    $communication_manager = new CommunicationManagement();
                    // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
                    $response = $communication_manager->composeMail(
                        $userData['Email'],
                        false,
                        false,
                        true,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false);
                    if ($response['success'] == false) {
                        // rollback transaction
                        $this->bk_db->rollBack();
                        return response()->json($response, 500);
                    } else {
                        // webhook to update default permissions per role
                        // $base_url = config('app.webhook_url');
                        // $url = $base_url . '/api/update-default-permissions';
                        $user_id = $inserted;
                        $role_id = $role;

                        $this->bk_db->commit();
                        $permissionmanager = new PermissionManagement();
                        $response = $permissionmanager->updateDefaultPermissions($user_id, $role_id);
                        // $response = $pension_user_manager->send_webhook($url, $user_id, $role_id);

                        // $logLogin = new LoggingController();
                        // $portalUser = $this->bk_db->table('portaluserlogoninfo')
                        //     ->select('Id')
                        //     ->where('email', $userData['Email'])
                        //     ->first();

                        // $logLogin->systemlog($portalUser->Id, 'Account Created');

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_success',
                            ['email' => $email],
                            ActivityLogging::SEVERITIES['INFO']['level']
                        );

                        return response()->json([
                            'success' => true,
                            'message' => 'User Registered & Verification email sent successfully',
                            'data' => null,
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to register user',
                        'data' => null,
                    ], 400);
                }

            } else if ($is_admin) {
                $standardfn = new StandardFunctions();
                $logger = new ActivityLogging();
                $accountid = $standardfn->generateUniqueAccountId();
                $userData = [
                    'created_on' => Carbon::now(),
                    'AccountId' => $accountid,
                    'FirstName' => $request->first_name ?? null,
                    'Othernames' => $request->other_names ?? null,
                    // 'CompanyName' => $request->company_name ?? null,
                    'Email' => $request->email,
                    'PhoneNumber' => $request->phone,
                    'IsLocal' => false,
                    'IsForeign' => false,
                    'IsActive' => false,
                ];

                $this->bk_db->beginTransaction();

                // insert and get the id
                $inserted = $this->bk_db->table('portaluserlogoninfo')->insertGetId($userData);
                if ($inserted) {
                    // insert the role
                    $roleData = [
                        'User' => $inserted,
                        'Role' => $role,
                        'created_on' => Carbon::now(),
                    ];

                    $roleSave = $this->bk_db->table('userroles')->insert($roleData);

                    if (!$roleSave) {
                        $this->bk_db->rollBack();

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_failed',
                            ['email' => $email, 'error' => 'Failed to assign role'],
                            ActivityLogging::SEVERITIES['ERROR']['level']
                        );
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to assign role',
                            'data' => null,
                        ], 400);
                    }

                    $communication_manager = new CommunicationManagement();
                    $response = $communication_manager->composeMail(
                        $userData['Email'],
                        $userData['FirstName'],
                        false,
                        true,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false,
                        false);
                    if ($response['success'] == false) {
                        // rollback transaction
                        $this->bk_db->rollBack();
                        return response()->json($response, 500);
                    } else {
                        $user_id = $inserted;
                        $role_id = $role;

                        $this->bk_db->commit();
                        $permissionmanager = new PermissionManagement();
                        $response = $permissionmanager->updateDefaultPermissions($user_id, $role_id);

                        $logger->logUserActivity(
                            $inserted,
                            ActivityLogging::ACTIVITIES['AUTH']['code'],
                            'registration_success',
                            ['email' => $email],
                            ActivityLogging::SEVERITIES['INFO']['level']
                        );

                        return response()->json([
                            'success' => true,
                            'message' => 'Admin user registered & verification email sent successfully',
                            'data' => null,
                        ], 200);
                    }
                } else {
                    $logger->logUserActivity(
                        null,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'registration_failed',
                        ['email' => $email, 'error' => 'Failed to register admin user'],
                        ActivityLogging::SEVERITIES['ERROR']['level']
                    );

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to register admin user',
                        'data' => null,
                    ], 400);
                }
            }


        } catch (\Throwable $th) {
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'registration_error',
                ['email' => $request->email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'Error registering user',
                'data' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    // Invite CorporateIntermediaries function
    public function inviteCorporateIntermediaries(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

    }


    public function setPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:8',
                'is_res' => 'required|boolean',
                'csrf_token' => 'required|string',
                'csrf_timestamp' => 'required|string',
            ]);

            $standard_functions = new StandardFunctions();
            // Verify CSRF token before proceeding
            if (!$standard_functions->validateCsrfToken($request->csrf_token, $request->csrf_timestamp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired security token',
                    'data' => null,
                ], 403);
            }

            $this->bk_db->beginTransaction();

            // Verify the reset signature from email link
            $verification = $this->bk_db->table('portaluseremailverification')
                ->join('portaluserlogoninfo', 'portaluserlogoninfo.Id', '=', 'portaluseremailverification.User')
                ->where('portaluserlogoninfo.Email', $request->email)
                //->where('portaluseremailverification.Signature', $request->signature)
                ->where('portaluseremailverification.ExpiresAt', '>', Carbon::now())
                ->where('portaluseremailverification.IsVerified', false)
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired password reset link',
                    'data' => null,
                ], 400);
            }
            // deployment

            $portalUser = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->email)
                ->first();

            if (!$portalUser) {
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ], 404);
            }

            // Set previous passwords to inactive
            $this->bk_db->table('portaluserpasswordshistory')
                ->where('User', $portalUser->Id)
                ->update(['IsActive' => false]);

            // Insert new password
            $password_hash = password_hash($request->password, PASSWORD_DEFAULT);
            $this->bk_db->table('portaluserpasswordshistory')->insert([
                'Password' => $password_hash,
                'IsActive' => true,
                'User' => $portalUser->Id,
                'created_on' => Carbon::now(),
            ]);

            // Mark verification as used
            $this->bk_db->table('portaluseremailverification')
                ->where('Id', $verification->Id)
                ->update(['IsVerified' => true]);

            // Activate user if needed
            if (!$request->is_res) {
                $this->bk_db->table('portaluserlogoninfo')
                    ->where('Id', $portalUser->Id)
                    ->update(['IsActive' => true]);
            }

            $this->bk_db->commit();

            // $logLogin = new LoggingController();
            // $logLogin->systemlog($portalUser->Id, 'Password Changed');

            $logger = new ActivityLogging();
            $logger->logUserActivity(
                $portalUser->Id,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'password_set_success',
                ['email' => $request->email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                isset($portalUser) ? $portalUser->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'password_set_error',
                ['email' => $request->email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'Error updating password',
                'data' => $th->getMessage(),
            ], 500);
        }
    }
    public function loginUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;
        $logger = new ActivityLogging();

        //use the email to query and get role
        $roles = $this->bk_db->table('portaluserlogoninfo')
            ->join('userroles', 'portaluserlogoninfo.Id', '=', 'userroles.User')
            ->join('roles', 'userroles.Role', '=', 'roles.Id')
            ->where('portaluserlogoninfo.Email', $email)
            ->select('roles.RoleName')
            ->get();



        try {
            $standard_functions = new StandardFunctions();
            // Check for too many failed attempts
            if ($standard_functions->checkTooManyFailedAttempts($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed login attempts. Please try again after ' . $standard_functions::LOCKOUT_TIME . ' minutes.',
                    'data' => null,
                ], 429);
            }

            $this->bk_db->beginTransaction();

            $portalUser = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $email)
                ->first();


            if (!$portalUser) {
                $standard_functions->incrementFailedAttempts($email);
                $logger->logUserActivity(
                    null, // no user id yet
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'login_failed',
                    ['email' => $email, 'error' => 'User not found'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                Log::info('User not found');
                return response()->json([
                    'success' => false,
                    // verbose message to avoid user enumeration
                    'message' => 'Invalid email or password.',
                    'data' => null,
                ], 404);
            }

            // check if user is active -> helps to detremine if this is the first time the user is logging in if there is no password
            if ($portalUser->IsActive == 0) {
                return response()->json([
                    'success' => false,
                    // verbose message to avoid user enumeration
                    'message' => 'This account is not active',
                    'data' => null,
                ], 401);
            }

            // get the password from UserPasswordHistory using the portal user id
            $portalPassword = $this->bk_db->table('portaluserpasswordshistory')
                ->where('User', $portalUser->Id)
                ->where('IsActive',  true)
                ->first();

            if (!$portalPassword) {
                $standard_functions->incrementFailedAttempts($email);
                $logger->logUserActivity(
                    $portalUser->Id,
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'login_failed',
                    ['email' => $email, 'error' => 'Invalid email or password'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                return response()->json([
                    'success' => false,
                    // verbose message to avoid user enumeration
                    'message' => 'Invalid email or password..',
                    'data' => null,
                ], 401);
            }
            // check if the $password is the same as $portalPassword
            if (!Hash::check($password, $portalPassword->Password)) {
                $standard_functions->incrementFailedAttempts($email);
                $logger->logUserActivity(
                    $portalUser->Id,
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'login_failed',
                    ['email' => $email, 'error' => 'Invalid password'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password...',
                    'data' => null,
                ], 401);
            }

            // Clear failed attempts on successful login
                $standard_functions->clearFailedAttempts($email);

                $verified_email = $portalUser->Email;

                // --- BYPASS OTP FOR DEVELOPER ---
                if ($verified_email === 'kevinjulu@gmail.com') {
                    $ip_address = $request->ip();
                    $token = $this->generateSecureToken($portalUser->Id, $ip_address);
                    
                    $stdfns = new StandardFunctions();
                    $role_id = $stdfns->get_user_role($portalUser->Id);

                    $this->bk_db->table('portaluserlogintoken')
                        ->where('User', $portalUser->Id)
                        ->update(['IsActive' => false]);

                    $this->bk_db->table('portaluserlogintoken')->insert([
                        'Token' => $token,
                        'IsActive' => true,
                        'User' => $portalUser->Id,
                        'ActiveRole' => $role_id,
                        'LastLogOn' => Carbon::now(),
                        'IpAddress' => $ip_address,
                        'UserAgent' => $request->header('User-Agent'),
                        'ExpiresAt' => Carbon::now()->addDay(),
                    ]);

                    $this->bk_db->commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Login Successful (Dev Bypass)',
                        'data' => null,
                        'otp_bypassed' => true,
                        'token' => $token // Frontend might need this if it sets cookie manually, but backend usually sets cookie. Wait, backend sets cookie in otpVerification. I should add cookie here too.
                    ], 200)->cookie('k-o-t', $token, 60 * 24);
                }
                // --- END BYPASS ---

                $firstName = $portalUser->FirstName;
                $communication_manager = new CommunicationManagement();
                // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
                $response = $communication_manager->composeMail(
                    $verified_email,
                    $firstName,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false);

                if ($response['success'] == false) {
                    // Rollback transaction if OTP sending fails
                    $this->bk_db->rollBack();
                    return response()->json($response, 500);
                }
                $this->bk_db->commit();

                // $logger->logUserActivity(
                //     $portalUser->Id,
                //     ActivityLogging::ACTIVITIES['AUTH']['code'],
                //     'login_successful',
                //     ['email' => $email],
                //     ActivityLogging::SEVERITIES['INFO']['level']
                // );

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => null,
                ], 200);



        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $standard_functions->incrementFailedAttempts($email);
            $logger->logUserActivity(
                isset($portalUser) ? $portalUser->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'login_error',
                ['email' => $email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'Error logging in',
                'data' => $th->getMessage(),
            ], 500);
        }
    }
    public function otpVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|integer',
            'ip_address' => 'required|string',
        ]);

        $email = $request->email;
        $otp = $request->otp;
        $ip_address = $request->ip_address;

        Log::info('OTP Verification:', ['OTP' => $otp, 'Email' => $email, 'IP Address' => $ip_address]);
        try {

            $this->bk_db->beginTransaction();

            // using the email, get the user id
            $portalUser = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $email)
                // ->where('Role', $role)
                ->first();

            if (!$portalUser) {
                // Return error with code 404 if user not found
                return response()->json([
                    'success' => false,
                    'message' => 'Please register for an account first',
                    'data' => null,
                ], 404);
            }

            // check if OTP exists
            $otp_exists = $this->bk_db->table('portaluserotphistory')
                ->join('portaluserlogoninfo', 'portaluserlogoninfo.Id', '=', 'portaluserotphistory.User')
                ->where('portaluserotphistory.OtpExpiry', '>', Carbon::now()->subMinutes(5))
                ->where('portaluserotphistory.Otp', $otp)
                ->where('portaluserotphistory.IsActive',  false)
                ->select('portaluserotphistory.*', 'portaluserlogoninfo.*')
                ->first();

            if (!$otp_exists) {
                // Return error with code 401
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'data' => null,
                ], 401);
            }

            // update OTP to used

            $this->bk_db->table('portaluserotphistory')
                ->where('User', $otp_exists->Id)
                ->update(['IsActive' => true]);

            // generate a secure token
            // $token = bin2hex(random_bytes(32));
            $token = $this->generateSecureToken($portalUser->Id, $ip_address);

            //update previous token to inactive
            $stdfns = new StandardFunctions();
            $role_id = $stdfns->get_user_role($portalUser->Id);

            $this->bk_db->table('portaluserlogintoken')
                ->where('User', $portalUser->Id)
                ->update(['IsActive' => false]);


            // save the token to the database
            $this->bk_db->table('portaluserlogintoken')->insert([
                'Token' => $token,
                'IsActive' => true,
                'User' => $portalUser->Id,
                'ActiveRole' => $role_id,
                'LastLogOn' => Carbon::now(),
                'IpAddress' => $ip_address,
                'UserAgent' => $request->header('User-Agent'),
                'ExpiresAt' => Carbon::now()->addDay(),
            ]);

            // commit transaction
            $this->bk_db->commit();

            $logger = new ActivityLogging();
            $logger->logUserActivity(
                $portalUser->Id,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'login_successful',
                ['email' => $email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );
            // attach a secure token to the response
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => $token,
            ], 200)->cookie('k-o-t', $token, 60 * 24); // 24 hours

        } catch (\Throwable $th) {
            //throw $th;
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                isset($portalUser) ? $portalUser->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'login_error',
                ['email' => $email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            $this->bk_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error verifying OTP',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $logger = new ActivityLogging();
        try {
            $portalUser = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->email)
                ->first();

            if (!$portalUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please register for an account first',
                    'data' => null,
                ], 404);
            }

            $communication_manager = new CommunicationManagement();
            // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
            $emailSent = $communication_manager->composeMail(
                $request->email,
                $portalUser->FirstName,
                false,
                false,
                false,
                false,
                true,
                false,
                false,
                false,
                false);

            if ($emailSent['success'] == false) {

                $logger->logUserActivity(
                    $portalUser->Id,
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'password_reset_error',
                    ['email' => $request->email, 'error' => 'Failed to send reset email'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send password reset email',
                    'data' => $emailSent['data'],
                ], 500);
            }

            $logger->logUserActivity(
                $portalUser->Id,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'password_reset_success',
                ['email' => $request->email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            return response()->json([
                'success' => true,
                'message' => 'Password reset link has been sent to your email',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            $logger->logUserActivity(
                isset($portalUser) ? $portalUser->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'password_reset_error',
                ['email' => $request->email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'Error initiating password reset',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function otpResend(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        //  $this->
        $email = $request->email;

        $name = $this->bk_db->table('portaluserlogoninfo')
        ->select('FirstName')
        ->where('Email', $email)
        ->first();
        $name = $name->FirstName;


        if($name){
            //means this is an Individual
            $firstName = $name;

        }else{
            $firstName = false;
        }
        Log::info('Resend Email:', ['Resend Email' => $email]);
        Log::info('Resend Name:', ['Resend Name' => $name]);
        try {

            $communication_manager = new CommunicationManagement();
            // public function composeMail($email, $firstName, $isOtp,  $isVerification,isIntermediaryVerification, $isGeneral, $isForgot, $isPromo, $isSubscription, $generalSubject = null, $generalBody = null)
            $response = $communication_manager->composeMail(
                $email,
                $firstName,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false);


            if ($response['success'] == false) {
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error resending OTP',
                    'data' => $response['data'],
                ], 500);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => null,
                ], 200);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error resending OTP',
                'data' => $th,
            ], 500);
        }
    }

    public function logoutUser(Request $request)
    {
        $full_token = $request->cookie('k-o-t');

        if (!$full_token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null,
            ], 401);
        }

        try {
            //code...
            //  $user = $this->get_user_id($login_id);
            //get current user
            $logger = new ActivityLogging();
            $currentUser = $this->getCurrentUserDetails($request);
            if (!$currentUser) {
                $logger->logUserActivity(
                    null,
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'logout_failed',
                    ['error' => 'No session cookie illegal login attempt'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                return response()->json([
                    'success' => false,
                    'message' => 'no session cookie illegal login attempt',
                    'data' => null,
                ], 401);
            }
            $this->bk_db->table('portaluserlogintoken')
                ->join('portaluserlogoninfo', 'portaluserlogintoken.User', '=', 'portaluserlogoninfo.Id')
                ->where('portaluserlogintoken.Token', $full_token)
                ->update(['portaluserlogintoken.IsActive' => false]);
            // unset the cookie
            // setcookie('token', '', [
            //     'expires' => strtotime('-1 day'),
            //     'path' => '/',
            //     'domain' => env('APP_URL'),
            //     'secure' => true,
            //     'httponly' => true,
            //     'samesite' => 'Strict'
            // ]);
            $logger = new ActivityLogging();
            $userData = null;
            if ($currentUser instanceof \Illuminate\Http\JsonResponse) {
                $responseData = $currentUser->getData(true);
                if (isset($responseData['data'])) {
                    $userData = $responseData['data'];
                }
            }
            $userId = $userData && isset($userData['Id']) ? $userData['Id'] : null;
            $userEmail = $userData && isset($userData['Email']) ? $userData['Email'] : null;

            $logger->logUserActivity(
                $userId,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'logout_successful',
                ['email' => $userEmail],
                ActivityLogging::SEVERITIES['INFO']['level']
            );
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {

            return response()->json([
                'success' => false,
                'message' => 'Error logging out: ' . $th->getMessage(), // Include a clear message
                'data' => [], // Set to an empty array instead of object
            ], 500);
        }
    }
    public function getCurrentUserDetails(Request $request)
    {
        // Token extraction logic
        //$token = 'f5ce6d35e3ec525ec68ad7708141b85f6807904d89a694259dbc8e87e0ee80ed';
        // get token from the request headers
        $token = $request->cookie('k-o-t');
        // $ipAddress = $request->input('ip');
        $ipAddress = $request->ip();
        // If the token is not available, return an unauthorized response

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource',
                'data' => null,
            ], 401);
        }

        try {
                      // Validate token and IP address
            $tokenValidation = $this->validateSecureToken($token, $ipAddress);
            $logger = new ActivityLogging();
            if (!$tokenValidation['valid']) {

                $logger->logUserActivity(
                    null,
                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                    'token_validation_failed',
                    ['reason' => $tokenValidation['reason'], 'ip' => $ipAddress, 'token_preview' => substr($token, 0, 8) . '...'],
                    ActivityLogging::SEVERITIES['ERROR']['level']
                );
                Log::warning('Token validation failed', [
                    'reason' => $tokenValidation['reason'],
                    'ip' => $ipAddress,
                    'token_preview' => substr($token, 0, 8) . '...'
                ]);
                Log::info('Token validation failed', [
                    'reason' => $tokenValidation['reason'],
                    'ip' => $ipAddress,
                    'token_preview' => substr($token, 0, 8) . '...'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - ' . $tokenValidation['reason'],
                    'data' => null,
                ], 401);
            }

            // Fetch user details
            $user_details = $this->bk_db->table('portaluserlogintoken AS t')
                ->join('portaluserlogoninfo AS u', 't.User', '=', 'u.Id')
                ->where('t.Token', $token)
                ->where('t.ExpiresAt', '>', Carbon::now())
                ->where('t.IsActive',  true)
                ->select(
                    'u.FirstName',
                    'u.OtherNames',
                    'u.CompanyName',
                    'u.Email',
                    'u.PhoneNumber',
                    'u.UserName',
                    'u.Id',
                    'u.AccountId',
                    'u.CdsNo',

                )
                ->first();

            if (!$user_details) {
                Log::info('User Details not found');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'data' => null,
                ], 401);
            }

            // using the user id get roles from userroles
            $user_roles = $this->bk_db->table('userroles')
                ->where('User', $user_details->Id)
                ->get();

            $roles = [];

            // check for which role is active on the token
            $active_role = $this->bk_db->table('portaluserlogintoken')
                ->where('Token', $token)
                ->first();

            foreach ($user_roles as $role) {
                // name from Roles table
                $original_role_name = $this->bk_db->table('roles')
                    ->where('Id', $role->Role)
                    ->first();

                $role_name = strtolower(str_replace(' ', '', $original_role_name->RoleName));
                $role_id = $original_role_name->Id;

                $roles[] = [
                    'id' => $role_id,
                    'name' => $role_name,
                    'is_active' => $role_id == $active_role->ActiveRole ? true  :  false,
                ];

                // now we get permissions per role
                $role_permissions = $this->bk_db->table('portaluserrolepermissions')
                    ->where('Role', $role->Role)
                    ->where('User', $user_details->Id)
                    ->get();

                $permissions = [];

                if ($role_permissions) {
                    // Get all properties of the permissions object

                    foreach ($role_permissions as $permission) {
                        $permission_properties = get_object_vars($permission);

                        //Log::info('Permission Properties:', ['Permission Properties' => $permission_properties]);
                        // Add permissions that are set to true and start with 'Can'
                        foreach ($permission_properties as $property => $value) {
                            if (str_starts_with($property, 'Can') && ($value === "1" || $value === true || $value === 1)) {
                                $permissions[] = $property;
                            }
                        }

                        //Log::info('Permissions:', ['Permissions' => $permissions]);
                    }

                }

                $roles[count($roles) - 1]['permissions'] = $permissions;
            }

            $user_details->Roles = $roles;

            // select * from leaveservice where Assignee = Id and now() between StartDate and EndDate

            foreach ($roles as $role) {
                if ($role['name'] == 'broker' || $role['name'] == 'authorizeddealer' || $role['name'] == 'corporate' || $role['name'] == 'agent') {
                    $leave_assignments = $this->bk_db->table('leaveservice')
                        ->select('AssignedBy')
                        ->where('Colleague', $user_details->Id)
                        ->where('StartDate', '<=', Carbon::now())
                        ->where('EndDate', '>=', Carbon::now())
                        ->get();

                    // get the email from portaluserlogoninfo
                    $emails = [];
                    foreach ($leave_assignments as $assignment) {
                        $email = $this->bk_db->table('portaluserlogoninfo')
                            ->select('Email')
                            ->where('Id', $assignment->AssignedBy)
                            ->first();

                        $emails[] = $email->Email;
                    }
                    Log::info('Leave Assignments:', ['Leave Assignments' => $emails]);
                    $user_details->LeaveAssignments = $emails;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'User details found',
                'data' => $user_details,
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error getting user details: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting user details',
                'data' => $th->getMessage(),
            ], 500);
        }
    }
    // setting the active role, we can use the token and update the ActiveRole on portaluserlogintoken
    public function setActiveRole(Request $request)
    {

        $request->validate([
            'role' => 'required|integer',
        ]);

        try {

            // get the token
            $token = $request->cookie('k-o-t');

            // update the active role
            $this->bk_db->table('portaluserlogintoken')
                ->where('Token', $token)
                ->update(['ActiveRole' => $request->role]);

            return response()->json([
                'success' => true,
                'message' => 'Active role set successfully',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'set_active_role_error',
                ['Cookie token' => $request->cookie('k-o-t'),'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'Error setting active role',
                'data' => $th->getMessage(),
            ], 500);
        }
    }

    public function delegateUserLeave(Request $request){
        $request->validate([
            // 'quote_id' => 'required|integer|exists:bk_db.quotebook,Id',
            'action' => 'required|string|in:delegate,reject',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'colleague_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'end_date' => 'required|date|after:today'
        ]);

        $quote_id = $request->quote_id;
        $user_email = $request->user_email;
        $colleague_email = $request->colleague_email;
        $action = $request->action;

        try{
            // Start transaction
            $this->bk_db->beginTransaction();
            $stdfns = new StandardFunctions();
            $user = $stdfns->get_user_id($user_email);
            $colleague = $stdfns->get_user_id($colleague_email);
            $quote = $this->bk_db->table('quotebook')->where('Id', $quote_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]);
            }

            if (!$colleague) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delegated User not found.',
                    'data' => null
                ]);
            }

            if ($action == 'suspend') {
                // Check for existing leave in leaveservice
                $existingLeaveInLeaveService = $this->bk_db->table('leaveservice')
                    ->where('EndDate', '>=', Carbon::now())
                    ->where('Colleague', $colleague->Id)
                    ->where('AssignedBy', $user->Id)
                    ->first();

                if (!$existingLeaveInLeaveService) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Active Leave not found so can not suspend.',
                        'data' => null
                    ]);
                }

                //update the leave end date so that it's suspended
                $this->bk_db->table('leaveservice')
                    ->where('EndDate', '>=', Carbon::now())
                    ->where('Colleague', $colleague->Id)
                    ->where('AssignedBy', $user->Id)
                    ->update(['EndDate' => Carbon::now()]);

                // Commit transaction
                $this->bk_db->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Leave suspended successfully.',
                    'data' => null
                ]);

            } else {
                // Check for existing leave in leaveservice
                $existingLeaveInLeaveService = $this->bk_db->table('leaveservice')
                    ->where('EndDate', '>=', Carbon::now())
                    ->where('Colleague', $colleague->Id)
                    ->where('AssignedBy', $user->Id)
                    ->first();

                if ($existingLeaveInLeaveService) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Active Leave found can not delegate leave.',
                        'data' => null
                    ]);
                }

                $this->bk_db->table('leaveservice')->insert([
                    'AssignedBy' => $user->Id,
                    'Colleague' => $colleague->Id,
                    'StartDate' => Carbon::now(),
                    'EndDate' => $request->end_date,
                    'created_on' => Carbon::now(),

                ]);

                // Commit transaction
                $this->bk_db->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Leave delegated successfully.',
                    'data' => null
                ]);

            }


        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'delegate_leave_error',
                ['narration' => 'An error occurred delegating leave. ','error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'An error occurred delegating leave.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function getActivityLogs()
    {
        try {
            // Fetch all activity logs from the table
            $activityLogs = $this->bk_db->table('activitylogs')
                ->join('portaluserlogoninfo', 'activitylogs.User', '=', 'portaluserlogoninfo.Id')
                ->join('roles', 'activitylogs.Role', '=', 'roles.Id')
                ->select(
                    'activitylogs.*',
                    'portaluserlogoninfo.FirstName',
                    'portaluserlogoninfo.OtherNames',
                    'portaluserlogoninfo.CompanyName',
                    'roles.RoleName'
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Activity logs retrieved successfully.',
                'data' => $activityLogs
            ]);
        } catch (\Throwable $th) {
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'get_activity_logs_error',
                ['narration' => 'An error occurred retrieving activity logs.', 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred retrieving activity logs.',
                'data' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            // Fetch all users from the portaluserlogoninfo table
            $users = $this->bk_db->table('portaluserlogoninfo')
                // ->select(
                //     'Id',
                //     'Email',
                //     'FirstName',
                //     'OtherNames',
                //     'CompanyName',
                //     'PhoneNumber',
                //     'UserName',
                //     'AccountId',
                //     'CdsNo'
                // )
                ->get();

            // For each user, fetch their roles
            foreach ($users as $user) {
                // Get all roles assigned to this user
                $userRoles = $this->bk_db->table('userroles')
                    ->join('roles', 'userroles.Role', '=', 'roles.Id')
                    ->where('userroles.User', $user->Id)
                    ->select('roles.Id', 'roles.RoleName', 'roles.RoleDescription')
                    ->get();

                // Add roles to the user object
                $user->Roles = $userRoles;
            }

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully',
                'data' => $users,
            ], 200);

        } catch (\Throwable $th) {
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'get_all_users_error',
                ['narration' => 'An error occurred retrieving users.', 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching users',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }


    public function suspendUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $user_id = $request->user_id;

        try {

            $this->bk_db->beginTransaction();
            // Check if user exists
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ], 404);
            }


            // Update user status to inactive
            $suspend = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->where('IsActive' , false)
                ->first();

            if ($suspend){

                return response()->json([
                    'success' => false,
                    'message' => 'User is already suspended',
                    // 'error' => $th->getMessage(),
                ], 500);

            }else{

                $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->update(['IsActive' => false]);

            }

            // Log the activity
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'user_suspended',
                ['narration' => 'User account suspended', 'suspended_user_id' => $user_id, 'suspended_user_email' => $user->Email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'User suspended successfully',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'suspend_user_error',
                ['narration' => 'An error occurred while suspending user.', 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while suspending user',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function reactivateUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $user_id = $request->user_id;

        try {

            $this->bk_db->beginTransaction();
            // Check if user exists
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ], 404);
            }


            // Update user status to inactive
            $suspend = $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->where('IsActive' , true)
                ->first();

            if ($suspend){

                return response()->json([
                    'success' => false,
                    'message' => 'User is already active',
                    // 'error' => $th->getMessage(),
                ], 500);

            }else{

                $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $user_id)
                ->update(['IsActive' => true]);

            }

            // Log the activity
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'user_reactivated',
                ['narration' => 'User account reactivated', 'reactivated_user_id' => $user_id, 'reactivated_user_email' => $user->Email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'User reactivated successfully',
                'data' => null,
            ], 200);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                0,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'user_reactivation_error',
                ['narration' => 'An error occurred while reactivating user.', 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while reactivating user',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllBrokersAndDealers()
    {
        try {
            // Get all users with roles 5 and 6 (brokers and dealers)
            $brokersAndDealers = $this->bk_db->table('userroles')
                ->whereIn('Role', [5, 6])
                ->join('portaluserlogoninfo', 'userroles.User', '=', 'portaluserlogoninfo.Id')
                ->where('portaluserlogoninfo.IsActive', true)
                ->select(
                    'portaluserlogoninfo.Id',
                    'portaluserlogoninfo.Email',
                    'portaluserlogoninfo.FirstName',
                    'portaluserlogoninfo.OtherNames',
                    'portaluserlogoninfo.CompanyName',
                    'portaluserlogoninfo.PhoneNumber',
                    'portaluserlogoninfo.UserName',
                    'portaluserlogoninfo.AccountId',
                    'portaluserlogoninfo.CdsNo'
                )
                ->get();

            // For each user, fetch their roles
            foreach ($brokersAndDealers as $user) {
                // Get all roles assigned to this user
                $userRoles = $this->bk_db->table('userroles')
                    ->join('roles', 'userroles.Role', '=', 'roles.Id')
                    ->where('userroles.User', $user->Id)
                    ->select('roles.Id', 'roles.RoleName', 'roles.RoleDescription')
                    ->get();

                // Add roles to the user object
                $user->Roles = $userRoles;
            }

            // Remove duplicate entries based on Email
            $uniqueEmails = [];
            $uniqueBrokersAndDealers = [];

            foreach ($brokersAndDealers as $user) {
                if (!in_array($user->Email, $uniqueEmails)) {
                    $uniqueEmails[] = $user->Email;
                    $uniqueBrokersAndDealers[] = $user;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Brokers and dealers fetched successfully',
                'data' => $uniqueBrokersAndDealers,
            ], 200);

        } catch (\Throwable $th) {
            $logger = new ActivityLogging();
            $logger->logUserActivity(
                null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'get_brokers_dealers_error',
                ['narration' => 'An error occurred retrieving brokers and dealers.', 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching brokers and dealers',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function completeIntermediaryRegistration(Request $request)
    {
        $request->validate([
            'is_broker' => 'required|boolean',
            'is_dealer' => 'required|boolean',
            'email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'token' => 'required|string',
            'signature' => 'required|string',
            'csrf_token' => 'required|string',
            'csrf_timestamp' => 'required|string',
            'company_name' => 'required|string',
            'phone' => 'required|string',
            // 'first_name' => 'required|string',
            // 'other_names' => 'required|string',
            // 'cds_number' => 'required|string',
            'locality' => 'required|string|in:local,foreign',
            'category_type' => 'nullable|string',
        ]);

        $is_broker = $request->is_broker;
        $is_dealer = $request->is_dealer;
        $email = $request->email;

        if ($is_broker) {
            $role = 5;
        } else if ($is_dealer) {
            $role = 6;
        }

        try {
            $this->bk_db->beginTransaction();
            $userMgt = new UserManagement();
            $logger = new ActivityLogging();
            $stdfns = new StandardFunctions();

            // Verify CSRF token before proceeding
            if (!$stdfns->validateCsrfToken($request->csrf_token, $request->csrf_timestamp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired security token',
                    'data' => null,
                ], 403);
            }


            // Verify the registration signature from email link
            // $verification = $this->bk_db->table('portaluseremailverification')
            //     ->join('portaluserlogoninfo', 'portaluserlogoninfo.Id', '=', 'portaluseremailverification.User')
            //     ->where('portaluserlogoninfo.Email', $request->email)
            //     ->where('portaluseremailverification.Signature', $request->signature)
            //     ->where('portaluseremailverification.ExpiresAt', '>', Carbon::now())
            //     ->where('portaluseremailverification.IsVerified', false)
            //     ->first();

            // if (!$verification) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Invalid or expired registration link',
            //         'data' => null
            //     ], 400);
            // }
            $user = $stdfns->get_user_id($email);

            $intermediary = $this->bk_db->table('portalintermediary')
                ->where('IntermediaryId', $user->Id)
                ->where('IsActive', false)
                ->first();


        if (!$intermediary) {
            // return response()->json($userExists, 400);
            return response()->json([
                'success' => false,
                'message' => 'Intermediary not found',
                'data' => null,
            ], 400);
        }
            // Update intermediary details
            $updated = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->email)
                ->update([
                    'CompanyName' => $request->company_name,
                    'PhoneNumber' => $request->phone,
                    // 'FirstName' => $request->first_name,
                    // 'OtherNames' => $request->other_names,
                    // 'CdsNo' => $request->cds_number,
                    'IsLocal' => $request->locality === 'local' ? true  :  false,
                    'IsForeign' => $request->locality === 'foreign' ? true  :  false,
                    'IsActive' => true,
                    // 'IsCmaLicensed' => $request->category_type === 'licensed' ? true  :  false,
                    // 'CmaLicenseNo' => $request->category_type === 'licensed' ? $request->cds_number : null,
                    'dola' => Carbon::now()
                ]);

            if (!$updated) {
                $this->bk_db->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update intermediary details',
                    'data' => null
                ], 400);
            }

            // Mark verification as used
            // $this->bk_db->table('portaluseremailverification')
            //     ->where('Id', $verification->Id)
            //     ->update(['IsVerified' => true]);

            $this->bk_db->table('portalintermediary')
                ->where('Id', $intermediary->Id)
                ->update(['IsActive' => true]);

                //for those who are dealers, update role to 6
            if ($is_dealer) {
                $this->bk_db->table('userroles')
                ->where('User', $user->Id)
                ->where('Role', 5)
                ->update(['Role' => $role]);
            }

                // Send approval notification to client
                $notifs = new NotificationController();
                $notification_status = $notifs->createNotification(
                    $user->Id,
                    3, // Account update notification type
                    'Your Account is now Active.',
                    null,
                    null

                );

                if ($notification_status['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($notification_status);
                }

                $notification_status2 = $notifs->createNotification(
                    $intermediary->User,
                    3, // Account update notification type
                    'Your Intermediary is now Active. Intermediary Email' . $email,
                    null,
                    null

                );

                if ($notification_status2['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($notification_status);
                }

                // Send email notification
                $communication_manager = new CommunicationManagement();
                $emailResponse = $communication_manager->composeMail(
                    $email,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    "Account Registration Successful",
                    "Your account is now active for use. Login to the portal. "
                );

                if ($emailResponse['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => $emailResponse
                    ], 500);
                }

            // Log the activity
            $logger->logUserActivity(
                $user->Id,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'intermediary_registration_completed',
                ['email' => $request->email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Intermediary registration completed successfully',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger->logUserActivity(
                isset($intermediary) ? $user->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'intermediary_registration_error',
                ['email' => $email, 'error' => $th->getMessage()],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred completing intermediary registration',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function approveIntermediaryClient(Request $request)
    {
        $request->validate([
            'intermediary_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'client_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'notification_id' => 'required|integer|exists:bk_db.notificationservices,Id',
            'is_approved' => 'required|boolean'
        ]);

        try {
            $this->bk_db->beginTransaction();
            $logger = new ActivityLogging();

            // Get user IDs
            $standardFn = new StandardFunctions();
            $intermediary = $standardFn->get_user_id($request->intermediary_email);
            $client = $standardFn->get_user_id($request->client_email);

            if (!$intermediary || !$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid intermediary or client email',
                    'data' => null
                ], 400);
            }

            // Update portalintermediary status
            if ($request->is_approved) {
                // Activate the relationship
                $this->bk_db->table('portalintermediary')
                    ->where('User', $client->Id)
                    ->where('IntermediaryId', $intermediary->Id)
                    ->update([
                        'IsActive' => true,
                        'dola' => Carbon::now()
                    ]);

                // Send approval notification to client
                $notifs = new NotificationController();
                $notification_status = $notifs->createNotification(
                    $client->Id,
                    3, // Account update notification type
                    'Your intermediary request has been approved by ' . $intermediary->FirstName . ' ' . $intermediary->OtherNames,
                    null,
                    null
                );

                if ($notification_status['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($notification_status);
                }

                // Send email notification
                $communication_manager = new CommunicationManagement();
                $emailResponse = $communication_manager->composeMail(
                    $client->Email,
                    $client->FirstName,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    "Intermediary Request Approved",
                    "Your intermediary request has been approved by " . $intermediary->FirstName . " " . $intermediary->OtherNames
                );

                if ($emailResponse['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($emailResponse, 500);
                }
            } else {
                // Delete the relationship if rejected
                // $this->bk_db->table('portalintermediary')
                //     ->where('User', $client->Id)
                //     ->where('IntermediaryId', $intermediary->Id)
                //     ->delete();
                if ($intermediary->FirstName === null){
                    $approverName = $intermediary->CompanyName;
                } else {
                    $approverName = $intermediary->FirstName . ' ' . $intermediary->OtherNames;
                }

                // Send rejection notification to client
                $notifs = new NotificationController();
                $notification_status = $notifs->createNotification(
                    $client->Id,
                    3, // Account update notification type
                    'Your intermediary request has been rejected by ' . $approverName,
                    null,
                    null
                );

                if ($notification_status['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($notification_status);
                }

                // Send email notification
                $communication_manager = new CommunicationManagement();
                $emailResponse = $communication_manager->composeMail(
                    $client->Email,
                    $client->FirstName,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    "Intermediary Request Rejected",
                    "Your intermediary request has been rejected by " . $intermediary->FirstName . " " . $intermediary->OtherNames
                );

                if ($emailResponse['success'] == false) {
                    $this->bk_db->rollBack();
                    return response()->json($emailResponse, 500);
                }
            }

            // Mark the notification as read
            $this->bk_db->table('notificationservices')
                ->where('Id', $request->notification_id)
                ->update(['IsRead' => true]);

            // Log the activity
            $logger->logUserActivity(
                $intermediary->Id,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'intermediary_client_' . ($request->is_approved ? 'approved' : 'rejected'),
                ['intermediary_email' => $request->intermediary_email, 'client_email' => $request->client_email],
                ActivityLogging::SEVERITIES['INFO']['level']
            );

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Intermediary client request ' . ($request->is_approved ? 'approved' : 'rejected') . ' successfully',
                'data' => null
            ]);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            $logger->logUserActivity(
                isset($intermediary) ? $intermediary->Id : null,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'intermediary_client_approval_error',
                [
                    'intermediary_email' => $request->intermediary_email,
                    'client_email' => $request->client_email,
                    'error' => $th->getMessage()
                ],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );

            return response()->json([
                'success' => false,
                'message' => 'An error occurred processing intermediary client request',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function getAdminUsers()
    {
        try {
            // Get all users with role 1 (admin)
            $adminUsers = $this->bk_db->table('portaluserlogoninfo as u')
                ->join('userroles as ur', 'u.Id', '=', 'ur.User')
                ->where('ur.Role', true)
                ->where('u.IsActive', true)
                ->select([
                    'u.Id',
                    'u.FirstName',
                    'u.OtherNames',
                    'u.Email',
                    'u.CompanyName'
                ])
                ->get();

            if ($adminUsers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No admin users found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin users fetched successfully',
                'data' => $adminUsers
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching admin users:', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin users',
                'error' => $th->getMessage()
            ], 500);
        }
    }
//getUserIntermediaries
    public function getUserIntermediaries(Request $request)
    {
        try {
            $request->validate([
                'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email'
            ]);

            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->user_email)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ], 404);
            }
            $this->bk_db->beginTransaction();
            $intermediaries = $this->bk_db->table('portalintermediary')
                ->join('portaluserlogoninfo as u', 'u.Id', '=', 'portalintermediary.IntermediaryId')
                ->where('portalintermediary.User', $user->Id)
                ->select(
                    'portalintermediary.*',
                    'u.FirstName as IntermediaryFirstName',
                    'u.OtherNames as IntermediaryOtherNames',
                    'u.CompanyName as IntermediaryCompanyName',
                    'u.Email as IntermediaryEmail',
                    'u.PhoneNumber as IntermediaryPhoneNumber',
                    'u.IsActive as IntermediaryIsActive'
                )
                ->get(); //continue this function

            $this->bk_db->commit();
            return response()->json([
                'success' => true,
                'message' => 'User intermediaries fetched successfully',
                'data' => $intermediaries
            ]);
        } catch (\Throwable $th) {
            $this->bk_db->rollBack();
            Log::error('Error fetching user intermediaries:', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching user intermediaries',
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

            // Get template variables
            $variables = $this->getTemplateVariables($templateName);
            if ($variables === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not parse template variables',
                    'data' => null
                ], 400);
            }

            // Validate required variables
            $missingVariables = array_diff($variables, array_keys($templateData));
            if (!empty($missingVariables)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required template variables',
                    'data' => [
                        'required' => $variables,
                        'missing' => array_values($missingVariables)
                    ]
                ], 400);
            }

            // Render template with provided data
            try {
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
                        'text' => $text,
                        'variables' => $variables
                    ]
                ]);

            } catch (\Throwable $renderError) {
                Log::error('Template render error:', [
                    'template' => $templateName,
                    'error' => $renderError->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error rendering template',
                    'error' => $renderError->getMessage()
                ], 500);
            }

        } catch (\Throwable $th) {
            Log::error('Template preview error:', [
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
            if ($content === false) {
                Log::error('Could not read template file:', ['template' => $templateName]);
                return null;
            }

            // Extract variables using multiple regex patterns
            $patterns = [
                '/\{\{\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\}\}/', // {{ $variable }}
                '/\@if\s*\(\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)/', // @if($variable)
                '/\{\!\!\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\!\!\}/', // {!! $variable !!}
                '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', // $variable
            ];

            $variables = [];
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                if (!empty($matches[1])) {
                    $variables = array_merge($variables, $matches[1]);
                }
            }

            // Filter and return unique variables
            $variables = array_unique(array_filter($variables));

            // Sort variables alphabetically
            sort($variables);

            return array_values($variables);

        } catch (\Throwable $th) {
            Log::error('Error parsing template variables:', [
                'template' => $templateName,
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
            return null;
        }
    }

         // Generate a secure token tied to user ID and IP address

         private function generateSecureToken($userId, $ipAddress)
         {
             // Generate a random base token
             $baseToken = bin2hex(random_bytes(32));

             // Create a payload that includes user ID, IP address, and timestamp
             $payload = [
                 'user_id' => $userId,
                 'ip_address' => $ipAddress,
                 'timestamp' => time(),
                 'base_token' => $baseToken
             ];

             // Convert payload to JSON and encrypt it
             $jsonPayload = json_encode($payload);

             // Encrypt the payload using Laravel's encryption
             $encryptedPayload = encrypt($jsonPayload);

             // Create final token by combining encrypted payload with HMAC signature
             $signature = hash_hmac('sha256', $encryptedPayload, config('app.key'));

             // Combine encrypted payload and signature (base64 encoded for safe transmission)
             $finalToken = base64_encode($encryptedPayload . '|' . $signature);

             return $finalToken;
         }


          // Validate secure token and IP address

         private function validateSecureToken($token, $currentIpAddress)
         {
             try {
                 // Decode the token
                 $decodedToken = base64_decode($token);

                 if (!$decodedToken) {
                     return ['valid' => false, 'reason' => 'Invalid token format'];
                 }

                 // Split encrypted payload and signature
                 $parts = explode('|', $decodedToken);

                 if (count($parts) !== 2) {
                     return ['valid' => false, 'reason' => 'Invalid token structure'];
                 }

                 [$encryptedPayload, $signature] = $parts;

                 // Verify HMAC signature
                 $expectedSignature = hash_hmac('sha256', $encryptedPayload, config('app.key'));

                 if (!hash_equals($expectedSignature, $signature)) {
                     return ['valid' => false, 'reason' => 'Token signature verification failed'];
                 }

                 // Decrypt the payload
                 $jsonPayload = decrypt($encryptedPayload);
                 $payload = json_decode($jsonPayload, true);
                 // Log::info($payload);

                 if (!$payload || !isset($payload['ip_address']) || !isset($payload['user_id'])) {
                     return ['valid' => false, 'reason' => 'Invalid token payload'];
                 }

                 // Verify IP address matches (with dev environment exception)
                 $isDevelopment = in_array(config('app.env'), ['local', 'development', 'dev', 'uat']);

                 if ($payload['ip_address'] !== $currentIpAddress) {
                     // In development environment, allow IP address changes
                     if ($isDevelopment) {
                         Log::info('Development mode: Allowing IP address change', [
                             'token_ip' => $payload['ip_address'],
                             'current_ip' => $currentIpAddress,
                             'user_id' => $payload['user_id']
                         ]);
                         // In dev mode, skip IP validation - just log the change
                     } else {
                         // In production, strict IP validation
                         return ['valid' => false, 'reason' => 'IP address mismatch'];
                     }
                 }

                 // Additional check: verify token exists in database and is valid
                 $tokenQuery = $this->bk_db->table('portaluserlogintoken')
                     ->where('Token', $token)
                     ->where('User', $payload['user_id'])
                     ->where('ExpiresAt', '>', now())
                     ->where('IsActive',  true);

                 // In dev mode, don't check IP address in database query
                 if (!$isDevelopment) {
                     $tokenQuery->where('IpAddress', $currentIpAddress);
                 }

                 $tokenRecord = $tokenQuery->first();
                 $logger = new ActivityLogging();
                 if (!$tokenRecord) {
                    $logger->logUserActivity(
                        null,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'token_validation_failed',
                        ['reason' => 'Token lookup failed', 'ip' => $currentIpAddress, 'token_preview' => substr($token, 0, 16) . '...','user_id' => $payload['user_id'],'is_development' => $isDevelopment,'query_conditions' => [
                            'user_id' => $payload['user_id'],
                            'expires_check' => 'ExpiresAt > now()',
                            'is_active' => true,
                            'ip_check_enabled' => !$isDevelopment,
                            'query_conditions' => [
                                'user_id' => $payload['user_id'],
                                'expires_check' => 'ExpiresAt > now()',
                                'is_active' => true,
                                'ip_check_enabled' => !$isDevelopment
                            ]
                        ]],
                        ActivityLogging::SEVERITIES['ERROR']['level']
                    );
                     // Add debugging for token lookup failure
                     Log::warning('Token lookup failed', [
                         'user_id' => $payload['user_id'],
                         'token_preview' => substr($token, 0, 16) . '...',
                         'current_ip' => $currentIpAddress,
                         'token_ip' => $payload['ip_address'],
                         'is_development' => $isDevelopment,
                         'query_conditions' => [
                             'user_id' => $payload['user_id'],
                             'expires_check' => 'ExpiresAt > now()',
                             'is_active' => true,
                             'ip_check_enabled' => !$isDevelopment
                         ]
                     ]);
                     Log::info('Token lookup failed',[
                        'user_id' => $payload['user_id'],
                        'token_preview' => substr($token, 0, 16) . '...',
                        'current_ip' => $currentIpAddress,
                        'token_ip' => $payload['ip_address'],
                        'is_development' => $isDevelopment,
                        'query_conditions' => [
                            'user_id' => $payload['user_id'],
                            'expires_check' => 'ExpiresAt > now()',
                            'is_active' => true,
                            'ip_check_enabled' => !$isDevelopment
                        ]
                     ]);

                     return ['valid' => false, 'reason' => 'Token not found or expired'];
                 }

                 return ['valid' => true, 'user_id' => $payload['user_id']];

             } catch (\Throwable $th) {
                 Log::error('Token validation error: ' . $th->getMessage());
                 return ['valid' => false, 'reason' => 'Token validation error'];
             }
         }

         // Get active concurrent users count
         public function getConcurrentUsers()
         {
             try {
                 $activeUsers = $this->bk_db->table('portaluserlogintoken')
                     ->where('IsActive',  true)
                     ->where('ExpiresAt', '>', Carbon::now())
                     ->count();

                 $userDetails = $this->bk_db->table('portaluserlogintoken as t')
                     ->join('portaluserlogoninfo as u', 't.User', '=', 'u.Id')
                     ->where('t.IsActive',  true)
                     ->where('t.ExpiresAt', '>', Carbon::now())
                     ->select(
                         'u.Email',
                         'u.FirstName',
                         'u.CompanyName',
                         't.LastLogOn',
                         't.IpAddress',
                         't.UserAgent'
                     )
                     ->get();

                 return response()->json([
                     'success' => true,
                     'message' => 'Active concurrent users retrieved successfully',
                     'data' => [
                         'total_active_users' => $activeUsers,
                         'active_users' => $userDetails
                     ]
                 ], 200);

             } catch (\Throwable $th) {
                 Log::error('Error getting concurrent users: ' . $th->getMessage());
                 return response()->json([
                     'success' => false,
                     'message' => 'Error getting concurrent users',
                     'error' => $th->getMessage()
                 ], 500);
             }
         }

         // Get client IP address considering proxies

         private function getClientIpAddress(Request $request)
         {
             // Check for various IP headers in order of preference
             $ipHeaders = [
                 'HTTP_CF_CONNECTING_IP',     // Cloudflare
                 'HTTP_CLIENT_IP',            // Proxy
                 'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
                 'HTTP_X_FORWARDED',          // Proxy
                 'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
                 'HTTP_FORWARDED_FOR',        // Proxy
                 'HTTP_FORWARDED',            // Proxy
                 'REMOTE_ADDR'                // Standard
             ];

             foreach ($ipHeaders as $header) {
                 if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                     $ips = explode(',', $_SERVER[$header]);
                     // Log::info($ips);
                     $ip = trim($ips[0]);

                     // Validate IP address
                     if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                         return $ip;
                     }
                 }
             }

             // Fallback to request IP
             return $request->ip();
         }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
        ]);

        $token = $request->cookie('k-o-t');
        $ipAddress = $request->ip();

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validation = $this->validateSecureToken($token, $ipAddress);
        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['reason']], 401);
        }

        $userId = $validation['user_id'];

        try {
            $this->bk_db->table('portaluserlogoninfo')
                ->where('Id', $userId)
                ->update([
                    'FirstName' => $request->first_name,
                    'OtherNames' => $request->last_name,
                    'dola' => Carbon::now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating profile',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getActiveSessions(Request $request)
    {
        $token = $request->cookie('k-o-t');
        $ipAddress = $request->ip();

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validation = $this->validateSecureToken($token, $ipAddress);
        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['reason']], 401);
        }

        $userId = $validation['user_id'];

        try {
            $sessions = $this->bk_db->table('portaluserlogintoken')
                ->where('User', $userId)
                ->where('IsActive', true)
                ->where('ExpiresAt', '>', Carbon::now())
                ->select('Id', 'LastLogOn', 'IpAddress', 'UserAgent', 'Token')
                ->orderBy('LastLogOn', 'desc')
                ->get();

            $formattedSessions = $sessions->map(function ($session) use ($token) {
                return [
                    'id' => $session->Id,
                    'is_current' => $session->Token === $token,
                    'ip_address' => $session->IpAddress,
                    'last_active' => Carbon::parse($session->LastLogOn)->diffForHumans(),
                    'device' => $this->parseUserAgent($session->UserAgent)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedSessions
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching sessions',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function revokeSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer'
        ]);

        $token = $request->cookie('k-o-t');
        $ipAddress = $request->ip();

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validation = $this->validateSecureToken($token, $ipAddress);
        if (!$validation['valid']) {
            return response()->json(['success' => false, 'message' => $validation['reason']], 401);
        }

        $userId = $validation['user_id'];

        try {
            // Ensure the session belongs to the user
            $updated = $this->bk_db->table('portaluserlogintoken')
                ->where('Id', $request->session_id)
                ->where('User', $userId)
                ->update(['IsActive' => false]);

            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Session revoked successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Session not found or already inactive'], 404);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error revoking session',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function parseUserAgent($userAgent)
    {
        if (!$userAgent) return 'Unknown Device';
        
        $browser = 'Unknown Browser';
        if (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/Opera/i', $userAgent)) $browser = 'Opera';

        $os = 'Unknown OS';
        if (preg_match('/Windows/i', $userAgent)) $os = 'Windows';
        elseif (preg_match('/Mac/i', $userAgent)) $os = 'Mac OS';
        elseif (preg_match('/Linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/Android/i', $userAgent)) $os = 'Android';
        elseif (preg_match('/iPhone/i', $userAgent)) $os = 'iPhone';
        elseif (preg_match('/iPad/i', $userAgent)) $os = 'iPad';

        return "$browser on $os";
    }
}
