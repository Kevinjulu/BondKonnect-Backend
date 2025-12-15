<?php

namespace App\Http\Controllers\V1\Defaults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Logs\ActivityLogging;
use App\Http\Controllers\V1\RoleActions\PermissionManagement;

class UserManagement extends Controller
{
    //

public function checkIfUserExists($email){
    try {
        $user = $this->bk_db->table('portaluserlogoninfo')
        ->where('Email', $email)
        ->first();

    return $user ? true : false;
    } catch (\Throwable $th) {

    Log::error('Error checking if user exists:', ['error' => $th->getMessage()]);
    return false;
    }
}

public function processIntermediaries($userid, $dealers) {
    $logger = new ActivityLogging();
    try {
        $dealerArrays = [
            'broker_dealer' => $dealers['broker_dealer'] ?? [],
            'alternate_dealer' => $dealers['alternate_dealer'] ?? [],
            'new_dealer_emails' => $dealers['new_dealer_emails'] ?? []
        ];

        // Check if all arrays are empty
        if (empty(array_filter($dealerArrays))) {
            $logger->logUserActivity(
                $userid,
                ActivityLogging::ACTIVITIES['AUTH']['code'],
                'registration_error',
                ['error' => 'No dealer emails provided'],
                ActivityLogging::SEVERITIES['ERROR']['level']
            );
            return response()->json([
                'success' => false,
                'message' => 'No dealer emails provided',
                'data' => null
            ], 400);
        }

        Log::info('Processing intermediaries:', ['dealers' => $dealerArrays]);

        foreach ($dealerArrays as $type => $dealerList) {
            if (empty($dealerList) || !is_array($dealerList)) {
                continue;
            }

            foreach ($dealerList as $email) {
                try {
                    // Skip if email is empty or not a string
                    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Log::warning('Invalid email format:', ['email' => $email]);
                        continue;
                    }

                    $logonInfoId = null;
                    $accountid = null;

                    // For existing dealers (broker_dealer and alternate_dealer), check if they exist
                    if ($type === 'broker_dealer' || $type === 'alternate_dealer') {
                        // Check if user already exists
                        $existingUser = $this->bk_db->table('portaluserlogoninfo')
                            ->where('Email', $email)
                            ->first();

                        if ($existingUser) {
                            Log::info('Using existing intermediary:', ['email' => $email]);
                            $logonInfoId = $existingUser->Id;
                        } else {
                           //If it does not exist , report error
                                $logger->logUserActivity(
                                    $userid,
                                    ActivityLogging::ACTIVITIES['AUTH']['code'],
                                    'registration_error',
                                    ['email' => $email, 'error' => 'Failed to register intermediary'],
                                    ActivityLogging::SEVERITIES['ERROR']['level']
                                );
                                // rollback transaction
                                $this->bk_db->rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => 'The Brokers/Dealers selected in the list are no longer active',
                                    'data' => null
                                ], 400);

                        }
                    } else {
                        // For new_dealer_emails, process as before
                        // Check if user already exists
                        if ($this->checkIfUserExists($email)) {
                            Log::info('Intermediary User already exists:', ['email' => $email]);
                            $this->bk_db->rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Intermediary User already exists',
                                'data' => null
                            ], 400);
                        }

                        $standardfn = new StandardFunctions();
                        $accountid = $standardfn->generateUniqueAccountId();
                        Log::info('Generated account ID:', ['accountid' => $accountid]);
                        Log::info('Processing intermediary:', ['email' => $email]);

                        // Insert into portaluserlogoninfo
                        $logonInfoId = $this->bk_db->table('portaluserlogoninfo')->insertGetId([
                            'created_on' => Carbon::now(),
                            'AccountId' => $accountid,
                            'Email' => $email,
                            'IsActive' => false,
                        ]);

                        // insert the broker role for each first
                        $roleData = [
                            'User' => $logonInfoId,
                            'Role' => 5,
                            'created_on' => Carbon::now(),
                        ];

                        $roleSave = $this->bk_db->table('userroles')->insert($roleData);
                        if (!$roleSave) {
                            $logger->logUserActivity(
                                $userid,
                                ActivityLogging::ACTIVITIES['AUTH']['code'],
                                'registration_error',
                                ['email' => $email, 'error' => 'Failed to assign role'],
                                ActivityLogging::SEVERITIES['ERROR']['level']
                            );
                            $this->bk_db->rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Failed to assign role',
                                'data' => null,
                            ], 400);
                        }

                        // Send intermediary verification email
                        $communication_manager = new CommunicationManagement();
                        $response = $communication_manager->composeMail(
                            $email,
                            false,
                            false,
                            false,
                            true,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false
                        );
                        Log::info('Intermediary Verification email sent:', ['email' => $email]);

                        if ($response['success'] == false) {
                            $logger->logUserActivity(
                                $userid,
                                ActivityLogging::ACTIVITIES['AUTH']['code'],
                                'registration_error',
                                ['email' => $email, 'error' => 'Failed to send verification email'],
                                ActivityLogging::SEVERITIES['ERROR']['level']
                            );
                            // rollback transaction
                            $this->bk_db->rollBack();
                            return response()->json($response, 500);
                        }
                    }

                    // Insert into portalintermediary for all types
                    $this->bk_db->table('portalintermediary')->insert([
                        'User' => $userid,
                        'IntermediaryId' => $logonInfoId,
                        'IsActive' => false,
                        'created_on' => Carbon::now()
                    ]);
                    Log::info('Intermediary inserted:', ['email' => $email]);

                    // Log successful registration
                    $logger->logUserActivity(
                        $userid,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'registration_success',
                        ['email' => $email],
                        ActivityLogging::SEVERITIES['INFO']['level']
                    );

                } catch (\Throwable $th) {
                    $logger->logUserActivity(
                        $userid,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'registration_error',
                        [
                            'email' => $email,
                            'error' => $th->getMessage(),
                            'line' => $th->getLine(),
                            'file' => $th->getFile()
                        ],
                        ActivityLogging::SEVERITIES['ERROR']['level']
                    );
                    Log::error('Error processing email:', [
                        'email' => $email,
                        'error' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ]);
                    continue; // Skip this email and continue with the next one
                }
            }
        }
        // return response()->json(['success'=>true,'message' => 'Intermediaries processed successfully'], 200);
        return response()->json([
            'success' => true,
            'message' => 'Intermediaries processed successfully',
            // 'data' => $users,
        ], 200);
    } catch (\Throwable $th) {
        $logger->logUserActivity(
            $userid,
            ActivityLogging::ACTIVITIES['AUTH']['code'],
            'registration_error',
            [
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ],
            ActivityLogging::SEVERITIES['ERROR']['level']
        );
        $this->bk_db->rollBack();
        Log::error('Error processing intermediaries:', ['error' => $th->getMessage(),'line'=>$th->getLine(),'file'=>$th->getFile()]);
        return response()->json([
            'success' => false,
            'message' => 'Error processing intermediaries',
            'error' => $th->getMessage(),
            'line' => $th->getLine(),
            'file' => $th->getFile()
        ], 500);

    }
}

public function processCorporateIntermediaries($userid, $dealers) {
    $logger = new ActivityLogging();
    try {
        $dealerArrays = [
            'broker_dealer' => $dealers['broker_dealer'] ?? [],
            'alternate_dealer' => $dealers['alternate_dealer'] ?? [],
            'new_dealer_emails' => $dealers['new_dealer_emails'] ?? []
        ];

        // Check if all arrays are empty
        if (empty(array_filter($dealerArrays))) {
            return response()->json([
                'success' => false,
                'message' => 'No dealer emails provided',
                'data' => null
            ], 400);
        }

        Log::info('Processing intermediaries:', ['dealers' => $dealerArrays]);

        foreach ($dealerArrays as $type => $dealerList) {
            if (empty($dealerList) || !is_array($dealerList)) {
                continue;
            }

            foreach ($dealerList as $email) {
                try {
                    // Skip if email is empty or not a string
                    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Log::warning('Invalid email format:', ['email' => $email]);
                        continue;
                    }

                    $logonInfoId = null;
                    $accountid = null;

                    // For existing dealers (broker_dealer and alternate_dealer), check if they exist
                    if ($type === 'broker_dealer' || $type === 'alternate_dealer') {
                        // Check if user already exists
                        $existingUser = $this->bk_db->table('portaluserlogoninfo')
                            ->where('Email', $email)
                            ->first();

                        if ($existingUser) {
                            Log::info('Using existing intermediary:', ['email' => $email]);
                            $logonInfoId = $existingUser->Id;
                        } else {
                            //If it does not exist , report error
                            $logger->logUserActivity(
                                $userid,
                                ActivityLogging::ACTIVITIES['AUTH']['code'],
                                'registration_error',
                                ['email' => $email, 'error' => 'Failed to register intermediary'],
                                ActivityLogging::SEVERITIES['ERROR']['level']
                            );
                            // rollback transaction
                            $this->bk_db->rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'The Brokers/Dealers selected in the list are no longer active',
                                'data' => null
                            ], 400);
                        }
                    } else {
                        // For new_dealer_emails, process as before
                        // Check if user already exists
                        if ($this->checkIfUserExists($email)) {
                            Log::info('Intermediary User already exists:', ['email' => $email]);
                            $this->bk_db->rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Intermediary User already exists',
                                'data' => null
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
                            $this->bk_db->rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid intermediary email .  Only company emails are accepted.',
                                'data' => null
                            ], 400);
                        }

                        $standardfn = new StandardFunctions();
                        $accountid = $standardfn->generateUniqueAccountId();
                        Log::info('Generated account ID:', ['accountid' => $accountid]);
                        Log::info('Processing intermediary:', ['email' => $email]);

                        // Insert into portaluserlogoninfo
                        $logonInfoId = $this->bk_db->table('portaluserlogoninfo')->insertGetId([
                            'created_on' => now(),
                            'AccountId' => $accountid,
                            'Email' => $email,
                            'IsActive' => false,
                        ]);

                        // insert the broker role for each first
                        $roleData = [
                            'User' => $logonInfoId,
                            'Role' => 5,
                            'created_on' => now(),
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

                        // Send intermediary verification email
                        $communication_manager = new CommunicationManagement();
                        $response = $communication_manager->composeMail(
                            $email,
                            false,
                            false,
                            false,
                            true,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false
                        );
                        Log::info('Intermediary Verification email sent:', ['email' => $email]);

                        if ($response['success'] == false) {
                            // rollback transaction
                            $this->bk_db->rollBack();
                            return response()->json($response, 500);
                        }
                    }

                    // Insert into portalintermediary for all types
                     $this->bk_db->table('portalintermediary')->insert([
                        'User' => $userid,
                        'IntermediaryId' => $logonInfoId,
                        'IsActive' => false,
                        'created_on' => now()
                    ]);
                    $role_id = 5;
                    Log::info('Intermediary Id Inserted:', ['intermediary' => $logonInfoId]);
                    // Assign default permissions
                    $permissionmanager = new PermissionManagement();
                    $response = $permissionmanager->updateDefaultPermissions($logonInfoId, $role_id);
                    if ($response['success'] == false) {
                        $this->bk_db->rollBack();
                        return response()->json($response, 500);
                    }
                    // Log successful registration
                    $logger->logUserActivity(
                        $logonInfoId,
                        ActivityLogging::ACTIVITIES['AUTH']['code'],
                        'registration_success',
                        ['Intermediary Registered'=> '','email' => $email],
                        ActivityLogging::SEVERITIES['INFO']['level']
                    );
                    Log::info('Intermediary inserted:', ['email' => $email]);

                } catch (\Throwable $th) {
                    Log::error('Error processing email:', [
                        'email' => $email,
                        'error' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ]);
                    continue; // Skip this email and continue with the next one
                }
            }
        }
        // return response()->json(['success'=>true,'message' => 'Intermediaries processed successfully'], 200);
        return response()->json([
            'success' => true,
            'message' => 'Intermediaries processed successfully',
            // 'data' => $users,
        ], 200);
    } catch (\Throwable $th) {
        $this->bk_db->rollBack();
        Log::error('Error processing intermediaries:', ['error' => $th->getMessage(),'line'=>$th->getLine(),'file'=>$th->getFile()]);
        return response()->json(['success'=>false,'message' => 'Error processing intermediaries','error' => $th->getMessage(),'line'=>$th->getLine(),'file'=>$th->getFile()], 500);

    }
}


}
